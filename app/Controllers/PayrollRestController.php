<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\directory\Util\remove;
use function IB\directory\Util\cfield;
use function IB\directory\Util\get_param;
use function IB\directory\Util\cdfield;
use function IB\directory\Util\t_error;
use Dompdf\Dompdf;

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

        register_rest_route('api/payroll', 'concept/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/payroll', '(?P<id>\d+)/personal', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_personal')
        ));

        register_rest_route('api/payroll', 'add-person', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_person')
        ));

        register_rest_route('api/payroll', 'add-concept', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_concept')
        ));

        register_rest_route('api/payroll', '/download', array(
            'methods' => 'POST',
            'callback' => array($this, 'download')
        ));

        register_rest_route('api/payroll', '/process', array(
            'methods' => 'POST',
            'callback' => array($this, 'process')
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

    public function pag($request)
    {
        global $wpdb;

        $db = get_option("db_ofis");

        // Parámetros de búsqueda
        $params = [
            'c.name'         => get_param($request, 'name'),
            'c.abbreviation' => get_param($request, 'abbreviation'),
            'c.pdt_code'     => get_param($request, 'pdt_code'),
            'c.description'  => get_param($request, 'description'),
            'c.type_id'      => get_param($request, 'type_id')
        ];

        $from = (int) get_param($request, 'from');
        $to   = (int) get_param($request, 'to');

        $where = [];
        $values = [];

        foreach ($params as $column => $value) {
            if ($value !== null && $value !== '') {

                // búsquedas exactas para números
                if ($column === 'c.type_id') {
                    $where[] = "$column = %d";
                    $values[] = (int) $value;
                } else {
                    $where[] = "UPPER($column) LIKE %s";
                    $values[] = '%' . strtoupper($value) . '%';
                }
            }
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS c.*
            FROM $db.per_concept c";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY c.id DESC";

        if ($to > 0) {
            $sql .= " LIMIT %d, %d";
            $values[] = $from;
            $values[] = $to;
        }

        // Preparar consulta segura
        $query = $wpdb->prepare($sql, $values);

        $results = $wpdb->get_results($query, OBJECT);

        if ($wpdb->last_error) {
            return t_error($wpdb->last_error);
        }

        return $to > 0
            ? ['data' => $results, 'size' => (int)$wpdb->get_var("SELECT FOUND_ROWS()")]
            : $results;
    }

    public function add_concept($request)
    {
        global $wpdb;

        // Obtener params compatible WP_REST_Request o array
        $o = get_param($request);

        $original_db = $wpdb->dbname;
        $wpdb->select(get_option("db_ofis"));

        $current_user = wp_get_current_user();

        $concept = $o['concept'] ?? [];
        $amount = $o['amount'] ?? null;

        $payroll_group_id = $o['payroll_group_id'] ?? null;
        $payroll_type_id  = $o['payroll_type_id'] ?? null;
        $type             = $o['targetType'] ?? null;
        $target_id        = $o['target'] ?? null;
        $ini_date         = $o['ini_date'] ?? date('Y-m-d');
        $end_date         = $o['end_date'] ?? null;

        if (!$concept) {
            $wpdb->select($original_db);
            return t_error('No concepts provided');
        }

        if (!$target_id) {
            $wpdb->select($original_db);
            return t_error('target_id is required');
        }

            $data = [
                'payroll_group_id' => $payroll_group_id,
                'payroll_type_id'  => $payroll_type_id,
                'type'             => $type,
                'target_id'        => $target_id,
                'concept_id'       => (int) $concept,
                'ini_date'         => $ini_date,
                'end_date'         => $end_date,
                'amount'           => (float) $amount,
                'canceled'         => 0
            ];

            $ok = $wpdb->insert('rem_payroll_amount', $data);

            if ($ok === false) {
                $last_error = $wpdb->last_error;
                $wpdb->select($original_db);
                if ($last_error) return t_error($last_error);
            }

            $inserted = $concept;
        

        $wpdb->select($original_db);

        return [
            'inserted' => $inserted
        ];
    }

    function add_person($request)
    {
        global $wpdb;

        // Obtener params compatible WP_REST_Request o array
        $o = get_param($request);

        $original_db = $wpdb->dbname;
        $wpdb->select(get_option("db_ofis"));

        $current_user = wp_get_current_user();

        $persons = $o['persons'] ?? [];
        $payroll_type_id = $o['payrollTypeId'] ?? 1;
        $beneficiary = $o['beneficiary'];
        $benefit_type_id = $o['benefit_type_id'];

        if (!is_array($persons) || empty($persons)) {
            $wpdb->select($original_db);
            return t_error('No persons provided');
        }

        $inserted = [];

        foreach ($persons as $person) {

            if (!is_array($person)) continue;

            $people_id = $person['id'] ?? null;

            if (!$people_id) continue;

            $data = [
                'payroll_type_id' => $payroll_type_id,
                'people_id'       => (int) $people_id,
                'beneficiary'     => $beneficiary,
                'benefit_type_id' => $benefit_type_id
            ];

            $ok = $wpdb->insert('rem_payroll_type_people', $data);

            if ($ok === false) {
                $last_error = $wpdb->last_error;
                $wpdb->select($original_db);
                if ($last_error) return t_error($last_error);
            }

            $inserted[] = $people_id;
        }

        $wpdb->select($original_db);

        return [
            'inserted' => $inserted,
            'count'    => count($inserted)
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
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $wpdb->select($db_erp);
        $year = get_param($request, 'year');
        $month = get_param($request, 'month');
    
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
        $otros_descuentos = [];
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
            } elseif ($c->type_id == 6) {
                $otros_descuentos[] = $item;
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

        $descuentos[] = [
            'title' => 'TOTAL DESCUENTOS DE LEY (B)',
            'is_total_ingresos' => true,
            'backgroundColor' => '#badefd',
            'color' => 'black'
        ];

        $otros_descuentos[] = [
            'title' => 'TOTAL OTROS DESCUENTOS (C)',
            'is_total_ingresos' => true,
            'backgroundColor' => '#badefd',
            'color' => 'black'
        ];

        $otros_descuentos[] = [
            'title' => 'TOTAL DESCUENTOS II = (A + B + C)',
            'is_total_ingresos' => true,
            'backgroundColor' => '#badefd',
            'color' => 'black'
        ];

        $headers = [
            ['title' => 'CODE', 'width' => 80, 'index' => 'code', 'class' => 'center'],
            ['title' => 'NOMBRE COMPLETO', 'width' => 200, 'index' => 'fullName'],
            ['title' => 'AFP / ONP', 'width' => 100, 'index' => 'pensionSystem', 'class' => 'center'],
            ['title' => 'N° CUSPP', 'width' => 120, 'index' => 'nCUSPP', 'class' => 'center'],
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
                'backgroundColor' => '#54e05e',
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
                'backgroundColor' => '#54e05e',
                'color' => 'black',
                'children' => $descuentos
            ],
            [
                'title' => 'APORTE SOLID. POR  CONV. COLECTIVO 0.5%',
            ],
            [
                'title' => 'OTROS DESCUENTOS',
                'backgroundColor' => '#54e05e',
                'color' => 'black',
                'children' => $otros_descuentos
            ],
            [
                'title' => 'APORTES',
                'backgroundColor' => '#54e05e',
                'children' => $aportaciones
            ]
        ];
        $params = $wpdb->get_results($wpdb->prepare("
            SELECT concept_id, amount, type, target_id, payroll_type_id
            FROM rem_payroll_amount
            WHERE canceled = 0
            AND ini_date <= %s
            AND (end_date IS NULL OR end_date >= %s)
        ", "$year-$month-01", "$year-$month-01"));

        $amountMap = [];
        foreach ($params as $p) {
            if($p->type=='PL') {
                $amountMap[''.$p->concept_id][$p->type]['1' /*send the payroll_type_id*/] = $p->amount;
            } else {
                $amountMap[''.$p->concept_id][$p->type][$p->target_id] = $p->amount;
            }
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
        /*$employees = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.apellidos_nombres fullName
         FROM rem_payroll_people pp
         INNER JOIN m_personal p ON p.n = pp.people_id
         WHERE pp.payroll_id = %d
         ORDER BY 1 ",
                $payroll->id
            )
        );*/
        $employees = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.apellidos_nombres fullName, pp.payroll_type_id, pp.people_id, p.afp_onp  pensionSystem, p.n_cuspp nCUSPP, p.dni code 
         FROM rem_payroll_type_people pp
         INNER JOIN m_personal p ON p.n = pp.people_id
         ORDER BY 1 ",
                $payroll->id
            )
        );
        $items = [];
        foreach ($employees as $employee) {

            $workedDays = $employee->worked_days ?? 30;

            $values = [];
            $values[] = $workedDays;

            $totalIngresos = 0;

            foreach ($concepts as $c) {
                $baseAmount = $this->resolveAmount($c->id, $employee, $employee -> payroll_type_id, $amountMap);
                if ($c->type_id == 1) {
                    $calculated = round(($baseAmount * $workedDays) / $diasMes, 2);
                    $totalIngresos += $calculated;
                    $values[] = $calculated;
                } else if ($c->type_id == 2) {
                    $calculated = $baseAmount;
                    $totalIngresos += $calculated;
                    $values[] = $calculated;
                }
            }

            // insertar TOTAL INGRESOS justo después de los ingresos
            $values[] = $totalIngresos;

            $totalEgresos = 0;

            foreach ($concepts as $c) {
                if ($c->type_id == 3) {
                    $baseAmount = $this->resolveAmount($c->id, $employee,  $employee -> payroll_type_id, $amountMap);
                    $amount = $baseAmount;
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

            $descuentos_ley = 0;

            foreach ($concepts as $c) {
                if ($c->type_id == 4) {
                    $baseAmount = $this->resolveAmount($c->id, $employee,  $employee -> payroll_type_id, $amountMap);
                    $calculated = round($baseAmount * $base_calculo_contribuciones, 2);
                    $descuentos_ley += $calculated;
                    $values[] = $calculated;
                }
            }

            $values[] = $descuentos_ley;

            //APORTE SOLID. POR  CONV. COLECTIVO
            $x = round($base_calculo_contribuciones * 0.005, 2);
            $values[] = $x;

            $otros_descuentos = $x;

            foreach ($concepts as $c) {
                if ($c->type_id == 6) {
                    $baseAmount = $this->resolveAmount($c->id, $employee,  $employee -> payroll_type_id, $amountMap);
                    $otros_descuentos += $baseAmount;
                    $values[] = $baseAmount;
                }
            }
            $values[] = $otros_descuentos;

            $values[] = $totalEgresos + $descuentos_ley + $otros_descuentos;


            $items[] = [
                'code' => $employee->code,
                'fullName' => $employee->fullName,
                'peopleId' => $employee->people_id,
                'pensionSystem' => $employee->pensionSystem,
                'nCUSPP' => $employee->nCUSPP,
                'payrollTypeId' => $employee->payroll_type_id,
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

    private function resolveAmount($conceptId, $employee, $payrollId, $amountMap) {
        
        if (isset($amountMap[$conceptId])) {

            $map = $amountMap[$conceptId];
            // Prioridad 1: monto específico a persona
            if (isset($map['PE'][$employee->people_id])) {
                return $map['PE'][$employee->people_id];
            }

            // Prioridad 2: monto por sistema de pensión
            if (isset($map['PS'][$employee->pensionSystem])) {
                return $map['PS'][$employee->pensionSystem];
            }

            // Prioridad 3: monto general de la planilla
            if (isset($map['PL'][$employee->payroll_type_id])) {
                return $map['PL'][$employee->payroll_type_id];
            }
        }
    }

    public function process($request)
    {
        global $wpdb;

        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $wpdb->select($db_erp);

        $year=get_param($request,'year');
        $month=get_param($request,'month');

        $payroll=$this->getOrCreatePayroll($year,$month,1);

        $items=$this->calculatePayroll($year,$month);
        /*
        limpiar planilla previa
        */

        $wpdb->delete("rem_payroll_concept",[
            "payroll_id"=>$payroll->id
        ]);

        $wpdb->delete("rem_payroll_people",[
            "payroll_id"=>$payroll->id
        ]);

        foreach($items as $item){

            $wpdb->insert("rem_payroll_people",[
                "payroll_id"=>$payroll->id,
                "people_id"=>$item["people_id"]
            ]);

            foreach($item["concepts"] as $c){

                if($c["amount"]==0){
                    continue;
                }

                $wpdb->insert("rem_payroll_concept",[
                    "payroll_id"=>$payroll->id,
                    "people_id"=>$item["people_id"],
                    "concept_id"=>$c["concept_id"],
                    "concept"=>$c["concept"],
                    "concept_type_id"=>$c["type_id"],
                    "amount"=>$c["amount"]
                ]);
            }
        }

        $wpdb->select($original_db);

        return [
            "success"=>true,
            "payroll_id"=>$payroll->id
        ];
    }

    private function calculatePayroll($year,$month)
    {
        global $wpdb;

        $date="$year-$month-01";

        /*
        CONCEPTOS
        */
        $concepts=$wpdb->get_results($wpdb->prepare("
            SELECT c.id,c.name,c.type_id
            FROM rem_payroll_amount a
            INNER JOIN per_concept c ON c.id=a.concept_id
            WHERE a.canceled=0
            AND a.ini_date<=%s
            AND (a.end_date IS NULL OR a.end_date>=%s)
            GROUP BY c.id
            ORDER BY c.weight
        ",$date,$date));

        /*
        AGRUPAR CONCEPTOS POR TIPO
        */

        $conceptGroups=[
            1=>[],
            2=>[],
            3=>[],
            4=>[],
            6=>[]
        ];

        foreach($concepts as $c){
            $conceptGroups[$c->type_id][]=$c;
        }

        /*
        PARAMETROS DE MONTO
        */

        $params=$wpdb->get_results($wpdb->prepare("
            SELECT concept_id,amount,type,target_id,payroll_type_id
            FROM rem_payroll_amount
            WHERE canceled=0
            AND ini_date<=%s
            AND (end_date IS NULL OR end_date>=%s)
        ",$date,$date));

        $amountMap=[];

        foreach($params as $p){

            if($p->type=='PL'){
                $amountMap[$p->concept_id]['PL'][$p->payroll_type_id]=$p->amount;
            }else{
                $amountMap[$p->concept_id][$p->type][$p->target_id]=$p->amount;
            }

        }

        /*
        EMPLEADOS
        */

        $employees=$wpdb->get_results("
            SELECT
                p.apellidos_nombres fullName,
                pp.payroll_type_id,
                pp.people_id,
                p.afp_onp pensionSystem,
                p.n_cuspp nCUSPP,
                p.dni code
            FROM rem_payroll_type_people pp
            INNER JOIN m_personal p ON p.n=pp.people_id
            ORDER BY 1
        ");

        $diasMes=30;

        $items=[];

        foreach($employees as $employee){

            $workedDays=30;

            $totalIngresos=0;
            $totalEgresos=0;
            $descuentosLey=0;
            $otrosDescuentos=0;

            $conceptResults=[];

            /*
            INGRESOS PROPORCIONALES
            */

            foreach($conceptGroups[1] as $c){

                $base=$this->resolveAmount($c->id,$employee,$employee->payroll_type_id,$amountMap);

                $value=round(($base*$workedDays)/$diasMes,2);

                $totalIngresos+=$value;

                $conceptResults[]=[
                    "concept_id"=>$c->id,
                    "concept"=>$c->name,
                    "type_id"=>$c->type_id,
                    "amount"=>$value
                ];
            }

            /*
            INGRESOS FIJOS
            */

            foreach($conceptGroups[2] as $c){

                $value=$this->resolveAmount($c->id,$employee,$employee->payroll_type_id,$amountMap);

                $totalIngresos+=$value;

                $conceptResults[]=[
                    "concept_id"=>$c->id,
                    "concept"=>$c->name,
                    "type_id"=>$c->type_id,
                    "amount"=>$value
                ];
            }

            /*
            EGRESOS
            */

            foreach($conceptGroups[3] as $c){

                $value=$this->resolveAmount($c->id,$employee,$employee->payroll_type_id,$amountMap);

                $totalEgresos+=$value;

                $conceptResults[]=[
                    "concept_id"=>$c->id,
                    "concept"=>$c->name,
                    "type_id"=>$c->type_id,
                    "amount"=>$value
                ];
            }

            /*
            BASE CALCULO
            */

            $baseContrib=$totalIngresos-$totalEgresos;

            /*
            DESCUENTOS LEY
            */

            foreach($conceptGroups[4] as $c){

                $base=$this->resolveAmount($c->id,$employee,$employee->payroll_type_id,$amountMap);

                $value=round($base*$baseContrib,2);

                $descuentosLey+=$value;

                $conceptResults[]=[
                    "concept_id"=>$c->id,
                    "concept"=>$c->name,
                    "type_id"=>$c->type_id,
                    "amount"=>$value
                ];
            }

            /*
            APORTE SOLIDARIO
            */

            $aporteSolidario=round($baseContrib*0.005,2);

            $otrosDescuentos+=$aporteSolidario;

            /*
            OTROS DESCUENTOS
            */

            foreach($conceptGroups[6] as $c){

                $value=$this->resolveAmount($c->id,$employee,$employee->payroll_type_id,$amountMap);

                $otrosDescuentos+=$value;

                $conceptResults[]=[
                    "concept_id"=>$c->id,
                    "concept"=>$c->name,
                    "type_id"=>$c->type_id,
                    "amount"=>$value
                ];
            }

            $items[]=[

                "people_id"=>$employee->people_id,
                "payroll_type_id"=>$employee->payroll_type_id,
                "fullName"=>$employee->fullName,
                "code"=>$employee->code,
                "pensionSystem"=>$employee->pensionSystem,
                "nCUSPP"=>$employee->nCUSPP,

                "totals"=>[
                    "ingresos"=>$totalIngresos,
                    "egresos"=>$totalEgresos,
                    "descuentos"=>$descuentosLey,
                    "otros"=>$otrosDescuentos
                ],

                "concepts"=>$conceptResults
            ];
        }

        return $items;
    }

    public function get_personal($request){
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $wpdb->select($db_erp);
        $employees = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.apellidos_nombres fullName, pp.people_id id, p.dni code
         FROM rem_payroll_type_people pp
         INNER JOIN m_personal p ON p.n = pp.people_id
         
         ORDER BY 1 ",
                1
            )
        );
        $wpdb->select($original_db);
        return $employees;

    }

    public function delete($data)
    {
        global $wpdb;
        $row = $wpdb->update('ds_gestante', array('canceled' => 1), array('id' => $data['id']));
        return $row;
    }

    function export_pdf($filename, $data)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        
        $html = $this->render_template($data);

        $dompdf = new Dompdf([
            'isRemoteEnabled' => true
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header("Content-Type: application/pdf");
        header("Content-Disposition: attachment; filename=\"{$filename}.pdf\"");
        echo $dompdf->output();
        exit;
    }

    function render_template($data) {
            ob_start();
        ?>
        <style>
            body{
                font-family: Arial;
                font-size:11px;
            }

            table{
                width:100%;
                border-collapse:collapse;
            }

            td,th{
                border:1px solid #999;
                padding:4px;
            }

            .title{
                text-align:center;
                font-weight:bold;
            }

            .right{
                text-align:right;
            }

            .boleta{
                margin-bottom:20px;
                page-break-inside: avoid;
            }
        </style>
        <?php foreach($data as $worker): ?>

        <div class="boleta">

        <table>
            <tr>
                <td colspan="8" class="title">
                BOLETA DE PAGOS CAS - D.LEG. N° 1057
                </td>
            </tr>
            <tr>
                <td colspan="4"><b>RUC: <?= $worker['code'] ?></b></td>
                <td colspan="4" class="right">MES DE PAGO: <?= $worker['month'] ?? '' ?></td>
            </tr>
            <tr>
                <td colspan="8">&nbsp</td>
            </tr>
            <tr>
                <td><b>Dependencia:</b></td>
                <td colspan="3"><?= $worker['dependence'] ?></td>
                <td><b>Nivel Remunerativo:</b></td>
                <td colspan="3"><?= $worker['remunerativeLevel'] ?? '' ?></td>
            </tr>
            <tr>
                <td><b>Nombre:</b></td>
                <td colspan="3"><?= $worker['fullName'] ?></td>
                <td><b>Cargo Estructural:</b></td>
                <td colspan="3"><?= $worker['position'] ?? '' ?></td>
            </tr>
            <tr>
                <td><b>CUSSP:</b></td>
                <td colspan="3"><?= $worker['nCUSSP'] ?></td>
                <td><b>Fecha del Ultimo Contrato:</b></td>
                <td colspan="3"><?= $worker['period'] ?? '' ?></td>
            </tr>
            <tr>
                <td><b>SNP/AFP:</b></td>
                <td colspan="3"><?= $worker['pensionSystem'] ?></td>
                <td><b>Nº de Cuenta Bancaria:</b></td>
                <td colspan="3"><?= $worker['period'] ?? '' ?></td>
            </tr>
            <tr>
                <td><b>VACACIONES:</b></td>
                <td><?= $worker['vacations'] ?></td>
                <td><b>DESC. MED.:</b></td>
                <td><?= $worker['pensionSystem'] ?></td>
                <td><b>Monto de Contrato:</b></td>
                <td colspan="3"><?= $worker['amount'] ?? '' ?></td>
            </tr>
        </table>
        <table>
            <tr>
            <th colspan="2" width="33.33">INGRESOS</th>
            <th colspan="2" width="33.34">DESCUENTOS</th>
            <th colspan="2" width="33.33">APORTES</th>
            </tr>
            <?php
            $max = max(
                count($worker['totalIncome'] ?? []),
                count($worker['totalDiscount'] ?? []),
                count($worker['totalContribution'] ?? [])
            );

            for($i=0;$i<$max;$i++):
                $inc = $worker['totalIncome'][$i] ?? null;
                $des = $worker['totalDiscount'][$i] ?? null;
                $apo = $worker['totalContribution'][$i] ?? null;
            ?>
            <tr>
                <td><?= $inc['name'] ?? '' ?></td>
                <td class="right"><?= isset($inc) ? number_format($inc['value'],2) : '' ?></td>

                <td><?= $des['name'] ?? '' ?></td>
                <td class="right"><?= isset($des) ? number_format($des['value'],2) : '' ?></td>

                <td><?= $apo['name'] ?? '' ?></td>
                <td class="right"><?= isset($apo) ? number_format($apo['value'],2) : '' ?></td>
            </tr>
            <?php endfor; ?>
            <tr>
                <td><b>TOTAL INGRESO</b></td>
                <td class="right"><?= number_format($worker['totalIncomeSum']??0,2) ?></td>

                <td><b>TOTAL DESCUENTO</b></td>
                <td class="right"><?= number_format($worker['totalDiscountSum']??0,2) ?></td>

                <td><b>TOTAL APORTE</b></td>
                <td class="right"><?= number_format($worker['totalContributionSum']??0,2) ?></td>
            </tr>
            <tr>
                <td><b>INGRESO NETO</b></td>
                <td class="right"><?= number_format($worker['netIncome'],2) ?></td>
                <td colspan="4"></td>
            </tr>
        </table>
        <table>
            <tr>
            <th height="40"></th>
            <th height="40"></th>
            </tr>
            <tr>
            <th>EMPLEADOR</th>
            <th>TRABAJADOR</th>
            </tr>
        </table>

        </div>

        <?php endforeach; ?>

        <?php

        return ob_get_clean();
    }

    public function download($request) {

        global $wpdb;

        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $wpdb->select($db_erp);

        $id = get_param($request,'id');

        $payroll = $wpdb->get_row($wpdb->prepare("
            SELECT year,month
            FROM rem_payroll
            WHERE id=%d
        ",$id));

        if(!$payroll){
            wp_die("Payroll no encontrado");
        }

  
        /*
        * 1️⃣ EMPLEADOS DE LA PLANILLA
        */
        $employees = $wpdb->get_results($wpdb->prepare("
            SELECT
                pp.people_id,
                p.apellidos_nombres fullName,
                p.afp_onp pensionSystem,
                p.n_cuspp nCUSSP,
                p.dni code,
                pp.position,
                pp.remunerative_level remunerativeLevel,
                d.unidad_organica dependence
            FROM rem_payroll_people pp
            LEFT JOIN m_personal p ON p.n = pp.people_id
            LEFT JOIN maestro_unidad d ON d.id = pp.dependency_id
            WHERE pp.payroll_id=%d
            ORDER BY p.apellidos_nombres
        ",$id));
        if ($wpdb->last_error) return t_error();
       
        /*
        * 2️⃣ TODOS LOS CONCEPTOS DE LA PLANILLA
        */
        $conceptRows = $wpdb->get_results($wpdb->prepare("
            SELECT
                people_id,
                concept,
                amount,
                concept_type_id
            FROM rem_payroll_concept
            WHERE payroll_id=%d
        ",$id));

        /*
        * 3️⃣ AGRUPAR CONCEPTOS POR PERSONA
        */
        $conceptsByPeople=[];

        foreach($conceptRows as $c){

            $pid = $c->people_id;

            if(!isset($conceptsByPeople[$pid])){
                $conceptsByPeople[$pid]=[];
            }

            $conceptsByPeople[$pid][]=$c;
        }

        $data=[];

        foreach($employees as $employee){

            $income=[];
            $discount=[];
            $contribution=[];

            $totalIncome=0;
            $totalDiscount=0;
            $totalContribution=0;

            $concepts = $conceptsByPeople[$employee->people_id] ?? [];

            foreach($concepts as $c){

                $row=[
                    "name"=>$c->concept,
                    "value"=>(float)$c->amount
                ];

                switch((int)$c->concept_type_id){

                    case 1:
                        $income[]=$row;
                        $totalIncome += $c->amount;
                        break;

                    case 2:
                        $discount[]=$row;
                        $totalDiscount += $c->amount;
                        break;

                    case 3:
                        $contribution[]=$row;
                        $totalContribution += $c->amount;
                        break;
                }
            }

            $netIncome = $totalIncome - $totalDiscount;

            $data[]=[

                "fullName"=>$employee->fullName,
                "code"=>$employee->code,
                "dependence"=>$employee->dependence,
                "remunerativeLevel"=>$employee->remunerativeLevel,
                "position"=>$employee->position,
                "pensionSystem"=>$employee->pensionSystem,
                "nCUSSP"=>$employee->nCUSSP,

                "month"=>$payroll->month." / ".$payroll->year,

                "totalIncome"=>$income,
                "totalDiscount"=>$discount,
                "totalContribution"=>$contribution,

                "totalIncomeSum"=>$totalIncome,
                "totalDiscountSum"=>$totalDiscount,
                "totalContributionSum"=>$totalContribution,

                "netIncome"=>$netIncome
            ];
        }

        $wpdb->select($original_db);
        $this->export_pdf("boletas_payroll_".$id,$data);
    }

}
