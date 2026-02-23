<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\directory\Util\remove;
use function IB\directory\Util\cfield;
use function IB\directory\Util\camelCase;
use function IB\directory\Util\cdfield;
use function IB\directory\Util\t_error;

class PayrollRestController extends Controller
{

    public function init()
    {
        add_role(
            'payroll_admin',
            'payroll_admin',
            array(
                'PAYROLL_ADMIN'         => true,
                'PAYROLL_READ'         => true
            )
        );
        add_role(
            'payroll_register',
            'payroll_register',
            array(
                'PAYROLL_REGISTER'         => true,
                'PAYROLL_READ'         => true
            )
        );
    }

    public function rest_api_init()
    {
        register_rest_route('api/payroll', 'period', array(
            'methods' => 'GET',
            'callback' => array($this, 'period')
        ));
    }

    function assignLeafIndexes(array &$headers)
    {
        $index = 0;

        $walk = function (&$items) use (&$walk, &$index) {
            foreach ($items as &$h) {
                if (isset($h['children']) && count($h['children']) > 0) {
                    $walk($h['children']);
                } elseif (!isset($h['index'])) {
                    $h['index'] = $index++; // solo hojas obtienen índice
                }
            }
        };

        $walk($headers);
        return $headers;
    }

    // Función para generar sílaba consonante+vocal
    function generarSílaba()
    {
        $consonantes = 'bcdfghjklmnpqrstvwxyz';
        $vocales = 'aeiou';
        $c = $consonantes[random_int(0, strlen($consonantes) - 1)];
        $v = $vocales[random_int(0, strlen($vocales) - 1)];
        return $c . $v;
    }

    // Función para generar palabra de 2 a 4 sílabas
    function generarPalabra()
    {
        $numSilabas = random_int(2, 4);
        $palabra = '';
        for ($i = 0; $i < $numSilabas; $i++) {
            $palabra .= $this->generarSílaba();
        }
        return ucfirst($palabra);
    }

    // Función para generar nombre completo con 3 o 4 palabras
    function generarNombreCompleto()
    {
        $numPalabras = (random_int(0, 1) === 0) ? 3 : 4;
        $palabras = [];
        for ($i = 0; $i < $numPalabras; $i++) {
            $palabras[] = $this->generarPalabra();
        }
        return implode(' ', $palabras);
    }

