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

class StatsRestController extends Controller
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
        register_rest_route('api/stats', '/personal/resource/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/stats', '/personal/resource/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/stats', '/personal/resource', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/stats', '/personal/resource/(?P<id>)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));

        register_rest_route('api/stats', '/personal/type-resource/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag_type_resource')
        ));

        register_rest_route('api/stats', '/microred', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_microred')
        ));

        register_rest_route('api/stats', '/establishment', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_establishment')
        ));

        register_rest_route('api/stats', '/period', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_period')
        ));

        register_rest_route('api/stats', '/ind_1_seguimiento_red_menor1a_mes_torta', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ind_1_seguimiento_red_menor1a_mes_torta')
        ));

        register_rest_route('api/stats', '/ind_1_seguimiento_red_menor1a_mes_linea_tiempo', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ind_1_seguimiento_red_menor1a_mes_linea_tiempo')
        ));

        register_rest_route('api/stats', '/ind_1_seguimiento_red_menor1a_mes_variable_barra', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ind_1_seguimiento_red_menor1a_mes_variable_barra')
        ));

        register_rest_route('api/stats', '/ind_1_seguimiento_red_menor1a_menor_avance_tabla', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ind_1_seguimiento_red_menor1a_menor_avance_tabla')
        ));
        
        register_rest_route('api/stats', '/ind_3_anemia_red_mes_variable_torta_inasistente_tratam', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ind_3_anemia_red_mes_variable_torta_inasistente_tratam')
        ));
        
        register_rest_route('api/stats', '/ind_3_anemia_mr_mes_linea_tiempo_inasistente_tratam', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ind_3_anemia_mr_mes_linea_tiempo_inasistente_tratam')
        ));

        register_rest_route('api/stats', '/ind_3_anemia_mr_mes_linea_tiempo_diagnosticado_recuperado', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ind_3_anemia_mr_mes_linea_tiempo_diagnosticado_recuperado')
        ));

        register_rest_route('api/stats', '/ind_3_anemia_mr_mes_variable_barra_diagnosticado_recuperado', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ind_3_anemia_mr_mes_variable_barra_diagnosticado_recuperado')
        ));
        

        register_rest_route('api/stats', '/seguimiento', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag_seguimiento')
        ));
        
    }

    public function get_ind_1_seguimiento_red_menor1a_mes_linea_tiempo($request){
        $microred = get_param($request, 'microred');
        $establishment = get_param($request, 'establishment');
        $period = get_param($request, 'period');
        $result = array();
        $current = new \DateTime();
        for ($i = 6; $i >= 0; $i--) {
            $date = (new \DateTime())->modify("-$i month");
            $periodo = $date->format('Y-n');
            $porcentaje = $i === 0
                ? 0.00
                : round(mt_rand(7300, 9100) / 100, 2);
            $result[] = array(
                'periodo' => $periodo,
                'porcentaje_asistencia_cred' => number_format($porcentaje, 2)
            );
        }
        return $result;
    }

    public function get_ind_1_seguimiento_red_menor1a_mes_torta($request){
        $microred = get_param($request, 'microred');
        $establishment = get_param($request, 'establishment');
        $period = get_param($request, 'period');
        $total = rand(800, 1000);
        $asistentes = rand(0, $total);
        $inasistentes = $total - $asistentes;
        return array(
            array("name" => 'Asistentes', "value" => $asistentes),
            array("name" => 'Inasistentes', "value" => $inasistentes)
        );
    }

    public function get_ind_1_seguimiento_red_menor1a_mes_variable_barra($request){
        $microred = get_param($request, 'microred');
        $establishment = get_param($request, 'establishment');
        $period = get_param($request, 'period');
        // Array fijo de microredes
        $microredes = [
            "OCROS","CORPANQUI","SHILLA","AIJA","CHASQUITAMBO","CARHUAZ",
            "ANTA","CAJACAY","MONTERREY","SAN NICOLAS","CATAC","HOSPITAL RECUAY",
            "HOSPITAL CARHUAZ","MARCARA","CHACAS","CHIQUIAN","HUALLANCA","PIRA",
            "HUARUPAMPA","PALMIRA","RECUAY","NICRUPAMPA"
        ];
        /*
        // Posible consulta real si quieres jalar desde DB:
        $db = new DatabaseConnection();
        if ($microred) {
            // Filtrar por microred y jalar todos sus establecimientos
            $query = "SELECT establishment AS ris_microred, porcentaje_asistencia_cred 
                    FROM tabla_asistencia 
                    WHERE microred = :microred";
            // Ejecutar query con $microred
        } else {
            // Traer todas las microredes
            $query = "SELECT microred AS ris_microred, AVG(porcentaje_asistencia_cred) AS porcentaje_asistencia_cred
                    FROM tabla_asistencia
                    GROUP BY microred";
        }
        */
        $result = array();

        foreach ($microredes as $ris) {
            $citados = mt_rand(500, 5000);
            $asistieron = mt_rand(0000, $citados);
            $porcentaje = ($asistieron/$citados)*100;
            $result[] = array(
                "name" => $ris,
                "citados" => $citados,
                "asistieron" => $asistieron,
                "percentage" => number_format($porcentaje, 2)
            );
        }

        return $result;
    }
    //coment
    public function get_ind_1_seguimiento_red_menor1a_menor_avance_tabla($request){
        $microred = get_param($request, 'microred');
        $establishment = get_param($request, 'establishment');
        $period = get_param($request, 'period');
        // Array fijo de microredes
        $microredes = [
            "OCROS","CORPANQUI","SHILLA","AIJA","CHASQUITAMBO","CARHUAZ",
            "ANTA","CAJACAY","MONTERREY","SAN NICOLAS","CATAC","HOSPITAL RECUAY",
            "HOSPITAL CARHUAZ","MARCARA","CHACAS","CHIQUIAN","HUALLANCA","PIRA",
            "HUARUPAMPA","PALMIRA","RECUAY","NICRUPAMPA"
        ];
            // Definir periodos (pueden ser dinámicos)
        // Definir periodos (pueden ser dinámicos)
        $periodos = ["JULIO 2025", "AGOSTO 2025", "SETIEMBRE 2025"];

        $result = [];

        foreach ($microredes as $index => $micro) {
            $row = [
                "codigo" => $index + 1, // opcional, puedes usar otro id
                "nombre" => $micro,
                "periodos" => []
            ];

            foreach ($periodos as $mes) {
                $citados = rand(0, 300); // aleatorio entre 0 y 5
                $asistieron = $citados > 0 ? rand(0, $citados) : 0;
                $porcentaje = $citados > 0 ? round(($asistieron / $citados) * 100, 2) : 0;

                $row["periodos"][] = [
                    "nombre" => $mes,
                    "citados" => $citados,
                    "asistieron" => $asistieron,
                    "porcentaje" => $porcentaje
                ];
            }

            $result[] = $row;
        }

        return $result;
    }

    public function get_ind_3_anemia_red_mes_variable_torta_inasistente_tratam($request){
        $microred = get_param($request, 'microred');
        $establishment = get_param($request, 'establishment');
        $period = get_param($request, 'period');
        $total = rand(800, 1000);
        $asistentes = rand(0, $total);
        $inasistentes = $total - $asistentes;
        return array(
            array("name" => 'Asistentes', "value" => $asistentes),
            array("name" => 'Inasistentes', "value" => $inasistentes)
        );
    }

    public function get_ind_3_anemia_mr_mes_linea_tiempo_inasistente_tratam($request){
        $microred = get_param($request, 'microred');
        $establishment = get_param($request, 'establishment');
        $period = get_param($request, 'period');
        $result = array();
        $current = new \DateTime();
        for ($i = 6; $i >= 0; $i--) {
            $date = (new \DateTime())->modify("-$i month");
            $periodo = $date->format('Y-n');
            $porcentaje = $i === 0
                ? 0.00
                : round(mt_rand(7300, 9100) / 100, 2);
            $result[] = array(
                'periodo' => $periodo,
                'porcentaje_asistencia_cred' => number_format($porcentaje, 2)
            );
        }
        return $result;
    }

    public function get_ind_3_anemia_mr_mes_linea_tiempo_diagnosticado_recuperado($request){
        $microred = get_param($request, 'microred');
        $establishment = get_param($request, 'establishment');
        $period = get_param($request, 'period');
        $result = array();
        $current = new \DateTime();
        for ($i = 12; $i >= 0; $i--) {
            $date = (new \DateTime())->modify("-$i month");
            $periodo = $date->format('Y-n');
            $recuperados =  mt_rand(0, 20);
            $diagnosticados = mt_rand($recuperados, $recuperados+5);
            $result[] = array(
                'periodo' => $periodo,
                'Diagnosticados' => $diagnosticados,
                'Recuperados' => $recuperados
            );
        }
        return $result;
    }

    public function get_ind_3_anemia_mr_mes_variable_barra_diagnosticado_recuperado($request){
        $microred = get_param($request, 'microred');
        $establishment = get_param($request, 'establishment');
        $period = get_param($request, 'period');
        // Array fijo de microredes
        $microredes = [
            "OCROS","CORPANQUI","SHILLA","AIJA","CHASQUITAMBO","CARHUAZ",
            "ANTA","CAJACAY","MONTERREY","SAN NICOLAS","CATAC","HOSPITAL RECUAY",
            "HOSPITAL CARHUAZ","MARCARA","CHACAS","CHIQUIAN","HUALLANCA","PIRA",
            "HUARUPAMPA","PALMIRA","RECUAY","NICRUPAMPA"
        ];
        /*
        // Posible consulta real si quieres jalar desde DB:
        $db = new DatabaseConnection();
        if ($microred) {
            // Filtrar por microred y jalar todos sus establecimientos
            $query = "SELECT establishment AS ris_microred, porcentaje_asistencia_cred 
                    FROM tabla_asistencia 
                    WHERE microred = :microred";
            // Ejecutar query con $microred
        } else {
            // Traer todas las microredes
            $query = "SELECT microred AS ris_microred, AVG(porcentaje_asistencia_cred) AS porcentaje_asistencia_cred
                    FROM tabla_asistencia
                    GROUP BY microred";
        }
        */
        $result = array();

        foreach ($microredes as $ris) {
            $diagnosticados = mt_rand(0, 20);
            $recuperados = mt_rand(0, 20);
            $result[] = array(
                "name" => $ris,
                "Diagnosticados" => $diagnosticados,
                "Recuperados" => $recuperados
            );
        }

        return $result;
    }

    function generarNumeroAleatorio($longitud = 8) {
        $cadena = '';
        for ($i = 0; $i < $longitud; $i++) {
            $cadena .= rand(0, 9); // genera un dígito aleatorio entre 0 y 9
        }
        return $cadena;
    }

    function generarNombreAleatorio() {
    // Letras para construir sílabas
    $consonantes = ['B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Y','Z'];
    $vocales = ['A','E','I','O','U'];

    // Función interna para crear una “palabra” aleatoria
    $generarPalabra = function($longitud) use ($consonantes, $vocales) {
        $palabra = '';
        for ($i = 0; $i < $longitud; $i++) {
            if ($i % 2 == 0) {
                // consonante
                $palabra .= $consonantes[array_rand($consonantes)];
            } else {
                // vocal
                $palabra .= $vocales[array_rand($vocales)];
            }
        }
        return $palabra;
    };

    // Generar dos apellidos y un nombre
    $apellido1 = $generarPalabra(rand(4, 7));
    $apellido2 = $generarPalabra(rand(4, 7));
    $nombre = $generarPalabra(rand(4, 7));

    return "$apellido1 $apellido2 $nombre";
}

