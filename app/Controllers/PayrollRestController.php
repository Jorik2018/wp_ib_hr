<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\directory\Util\mapKeysToCamelCase;
use function IB\directory\Util\mapKeysToSnakeCase;
use function IB\directory\Util\get_param;
use function IB\directory\Util\t_error;
use function IB\directory\Util\remove;
use Dompdf\Dompdf;

interface Node {
    public function eval($ctx);
    public function dump();
}

class NumberNode implements Node {
    public function __construct(public $value) {}

    public function eval($ctx) {
        return $this->value;
    }

    public function dump() {
        return (string)$this->value;
    }
}

class ConceptNode implements Node {
    public function __construct(public $id) {}

    public function eval($ctx) {
        return $ctx->evalConcept($this->id);
    }

    public function dump() {
        return "C{$this->id}";
    }
}

class GroupNode implements Node {
    public function __construct(public $groupId) {}

    public function eval($ctx) {
        return $ctx->sumGroup($this->groupId);
    }

    public function dump() {
        return "G{$this->groupId}";
    }
}

class BinaryOpNode implements Node {
    public function __construct(
        public $left,
        public $op,
        public $right
    ) {}

    public function eval($ctx) {
        $l = $this->left->eval($ctx);
        $r = $this->right->eval($ctx);

        return match($this->op) {
            '+' => $l + $r,
            '-' => $l - $r,
            '*' => $l * $r,
            '/' => $r != 0 ? $l / $r : 0,
        };
    }

    public function dump() {
        return "({$this->left->dump()} {$this->op} {$this->right->dump()})";
    }
}

class FunctionNode implements Node {
    public function __construct(public $name, public $args) {}

    public function eval($ctx) {
        $vals = array_map(fn($a) => $a->eval($ctx), $this->args);

        return match(strtoupper($this->name)) {
            'MIN' => min($vals),
            'MAX' => max($vals),
        };
    }

    public function dump() {
        $args = implode(',', array_map(fn($a) => $a->dump(), $this->args));
        return "{$this->name}($args)";
    }
}

class EvalContext {

    public $values = [];
    public $visiting = [];

    public function __construct(
        public $employee,
        public $conceptMap,
        public $groupMap,
        public $astMap,
        public $amountMap,
        public $diasMes
    ) {}

    public function evalConcept($id) {

        // ✔ cache
        if (isset($this->values[$id])) {
            return $this->values[$id];
        }
        $c = $this->conceptMap[$id];
        // 🚨 ciclo
        if (isset($this->visiting[$id])) {
            // 🔥 si ya hay valor parcial, usarlo (self reference)
            if (isset($this->values[$id])) {
                return $this->values[$id];
            }
            // 🔹 usar base_value si existe
            if (isset($c->base_value)) {
                return $c->base_value;
            }

            // 🔹 fallback: usar base de resolveAmount
            $base = $this->resolveAmount($id);

            if ($base != 0) {
                return $base;
            }
            // ❌ ciclo real
            throw new \Exception("Ciclo detectado en C$id".json_encode($c));
        }
        $this->visiting[$id] = true;
        $typeId = $c->type_id ?? 0;

        // 🔹 BASE
        $base = $this->resolveAmount($c->id);
        // 🔹 AJUSTE DIAS
        if ($typeId == 1) {
            $workedDays = $this->employee->workedDays ?? $this->diasMes;
            $base = round(($base * $workedDays) / $this->diasMes, 2);
        }
        $this->values[$id] = $base;
        // 🔹 FORMULA
        if (!empty($c->formula)) {
            $val = $this->astMap[$id]->eval($this);
        } else {
            $val = $base;
        }
        unset($this->visiting[$id]);
        return $this->values[$id] = round($val ?? 0, 2);
    }

    // 🔥 Gx
    public function sumGroup($g) {

        $sum = 0;

        foreach ($this->groupMap[$g] ?? [] as $cid) {
            $sum += $this->evalConcept($cid);
        }

        return $sum;
    }

