<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\cv\Util\remove;
use function IB\cv\Util\cfield;
use function IB\cv\Util\cdfield;
use function IB\cv\Util\t_error;
use function IB\cv\Util\get_param;
use function IB\cv\Util\toCamelCase;

class InventoryRestController extends Controller
{

    public function init()
    {
        register_rest_route('api/inventory', '/item', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/inventory', '/item/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/inventory', '/item/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/inventory', '/item/(?P<ids>[0-9,]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));
    }

    public function post($request)
    {
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
            $o['canceled'] = $o['canceled'] == "1";
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

    public function get($request)
    {
        global $wpdb;
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.inv_inventory WHERE id=" . $request['id']), OBJECT);
        if ($wpdb->last_error) return t_error();
        return $o; //Util\toCamelCase($o);
    }

    public function pag($request)
    {
        global $wpdb;
        $params = [
            'usuario_responsable' => get_param($request, 'usuario_responsable'),
            'usuario_area' => get_param($request, 'usuario_area'),
            'codigo_patrimonial' => get_param($request, 'codigo_patrimonial'),
            'codigo_inventario' => get_param($request, 'codigo_inventario')
        ];
        $from = get_param($request, 'from');
        $to = get_param($request, 'to');
        $query = "SELECT SQL_CALC_FOUND_ROWS o.* FROM grupoipe_erp.inv_inventory o WHERE o.canceled=0";
        foreach ($params as $key => $value) {
            if ($value) {
                $value = strtoupper($value);
                $query .= " AND UPPER(o.$key) LIKE '%$value%'";
            }
        }
        $query .= " ORDER BY o.id DESC";
        if ($to > 0) {
            $query .= " LIMIT $from, $to";
        }
        $results = $wpdb->get_results($query, OBJECT);
        if ($wpdb->last_error) return t_error();
        return $to > 0 ? ['data' => $results, 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')] : $results;
    }

    public function delete($data)
    {
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
