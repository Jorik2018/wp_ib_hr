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

class PersonalRestController extends Controller
{

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
        register_rest_route('api/hr', '/personal/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/hr', '/personal/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/hr', '/personal', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/hr', '/personal/position', array(
            'methods' => 'POST',
            'callback' => array($this, 'position')
        ));

        register_rest_route('api/hr', '/personal/(?P<id>)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));
    }

    private const FIELD_MAP = [
        'firstSurname' => 'first_surname',
        'lastSurname' => 'last_surname',
        'secuenciaFuncional' => 'secuencia_funcional',
        'codigoAirhsp' => 'codigo_airhsp',
        'unidadOrganica' => 'unidad_organica',
        'apellidosNombres' => 'apellidos_nombres',
        'fechaDeInicioContrato' => 'fecha_de_inicio_contrato',
        'fechaDeInicioOfis' => 'fecha_de_inicio_ofis',
        'tipoDeContrato' => 'tipo_de_contrato',
        'clasificadorDeGastoContrato' => 'clasificador_de_gasto_contrato',
        'afpOnp' => 'afp_onp',
        'nCuspp' => 'n_cuspp',
        'insertDate' => 'insert_date',
        'updatedDate' => 'updated_date'
    ];

    public function post($request)
    {
        global $wpdb;
        $o = method_exists($request, 'get_params') ? $request->get_params() : $request;
        $current_user = wp_get_current_user();
        cdfield($o, 'fechaDeInicioContrato');
        cdfield($o, 'fechaDeInicioOfis');
        $o = renameFields($o, self::FIELD_MAP);
        unset($o['editable']);
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_erp");
        $db_erp = "bwgvinpi_ofis";
        $wpdb->select($db_erp);
        $people = $o;

        if (isset($o['id'])) {
            $updated = $wpdb->update('m_personal', $people, array('id' => $people['id']));
        } else {
            $wpdb->insert('m_personal', $o);
        }
        $wpdb->select($original_db);
        if (false === $updated) return t_error();
        return $o;
    }

    public function get($request)
    {
        global $wpdb;
        $db_erp = get_option("db_erp");
        $db_erp = "bwgvinpi_ofis";
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM $db_erp.m_personal WHERE n=%d", $request['id']), ARRAY_A);
        $o['editable'] = true;
        $o['id'] = $o['n'];
        //if ($wpdb->last_error) return t_error();
        /*$people = $wpdb->get_row($wpdb->prepare("SELECT * FROM $db_erp.drt_people WHERE id=%d", $o['people_id']), ARRAY_A);
        cfield($people, 'first_surname', 'firstSurname');
        cfield($people, 'last_surname', 'lastSurname');
        cfield($people, 'full_name', 'fullName');*/
        /*$o['names'] = $people['names'];
        $o['firstSurname'] = $people['firstSurname'];
        $o['lastSurname'] = $people['lastSurname'];
        $o['fullName'] = $people['fullName'];
        $o['code'] = $people['code'];*/
        //$controller = new ExperienceRestController(array());
        //$o['experience'] = Util\toCamelCase($controller->pag(array('from' => 0, 'to' => 0, 'employee_id' => $o['id'])));
        return Util\toCamelCase($o);
    }

    public function pag($request)
    {
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $query = get_param($request, 'query');
        $dni = get_param($request, 'dni');
        $apellidosNombres = get_param($request, 'apellidosNombres');
        $tipoDeContrato = get_param($request, 'tipoDeContrato');
        $afpOnp = get_param($request, 'afpOnp');
        $current_user = wp_get_current_user();
        $db_erp = get_option("db_ofis");
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS pe.*, pe.n id FROM $db_erp.m_personal pe " .
            "WHERE 1=1 " . 
            (isset($query) ? " AND (pe.apellidos_nombres LIKE '%$query%') " : "") .
            (isset($apellidosNombres) ? " AND (pe.apellidos_nombres LIKE '%$apellidosNombres%') " : "") .
            (isset($tipoDeContrato) ? " AND (pe.tipo_de_contrato LIKE '%$tipoDeContrato%') " : "") .
            (isset($afpOnp) ? " AND (pe.afp_onp LIKE '%$afpOnp%') " : "") .
            (isset($dni) ? " AND (pe.dni LIKE '%$dni%') " : "") .
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