    // 🔹 reemplaza tu resolveAmount
    private function resolveAmount($conceptId) {

        $employee = $this->employee;
        $amountMap = $this->amountMap;

        if (isset($amountMap[$conceptId])) {

            $map = $amountMap[$conceptId];

            if (isset($map['PE'][$employee->peopleId])) {
                return $map['PE'][$employee->peopleId];
            }

            if (!empty($employee->groups) && isset($map['GR'])) {
                foreach ($employee->groups as $gid) {
                    if (isset($map['GR'][$gid])) {
                        return $map['GR'][$gid];
                    }
                }
            }

            if (isset($map['PS'][$employee->pensionSystem])) {
                return $map['PS'][$employee->pensionSystem];
            }

            if (isset($map['PT'][$employee->payrollTypeId])) {
                return $map['PT'][$employee->payrollTypeId];
            }
        }

        return 0;
    }
}

function splitArgs($str) {
    $args = [];
    $level = 0;
    $current = '';

    for ($i = 0; $i < strlen($str); $i++) {
        $ch = $str[$i];

        if ($ch === ',' && $level === 0) {
            $args[] = $current;
            $current = '';
            continue;
        }

        if ($ch === '(') $level++;
        if ($ch === ')') $level--;

        $current .= $ch;
    }

    if ($current !== '') {
        $args[] = $current;
    }

    return $args;
}

