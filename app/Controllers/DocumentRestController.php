<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\cv\Util\remove;
use function IB\cv\Util\cfield;
use function IB\cv\Util\cdfield;
use function IB\cv\Util\t_error;
use function IB\cv\directory\get_param;
use function IB\cv\Util\toCamelCase;

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

        register_rest_route( 'api/hr', '/document/(?P<ids>[0-9,]+)',array(
            'methods' => 'DELETE',
            'callback' => array($this,'delete')
        ));



        register_rest_route( 'api/inventory','/item', array(
            'methods' => 'POST',
            'callback' => array($this,'inventory_post')
        ));

        register_rest_route( 'api/inventory','/item/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'inventory_pag')
        ));

        register_rest_route( 'api/inventory','/item/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'inventory_get')
        ));

        register_rest_route('api/inventory', '/item/(?P<ids>[0-9,]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this,'inventory_delete')
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
        return toCamelCase($o);
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
        return $to > 0 ? array('data' => toCamelCase($results), 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;    
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


    public function inventory_post($request){
        global $wpdb;
        $o = method_exists($request, 'get_params') ? $request->get_params() : $request;
        $current_user = wp_get_current_user();

        $original_db = $wpdb->dbname;
        $wpdb->select('grupoipe_erp');
        
        $tmpId = remove($o, 'tmpId');
        unset($o['synchronized']);
        $inserted = 0;
        if ($o['id'] > 0) {
            $o['uid_update'] = $current_user->ID;
            $o['user_update'] = $current_user->user_login;
            $o['update_date'] = current_time('mysql', 1);
            $o['canceled']=$o['canceled']=="1";
            $updated = $wpdb->update('inv_inventory', $o, array('id' => $o['id']));

        } else {
            unset($o['id']);
            $o['uid_insert'] = $current_user->ID;
            $o['user_insert'] = $current_user->user_login;
            $o['insert_date'] = current_time('mysql', 1);
            if ($tmpId) $o['offline'] = $tmpId;
            $updated = $wpdb->insert('inv_inventory', $o);
            $o['id'] = $wpdb->insert_id;
            $inserted = 1;
        }
        if (false === $updated) return t_error();
        if ($tmpId) {
            $o['tmpId'] = $tmpId;
            $o['synchronized'] = 1;
        }
        $wpdb->select($original_db);
        return $o;
    }

    public function inventory_get($request){    
        global $wpdb;
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.inv_inventory WHERE id=" . $request['id']), OBJECT);
        if ($wpdb->last_error) return t_error();
        return $o;//Util\toCamelCase($o);
    }


    public function inventory_pag($request){
        global $wpdb;
        $from = get_param($request,'from');
        $to = get_param($request,'to');
        $usuario_responsable=get_param($request,'usuario_responsable');
        $usuario_area=get_param($request,'usuario_area');
        $codigo_patrimonial=get_param($request,'codigo_patrimonial');
        $codigo_inventario=get_param($request,'codigo_inventario');
        $current_user = wp_get_current_user();
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS o.* FROM grupoipe_erp.inv_inventory o " .
            "WHERE o.canceled=0 ".
            (isset($usuario_responsable)?" AND UPPER(o.usuario_responsable) LIKE '%".strtoupper($usuario_responsable)."%' ":"").
            (isset($usuario_area)?" AND UPPER(o.usuario_area) LIKE '%".strtoupper($usuario_area)."%' ":"").
            ($codigo_patrimonial?" AND UPPER(o.codigo_patrimonial) LIKE '%".strtoupper($codigo_patrimonial)."%' ":"").
            ($codigo_inventario?" AND UPPER(o.codigo_inventario) LIKE '%".strtoupper($codigo_inventario)."%' ":"").
            "ORDER BY o.id DESC " .
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : ""), OBJECT);
    
        if ($wpdb->last_error) return t_error();
        return $to > 0 ? array('data' => $results, 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;    
    }

    public function inventory_delete($data){
        global $wpdb;
        $original_db = $wpdb->dbname;
        $wpdb->select('grupoipe_erp');
        $wpdb->query('START TRANSACTION');

        $result = array_map(function ($id) use ($wpdb) {
            return $wpdb->update('inv_inventory', array('canceled' => 1, 'delete_date' => current_time('mysql')), array('id' => $id));
        }, explode(",", $data['ids']));

        $success = !in_array(false, $result, true);
        if ($success) {
            $wpdb->query('COMMIT');
        } else {
            $wpdb->query('ROLLBACK');
        }
        $wpdb->select($original_db);
        return $success;
    }

}