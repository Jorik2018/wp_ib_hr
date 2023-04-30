<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use IB\cv\cfield;
use IB\cv\toCamelCase as toCamelCase;

class ExperienceRestController extends Controller
{
    public function init()
    {
 
        register_rest_route( 'api/hr','/experience', array(
            'methods' => 'POST',
            'callback' => array($this,'post')
        ));

        register_rest_route( 'api/hr','/experience/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'pag')
        ));

        register_rest_route( 'api/hr','/experience/(?P<id>\d+)', array(
            'methods' => 'GET',
            array($this,'get')
        ));

        register_rest_route( 'api/hr', '/experience/(?P<id>)',array(
            'methods' => 'DELETE',
            'callback' => 'delete'
        ));

    }

    public function post($request){
        global $wpdb;
        $o = method_exists($request, 'get_params') ? $request->get_params() : $request;
        $current_user = wp_get_current_user();
        cfield($o, 'employeeId', 'employee_id');
        cfield($o, 'startDate', 'start_date');
        cfield($o, 'endDate', 'end_date');
        $tmpId = remove($o, 'tmpId');
        unset($o['synchronized']);
        $inserted = 0;
        if ($o['id'] > 0) {
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
        if (false === $updated) return t_error();
        if ($tmpId) {
            $o['tmpId'] = $tmpId;
            $o['synchronized'] = 1;
        }
        return $o;
    }

    public function get($request){    
        global $wpdb;
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM hr_experience WHERE id=" . $request['id']), ARRAY_A);
        if ($wpdb->last_error) return t_error();
        return IB\cv\toCamelCase($o);
    }

    public function pag($request){
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $people_id = method_exists($request, 'get_param') ? $request->get_param('people_id') : $request['people_id'];
        $current_user = wp_get_current_user();
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS o.* FROM hr_experience o " .
            "WHERE o.canceled=0 " . (isset($people_id) ? " AND o.people_id=$people_id " : "") .
            "ORDER BY o.id DESC " .
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : ""), ARRAY_A);
    
        if ($wpdb->last_error) return t_error();
        return $to > 0 ? array('data' => IB\cv\toCamelCase($results), 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;    
    }

    public function delete($data){
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