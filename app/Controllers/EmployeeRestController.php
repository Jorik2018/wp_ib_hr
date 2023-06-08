<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use IB\cv\Util;
require_once __DIR__ . '/../Util/Utils.php';

class EmployeeRestController extends Controller
{

    public function init()
    {
        register_rest_route( 'api/hr','/employee/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'pag')
        ));

        register_rest_route( 'api/hr','/employee/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'get')
        ));

        register_rest_route( 'api/hr','/employee', array(
            'methods' => 'POST',
            'callback' => array($this,'post')
        ));

        register_rest_route( 'api/hr', '/employee/(?P<id>)',array(
            'methods' => 'DELETE',
            'callback' => array($this,'delete')
        ));

    }
    
    public function post($request){
        global $wpdb;
        $o = method_exists($request, 'get_params') ? $request->get_params() : $request;
        $current_user = wp_get_current_user();
        cfield($o, 'employeeId', 'employee_id');
		cfield($o, 'code', 'people_code');
        $tmpId = remove($o, 'tmpId');
		$first_name=$o['names'];
		$last_name=$o['surnames'];
        unset($o['synchronized']);
        $inserted = 0;
        if ($o['id'] > 0) {
			$o=array('id'=>$o['id'],'people_code'=>$o['people_code'],
				'uid_update'=>$current_user->ID,
				'user_update'=>$current_user->user_login,
				'update_date'=>current_time('mysql', 1));
			
			$user_ids = get_users(array(
				'meta_key' => 'nickname',
				'meta_value' => $o['people_code'],
				'fields' => 'ID',
			));
			if (!empty($user_ids)) {
				foreach ($user_ids as $user_id) {
					$o['people_id']=$user_id;break;
				}
			}
			if(!isset($o['people_id'])){
				status_header(400);
				wp_send_json_error(array(
					'error' => 'People code no valid!',
				));
			}
			$o['people_id']=intval($o['people_id']);
			update_user_meta($o['people_id'], 'first_name', $first_name);
			update_user_meta($o['people_id'], 'last_name', $last_name);
            $updated = $wpdb->update('hr_employee', $o, array('id' => $o['id']));
        } else {
            unset($o['id']);
            $o['uid_insert'] = $current_user->ID;
            $o['user_insert'] = $current_user->user_login;
            $o['insert_date'] = current_time('mysql', 1);
            if ($tmpId) $o['offline'] = $tmpId;
            $updated = $wpdb->insert('hr_employee', $o);
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
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM hr_employee WHERE id=" . $request['id']), ARRAY_A);
		if(isset($o['people_id'])){
			$o['people_id']=intval($o['people_id']);
			foreach(array('names'=>'first_name','surnames'=>'last_name') as $key=>$field)
				$o[$key] = get_user_meta($o['people_id'],$field, true);
		}
        if ($wpdb->last_error) return t_error();
		cfield($o, 'people_code', 'code');
		$o['study']=new StudyRestController().pag(array('from'=>0,'to'=>0));
        return Util\toCamelCase($o);
    }

    public function pag($request){
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $people_id = method_exists($request, 'get_param') ? $request->get_param('people_id') : $request['people_id'];
        $current_user = wp_get_current_user();
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS o.* FROM hr_employee o " .
            "WHERE o.canceled=0 " . (isset($people_id) ? " AND o.people_id=$people_id " : "") .
            "ORDER BY o.id DESC " .
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : ""), ARRAY_A);
    
        if ($wpdb->last_error) return t_error();
        return $to > 0 ? array('data' => Util\toCamelCase($results), 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;    
    }

    public function delete($data){
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