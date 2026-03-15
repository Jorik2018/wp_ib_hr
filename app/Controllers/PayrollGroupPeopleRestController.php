<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\directory\Util\mapKeysToCamelCase;
use function IB\directory\Util\mapKeysToSnakeCase;
use function IB\directory\Util\get_param;
use function IB\directory\Util\t_error;

class PayrollGroupPeopleRestController extends Controller
{

    public function init()
    {

        remove_role('payroll_group_people_admin');
        remove_role('payroll_group_people_register');
        remove_role('payroll_group_people_read');

        add_role(
            'payroll_group_people_read',
            'payroll_group_people_read',
            array('PAYROLL_GROUP_PEOPLE_READ' => true)
        );

        add_role(
            'payroll_group_people_admin',
            'payroll_group_people_admin',
            array(
                'PAYROLL_GROUP_PEOPLE_REGISTER' => true,
                'PAYROLL_GROUP_PEOPLE_ADMIN' => true,
                'PAYROLL_GROUP_PEOPLE_READ' => true
            )
        );

        add_role(
            'payroll_group_people_register',
            'payroll_group_people_register',
            array(
                'PAYROLL_GROUP_PEOPLE_REGISTER' => true,
                'PAYROLL_GROUP_PEOPLE_READ' => true
            )
        );

    }

    public function rest_api_init()
    {

        register_rest_route('api/payroll', '/group-people/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/payroll', '/group-people/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/payroll', '/group-people', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/payroll', '/group-people/(?P<id>[0-9,]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));
    }

    public function post($request)
    {

        global $wpdb;

        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");

        $o = get_param($request);

        if (empty($o['people'])) {
            return t_error('Persona es obligatoria');
        }

        if (empty($o['group'])) {
            return t_error('Grupo es obligatorio');
        }

        $o = mapKeysToSnakeCase($o,['people'=>'people_id','group'=>'group_id']);

        try {

            $wpdb->select($db_erp);

            if (isset($o['id'])) {

                $updated = $wpdb->update(
                    'rem_group_people',
                    $o,
                    ['id' => $o['id']]
                );

            } else {

                $updated = $wpdb->insert(
                    'rem_group_people',
                    $o
                );

                $o['id'] = $wpdb->insert_id;
            }

            if ($updated === false) {
                return t_error($wpdb->last_error);
            }

        } finally {

            $wpdb->select($original_db);

        }

        return mapKeysToCamelCase($o);
    }

    public function get($request)
    {

        global $wpdb;

        $db_erp = get_option("db_ofis");

        $o = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    gp.*,
                    p.dni code,
                    apellidos_nombres AS full_name,
                    g.name AS group_name
                FROM $db_erp.rem_group_people gp
                JOIN $db_erp.m_personal p ON p.n = gp.people_id
                JOIN $db_erp.rem_group g ON g.id = gp.group_id
                WHERE gp.id=%d",
                $request['id']
            ),
            ARRAY_A
        );

        if (!$o) {
            return t_error('Registro no encontrado');
        }

        return mapKeysToCamelCase($o);
    }

    public function pag($request)
    {

        global $wpdb;

        $from = intval($request['from']);
        $to = intval($request['to']);

        $code = get_param($request, 'code');
        $fullName = get_param($request, 'fullName');
        $groupName = get_param($request, 'groupName');

        $db_erp = get_option("db_ofis");

        $results = $wpdb->get_results(
            "SELECT SQL_CALC_FOUND_ROWS
                gp.*,
                p.dni code,
                apellidos_nombres AS full_name,
                g.name AS group_name
            FROM $db_erp.rem_group_people gp
            JOIN $db_erp.m_personal p ON p.n = gp.people_id
            JOIN $db_erp.rem_group g ON g.id = gp.group_id
            WHERE 1=1 " .

            (isset($code) ? " AND p.code LIKE '%$code%'" : "") .
            (isset($fullName) ? " AND apellidos_nombres LIKE '%$fullName%'" : "") .
            (isset($groupName) ? " AND g.name LIKE '%$groupName%'" : "") .

            ($to > 0 ? " LIMIT $from,$to" : ""),

            ARRAY_A
        );

        if ($wpdb->last_error) {
            return t_error();
        }

        $results = mapKeysToCamelCase($results);

        return $to > 0 ? [
            'data' => $results,
            'size' => $wpdb->get_var('SELECT FOUND_ROWS()')
        ] : $results;

    }

    public function delete($data)
    {

        global $wpdb;

        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");

        $ids = array_map('intval', explode(",", $data['id']));

        $wpdb->select($db_erp);

        $wpdb->query('START TRANSACTION');

        foreach ($ids as $id) {

            $deleted = $wpdb->delete(
                'rem_group_people',
                ['id' => $id]
            );

            if ($deleted === false) {

                $wpdb->query('ROLLBACK');
                $wpdb->select($original_db);

                return t_error($wpdb->last_error);
            }

        }

        $wpdb->query('COMMIT');

        $wpdb->select($original_db);

        return true;
    }

}