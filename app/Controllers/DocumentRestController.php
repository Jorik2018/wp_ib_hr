<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use IB\cv\Util;
require_once __DIR__ . '/../Util/Utils.php';

class DocumentRestController extends Controller
{

    public function init()
    {
 
        register_rest_route( 'api/hr','/document', array(
            'methods' => 'POST',
            'callback' => array($this,'post')
        ));

        register_rest_route( 'api/hr','/document/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'pag')
        ));

        register_rest_route( 'api/hr','/document/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'get')
        ));

        register_rest_route( 'api/hr', '/document/(?P<id>)',array(
            'methods' => 'DELETE',
            'callback' => array($this,'delete')
        ));

    }

    public function post($request){
        global $wpdb;
        $o = method_exists($request, 'get_params') ? $request->get_params() : $request;
        $current_user = wp_get_current_user();
        cfield($o, 'employeeId', 'employee_id');
        cfield($o, 'inProgress', 'in_progress');
        remove($o,'uidInsert');
        remove($o,'userInsert');
        remove($o,'insertDate');
        remove($o,'uidUpdate');
        remove($o,'userUpdate');
        remove($o,'updateDate');
        remove($o,'uidDelete');
        remove($o,'userDelete');
        remove($o,'deleteDate');
        cfield($o, 'expeditionDate', 'expedition_date');
        cdfield($o,'expedition_date');

        $tmpId = remove($o, 'tmpId');
        unset($o['synchronized']);
        $inserted = 0;
        if ($o['id'] > 0) {
            $o['uid_update'] = $current_user->ID;
            $o['user_update'] = $current_user->user_login;
            $o['update_date'] = current_time('mysql', 1);
            $updated = $wpdb->update('hr_document', $o, array('id' => $o['id']));
        } else {
            unset($o['id']);
            $o['uid_insert'] = $current_user->ID;
            $o['user_insert'] = $current_user->user_login;
            $o['insert_date'] = current_time('mysql', 1);
            if ($tmpId) $o['offline'] = $tmpId;
            $updated = $wpdb->insert('hr_document', $o);
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
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM hr_document WHERE id=" . $request['id']), OBJECT);
        if ($wpdb->last_error) return t_error();
        return Util\toCamelCase($o);
    }

    public function pag($request){
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $people_id = method_exists($request, 'get_param') ? $request->get_param('people_id') : $request['people_id'];
        $current_user = wp_get_current_user();
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS o.* FROM hr_document o " .
            "WHERE o.canceled=0 " . (isset($people_id) ? " AND o.people_id=$people_id " : "") .
            "ORDER BY o.id DESC " .
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : ""), OBJECT);
    
        if ($wpdb->last_error) return t_error();
        return $to > 0 ? array('data' => Util\toCamelCase($results), 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;    
    }

    public function delete($data){
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $result = array_map(function ($id) use ($wpdb) {
            return $wpdb->update('hr_document', array('canceled' => 1, 'delete_date' => current_time('mysql')), array('id' => $id));
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