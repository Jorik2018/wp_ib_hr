<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\directory\Util\mapKeysToCamelCase;
use function IB\directory\Util\mapKeysToSnakeCase;
use function IB\directory\Util\get_param;
use function IB\directory\Util\t_error;
use function IB\directory\Util\remove;

class PayrollAmountRestController extends Controller
{

    public function init()
    {
        remove_role('payroll_amount_admin');
        remove_role('payroll_amount_register');
        remove_role('payroll_amount_read');

        add_role(
            'payroll_amount_read',
            'payroll_amount_read',
            array('PAYROLL_AMOUNT_READ' => true)
        );

        add_role(
            'payroll_amount_admin',
            'payroll_amount_admin',
            array(
                'PAYROLL_AMOUNT_REGISTER' => true,
                'PAYROLL_AMOUNT_ADMIN' => true,
                'PAYROLL_AMOUNT_READ' => true
            )
        );

        add_role(
            'payroll_amount_register',
            'payroll_amount_register',
            array(
                'PAYROLL_AMOUNT_REGISTER' => true,
                'PAYROLL_AMOUNT_READ' => true
            )
        );
    }

    public function rest_api_init()
    {

        register_rest_route('api/payroll', '/amount/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/payroll', '/amount/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/payroll', '/amount', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/payroll', '/amount/(?P<id>[0-9,]+)', array(
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
        remove($o, 'conceptType');

        if (empty($o['concept'])) {
            return t_error('Concepto es obligatorio');
        }

        if (empty($o['iniDate'])) {
            return t_error('Fecha inicio es obligatoria');
        }

        if (!isset($o['amount'])) {
            return t_error('Monto es obligatorio');
        }

        $o = mapKeysToSnakeCase($o,['concept'=>'concept_id',
        'payrollType'=>'payroll_type_id']);

        try {

            $wpdb->select($db_erp);

            if (isset($o['id'])) {

                $updated = $wpdb->update(
                    'rem_payroll_amount',
                    $o,
                    ['id' => $o['id']]
                );

            } else {

                $updated = $wpdb->insert(
                    'rem_payroll_amount',
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
                "SELECT * FROM $db_erp.rem_payroll_amount WHERE id=%d",
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

    $conceptId = get_param($request, 'conceptId');
    $targetId = get_param($request, 'targetId');
    $type = get_param($request, 'type');
    $target = get_param($request, 'target'); // texto a buscar

    $original_db = $wpdb->dbname;
    $db_erp = get_option("db_ofis");
    $wpdb->select($db_erp);

    try {

        $joins = "";
        $targetSelect = "";
        $targetFilter = "";

        if ($type) {

            // join solo a la tabla necesaria
            switch ($type) {
                case 'PT':
                    $joins .= " LEFT JOIN rem_payroll_type pt ON pt.id = pa.target_id ";
                    $targetSelect = " pt.name targetName ";
                    $targetFilter = $target ? " AND pt.name LIKE '%$target%' " : "";
                    break;

                case 'PE':
                    $joins .= " LEFT JOIN m_personal pe ON pe.n = pa.target_id ";
                    $targetSelect = " pe.apellidos_nombres targetName ";
                    $targetFilter = $target ? " AND pe.name LIKE '%$target%' " : "";
                    break;

                case 'GR':
                    $joins .= " LEFT JOIN `rem_group` gr ON gr.id = pa.target_id ";
                    $targetSelect = " gr.name targetName ";
                    $targetFilter = $target ? " AND gr.name LIKE '%$target%' " : "";
                    break;
            }

        } else {

            // joins polimorficos
            $joins .= "
            LEFT JOIN rem_payroll_type pt ON pa.type='PT' AND pt.id=pa.target_id
            LEFT JOIN m_personal pe ON pa.type='PE' AND pe.n=pa.target_id
            LEFT JOIN `rem_group` gr ON pa.type='GR' AND gr.id=pa.target_id
            ";

            $targetSelect = " COALESCE(pt.name,pe.apellidos_nombres,gr.name) targetName ";

            if ($target) {
                $targetFilter = "
                AND (
                    pt.name LIKE '%$target%' OR
                    pe.apellidos_nombres LIKE '%$target%' OR
                    gr.name LIKE '%$target%' 
                )";
            }
        }

        $sql = "
        SELECT SQL_CALC_FOUND_ROWS
            pa.*, 
            c.name conceptName,
            $targetSelect
        FROM $db_erp.rem_payroll_amount pa
        JOIN per_concept c ON c.id = pa.concept_id
        $joins
        WHERE pa.canceled=0
        " .
        ($conceptId ? " AND pa.concept_id='$conceptId'" : "") .
        ($targetId ? " AND pa.target_id='$targetId'" : "") .
        ($type ? " AND pa.type='$type'" : "") .
        $targetFilter .
        " ORDER BY pa.id DESC " .
        ($to > 0 ? " LIMIT $from,$to" : "");

        $results = $wpdb->get_results($sql, ARRAY_A);

        if ($wpdb->last_error) {
            return t_error();
        }

    } finally {
        $wpdb->select($original_db);
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

            $deleted = $wpdb->update(
                'rem_payroll_amount',
                ['canceled' => 1],
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