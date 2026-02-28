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

    /*
        $headers = [
            ['title' => 'NOMBRE COMPLETO', 'width' => 200, 'index' => 'fullName'],
            ['title' => 'DIAS LABORADOS', 'width' => 100],
            [
                'title' => 'INGRESO',
                'backgroundColor' => '#20ab29',
                'children' => [
                    ['title' => 'REMUNERACION', 'width' => 120, 'code' => '0131', 'type' => 1],
                    ['title' => 'D.S. N° 311-2022-EF', 'code' => '0897', 'type' => 1],
                    ['title' => 'D.S. N° 313-2023-EF', 'code' => '0981', 'type' => 1],
                    ['title' => 'D.S. N° 265-2024-EF', 'code' => '1051', 'type' => 1],
                    ['title' => 'D.S. N° 279-2024-EF', 'code' => '1053', 'type' => 1],
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
        ];*/

    function getOrCreatePayroll($year, $month, $typeId, $fuenteFinanc = null, $preparedBy = null)
    {
        global $wpdb;

        // 1️⃣ Buscar existente
        $payroll = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * 
             FROM rem_payroll
             WHERE year = %d 
             AND month = %d 
             AND type_id = %d
             LIMIT 1",
                $year,
                $month,
                $typeId
            )
        );

        if ($payroll) {
            return $payroll;
        }

        // 2️⃣ Insertar si no existe
        $wpdb->insert(
            'rem_payroll',
            [
                'year' => $year,
                'month' => $month,
                'type_id' => $typeId,
                'number' => 1, // puedes ajustar lógica si necesitas correlativo
                'id_fuente_financ' => $fuenteFinanc,
                'closed' => 0,
                'canceled' => 0,
                'prepared_by' => $preparedBy,
                'generate_date' => current_time('mysql')
            ],
            [
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%s'
            ]
        );

        $newId = $wpdb->insert_id;

        // 3️⃣ Devolver el nuevo registro
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM rem_payroll WHERE id = %d",
                $newId
            )
        );
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

    function obtenerNomina($request)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $wpdb->select($db_erp);
        $year = $request->get_param('year');
        $month = $request->get_param('month');
        $concepts = $wpdb->get_results($wpdb->prepare("
    SELECT DISTINCT c.id, c.name, c.pdt_code, c.type_id, c.weight
    FROM rem_payroll_amount a
    INNER JOIN per_concept c ON c.id = a.concept_id
    WHERE a.canceled = 0
      AND a.ini_date <= %s
      AND (a.end_date IS NULL OR a.end_date >= %s)
    ORDER BY c.weight
", "$year-$month-01", "$year-$month-01"));
        $ingresos = [];
        $egresos = [];
        $descuentos = [];
        $aportaciones = [];
        foreach ($concepts as $c) {

            $item = [
                'title' => $c->name,
                'code'  => $c->pdt_code,
                'concept_id' => $c->id
            ];

            if ($c->type_id == 1 || $c->type_id == 2) {
                $ingresos[] = $item;
            } elseif ($c->type_id == 3) {
                $egresos[] = $item;
            } elseif ($c->type_id == 4) {
                $descuentos[] = $item;
            } else {
                $aportaciones[] = $item;
            }
        }

        $ingresos[] = [
            'title' => 'TOTAL INGRESOS I',
            'is_total_ingresos' => true,
            'backgroundColor' => '#badefd',
            'color' => 'black'
        ];
        $egresos[] = [
            'title' => 'TOTAL',
            'is_total_ingresos' => true
        ];
        $egresos[] = [
            'title' => 'TOTAL DSCTO. QUE AFECTAN LA BASE IMPONIBLE (A)',
            'is_total_ingresos' => true,
            'backgroundColor' => '#badefd',
            'color' => 'black'
        ];

        $headers = [
            ['title' => 'NOMBRE COMPLETO', 'width' => 200, 'index' => 'fullName'],
            ['title' => 'DIAS LABORADOS', 'width' => 100],

            [
                'title' => 'INGRESOS',
                'backgroundColor' => '#fbff00',
                'color' => 'black',
                'width' => 110,
                'children' => $ingresos
            ],
            [
                'title' => 'EGRESOS QUE AFECTAN LA BASE IMPONIBLE',
                'backgroundColor' => '#20ab29',
                'color' => 'black',
                'width' => 110,
                'children' => $egresos
            ],

            [
                'title' => 'BASE DE CALCULO CONTRIBUCIONES', ///este es calculado
                'backgroundColor' => '#ad1805',
                'color' => 'white'
            ],
            [
                'title' => 'BASE DE CALCULO  4TA CATG.', ///este es calculado
                'backgroundColor' => '#5f10c7',
                'color' => 'white'
            ],
                        [
                'title' => 'DESCUENTOS DE LEY',
                'backgroundColor' => '#20ab29',
                'color' => 'black',
                'children' => $descuentos
            ],
            [
                'title' => 'APORTES',
                'backgroundColor' => '#20ab29',
                'children' => $aportaciones
            ]
        ];
        $params = $wpdb->get_results($wpdb->prepare("
    SELECT concept_id, amount
    FROM rem_payroll_amount
    WHERE canceled = 0
      AND ini_date <= %s
      AND (end_date IS NULL OR end_date >= %s)
", "$year-$month-01", "$year-$month-01"));

        $amountMap = [];
        foreach ($params as $p) {
            $amountMap[$p->concept_id] = $p->amount;
        }
        $diasMes = 30; //cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $headers = $this->assignLeafIndexes($headers);
        $ingresoConceptIndexes = [];

        foreach ($headers as $h) {
            if (!empty($h['children'])) {
                foreach ($h['children'] as $child) {
                    if (isset($child['concept_id']) && !empty($child['type']) === false) {
                        $ingresoConceptIndexes[$child['concept_id']] = $child['index'];
                    }
                }
            }
        }

        $payroll = $this->getOrCreatePayroll($year, $month, 1);

        // Obtener nombres reales
        $employees = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.apellidos_nombres fullName
         FROM rem_payroll_people pp
         INNER JOIN m_personal p ON p.n = pp.people_id
         WHERE pp.payroll_id = %d
         ORDER BY 1 ",
                $payroll->id
            )
        );
        $ingresoConceptIds = [];
        foreach ($concepts as $c) {
            if ($c->type_id == 1||$c->type_id == 2) {
                $ingresoConceptIds[] = $c->id;
            }
        }
        $items = [];
        foreach ($employees as $employee) {

            $workedDays = $employee->worked_days ?? 30;

            $values = [];
            $values[] = $workedDays;

            $totalIngresos = 0;

            foreach ($concepts as $c) {

                $baseAmount = $amountMap[$c->id] ?? 0;

                if ($c->type_id == 1) {
                    $calculated = round(($baseAmount * $workedDays) / $diasMes, 2);
                    $totalIngresos += $calculated;
                    $values[] = $calculated;
                }else if($c->type_id == 2){
                    $calculated = $baseAmount;
                    $totalIngresos += $calculated;
                    $values[] = $calculated;
                }

                
            }

            // insertar TOTAL INGRESOS justo después de los ingresos
            $values[] = $totalIngresos;
            
            $totalEgresos = 0;

            foreach ($concepts as $c) {
                if ($c->type_id == 3){
                    $amount = $amountMap[$c->id] ?? 0;
                    $totalEgresos += $amount;
                    $values[] = $amount;
                }
            }

            //Total
            $values[] = $totalEgresos;

            //debe caer en TOTAL DSCTO. QUE AFECTAN LA BASE IMPONIBLE (A)
            $values[] = $totalEgresos;

            //BASE DE CALCULO CONTRIBUCIONES
            $base_calculo_contribuciones = $totalIngresos - $totalEgresos;
            $values[] = $base_calculo_contribuciones;

            //BASE DE CALCULO  4TA CATG.
            $values[] = $totalIngresos - $totalEgresos;

            //APORTE SOLID. POR  CONV. COLECTIVO
            $values[] = $base_calculo_contribuciones*0.08;

            $items[] = [
                'fullName' => $employee->fullName,
                'values'   => $values
            ];
        }
        $wpdb->select($original_db);
        return [
            'success' => true,
            'data' => $items,
            'headers' => $headers,
            'payroll' => $payroll
        ];
    }

    function loadPayroll($id)
    {
        global $wpdb;

        // ===== 1. LOAD PAYROLL =====
        $payroll = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM rem_payroll WHERE id = %d", $id)
        );

        if (!$payroll) return null;

        // ===== 2. LOAD PEOPLE =====
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pp.people_id,
                    pe.full_name,
                    pe.document
             FROM payroll_people pp
             JOIN people pe ON pe.code = pp.people_id
             WHERE pp.payroll_id = %d
             AND pp.people_id > 0
             ORDER BY pe.fullname",
                $id
            )
        );

        $persons = [];
        $peopleMap = [];

        foreach ($rows as $r) {
            $pp = new \stdClass();
            $pp->peopleId = $r->people_id;
            $pp->fullName = $r->full_name;
            $pp->document = $r->document;

            // equivalente a new Object[1]
            $pp->ext = [null];

            $persons[] = $pp;
            $peopleMap[$r->people_id] = $pp;
        }

        // ===== 3. LOAD CONCEPTS (concept_id = 0) =====
        $concepts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT people_id, amount
             FROM rem_payroll_concept
             WHERE payroll_id = %d
             AND concept_id = 0",
                $id
            )
        );

        foreach ($concepts as $c) {
            if (isset($peopleMap[$c->people_id])) {
                $peopleMap[$c->people_id]->ext[0] = $c->amount;
            }
        }

        // ===== 4. EXT STRUCTURE =====
        $payroll->ext = [
            "persons" => $persons
        ];

        return $payroll;
    }

    function getPayrollPeopleList($payrollId0, $group)
    {
        global $wpdb;
        $columns = 13;
        $result = [];
        // ===== PAYROLL BASE =====
        $payroll0 = $wpdb->get_row(
            $wpdb->prepare("SELECT p.*, pt.group_id 
                        FROM payroll p
                        JOIN payroll_type pt ON pt.id=p.type_id
                        WHERE p.id=%d", $payrollId0)
        );

        if (!$payroll0) return [];

        // ===== LISTA PAYROLL =====
        if ($group > 0) {
            $payrollList = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.*
                 FROM payroll p
                 JOIN payroll_type pt ON pt.id=p.type_id
                 WHERE p.year=%d AND p.month=%d AND pt.group_id=%d",
                    $payroll0->year,
                    $payroll0->month,
                    $payroll0->group_id
                )
            );
        } else {
            $payrollList = [$payroll0];
        }

        foreach ($payrollList as $payroll) {

            // ========= FORMATEOS =========
            $payroll->code = sprintf("%02d", $payroll->month)
                . substr($payroll->year, 2)
                . "-" . sprintf("%04d", $payroll->number);

            $months = [
                "ENERO",
                "FEBRERO",
                "MARZO",
                "ABRIL",
                "MAYO",
                "JUNIO",
                "JULIO",
                "AGOSTO",
                "SEPTIEMBRE",
                "OCTUBRE",
                "NOVIEMBRE",
                "DICIEMBRE"
            ];

            $payroll->monthName = $months[$payroll->month - 1];

            // ========= CONCEPTOS =========
            $concepts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT c.id, c.name, c.abbreviation
                 FROM payroll_concept pc
                 JOIN per_concept c ON c.id = pc.concept_id
                 WHERE pc.payroll_id=%d
                 AND c.type_id>0
                 ORDER BY c.type_id, c.weight",
                    $payroll->id
                )
            );

            $positionMap = [];
            $headerList = [];
            $summaryList = [];

            $pos = 0;

            foreach ($concepts as $c) {

                if ($pos >= $columns) $pos = 0;

                if ($pos == 0) {
                    $headerList[] = array_fill(0, $columns, null);
                    $summaryList[] = array_fill(0, $columns, 0);
                }

                $headerList[count($headerList) - 1][$pos] =
                    $c->abbreviation ?: $c->name;

                $positionMap[$c->id] =
                    (count($headerList) - 1) * $columns + $pos;

                $pos++;
            }

            // ========= PERSONAS =========
            $peopleRows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pp.*, pe.full_name,
                        em.income_date
                 FROM payroll_people pp
                 LEFT JOIN people pe ON pe.code = pp.people_id
                 LEFT JOIN employee em ON em.id = pp.employee_id
                 WHERE pp.payroll_id=%d
                 ORDER BY pe.full_name",
                    $payroll->id
                )
            );

            $peopleMap = [];
            $peopleList = [];

            foreach ($peopleRows as $row) {

                $extConcept = [];
                for ($i = 0; $i < count($headerList); $i++)
                    $extConcept[$i] = array_fill(0, $columns, 0);

                $row->ext = [
                    "header" => $headerList,
                    "concept" => $extConcept,
                    "concepts" => [],
                    "summary" => $summaryList
                ];

                $row->totalDesc = $row->totalDesc ?? 0;

                $peopleMap[$row->people_id] = $row;
                $peopleList[] = $row;
            }

            // ========= MONTOS =========
            $amounts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT people_id, concept_id, amount
                 FROM payroll_concept
                 WHERE payroll_id=%d",
                    $payroll->id
                )
            );

            foreach ($amounts as $pc) {

                if (!isset($peopleMap[$pc->people_id])) continue;

                if (!isset($positionMap[$pc->concept_id])) continue;

                $pp = $peopleMap[$pc->people_id];
                $po = $positionMap[$pc->concept_id];

                $rowIndex = intdiv($po, $columns);
                $colIndex = $po % $columns;

                $pp->ext["concept"][$rowIndex][$colIndex] = $pc->amount;
                $pp->ext["summary"][$rowIndex][$colIndex] += $pc->amount;

                if ($pc->concept_id == 80)
                    $pp->esSalud = $pc->amount;
            }

            // asegurar 5 filas
            while (count($headerList) < 5) {
                $headerList[] = array_fill(0, $columns, null);
                $summaryList[] = array_fill(0, $columns, 0);
            }

            $result = array_merge($result, $peopleList);
        }

        return $result;
    }


    public function period($request)
    {
        return $this->obtenerNomina($request);
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