function generarArrayAleatorio() {
    // Cantidad aleatoria de elementos (entre 0 y 6)
    $cantidad = rand(0, 6);

    $array = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $array[] = rand(1, 9); // valor aleatorio entre 1 y 9
    }

    return $array;
}
    public function pag_seguimiento($request){
        $microred = get_param($request, 'microred');
        $establishment = get_param($request, 'establishment');
        $period = get_param($request, 'period');

        $patient = array();
        for ($i = 0; $i < 16; $i++) {
            $citados = mt_rand(500, 5000);
            $asistieron = mt_rand(0000, $citados);
            $porcentaje = ($asistieron/$citados)*100;
            $patient[] = array(
                "fullName" => $this->generarNombreAleatorio(),
                "dni" => $this->generarNumeroAleatorio(),
                "fechaNac" => '2025-04-05',
                "age" => '6M 15D',
                'mother' => array(
                    "fullName" => $this->generarNombreAleatorio(),
                    "code" => $this->generarNumeroAleatorio(),
                    "phone" => $this->generarNumeroAleatorio(9)
                ),
                'paquete_integral' => array('T.N.'=>array(), 'V.D.'=>array()),
                'RN' => array(array('Fecha Atensión' => '', 'HIS' => '')),
                'year_less_1' => $this->generarArrayAleatorio(),
                'year_1' => $this->generarArrayAleatorio(),
                'year_2' => $this->generarArrayAleatorio(),
                'year_3' => $this->generarArrayAleatorio(),
                'year_4' => $this->generarArrayAleatorio()
            );
        }
        return array(
            "stats" => array(
                array( "value" => rand(4, 300), "label" => "Niñas y Niños menores de 5 años", "color" => "rgb(172, 128, 238)" ),
                array( "value" => rand(4, 300), "label" => "Niñas y Niños menores de 1 año", "color" => "hsl(var(--primary))" ),
                array( "value" => rand(4, 300), "label" => "Menores de 1 año que asistieron al CRED en Octubre", "color" => "hsl(142, 76%, 36%)" ),
                array( "value" => rand(4, 300), "label" => "Niñas y Niños con Factor de Riesgo", "color" => "hsl(0, 84%, 60%)" ),
            ),
            'data' => $patient
        );
    }
    
    public function get_microred(){
        $microred = array();
        for($i=1;$i<=9;$i++){
            $microred[] = array('name'=>"MICRORED ".$i, 'code'=>$i);
        }
        return $microred;
    }

    public function get_establishment($request){
        $microred = get_param($request, 'microred');
        $establishment = array();
        for($i=1;$i<=9;$i++){
            $establishment[] = array('name'=>"STABLISMENT ".$microred."-".$i, 'code'=>$microred."-".$i);
        }
        return $establishment;
    }

    public function get_period($request) {
        $microred = get_param($request, 'microred'); // reservado para uso futuro

        $periods = array();
        $current = new \DateTime(); // fecha actual

        for ($i = 0; $i < 10; $i++) {
            $periods[] = array(
                'name' => $current->format('Y-m'),
                'code' => $current->format('Y-m')
            );
            $current->modify('-1 month');
        }

        return $periods;
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
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS em.* FROM $db_erp.t_recursos em " .
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
