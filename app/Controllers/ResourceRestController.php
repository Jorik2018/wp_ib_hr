<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use IB\cv\Util;
use function IB\directory\Util\remove;
use function IB\directory\Util\cfield;
use function IB\directory\Util\camelCase;
use function IB\directory\Util\cdfield;
use function IB\directory\Util\t_error;
use function IB\directory\Util\get_param;
use function IB\directory\Util\renameFields;

class ResourceRestController extends Controller
{

    private const FIELD_MAP = [
        'usuarioDeRed' => 'usuario_de_red',
        'fechaAsignacion' => 'fecha_asignacion',
        'fechaDevolucion' => 'fecha_devolucion'
    ];

    public function init()
    {
        remove_role('hr_personal_admin');
        remove_role('hr_personal_register');
        remove_role('hr_personal_read');
        add_role(
            'hr_personal_read',
            'hr_personal_read',
            array(
                'HR_PERSONAL_READ' => true
            )
        );
        add_role(
            'hr_personal_admin',
            'hr_personal_admin',
            array(
                'HR_PERSONAL_REGISTER' => true,
                'HR_PERSONAL_ADMIN' => true,
                'HR_PERSONAL_READ' => true,
                'HR_PERSONAL_DET' => true
            )
        );
        add_role(
            'hr_personal_register',
            'hr_personal_register',
            array(
                'HR_PERSONAL_REGISTER' => true,
                'HR_PERSONAL_READ' => true,
                'HR_PERSONAL_DET' => true
            )
        );
    }

    public function rest_api_init()
    {
        register_rest_route('api/hr', '/personal/resource/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/hr', '/personal/resource/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/hr', '/personal/resource', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/hr', '/personal/resource/(?P<id>)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));

        register_rest_route('api/hr', '/personal/type-resource/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag_type_resource')
        ));

        register_rest_route('api/hr', '/personal/unidad/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag_maestro_unidad')
        ));
        
    }

    public function post($request)
    {
        global $wpdb;
        $o = get_param($request);
        $current_user = wp_get_current_user();
        cdfield($o, 'fechaAsignacion');
        cdfield($o, 'fechaDevolucion');
        unset($o['apellidosNombres']);
        unset($o['personal']);
        unset($o['fechaCrea']);
        unset($o['fechaModifica']);
        unset($o['insertDate']);
        unset($o['updateDate']);
        unset($o['editable']);
        $o = renameFields($o, self::FIELD_MAP);
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $wpdb->select($db_erp);
        if (isset($o['id'])) {
            $o['update_date'] = current_time('mysql', 1);
            $updated = $wpdb->update('t_recursos', $o, array('id' => $o['id']));
        } else {
            $o['insert_date'] = current_time('mysql', 1);
            $updated = $wpdb->insert('t_recursos', $o);
            $o['id'] = $wpdb->insert_id;
        }
        if (false === $updated) return t_error();
        $wpdb->select($original_db);
        return Util\toCamelCase($o);
    }

    public function get($request)
    {
        global $wpdb;
        $db = get_option("db_ofis");
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM $db.t_recursos WHERE id=%d", $request['id']), ARRAY_A);
        $o['editable'] = true;
        if ($wpdb->last_error) return t_error();
        $people = $wpdb->get_row($wpdb->prepare("SELECT * FROM $db.m_personal WHERE dni=%s", $o['dni']), ARRAY_A);
        if ($wpdb->last_error) return t_error();
        $o['apellidosNombres'] = $people['apellidos_nombres'];
        $o['personal'] = $people['n'];
        return Util\toCamelCase($o);
    }
    
    public function pag_type_resource($request)
    {
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $current_user = wp_get_current_user();
        $db_erp = get_option("db_ofis");
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS em.id code, upper(em.tipo) name FROM $db_erp.maestro_tipo_bien em " .
            "WHERE 1=1 ".
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : "")." ORDER BY 2", ARRAY_A);
        if ($wpdb->last_error) return t_error();
        $results = Util\toCamelCase($results);
        return $to > 0 ? array('data' => $results, 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;
    }

    public function pag_maestro_unidad($request)
    {
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $current_user = wp_get_current_user();
        $db_erp = get_option("db_ofis");
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS em.id code, id_organo organo, upper(em.unidad_organica) name FROM $db_erp.maestro_unidad em " .
            "WHERE 1=1 ".
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : "")." ORDER BY 3", ARRAY_A);
        if ($wpdb->last_error) return t_error();
        $results = Util\toCamelCase($results);
        return $to > 0 ? array('data' => $results, 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;
    }

    public function pag($request)
    {
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $query = get_param($request, 'query');
        $personal = get_param($request, 'personal');
        $current_user = wp_get_current_user();
        $db_erp = get_option("db_ofis");
        $people = $wpdb->get_row($wpdb->prepare("SELECT dni FROM $db_erp.m_personal WHERE n=%s", $personal), ARRAY_A);
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS em.*, upper(tb.tipo) type_name FROM $db_erp.t_recursos em LEFT JOIN $db_erp.maestro_tipo_bien tb ON tb.id=em.tipo " .
            "WHERE 1=1 AND dni='".$people['dni']."' " 
            . (isset($query) ? " AND (pe.apellidos_nombres LIKE '%$query%') " : "") .
            ($to > 0 ? ("LIMIT " . $from . ', ' . $to) : ""), ARRAY_A);
        if ($wpdb->last_error) return t_error();
        $results = Util\toCamelCase($results);
        return $to > 0 ? array('data' => $results, 'size' => $wpdb->get_var('SELECT FOUND_ROWS()')) : $results;
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
