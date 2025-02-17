<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use IB\cv\Util;
use function IB\directory\Util\remove;
use function IB\directory\Util\cfield;
use function IB\directory\Util\camelCase;
use function IB\directory\Util\cdfield;
use function IB\directory\Util\t_error;

class RiskTypeRestController extends Controller
{

    public function init()
    {
        register_rest_route('api/obresec', '/risk/type/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/obresec', '/risk/type/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/obresec', '/risk/type', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/obresec', '/risk/type/position', array(
            'methods' => 'POST',
            'callback' => array($this, 'position')
        ));

        register_rest_route('api/obresec', '/risk/type/(?P<id>)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));
    }

    public function post($request)
    {
        global $wpdb;
        $o = method_exists($request, 'get_params') ? $request->get_params() : $request;
        $current_user = wp_get_current_user();
        cfield($o, 'firstSurname', 'first_surname');
        cfield($o, 'lastSurname', 'last_surname');

        $original_db = $wpdb->dbname;
        $wpdb->select('grupoipe_erp');
        if (isset($o['id'])) {
            $risktype = $wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.hr_employee WHERE id=%d", $request['id']), ARRAY_A);
            $people['ruc'] = $o['ruc'];
            $people = $wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.drt_people WHERE id=%d", $risktype['people_id']), ARRAY_A);
            $people['names'] = $o['names'];
            $people['first_surname'] = $o['first_surname'];
            $people['last_surname'] = $o['last_surname'];
            $people['full_name'] = $people['first_surname'] . ' ' . $people['last_surname'] . ' ' . $people['names'];
            $people['code'] = $o['code'];
            $updated = $wpdb->update('drt_people', $people, array('id' => $people['id']));
            $updated = $wpdb->update('hr_employee', $risktype, array('id' => $risktype['id']));
        } else {
            $people = array(
                'document_type_id' => 1,
                'code' => $o['code'],
                'names' => $o['names'],
                'first_surname' => $o['first_surname'],
                'last_surname' => $o['last_surname'],
                'full_name' => $o['first_surname'] . ' ' . $o['last_surname'] . ' ' . $o['names']
            );
            $updated = $wpdb->insert('drt_people', $people);
            if ($updated) {
                $risktype = array('people_id' => $wpdb->insert_id, 'ruc' => $o['ruc'], 'people_code' => $people['code']);
                $updated = $wpdb->insert('hr_employee', $risktype);
                $o['id'] = $wpdb->insert_id;
            }
        }
        $wpdb->select($original_db);
        if (false === $updated) return t_error();
        return $o;
    }

    public function get($request)
    {
        global $wpdb;
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.hr_employee WHERE id=%d", $request['id']), ARRAY_A);
        if ($wpdb->last_error) return t_error();
        $people = $wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.drt_people WHERE id=%d", $o['people_id']), ARRAY_A);
        cfield($people, 'first_surname', 'firstSurname');
        cfield($people, 'last_surname', 'lastSurname');
        cfield($people, 'full_name', 'fullName');
        $o['names'] = $people['names'];
        $o['firstSurname'] = $people['firstSurname'];
        $o['lastSurname'] = $people['lastSurname'];
        $o['fullName'] = $people['fullName'];
        $o['code'] = $people['code'];
        $controller = new ExperienceRestController(array());
        $o['experience'] = Util\toCamelCase($controller->pag(array('from' => 0, 'to' => 0, 'employee_id' => $o['id'])));
        return Util\toCamelCase($o);
    }

    public function pag($request)
    {
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $query = method_exists($request, 'get_param') ? $request->get_param('query') : $request['query'];
        $current_user = wp_get_current_user();
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS em.* FROM grupoipe_erp.risk_type em " .
            "WHERE 1=1 " .
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : ""), ARRAY_A);


        if ($wpdb->last_error) return t_error();
        return $to > 0 ? array('data' => Util\toCamelCase($results), 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;
    }

    public function delete($data)
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $result = array_map(function ($id) use ($wpdb) {
            return $wpdb->update('hr_employee', array('canceled' => 1, 'delete_date' => current_time('mysql')), array('id' => $id));
        }, explode(",", $data['id']));
        $success = !in_array(false, $result, true);
        if ($success) {
            $wpdb->query('COMMIT');
        } else {
            $wpdb->query('ROLLBACK');
        }
        return $success;
    }

}
