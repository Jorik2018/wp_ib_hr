<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use IB\cv\Util;
use function IB\directory\Util\remove;
use function IB\directory\Util\cfield;
use function IB\directory\Util\camelCase;
use function IB\directory\Util\cdfield;
use function IB\directory\Util\t_error;

class EmployeeRestController extends Controller
{

    public function init()
    {
        register_rest_route('api/hr', '/employee/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/hr', '/employee/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/hr', '/concept/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'concept_get')
        ));

        register_rest_route('api/hr', '/employee', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/hr', '/employee/position', array(
            'methods' => 'POST',
            'callback' => array($this, 'position')
        ));

        register_rest_route('api/hr', '/employee/(?P<id>)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));
    }

    public function position($request)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $o = method_exists($request, 'get_params') ? $request->get_params() : $request;
        $wpdb->select('grupoipe_erp');
        cfield($o, 'employeeId', 'employee_id');
        cfield($o, 'startDate', 'start_date');
        cdfield($o, 'start_date');
        cfield($o, 'endDate', 'end_date');
        cdfield($o, 'end_date');
        if (isset($o['id'])) {
            $updated = $wpdb->update('hr_experience', $o, array('id' => $o['id']));
        } else {
            $updated = $wpdb->insert('hr_experience', $o);
            $o['id'] = $wpdb->insert_id;
        }
        $wpdb->select($original_db);
        if (false === $updated) return t_error();
        return $o;
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
            $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.hr_employee WHERE id=%d", $request['id']), ARRAY_A);
            $people['ruc'] = $o['ruc'];
            $people = $wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.drt_people WHERE id=%d", $employee['people_id']), ARRAY_A);
            $people['names'] = $o['names'];
            $people['first_surname'] = $o['first_surname'];
            $people['last_surname'] = $o['last_surname'];
            $people['full_name'] = $people['first_surname'] . ' ' . $people['last_surname'] . ' ' . $people['names'];
            $people['code'] = $o['code'];
            $updated = $wpdb->update('drt_people', $people, array('id' => $people['id']));
            $updated = $wpdb->update('hr_employee', $employee, array('id' => $employee['id']));
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
                $employee = array('people_id' => $wpdb->insert_id, 'ruc' => $o['ruc'], 'people_code' => $people['code']);
                $updated = $wpdb->insert('hr_employee', $employee);
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

        /*if (isset($o['people_id'])) {
            $o['people_id'] = intval($o['people_id']);
            foreach (array('names' => 'first_name', 'surnames' => 'last_name') as $key => $field)
                $o[$key] = get_user_meta($o['people_id'], $field, true);
        }
        if ($wpdb->last_error) return t_error();
        cfield($o, 'people_code', 'code');
        $controller = new StudyRestController(array());
        $o['study'] = Util\toCamelCase($controller->pag(array('from' => 0, 'to' => 0, 'employee_id' => $o['id'])));
        $controller = new TrainingRestController(array());
        $o['training'] = Util\toCamelCase($controller->pag(array('from' => 0, 'to' => 0, 'employee_id' => $o['id'])));
        */
        $controller = new ExperienceRestController(array());
        $o['experience'] = Util\toCamelCase($controller->pag(array('from' => 0, 'to' => 0, 'employee_id' => $o['id'])));
        return Util\toCamelCase($o);
    }


    public function concept_get($request)
    {
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $query = method_exists($request, 'get_param') ? $request->get_param('query') : $request['query'];
        $type = method_exists($request, 'get_param') ? $request->get_param('type') : $request['type'];
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS em.* FROM grupoipe_erp.rem_concept em " .
            "WHERE 1=1 " . (isset($query) ? " AND (em.name LIKE '%$query%') " : "") .
         (isset($type) ? " AND (em.type_id = $type) " : "") .
         " ORDER BY em.name".
            ($to > 0 ? (" LIMIT " . $from . ', ' . $to) : ""), ARRAY_A);


        if ($wpdb->last_error) return t_error();
        return $to > 0 ? array('data' => Util\toCamelCase($results), 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;
    }

    public function pag($request)
    {
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $query = method_exists($request, 'get_param') ? $request->get_param('query') : $request['query'];
        $current_user = wp_get_current_user();
        $wpdb->last_error = '';
        /*$results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS o.*,CONCAT(um.meta_value,' ',umln.meta_value) fullName FROM hr_employee o LEFT OUTER JOIN $wpdb->usermeta um ON um.user_id=o.people_id AND um.meta_key='first_name' LEFT OUTER JOIN $wpdb->usermeta umln ON umln.user_id=o.people_id AND umln.meta_key='last_name'" .
            "WHERE o.canceled=0 " . (isset($people_id) ? " AND o.people_id=$people_id " : "") .
            "ORDER BY o.id DESC " .
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : ""), ARRAY_A);*/
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS em.*, pe.full_name as fullName,pe.code FROM grupoipe_erp.hr_employee em LEFT JOIN grupoipe_erp.drt_people pe ON pe.id=em.people_id " .
            "WHERE 1=1 " . (isset($query) ? " AND (pe.full_name LIKE '%$query%' OR pe.code LIKE '%$query%') " : "") .
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