function parse($expr) {

    $expr = str_replace(' ', '', $expr);

    // número
    if (is_numeric($expr)) {
        return new NumberNode($expr);
    }

    // Cx
    if (preg_match('/^C(\d+)$/', $expr, $m)) {
        return new ConceptNode($m[1]);
    }

    // Gx
    if (preg_match('/^G(\d+)$/', $expr, $m)) {
        return new GroupNode($m[1]);
    }

    // función
    if (preg_match('/^(MIN|MAX)\((.*)\)$/i', $expr, $m)) {

        $args = splitArgs($m[2]);

        return new FunctionNode(
            $m[1],
            array_map(fn($a) => parse($a), $args)
        );
    }

    // operadores (simple)
    foreach (['+', '-', '*', '/'] as $op) {
        $pos = strrpos($expr, $op);
        if ($pos !== false) {
            $l = substr($expr, 0, $pos);
            $r = substr($expr, $pos + 1);

            return new BinaryOpNode(
                parse($l),
                $op,
                parse($r)
            );
        }
    }

    throw new \Exception("No se pudo parsear: $expr");
}

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
        register_rest_route('api/payroll', '(?P<id>\d+)/preview', array(
            'methods' => 'GET',
            'callback' => array($this, 'preview')
        ));

        register_rest_route('api/payroll', 'concept/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag_concept')
        ));

        register_rest_route('api/payroll', 'group/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag_group')
        ));

        register_rest_route('api/payroll', '(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/payroll', '(?P<id>\d+)/personal', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_personal')
        ));

        register_rest_route('api/payroll', 'type', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag_type')
        ));

        register_rest_route('api/payroll', 'add-person', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_person')
        ));

        register_rest_route('api/payroll', 'people', array(
            'methods' => 'POST',
            'callback' => array($this, 'post_people')
        ));

        register_rest_route('api/payroll', 'remove-person', array(
            'methods' => 'POST',
            'callback' => array($this, 'remove_people')
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
    
        register_rest_route('api', '/payroll', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));
    }

    public function post($request)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $o = get_param($request);
        remove($o,'typeName');
        $o = mapKeysToSnakeCase($o);
        try {
            $wpdb->select($db_erp);

            if (isset($o['id'])) {
                $updated = $wpdb->update('rem_payroll', $o, ['id' => $o['id']]);
            } else {
                $updated = $wpdb->insert('rem_payroll', $o);
                $o['id'] = $wpdb->insert_id;
            }
            if (false === $updated) return t_error($wpdb->last_error);
        } finally {
            $wpdb->select($original_db);
        }
        return mapKeysToCamelCase($o);
    }

    public function post_people($request)
    {
        global $wpdb;

        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");

        $o = get_param($request);
        $payrollType = get_param($o, 'payrollType');
        $items = get_param($o, 'items'); 
        // [{peopleId:1, workedDays:20}, ...]

        try {
            $wpdb->select($db_erp);

            foreach ($items as $item) {
                $peopleId = get_param($item, 'peopleId');
                $workedDays = get_param($item, 'workedDays');

                $updated = $wpdb->update(
                    'rem_payroll_type_people',
                    ['worked_days' => $workedDays],
                    [
                        'people_id' => $peopleId,
                        'payroll_type_id' => $payrollType
                    ],
                    ['%d'], // worked_days
                    ['%d', '%s'] // where
                );

                if ($updated === false) {
                    return t_error($wpdb->last_error);
                }
            }

        } finally {
            $wpdb->select($original_db);
        }

        return mapKeysToCamelCase([
            'payrollType' => $payrollType,
            'items' => $items
        ]);
    }
    public function pag($request)
    {

        global $wpdb;

        $from = intval($request['from']);
        $to = intval($request['to']);

        $query = get_param($request, 'query');

        $db_erp = get_option("db_ofis");

        $results = $wpdb->get_results(
            "SELECT SQL_CALC_FOUND_ROWS p.*, pt.name typeName
             FROM $db_erp.rem_payroll p 
             LEFT JOIN $db_erp.rem_payroll_type pt ON pt.id=p.type_id
             WHERE canceled=0 " .
            (isset($query) ? " AND (comments LIKE '%$query%')" : "") .
            ($to > 0 ? " LIMIT $from,$to" : ""),
            ARRAY_A
        );

        if ($wpdb->last_error) return t_error($wpdb->last_error);

        $results = mapKeysToCamelCase($results);

        return $to > 0 ? [
            'data' => $results,
            'size' => $wpdb->get_var('SELECT FOUND_ROWS()')
        ] : $results;
    }

    public function pag_type($request) {
        global $wpdb;

        $from = 0;//intval($request['from']);
        $to = 0;//intval($request['to']);

        $query = get_param($request, 'query');

        $db_erp = get_option("db_ofis");

        $results = $wpdb->get_results(
            "SELECT SQL_CALC_FOUND_ROWS p.*
             FROM $db_erp.rem_payroll_type p  " .
            ($to > 0 ? " LIMIT $from,$to" : ""),
            ARRAY_A
        );

        if ($wpdb->last_error) return t_error($wpdb->last_error);

        $results = mapKeysToCamelCase($results);

        return $to > 0 ? [
            'data' => $results,
            'size' => $wpdb->get_var('SELECT FOUND_ROWS()')
        ] : $results;
    }
    function getOrCreatePayroll($year, $month, $typeId, $id = 0, $fuenteFinanc = null, $preparedBy = null)
    {
        global $wpdb;

        // 1️⃣ Buscar existente
        $payroll = $wpdb->get_row(
            $id?            $wpdb->prepare(
                "SELECT p.* , pt.name payrollTypeName
             FROM rem_payroll p JOIN rem_payroll_type pt ON pt.id=p.type_id
             WHERE  p.id = %d 
             LIMIT 1",
                $id
            ):
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

    public function pag_concept($request)
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

    public function pag_group($request)
    {
        global $wpdb;

        $db = get_option("db_ofis");

        // Parámetros de búsqueda
        $params = [
            'c.name'         => get_param($request, 'name'),
            'c.parent_id'      => get_param($request, 'parentId')
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
            FROM $db.rem_group c";

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
        $results = mapKeysToCamelCase($results);
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
        $payroll_type_id = $o['payrollType'];
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

    function remove_people($request) {
        global $wpdb;

        // Obtener params compatible WP_REST_Request o array
        $o = get_param($request);

        $original_db = $wpdb->dbname;
        $wpdb->select(get_option("db_ofis"));

        $persons = $o['persons'] ?? [];
        $payroll_type_id = $o['payrollType'] ?? null;

        if (!is_array($persons) || empty($persons)) {
            $wpdb->select($original_db);
            return t_error('No persons provided');
        }

        if (!$payroll_type_id) {
            $wpdb->select($original_db);
            return t_error('No payroll type provided');
        }

        $deleted = [];

        foreach ($persons as $people_id) {

            $people_id = (int)$people_id;
            if (!$people_id) continue;

            $where = [
                'payroll_type_id' => $payroll_type_id,
                'people_id'       => $people_id
            ];

            $ok = $wpdb->delete('rem_payroll_type_people', $where);

            if ($ok === false) {
                $last_error = $wpdb->last_error;
                $wpdb->select($original_db);
                if ($last_error) return t_error($last_error);
            }

            // si $ok === 0 → no existía, igual lo podemos considerar "eliminado"
            $deleted[] = $people_id;
        }

        $wpdb->select($original_db);

        return [
            'deleted' => $deleted,
            'count'   => count($deleted)
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

    function buildHeaders($parentId, $conceptTree) {
        $headers = [];

        if (!isset($conceptTree[$parentId])) {
            return $headers;
        }

        foreach ($conceptTree[$parentId] as $c) {
            // Construir recursivamente los hijos
            $children = $this->buildHeaders($c->id, $conceptTree);

            // Si es 'is_parent' y no tiene hijos, lo omitimos
            if (!empty($c->is_parent) && empty($children)) {
                continue; // salta este concepto
            }

            $header = [
                'title' => $c->name,
                'class' => $c->class
            ];

            // Si tiene hijos, agregamos 'children'
            if (!empty($children)) {
                $header['children'] = $children;
            } else {
                // Si no tiene hijos, asignamos concept_id e index para data
                $header['concept_id'] = $c->id;
                if (!empty($c->pdt_code)) {
                    $header['index'] = $c->pdt_code;
                }
            }

            $headers[] = $header;
        }

        return $headers;
    }

    public function preview($request)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $wpdb->select($db_erp);
        $id = intval($request['id']);
        $year = get_param($request, 'year');
        $month = get_param($request, 'month');
        $payroll_type_id = get_param($request, 'payrollType')??1;

        $payroll = $this->getOrCreatePayroll($year, $month, $payroll_type_id, $id);

        $result = $this -> calculatePayroll($payroll);
        $headers = $result['headers'];
        $wpdb->select($original_db);
        $headers = $this->assignLeafIndexes($headers);
        return [
            ... (array)mapKeysToCamelCase($payroll),
            
            'headers' => $headers,
            'items' => $result['items'],
            '$astMap' => $result['$astMap'],
            'conceptGroups' => $result['conceptGroups'],
            'amountMap'=> $result['amountMap'],
            '$order' => $result['$order']
        ];
    }

    public function process($request)
    {
        global $wpdb;

        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $wpdb->select($db_erp);

        $year=get_param($request,'year');
        $month=get_param($request,'month');
        $id=get_param($request,'id');
        $type=get_param($request,'type');

        $payroll=$this->getOrCreatePayroll($year,$month,$type, $id);

        $result=$this->calculatePayroll($payroll);

        $items = $result['items'];
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
                "people_id"=>$item["peopleId"],
                "position"=>$item["position"],
                "dependency_id"=>$item["dependency_id"],

            ]);

            foreach($item["concepts"] as $c){

                if($c["amount"]==0){
                    continue;
                }

                $wpdb->insert("rem_payroll_concept",[
                    "payroll_id"=>$payroll->id,
                    "people_id"=>$item["peopleId"],
                    "concept_id"=>$c["concept_id"],
                    "concept"=>$c["concept"],
                    "concept_type_id"=>$c["type_id"],
                    "amount"=>$c["amount"]
                ]);
            }
        }
        if ($wpdb->last_error) {
            return t_error();
        }
        $wpdb->select($original_db);

        return [
            "success"=>true,
            "payroll_id"=>$payroll->id
        ];
    }

    private function calculatePayroll($payroll)
    {
        global $wpdb;
        $year = $payroll -> year;
        $month = $payroll -> month;

        $concepts = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT c.id, c.name, c.pdt_code, c.type_id, c.weight, c.formula, c.parent_id, c.is_parent, c.class
            FROM per_concept c
            LEFT JOIN rem_payroll_amount a ON c.id = a.concept_id
            AND a.canceled = 0
            AND a.ini_date <= %s
            AND (a.end_date IS NULL OR a.end_date >= %s)
            WHERE a.concept_id IS NOT NULL OR (c.formula IS NOT NULL AND c.formula <> '') OR c.is_parent
            ORDER BY c.weight
        ", "$year-$month-01", "$year-$month-01"));
        $order = $this->sortConceptsByDependency($concepts);
        $conceptMap = [];
        foreach ($concepts as $c) {
            $conceptMap[$c->id] = $c;
        }
        $astMap = [];

        foreach ($conceptMap as $id => $c) {
            if (!empty($c->formula)) {
                $astMap[$id] = parse($c->formula); // 🔥 SOLO UNA VEZ
            }
        }
        $groupMap = [];
        foreach ($conceptMap as $conceptId => $c) {
            $typeId = $c->type_id ?? 0;
            if ($typeId > 0) {
                if (!isset($groupMap[$typeId])) {
                    $groupMap[$typeId] = [];
                }
                $groupMap[$typeId][] = $conceptId;
            }
        }
        $conceptTree = [];
        foreach ($concepts as $c) {
            $parentId = $c->parent_id ?? 0; // 0 indica que es root
            if (!isset($conceptTree[$parentId])) {
                $conceptTree[$parentId] = [];
            }
            $conceptTree[$parentId][] = $c;
        }
        $headers = [
            ['title' => 'CODE', 'width' => 80, 'index' => 'code', 'class' => 'center'],
            ['title' => 'NOMBRE COMPLETO', 'width' => 200, 'index' => 'fullName'],
            ['title' => 'AFP / ONP', 'width' => 100, 'index' => 'pensionSystem', 'class' => 'center'],
            ['title' => 'N° CUSPP', 'width' => 120, 'index' => 'nCUSPP', 'class' => 'center'],
            ['title' => 'DIAS LABORADOS', 'index' => 'workedDays', 'width' => 100, 'class' => 'right']
        ];
        $dynamicHeaders = $this -> buildHeaders(0, $conceptTree);
        // Unir columnas fijas con las dinámicas
        $headers = array_merge($headers, $dynamicHeaders);

        /*
        AGRUPAR CONCEPTOS POR TIPO
        */       
        $conceptGroups = [];
        $totalGroups = [];
        foreach ($concepts as $c) {
            $type_id = $c->type_id ?? 0;
            if (!isset($conceptGroups[$type_id])) {
                $conceptGroups[$type_id] = [];
            }
            $conceptGroups[$type_id][] = $c;
        }
        $params = $wpdb->get_results($wpdb->prepare("
            SELECT concept_id, amount, type, target_id, payroll_type_id
            FROM rem_payroll_amount
            WHERE canceled = 0
            AND ini_date <= %s
            AND (end_date IS NULL OR end_date >= %s)
        ", "$year-$month-01", "$year-$month-01"));

        $amountMap = [];
        foreach ($params as $p) {
            if($p->type=='PT') {
                $amountMap[$p->concept_id][$p->type][$payroll->type_id] = $p->amount;
            } else {
                $amountMap[$p->concept_id][$p->type][$p->target_id] = $p->amount;
            }
        }
         
        $diasMes = 30; //cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $employees = $wpdb->get_results(
            $wpdb->prepare("SELECT 
                    p.apellidos_nombres fullName,
                    pp.payroll_type_id payrollTypeId,
                    pp.people_id peopleId,
                    p.afp_onp pensionSystem,
                    p.n_cuspp nCUSPP,
                    pp.worked_days workedDays,
                    p.dni code,
                    GROUP_CONCAT(gp.group_id) `groups`
                    FROM rem_payroll_type_people pp
                    INNER JOIN m_personal p ON p.n = pp.people_id
                    LEFT JOIN rem_group_people gp ON gp.people_id = p.n
                    WHERE pp.payroll_type_id = %d
                    GROUP BY pp.people_id
                    ORDER BY 1",
                $payroll->type_id
            )
        );
        if ($wpdb->last_error) {
            return t_error($wpdb->last_error);
        }


        $items = [];
foreach ($employees as $employee) {

    $employee->groups = $employee->groups ? explode(',', $employee->groups) : [];
    $employee->workedDays = $employee->workedDays ?? $diasMes;

    $ctx = new EvalContext(
        $employee,
        $conceptMap,
        $groupMap,
        $astMap,
        $amountMap,
        $diasMes
    );

    $conceptList = [];

    foreach ($conceptMap as $conceptId => $c) {

        $value = $ctx->evalConcept($conceptId);

        $conceptList[] = [
            "concept_id" => $conceptId,
            "concept"    => $c->name,
            "type_id"    => $c->type_id,
            "amount"     => $value,
        ];
    }

    $items[] = [
        ...(array)$employee,
        'values'   => $ctx->values,
        'concepts' => $conceptList,
    ];
}
       $astDebug = [];

foreach ($astMap as $id => $ast) {
    $astDebug[$id] = $ast->dump();
}
        return  [
            'headers' => $headers,
            'items' => $items,
            'conceptGroups' => $conceptGroups,
            'amountMap'=> $amountMap,
            '$order' => $astDebug
        ];
    }

    private function evaluateFormula($formula, $values, $totalGroups) {

        // Reemplazar Cxx
        $formula = preg_replace_callback('/C(\d+)/', function($m) use ($values) {
            return $values[$m[1]] ?? 0;
        }, $formula);

        // Reemplazar Gxx
        $formula = preg_replace_callback('/G(\d+)/', function($m) use ($totalGroups) {
            return $totalGroups[$m[1]] ?? 0;
        }, $formula);

        // 🔥 LIMPIEZA CRÍTICA
        $formula = trim($formula);

        // evitar cosas como "339+"
        if (preg_match('/[+\-*/]$/', $formula)) {
            return 0;
        }

        // debug
        // error_log("EVAL: " . $formula);

        return $this->safeEval($formula);
    }

    private function safeEval($expression) {

        if (!preg_match('/^[0-9+\-*/().\s]+$/', $expression)) {
            return 0;
        }

        try {
            return eval("return $expression;");
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function extractDependencies($formula, $groupToConcepts) {
        $deps = [];

        // Cx directos
        preg_match_all('/C(\d+)/', $formula, $m1);
        $deps = $m1[1];

        // Gx → expandir a conceptos reales
        preg_match_all('/G(\d+)/', $formula, $m2);

        foreach ($m2[1] as $groupId) {
            if (isset($groupToConcepts[$groupId])) {
                $deps = array_merge($deps, $groupToConcepts[$groupId]);
            }
        }

        return array_unique($deps);
    }

    private function sortConceptsByDependency($concepts) {

        $graph = [];
        $inDegree = [];

        // 🔹 mapa de grupos
        $groupToConcepts = [];
        foreach ($concepts as $c) {
            $typeId = $c->type_id ?? 0;
            if ($typeId > 0) {
                $groupToConcepts[$typeId][] = $c->id;
            }
        }

        // init
        foreach ($concepts as $c) {
            $graph[$c->id] = [];
            $inDegree[$c->id] = 0;
        }

        // dependencias
        foreach ($concepts as $c) {
            if (!$c->formula) continue;

            $deps = $this->extractDependencies($c->formula, $groupToConcepts);

            foreach ($deps as $dep) {
                // ⚠️ validar que exista como concepto
                if (!isset($graph[$dep])) continue;

                $graph[$dep][] = $c->id;
                $inDegree[$c->id]++;
            }
        }

        // Kahn
        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg == 0) $queue[] = $id;
        }

        $sorted = [];
        while ($queue) {
            $node = array_shift($queue);
            $sorted[] = $node;

            foreach ($graph[$node] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] == 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $sorted;
    }

    private function resolveAmount($conceptId, $employee, $payrollId, $amountMap) {
        
        if (isset($amountMap[$conceptId])) {

            $map = $amountMap[$conceptId];
            // Prioridad 1: monto específico a persona
            if (isset($map['PE'][$employee->peopleId])) {
                return $map['PE'][$employee->peopleId];
            }

            // 2 GRUPO
            if (!empty($employee->groups) && isset($map['GR'])) {
                foreach ($employee->groups as $gid) {
                    if (isset($map['GR'][$gid])) {
                        return $map['GR'][$gid];
                    }
                }
            }

            // Prioridad 2: monto por sistema de pensión
            if (isset($map['PS'][$employee->pensionSystem])) {
                return $map['PS'][$employee->pensionSystem];
            }

            // Prioridad 3: monto general de la planilla
            if (isset($map['PT'][$employee->payrollTypeId])) {
                return $map['PT'][$employee->payrollTypeId];
            }
        }
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
                <td colspan="8"><br/></td>
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
                    case 2:
                        $income[]=$row;
                        $totalIncome += $c->amount;
                        break;

                    case 3:
                    case 4:
                    case 5:
                        $discount[]=$row;
                        $totalDiscount += $c->amount;
                        break;

                    case 6:
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