    // Simulación de endpoint similar a mock.onGet
    function obtenerNomina()
    {
        $headers = [
            ['title' => 'NOMBRE COMPLETO', 'width' => 200, 'index' => 'fullName'],
            ['title' => 'DIAS LABORADOS', 'width' => 100],
            [
                'title' => 'INGRESO',
                'backgroundColor' => '#20ab29',
                'children' => [
                    ['title' => '_REMUNERACION', 'code' => '0131'],
                    ['title' => 'REMUNERACION', 'width' => 120, 'code' => '0131', 'type' => 1],
                    ['title' => '_D.S. N° 311-2022-EF', 'code' => '0897'],
                    ['title' => 'D.S. N° 311-2022-EF', 'code' => '0897', 'type' => 1],
                    ['title' => '_D.S. N° 313-2023-EF', 'code' => '0981'],
                    ['title' => 'D.S. N° 313-2023-EF', 'code' => '0981', 'type' => 1],
                    ['title' => '_D.S. N° 265-2024-EF', 'code' => '1051'],
                    ['title' => 'D.S. N° 265-2024-EF', 'code' => '1051', 'type' => 1],
                    ['title' => '_D.S. N° 279-2024-EF', 'code' => '1053'],
                    ['title' => 'D.S. N° 279-2024-EF', 'code' => '1053', 'type' => 1],
                    ['title' => '_DS 327-2025-EF', 'code' => ''],
                    ['title' => 'DS 327-2025-EF', 'code' => '', 'type' => 1],
                    ['title' => 'DIFERENCIAL SUBSIDIO', 'width' => 100, 'code' => '', 'type' => 1],
                    ['title' => 'REINTEGRO / COPAGO', 'code' => '0236'],
                    ['title' => 'CLASIFICADOR INGRESOS', 'width' => 100],
                    ['title' => 'TOTAL INGRESOS', 'backgroundColor' => '#badefd', 'color' => 'black']
                ]
            ],
            [
                'title' => 'EGRESOS QUE AFECTAN LA BASE IMPONIBLE',
                'backgroundColor' => '#20ab29',
                'children' => [
                    ['title' => 'TARDANZAS', 'type' => 2],
                    ['title' => 'JORN. INCOMPLETA', 'width' => 100, 'type' => 2],
                    ['title' => 'PERMISO PERSONAL', 'type' => 2],
                    ['title' => 'INASISTENCIAS/ LSGH', 'type' => 2],
                    ['title' => 'TOTAL'],
                    ['title' => 'PAGO EN EXCESO'],
                    ['title' => 'TOTAL DSCTO. QUE AFECTAN LA BASE IMPONIBLE (A)', 'width' => 120, 'backgroundColor' => '#badefd', 'color' => 'black']
                ]
            ],
            ['title' => 'BASE DE CALCULO CONTRIBUCIONES'],
            ['title' => 'BASE DE CALCULO  4TA CATG.', 'backgroundColor' => '#5f2da3'],
            [
                'title' => 'DESCUENTOS DE LEY',
                'backgroundColor' => '#20ab29',
                'children' => [
                    ['title' => 'SUSPENSIÓN 4TA SI/NO'],
                    ['title' => 'RETENCION DE 4TA', 'type' => 3],
                    ['title' => 'APORTE ONP', 'type' => 3],
                    ['title' => 'APORTE OBLIGATORIO AFP 10%', 'width' => 100, 'type' => 3],
                    ['title' => 'APORTE SEGURO AFP', 'type' => 3],
                    ['title' => 'APORTE COMISION AFP', 'type' => 3],
                    ['title' => 'TOTAL DESCUENTOS DE LEY (B)', 'backgroundColor' => '#badefd', 'color' => 'black']
                ]
            ],
            ['title' => 'SI/NO'],
            ['title' => 'APORTE SOLID. POR  CONV. COLECTIVO 0.5%', 'width' => 100],
            [
                'title' => 'OTROS DESCUENTOS',
                'backgroundColor' => '#20ab29',
                'children' => [
                    ['title' => 'OTROS COOPAC SAN MIGUEL', 'width' => 100],
                    ['title' => 'ESSALUD + VIDA'],
                    ['title' => 'JUDICIAL / COACTIVO'],
                    ['title' => 'TOTAL OTROS DESCUENTOS (C)', 'width' => 100, 'backgroundColor' => '#badefd', 'color' => 'black'],
                    ['title' => 'TOTAL DESCUENTOS II = (A + B + C)', 'width' => 100, 'backgroundColor' => '#badefd', 'color' => 'black']
                ]
            ],
            ['title' => 'AGUINALDO', 'width' => 90],
            ['title' => 'NETO A PAGAR (I) - (II)', 'width' => 100, 'backgroundColor' => '#badefd', 'color' => 'black'],
            ['title' => 'ESSALUD CAS']
        ];
        $headers = $this->assignLeafIndexes($headers);

        $items = [];
        for ($i = 0; $i < 20; $i++) {
            $items[] = [
                'fullName' => strtoupper($this->generarNombreCompleto()),
                'values' => [
                    30,
                    2500,
                    null,
                    64.19,
                    null,
                    50,
                    null,
                    50,
                    null,
                    100,
                    null,
                    100,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    8,
                    null,
                    null,
                    7,
                    88,
                    9,
                    736.42,
                    100.89,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null
                ]
            ];
        }

        return [
            'success' => true,
            'data' => $items,
            'headers' => $headers
        ];
    }

    public function period($request)
    {



        return $this->obtenerNomina();


        global $wpdb;
        $edb = 2;
        $from = $request['from'];
        $to = $request['to'];
        $numeroDNI = method_exists($request, 'get_param') ? $request->get_param('numeroDNI') : $request['numeroDNI'];
        $fullName = method_exists($request, 'get_param') ? $request->get_param('fullName') : $request['fullName'];
        $red = method_exists($request, 'get_param') ? $request->get_param('red') : $request['red'];
        $microred = method_exists($request, 'get_param') ? $request->get_param('microred') : $request['microred'];
        $microredName = method_exists($request, 'get_param') ? $request->get_param('microredName') : $request['microredName'];
        $current_user = wp_get_current_user();
        $wpdb->last_error  = '';

        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS g.*,r.red as nameRed,mr.microred as nameMicroRed,COUNT(v.id) AS visits FROM ds_gestante g " .
            "LEFT JOIN ds_gestante_visita v ON v.gestante_id=g.id 
            LEFT JOIN grupoipe_project.MAESTRO_RED r ON r.codigo_red=g.red
            LEFT JOIN grupoipe_project.MAESTRO_MICRORED mr ON mr.codigo_cocadenado=g.microred
            WHERE g.canceled=0 " . (isset($numeroDNI) ? " AND g.numero_dni like '%$numeroDNI%' " : "")
            . (isset($fullName) ? " AND CONCAT(g.apellido_paterno,g.apellido_materno,g.nombres) like '%$fullName%' " : "")
            . (isset($red) ? " AND g.red like '%$red%' " : "")
            . (isset($microred) ? " AND g.microred like '%$microred%' " : "")
            . (isset($microredName) ? " AND UPPER(mr.microred) like UPPER('%$microredName%') " : "") .
            "GROUP BY g.id " .
            "ORDER BY id desc LIMIT " . $from . ', ' . $to, ARRAY_A);

