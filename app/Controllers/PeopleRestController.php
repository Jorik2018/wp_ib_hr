<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\cv\Util\remove;
use function IB\cv\Util\cfield;
use function IB\cv\Util\cdfield;
use function IB\cv\Util\t_error;
use function IB\cv\Util\get_param;
use function IB\cv\Util\toCamelCase;

class PeopleRestController extends Controller
{

    public function init()
    {
        remove_role('emed_admin');
        remove_role('emed_register');
        remove_role('emed_inst');
        remove_role('emed_read');
        add_role(
            'emed_read',
            'emed_read',
            array(
                'EMED_READ' => true
            )
        );
        add_role(
            'ds_people_admin',
            'ds_people_admin',
            array(
                'DS_PEOPLE_REGISTER' => true,
                'DS_PEOPLE_ADMIN' => true,
                'DS_PEOPLE_READ' => true,
                'DS_PEOPLE_DET' => true
            )
        );
        add_role(
            'ds_people_register',
            'ds_people_register',
            array(
                'DS_PEOPLE_REGISTER' => true,
                'DS_PEOPLE_READ' => true,
                'DS_PEOPLE_DET' => true
            )
        );
        add_role(
            'ds_people_inst',
            'ds_people_inst',
            array(
                'DS_PEOPLE_REGISTER' => true,
                'DS_PEOPLE_ADMIN' => true,
                'DS_PEOPLE_READ' => true
            )
        );
    }

    public function rest_api_init()
    {
        register_rest_route('api/desarrollo-social', '/people', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/desarrollo-social', '/people/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/desarrollo-social', '/people/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/desarrollo-social', '/people/(?P<ids>[0-9,]+)', array(
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
        cdfield($o, 'fecha_nacimiento');
        $o['ape_paterno'] = strtoupper($o['ape_paterno']);
        $o['ape_materno'] = strtoupper($o['ape_materno']);
        $o['nombres'] = strtoupper($o['nombres']);
        $inserted = 0;
        if ($o['id'] > 0) {
            $o['update_uid'] = $current_user->ID;
            $o['update_user'] = $current_user->user_login;
            $o['update_date'] = current_time('mysql', 1);
            $o['canceled'] = $o['canceled'] == "1";
            $updated = $wpdb->update('matm_persona', $o, array('id' => $o['id']));
        } else {
            unset($o['id']);
            $o['insert_uid'] = $current_user->ID;
            $o['insert_user'] = $current_user->user_login;
            $o['insert_date'] = current_time('mysql', 1);
            if ($tmpId) $o['offline'] = $tmpId;
            $updated = $wpdb->insert('matm_persona', $o);
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
        $o = (array)$wpdb->get_row($wpdb->prepare("SELECT p.*,1 editable FROM grupoipe_erp.matm_persona p WHERE id=" . $request['id']), OBJECT);
        if ($wpdb->last_error) return t_error();
        $ccpp = (array)$wpdb->get_row("SELECT distinct Ubigeo_Centropoblado AS id,
        Ubigeo_Centropoblado AS codccpp,
        Ubigeo_Distrito,
        Distrito AS distrito,
        Provincia AS provincia,
        Nombre_Centro_Poblado AS name FROM drt_ccpp 
        WHERE Ubigeo_Centropoblado='" . $o['ubigeo'] . $o['ubigeo_ccpp'] . "' order by Ubigeo_Distrito,3");

        $o['provincia'] = $ccpp['provincia'];
        $o['ubigeo_distrito'] = $ccpp['Ubigeo_Distrito'];
        $o['distrito'] = $ccpp['distrito'];
        $o['ccpp'] = $ccpp['name'];
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
        $query = "SELECT SQL_CALC_FOUND_ROWS o.* FROM grupoipe_erp.matm_persona o WHERE o.canceled=0";
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
        $current_user = wp_get_current_user();
        $wpdb->select('grupoipe_erp');
        $wpdb->query('START TRANSACTION');
        $result = array_map(function ($id) use ($wpdb, $current_user) {
            return $wpdb->update('matm_persona', array('canceled' => 1, 'delete_user' => $current_user->user_login, 'delete_uid' => $current_user->ID, 'delete_date' => current_time('mysql')), array('id' => $id));
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
