<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\cv\Util\cbfield;
use function IB\cv\Util\toCamelCase;
use function IB\cv\Util\remove;
use function IB\cv\Util\cdfield;
use function IB\cv\Util\cfield;
use function IB\directory\Util\t_error;

class ExperienceRestController extends Controller
{

    public function init()
    {

        register_rest_route('api/hr', '/experience', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/hr', '/experience/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/hr', '/experience/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/hr', '/experience/(?P<id>)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));
    }

    public function post($request)
    {
        global $wpdb;
        $o = method_exists($request, 'get_params') ? $request->get_params() : $request;
        $current_user = wp_get_current_user();
        cfield($o, 'employeeId', 'employee_id');
        cfield($o, 'dependencyId', 'dependency_id');
        cbfield($o, 'inProgress', 'in_progress');
        remove($o, 'canceled');
        remove($o, 'uidInsert');
        remove($o, 'userInsert');
        remove($o, 'insertDate');
        remove($o, 'uidUpdate');
        remove($o, 'userUpdate');
        remove($o, 'people');
        remove($o, 'updateDate');
        remove($o, 'uidDelete');
        remove($o, 'userDelete');
        remove($o, 'deleteDate');
        cfield($o, 'endDate', 'end_date');
        cdfield($o, 'end_date');
        cfield($o, 'startDate', 'start_date');
        cdfield($o, 'start_date');
        if (isset($o['attachment']) && is_array($o['attachment'])) {
            $o['attachment'] = $o['attachment']['tempFile'];
        }

        $tmpId = remove($o, 'tmpId');
        unset($o['synchronized']);
        $inserted = 0;
        $original_db = $wpdb->dbname;
        $wpdb->select('grupoipe_erp');
        if (isset($o['id'])) {
            $o['uid_update'] = $current_user->ID;
            $o['user_update'] = $current_user->user_login;
            $o['update_date'] = current_time('mysql', 1);

            $updated = $wpdb->update('hr_experience', $o, array('id' => $o['id']));
        } else {
            unset($o['id']);
            $o['uid_insert'] = $current_user->ID;
            $o['user_insert'] = $current_user->user_login;
            $o['insert_date'] = current_time('mysql', 1);
            if ($tmpId) $o['offline'] = $tmpId;
            $updated = $wpdb->insert('hr_experience', $o);
            $o['id'] = $wpdb->insert_id;
            $inserted = 1;
        }
        $wpdb->select($original_db);
        if (false === $updated) return t_error();
        if ($tmpId) {
            $o['tmpId'] = $tmpId;
            $o['synchronized'] = 1;
        }
        return toCamelCase($o);
    }

    public function get($request)
    {
        global $wpdb;
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.hr_experience WHERE id=" . $request['id']), ARRAY_A);
        $e = $wpdb->get_row($wpdb->prepare("SELECT e.people_id, p.full_name as fullName FROM grupoipe_erp.hr_employee e LEFT JOIN grupoipe_erp.drt_people p ON p.id=e.people_id WHERE e.id=%d", $o['employee_id']), ARRAY_A);
        $o['people'] = $e;
        /*if (isset($e['people_id'])) {
            $e['people_id'] = intval($e['people_id']);
            $o['people'] = array();
            foreach (array('names' => 'first_name', 'surnames' => 'last_name') as $key => $field)
                $o['people'][$key] = get_user_meta($e['people_id'], $field, true);
        }*/
        if ($wpdb->last_error) return t_error();
        return toCamelCase($o);
    }

    public function pag($request)
    {
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];

        $people_id = (!is_array($request) && method_exists($request, 'get_param'))
        ? $request->get_param('people_id') 
        : $request['people_id'];
        $employee_id = (!is_array($request) && method_exists($request, 'get_param')) ? $request->get_param('employee_id') : $request['employee_id'];

     

        $current_user = wp_get_current_user();
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS o.* FROM grupoipe_erp.hr_experience o " .
            "WHERE o.canceled=0 " . (isset($people_id) ? " AND o.people_id=$people_id " : "") .
            (isset($employee_id) ? " AND o.employee_id=$employee_id " : "") .
            "ORDER BY o.start_date DESC " .
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : ""), OBJECT);

        if ($wpdb->last_error) return t_error();
        return $to > 0 ? array('data' => toCamelCase($results), 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;
    }

    public function delete($data)
    {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $result = array_map(function ($id) use ($wpdb) {
            return $wpdb->update('hr_experience', array('canceled' => 1, 'delete_date' => current_time('mysql')), array('id' => $id));
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
