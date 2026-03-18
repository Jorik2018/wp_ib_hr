<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
use function IB\directory\Util\mapKeysToCamelCase;
use function IB\directory\Util\mapKeysToSnakeCase;
use function IB\directory\Util\get_param;
use function IB\directory\Util\t_error;
use function IB\directory\Util\remove;

class GroupRestController extends Controller
{
    public function init()
    {
        remove_role('group_admin');
        remove_role('group_register');
        remove_role('group_read');

        add_role('group_read', 'group_read', ['GROUP_READ' => true]);
        add_role('group_admin', 'group_admin', [
            'GROUP_REGISTER' => true,
            'GROUP_ADMIN' => true,
            'GROUP_READ' => true
        ]);
        add_role('group_register', 'group_register', [
            'GROUP_REGISTER' => true,
            'GROUP_READ' => true
        ]);
    }

    public function rest_api_init()
    {
        register_rest_route('api/group', '/group/(?P<from>\d+)/(?P<to>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'pag']
        ]);

        register_rest_route('api/group', '/group/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get']
        ]);

        register_rest_route('api/group', '/group', [
            'methods' => 'POST',
            'callback' => [$this, 'post']
        ]);

        register_rest_route('api/group', '/group/(?P<id>[0-9,]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete']
        ]);
    }

    public function post($request)
    {
        global $wpdb;
        $original_db = $wpdb->dbname;
        $db_erp = get_option("db_ofis");

        $o = get_param($request);

        if (empty($o['name'])) return t_error('Nombre es obligatorio');

        $o = mapKeysToSnakeCase($o);

        try {
            $wpdb->select($db_erp);

            if (isset($o['id'])) {
                // Actualizar
                $updated = $wpdb->update('rem_group', $o, ['id' => $o['id']]);
            } else {
                // Insertar nuevo
                $updated = $wpdb->insert('rem_group', $o);
                $o['id'] = $wpdb->insert_id;

                // Marcar como is_parent si tiene parent_id
                if (!empty($o['parent_id'])) {
                    $wpdb->update('rem_group', ['is_parent' => 1], ['id' => $o['parent_id']]);
                }
            }

            if ($updated === false) return t_error($wpdb->last_error);

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
            $wpdb->prepare("SELECT * FROM $db_erp.rem_group WHERE id=%d", $request['id']),
            ARRAY_A
        );

        if (!$o) return t_error('Registro no encontrado');
        return mapKeysToCamelCase($o);
    }

public function pag($request)
{
    global $wpdb;

    $from = intval($request['from']);
    $to = intval($request['to']);
    $orphan = get_param($request, 'orphan');

    $original_db = $wpdb->dbname;
    $db_erp = get_option("db_ofis");
    $wpdb->select($db_erp);

    try {
        $where = '';
        if ($orphan) {
            $where = "WHERE g.parent_id IS NULL";
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS g.*, p.name parentName
                FROM rem_group g
                LEFT JOIN rem_group p ON g.parent_id = p.id
                $where
                ORDER BY g.id DESC
                " . ($to > 0 ? " LIMIT $from,$to" : "");

        $results = $wpdb->get_results($sql, ARRAY_A);

        if ($wpdb->last_error) return t_error($wpdb->last_error);

    } finally {
        $wpdb->select($original_db);
    }

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
            $deleted = $wpdb->delete('rem_group', ['id' => $id]);
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