        if ($wpdb->last_error) return t_error();
        foreach ($results as &$r) {
            cfield($r, 'numero_dni', 'numeroDNI');
            if (isset($r['nameRed'])) $r['red'] = array('code' => $r['red'], 'name' => $r['nameRed']);
            if (isset($r['nameMicroRed'])) $r['microred'] = array('code' => $r['microred'], 'name' => $r['nameMicroRed']);
            cfield($r, 'estado_civil', 'estadoCivil');
            cfield($r, 'emergency_microred', 'emergencyMicrored');
            cfield($r, 'grado_instruccion', 'gradoInstruccion');
        }
        $count = $wpdb->get_var('SELECT FOUND_ROWS()');
        if ($wpdb->last_error) return t_error();
        return array('data' => $results, 'size' => $count);
    }


    public function visit_pag($request)
    {
        global $wpdb;
        $from = $request['from'];
        $to = $request['to'];
        $gestanteId = method_exists($request, 'get_param') ? $request->get_param('gestanteId') : $request['gestanteId'];
        $current_user = wp_get_current_user();
        $wpdb->last_error  = '';
        $results = $wpdb->get_results("SELECT SQL_CALC_FOUND_ROWS * FROM ds_gestante_visita d Where canceled=0 " . ($gestanteId ? "AND gestante_id=$gestanteId" : "") . " ORDER BY id desc " . ($to ? "LIMIT " . $from . ', ' . $to : ""), ARRAY_A);
        if ($wpdb->last_error) return t_error();
        foreach ($results as &$r) {
            cfield($r, 'fecha_visita', 'fechaVisita');
            cfield($r, 'numero_visita', 'number');
            cfield($r, 'gestante_id', 'gestanteId');
        }
        $count = $wpdb->get_var('SELECT FOUND_ROWS()');
        if ($wpdb->last_error) return t_error();

        return $to ? array('data' => $results, 'size' => $count) : $results;
    }

    public function delete($data)
    {
        global $wpdb;
        $row = $wpdb->update('ds_gestante', array('canceled' => 1), array('id' => $data['id']));
        return $row;
    }

    function visit_post(&$request)
    {
        global $wpdb;
        $o = method_exists($request, 'get_params') ? $request->get_params() : $request;
        $current_user = wp_get_current_user();
        cdfield($o, 'fechaVisita');

        cfield($o, 'pregnantId', 'gestante_id');
        cfield($o, 'fechaVisita', 'fecha_visita');
        cfield($o, 'number', 'numero_visita');
        cdfield($o, 'fechaProxVisita');
        cfield($o, 'fechaProxVisita', 'fecha_prox_visita');
        unset($o['people']);
        unset($o['ext']);
        $tmpId = remove($o, 'tmpId');
        unset($o['synchronized']);
        $o['uid'] = $current_user->ID;

        $inserted = 0;
        if ($o['id'] > 0) {
            $o['updated_date'] = current_time('mysql', 1);
            $updated = $wpdb->update('ds_gestante_visita', $o, array('id' => $o['id']));
        } else {
            unset($o['id']);
            $max = $wpdb->get_row($wpdb->prepare("SELECT ifnull(max(`numero_visita`),0)+1 AS max FROM ds_gestante_visita WHERE gestante_id=" . $o['gestante_id']), ARRAY_A);
            $o['numero_visita'] = $max['max'];
            $o['user_register'] = $current_user->user_login;
            $o['inserted_date'] = current_time('mysql', 1);
            if ($tmpId) $o['offline'] = $tmpId;
            $updated = $wpdb->insert('ds_gestante_visita', $o);
            $o['id'] = $wpdb->insert_id;
            $inserted = 1;
        }
        if (false === $updated) return t_error();
        if ($inserted && $tmpId) {
            $updated = $wpdb->update('ds_sivico_agreement', array('people_id' => $o['id']), array('people_id' => -$tmpId));
            if (false === $updated) return t_error();
        }
        if ($tmpId) {
            $o['tmpId'] = $tmpId;
            $o['synchronized'] = 1;
        }

        cfield($o, 'numero_visita', 'numeroVisita');
        return $o;
    }

    function visit_get($data)
    {
        global $wpdb;
        //$data=method_exists($data,'get_params')?$data->get_params():$data;
        $o = $wpdb->get_row($wpdb->prepare("SELECT * FROM ds_gestante_visita WHERE id=" . $data['id']), ARRAY_A);
        if ($wpdb->last_error) return t_error();
        cfield($o, 'fecha_visita', 'fechaVisita');
        cdfield($o, 'fechaProxVisita');
        cfield($o, 'fecha_prox_visita', 'fechaProxVisita');
        cfield($o, 'numero_visita', 'number');
        cfield($o, 'gestante_id', 'pregnantId');
        cdfield($o, 'fechaVisita');
        return $o;
    }

    function visit_number_get($request)
    {
        global $wpdb;
        $max = $wpdb->get_row($wpdb->prepare("SELECT ifnull(max(`numero_visita`),0)+1 AS max FROM ds_gestante_visita WHERE gestante_id=" . $request['pregnant']), ARRAY_A);
        return $max['max'];
    }
}
