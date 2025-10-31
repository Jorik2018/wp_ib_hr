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

class MovimientoRestController extends Controller
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
        register_rest_route('api/hr', '/movement/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/hr', '/movement/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/hr', '/movement', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/hr', '/movement/(?P<id>)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));
    }

    public function post_upload($request)
    {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'No se recibió archivo o hubo un error.'];
        }

        $tmpName = $_FILES['file']['tmp_name'];
        $originalName = $_FILES['file']['name'];

        // Carpeta temporal
        $tempDir = WP_CONTENT_DIR . '/uploads/temp/';
        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

        $tempFile = $tempDir . uniqid() . '_' . basename($originalName);

        if (move_uploaded_file($tmpName, $tempFile)) {
            // Guardar info en session para submit final
            if (!session_id()) session_start();
            $_SESSION['temp_file'] = $tempFile;
            $_SESSION['temp_file_name'] = $originalName;

            return [
                'success' => true,
                'temp_file_name' => $originalName,
                'message' => 'Archivo subido temporalmente.'
            ];
        } else {
            return ['error' => 'Error al mover el archivo a la carpeta temporal.'];
        }
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
        'organoId' => 'organo_id',
        'unidadId' => 'unidad_id',
        'insertDate' => 'insert_date',
        'updatedDate' => 'updated_date'
    ];

    public function post($request)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $o = get_param($request);
        $resources = remove($o, 'resources');
        $personal  = remove($o, 'personal');
        $o['dni'] = $personal['dni'];
        $wpdb->select($db_erp);
        if (isset($o['id'])) {
            $o['update_date'] = current_time('mysql', 1);
            $updated = $wpdb->update('r_actas', $o, ['id' => $o['id']]);
        } else {
            $o['insert_date'] = current_time('mysql', 1);
            $updated = $wpdb->insert('r_actas', $o);
            $o['id'] = $wpdb->insert_id;
        }
        if ($updated === false ) return t_error();
        $id = $o['id'];
        $resourcesOut = [];
        foreach ($resources as $row) {
            $detalleId  = $row['id'] ?? null;
            if (!empty($row['delete'])) {
                if ($detalleId) {
                    $wpdb->delete('r_actas_det', ['id' => $detalleId]);
                }
                $resourcesOut[] = [
                    'id' => $detalleId,
                    'deleted' => true
                ];
            } else if ($detalleId) {
                $resourcesOut[] = $row;
                continue;
            } else {
                $updated = $wpdb->insert('r_actas_det', [
                    'movement_id' => $id,
                    'resource_id' => $row['resourceId']
                ]);
                if ($updated === false ) return t_error();
                $row['id'] = $wpdb->insert_id;
                $resourcesOut[] = $row;
            }
        }

        if (!empty($o['active']) && $o['active'] === true) {
            foreach ($resourcesOut as $row) {
                if (!empty($row['id']) && empty($row['deleted'])) {
                    $wpdb->update(
                        't_recursos',
                        ['dni' => $personal['dni']], 
                        ['id' => $row['resourceId']]
                    );
                    if ($wpdb->last_error) return t_error();
                }
            }
        }

        $wpdb->select($original_db);
        $o['resources'] = $resourcesOut;
        return $o;
    }


    public function get($request)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM $db_erp.r_actas WHERE id=%d", $request['id']), ARRAY_A);
        $o['personal'] = $wpdb->get_row($wpdb->prepare("SELECT * FROM $db_erp.m_personal WHERE dni=%d", $o['dni']), ARRAY_A);
        if ($wpdb->last_error) return t_error();
        $wpdb->select($original_db);
        $o['editable'] = true;
        $o['resources'] = $wpdb->get_results($wpdb->prepare("SELECT 
                d.id AS id,
                d.resource_id AS resourceId,
                r.tipo,
                r.codpatrimonio,
                r.codigo,
                r.modelo,
                r.marca,
                r.observaciones,
                tb.tipo AS typeName
            FROM 
                $db_erp.r_actas_det d
            INNER JOIN 
                $db_erp.t_recursos r ON r.id = d.resource_id
            INNER JOIN 
                $db_erp.maestro_tipo_bien tb ON tb.id = r.tipo
            WHERE 
                d.movement_id = %d
            ORDER BY d.id ASC", $o['id']),
            ARRAY_A
        );
        if ($wpdb->last_error) return t_error();
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
        $organo = get_param($request, 'organo');
        $afpOnp = get_param($request, 'afpOnp');
        $current_user = wp_get_current_user();
        $db_erp = get_option("db_ofis");
        $wpdb->last_error = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS ac.*, pe.apellidos_nombres FROM $db_erp.r_actas ac LEFT JOIN m_personal pe ON pe.dni=ac.dni " .
            "WHERE 1=1 " . 
            (isset($query) ? " AND (pe.apellidos_nombres LIKE '%$query%') " : "") .
            (isset($apellidosNombres) ? " AND (pe.apellidos_nombres LIKE '%$apellidosNombres%') " : "").
            (isset($organo) ? " AND (pe.organo LIKE '%$organo%') " : "").
            (isset($dni) ? " AND (ac.dni LIKE '%$dni%') " : "").
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
