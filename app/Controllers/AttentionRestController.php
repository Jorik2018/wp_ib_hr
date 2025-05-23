<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\cv\Util\remove;
use function IB\cv\Util\cfield;
use function IB\cv\Util\cdfield;
use function IB\cv\Util\t_error;
use function IB\cv\Util\get_param;
use function IB\cv\Util\toLowerCase;

class AttentionRestController extends Controller
{

    public function init()
    {
        register_rest_route('api/desarrollo-social', '/attention', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/desarrollo-social', '/attention/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/desarrollo-social', '/attention/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/desarrollo-social', '/attention/(?P<ids>[0-9,]+)', array(
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
            $o['update_uid'] = $current_user->ID;
            $o['update_user'] = $current_user->user_login;
            $o['update_date'] = current_time('mysql', 1);
            $o['canceled'] = $o['canceled'] == "1";
            $updated = $wpdb->update('mon_atenciones', $o, array('id' => $o['id']));
        } else {
            unset($o['id']);
            $o['insert_uid'] = $current_user->ID;
            $o['insert_user'] = $current_user->user_login;
            $o['insert_date'] = current_time('mysql', 1);
            if ($tmpId) $o['offline'] = $tmpId;
            $updated = $wpdb->insert('mon_atenciones', $o);
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
        $o = (array)$wpdb->get_row($wpdb->prepare("SELECT * FROM grupoipe_erp.mon_atenciones WHERE id=" . $request['id']), OBJECT);
        if ($wpdb->last_error) return t_error();
        $o = (array)toLowerCase($o);
        $o['people'] = $wpdb->get_row($wpdb->prepare("SELECT documento_tipo,documento_nro,ape_paterno,ape_materno,nombres FROM grupoipe_erp.matm_persona p WHERE id=" . $o['persona_id']), OBJECT);

        $ipress = $wpdb->get_row($wpdb->prepare("SELECT codigo_microred,codigo_red,codigo_cocadenado FROM grupoipe_master.ipress_eess p WHERE Codigo_Unico=" . $o['codigo_unico']), OBJECT);
        $ipress =(array)toLowerCase( $ipress);
        $o['red'] =$ipress['codigo_red'];
        $o['microred'] =$ipress['codigo_cocadenado'];
        if ($wpdb->last_error) return t_error();
        return toLowerCase($o);
    }

    public function pag($request)
    {
        global $wpdb;
        $params = [
            'persona_id' => get_param($request, 'people'),
        ];
        $from = get_param($request, 'from');
        $to = get_param($request, 'to');
        $query = "SELECT SQL_CALC_FOUND_ROWS o.* FROM grupoipe_erp.mon_atenciones o WHERE o.canceled=0";
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
        $results = toLowerCase($wpdb->get_results($query, OBJECT));
        if ($wpdb->last_error) return t_error();
        return $to > 0 ? ['data' => $results, 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')] : $results;
    }

    public function delete($data)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $current_user = wp_get_current_user();
        $wpdb->select('grupoipe_erp');
        $wpdb->query('START TRANSACTION');
        $result = array_map(function ($id) use ($wpdb, $current_user) {
            return $wpdb->update('mon_atenciones', array('canceled' => 1, 'delete_user' => $current_user->user_login, 'delete_uid' => $current_user->ID,  'delete_date' => current_time('mysql')), array('id' => $id));
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
