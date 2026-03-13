<?php 

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\directory\Util\mapKeysToCamelCase;
use function IB\directory\Util\mapKeysToSnakeCase;
use function IB\directory\Util\get_param;
use function IB\directory\Util\t_error;

class ConceptRestController extends Controller
{
    public function init()
    {
        remove_role('payroll_concept_admin');
        remove_role('payroll_concept_register');
        remove_role('payroll_concept_read');

        add_role(
            'payroll_concept_read',
            'payroll_concept_read',
            array('PAYROLL_CONCEPT_READ' => true)
        );

        add_role(
            'payroll_concept_admin',
            'payroll_concept_admin',
            array(
                'PAYROLL_CONCEPT_REGISTER' => true,
                'PAYROLL_CONCEPT_ADMIN' => true,
                'PAYROLL_CONCEPT_READ' => true,
                'PAYROLL_CONCEPT_DET' => true
            )
        );

        add_role(
            'payroll_concept_register',
            'payroll_concept_register',
            array(
                'PAYROLL_CONCEPT_REGISTER' => true,
                'PAYROLL_CONCEPT_READ' => true,
                'PAYROLL_CONCEPT_DET' => true
            )
        );
    }

    public function rest_api_init()
    {
        register_rest_route('api/payroll', '/concept/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'pag')
        ));

        register_rest_route('api/payroll', '/concept/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get')
        ));

        register_rest_route('api/payroll', '/concept', array(
            'methods' => 'POST',
            'callback' => array($this, 'post')
        ));

        register_rest_route('api/payroll', '/concept/(?P<id>[0-9,]+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete')
        ));
    }

    public function post($request)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $o = get_param($request);
        if (empty($o['name'])) {
            return t_error('El nombre del concepto es obligatorio');
        }
        $o = mapKeysToSnakeCase($o);
        try {
            $wpdb->select($db_erp);

            if (isset($o['id'])) {
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM per_concept WHERE name = %s AND id <> %d",
                        $o['name'],
                        $o['id']
                    )
                );
            } else {
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM per_concept WHERE name = %s",
                        $o['name']
                    )
                );
            }

            if ($wpdb->last_error) {
                return t_error($wpdb->last_error);
            }

            if ($exists > 0) {
                return t_error('El concepto ya existe');
            }

            if (isset($o['n'])) {
                $updated = $wpdb->update('per_concept', $o, ['id' => $o['id']]);
            } else {
                $updated = $wpdb->insert('per_concept', $o);
                $o['id'] = $wpdb->insert_id;
            }
            if (false === $updated) return t_error($wpdb->last_error);
        } finally {
            $wpdb->select($original_db);
        }
        return mapKeysToCamelCase($o);
    }

    public function get($request)
    {
        global $wpdb;
        $db_erp = get_option("db_ofis");
        $o = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $db_erp.per_concept WHERE id=%d", $request['id']),
            ARRAY_A
        );
        if (!$o) return t_error('Concepto no encontrado');
        return mapKeysToCamelCase($o);
    }

    public function pag($request)
    {
        global $wpdb;
        $from = intval($request['from']);
        $to = intval($request['to']);
        $query = get_param($request, 'query');

        $db_erp = get_option("db_ofis");
        $wpdb->last_error = '';

        $results = $wpdb->get_results(
            "SELECT SQL_CALC_FOUND_ROWS * FROM $db_erp.per_concept WHERE 1=1" .
            (isset($query) ? " AND (name LIKE '%$query%' OR abbreviation LIKE '%$query%')" : "") .
            ($to > 0 ? " LIMIT $from, $to" : ""),
            ARRAY_A
        );

        if ($wpdb->last_error) return t_error();

        $results = mapKeysToCamelCase($results);

        return $to > 0 ? [
            'data' => $results,
            'size' => $wpdb->get_var('SELECT FOUND_ROWS()')
        ] : $results;
    }

    public function delete($data)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");
        $ids = array_map('intval', explode(",", $data['id']));

        $wpdb->select($db_erp);
        $wpdb->query('START TRANSACTION');

        foreach ($ids as $id) {

            // Validación: Si tiene hijos, no permitir borrar
            $childrenCount = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM per_concept WHERE parent_id = %d", $id)
            );

            if ($childrenCount > 0) {
                $wpdb->query('ROLLBACK');
                $wpdb->select($original_db);
                return t_error("El concepto ID $id tiene conceptos hijos. No se puede borrar.");
            }

            $deleted = $wpdb->delete('per_concept', ['id' => $id]);
            if ($deleted === false) {
                $wpdb->query('ROLLBACK');
                $wpdb->select($original_db);
                return t_error($wpdb->last_error);
            }
        }

        $wpdb->query('COMMIT');
        $wpdb->select($original_db);

        return true;
    }
}