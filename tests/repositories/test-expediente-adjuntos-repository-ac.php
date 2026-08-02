<?php
/**
 * AC — ExpedienteAdjuntosRepository (MC4a2).
 *
 * Ejecutar: php tests/repositories/test-expediente-adjuntos-repository-ac.php
 */

$plugin_root = dirname(__DIR__, 2);

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        public function __construct($code = '', $message = '') {
            $this->code = $code;
            $this->message = $message;
        }
        public function get_error_code() {
            return $this->code;
        }
        public function get_error_message() {
            return $this->message;
        }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}
if (!function_exists('current_time')) {
    function current_time($type) {
        return '2026-07-30 18:00:00';
    }
}

require_once $plugin_root . '/includes/repositories/ExpedienteAdjuntosRepository.php';

$src = file_get_contents($plugin_root . '/includes/repositories/ExpedienteAdjuntosRepository.php');
ac_assert('repo file exists', is_string($src) && $src !== '');
ac_assert('insert_finalized', strpos($src, 'function insert_finalized') !== false);
ac_assert('find_by_upload_operation_id', strpos($src, 'function find_by_upload_operation_id') !== false);
ac_assert('find_by_storage_path', strpos($src, 'function find_by_storage_path') !== false);
ac_assert('list_by_record_for_client', strpos($src, 'function list_by_record_for_client') !== false);
ac_assert('sin delete genérico', !preg_match('/function delete\b/', $src));
ac_assert('delete_by_record_for_client (MC5c2)', strpos($src, 'function delete_by_record_for_client') !== false);
ac_assert('delete_by_id_for_client (MC5c1)', strpos($src, 'function delete_by_id_for_client') !== false);
ac_assert('delete_by_record idempotente via COUNT', strpos($src, 'delete_by_record_for_client') !== false
    && strpos($src, 'SELECT COUNT(*)') !== false);
ac_assert('tabla aa_expediente_adjuntos', strpos($src, 'aa_expediente_adjuntos') !== false);
ac_assert('find_latest_by_record_ids', strpos($src, 'function find_latest_by_record_ids') !== false);
ac_assert('bulk usa MAX(id) GROUP BY', strpos($src, 'MAX(id)') !== false && strpos($src, 'GROUP BY record_id') !== false);
ac_assert('list_by_record_ids (MC5a)', strpos($src, 'function list_by_record_ids') !== false);
ac_assert('list bulk ordena id DESC por registro', strpos($src, 'ORDER BY record_id ASC, id DESC') !== false);
ac_assert('sum_byte_size_total (MC5d2)', strpos($src, 'function sum_byte_size_total') !== false);
ac_assert('sum usa COALESCE(SUM(byte_size), 0)', strpos($src, 'COALESCE(SUM(byte_size), 0)') !== false);

global $wpdb;
$wpdb = new class {
    public $prefix = 'wp_5_';
    public $last_error = '';
    public $insert_id = 0;
    public $rows_by_op = [];
    public $rows_by_path = [];
    public $rows_by_id = [];
    public $inserted = null;
    public $insert_ok = true;
    public $list_rows = [];
    public $get_results_calls = 0;
    public $last_results_query = null;

    public function prepare($query, ...$args) {
        return ['sql' => $query, 'args' => $args];
    }

    public function get_row($query, $output = OBJECT) {
        $args = is_array($query) ? ($query['args'] ?? []) : [];
        $sql = is_array($query) ? (string) ($query['sql'] ?? '') : (string) $query;

        $row = null;
        if (strpos($sql, 'WHERE upload_operation_id =') !== false) {
            $key = isset($args[0]) ? (string) $args[0] : '';
            $row = $this->rows_by_op[$key] ?? null;
        } elseif (strpos($sql, 'WHERE storage_path =') !== false) {
            $key = isset($args[0]) ? (string) $args[0] : '';
            $row = $this->rows_by_path[$key] ?? null;
        } elseif (strpos($sql, 'WHERE id = %d AND client_id = %d') !== false) {
            $id = (int) ($args[0] ?? 0);
            $client = (int) ($args[1] ?? 0);
            $cand = $this->rows_by_id[$id] ?? null;
            if (is_array($cand) && (int) $cand['client_id'] === $client) {
                $row = $cand;
            }
        }

        if ($output === ARRAY_A) {
            return $row;
        }
        return $row ? (object) $row : null;
    }

    public $sum_value = '0';
    public $last_var_query = null;

    public function get_var($query) {
        $this->last_var_query = is_array($query) ? (string) ($query['sql'] ?? '') : (string) $query;
        if (strpos($this->last_var_query, 'SUM(byte_size)') !== false) {
            return $this->sum_value;
        }
        return '0';
    }

    public function get_results($query, $output = OBJECT) {
        $this->get_results_calls++;
        $this->last_results_query = $query;
        if ($output === ARRAY_A) {
            return $this->list_rows;
        }
        return array_map(static function ($r) {
            return (object) $r;
        }, $this->list_rows);
    }

    public function insert($table, $data, $format = null) {
        $this->inserted = ['table' => $table, 'data' => $data];
        if (!$this->insert_ok) {
            $this->last_error = 'duplicate';
            return false;
        }
        $this->insert_id = 51;
        $row = array_merge($data, ['id' => 51]);
        $this->rows_by_op[$data['upload_operation_id']] = $row;
        $this->rows_by_path[$data['storage_path']] = $row;
        $this->rows_by_id[51] = $row;
        return 1;
    }
};

$base = [
    'record_id' => 10,
    'client_id' => 3,
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'storage_path' => 'installations/11111111-2222-4333-8444-555555555555/clients/3/records/10/550e8400-e29b-41d4-a716-446655440000.jpg',
    'mime_type' => 'image/jpeg',
    'byte_size' => 1200,
    'width' => 800,
    'height' => 600,
    'created_at' => '2026-07-30 18:00:00',
];

$created = ExpedienteAdjuntosRepository::insert_finalized($base);
ac_assert('insert OK', is_array($created) && (int) $created['id'] === 51);
ac_assert('insert tabla prefijada', ($wpdb->inserted['table'] ?? '') === 'wp_5_aa_expediente_adjuntos');

$again = ExpedienteAdjuntosRepository::insert_finalized($base);
ac_assert('reinsert idempotente misma fila', is_array($again) && (int) $again['id'] === 51);
ac_assert('no reinserta en DB en repetición', count($wpdb->rows_by_id) === 1);

$conflict_meta = $base;
$conflict_meta['byte_size'] = 999;
$bad = ExpedienteAdjuntosRepository::insert_finalized($conflict_meta);
ac_assert('conflicto meta → WP_Error', is_wp_error($bad) && $bad->get_error_code() === 'adjunto_meta_conflict');

$conflict_client = $base;
$conflict_client['client_id'] = 99;
$bad2 = ExpedienteAdjuntosRepository::insert_finalized($conflict_client);
ac_assert('conflicto client → WP_Error', is_wp_error($bad2));

// Identidades cruzadas: misma op apunta a fila A, path a fila B
$wpdb->rows_by_path[$base['storage_path']] = array_merge($base, [
    'id' => 99,
    'upload_operation_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
]);
$cross = ExpedienteAdjuntosRepository::insert_finalized($base);
ac_assert('conflicto identidad cruzada', is_wp_error($cross) && $cross->get_error_code() === 'adjunto_identity_conflict');

// Restaurar path row
$wpdb->rows_by_path[$base['storage_path']] = $wpdb->rows_by_op[$base['upload_operation_id']];

$wpdb->list_rows = [
    array_merge($base, ['id' => 51]),
];
$list = ExpedienteAdjuntosRepository::list_by_record_for_client(10, 3);
ac_assert('list 1 fila', count($list) === 1 && $list[0]['id'] === 51);
ac_assert('list client inválido → []', ExpedienteAdjuntosRepository::list_by_record_for_client(10, 0) === []);

$found = ExpedienteAdjuntosRepository::find_by_id_for_client(51, 3);
ac_assert('find_by_id_for_client OK', is_array($found) && $found['storage_path'] === $base['storage_path']);
ac_assert('find cross-client → null', ExpedienteAdjuntosRepository::find_by_id_for_client(51, 9) === null);

$invalid = ExpedienteAdjuntosRepository::insert_finalized(array_merge($base, ['mime_type' => 'image/png']));
ac_assert('mime inválido → WP_Error', is_wp_error($invalid));

// ── MC4c: find_latest_by_record_ids (bulk, sin N+1) ──
$wpdb->list_rows = [
    array_merge($base, ['id' => 51, 'record_id' => 10]),
    array_merge($base, ['id' => 77, 'record_id' => 20]),
];
$wpdb->get_results_calls = 0;

$latest = ExpedienteAdjuntosRepository::find_latest_by_record_ids([10, 20, 20, 0, -5, '10'], 3);
ac_assert('bulk una sola consulta', $wpdb->get_results_calls === 1, 'calls=' . $wpdb->get_results_calls);
ac_assert('bulk mapa por record_id', isset($latest[10]) && isset($latest[20]) && count($latest) === 2);
ac_assert('bulk fila último id', (int) $latest[20]['id'] === 77);
$bulk_sql = is_array($wpdb->last_results_query) ? (string) $wpdb->last_results_query['sql'] : '';
ac_assert('bulk SQL MAX(id) + GROUP BY + IN', strpos($bulk_sql, 'MAX(id)') !== false
    && strpos($bulk_sql, 'GROUP BY record_id') !== false
    && strpos($bulk_sql, 'IN (') !== false);

$wpdb->get_results_calls = 0;
ac_assert('bulk ids vacíos → [] sin query',
    ExpedienteAdjuntosRepository::find_latest_by_record_ids([], 3) === [] && $wpdb->get_results_calls === 0);
ac_assert('bulk client inválido → []',
    ExpedienteAdjuntosRepository::find_latest_by_record_ids([10], 0) === [] && $wpdb->get_results_calls === 0);

// ── MC5a: list_by_record_ids (todos los adjuntos, bulk, agrupados) ──
$wpdb->list_rows = [
    array_merge($base, ['id' => 90, 'record_id' => 10]),
    array_merge($base, ['id' => 51, 'record_id' => 10]),
    array_merge($base, ['id' => 77, 'record_id' => 20]),
];
$wpdb->get_results_calls = 0;

$grouped = ExpedienteAdjuntosRepository::list_by_record_ids([10, 20, 20, 30, 0, -1], 3);
ac_assert('list bulk una sola consulta', $wpdb->get_results_calls === 1, 'calls=' . $wpdb->get_results_calls);
ac_assert('list bulk agrupa por record_id', isset($grouped[10]) && isset($grouped[20]) && count($grouped) === 2);
ac_assert('list bulk record 10 con 2 filas id DESC',
    count($grouped[10]) === 2 && (int) $grouped[10][0]['id'] === 90 && (int) $grouped[10][1]['id'] === 51);
ac_assert('list bulk registro sin filas ausente del mapa', !isset($grouped[30]));
$list_sql = is_array($wpdb->last_results_query) ? (string) $wpdb->last_results_query['sql'] : '';
ac_assert('list bulk SQL con IN y ORDER BY record_id ASC, id DESC',
    strpos($list_sql, 'IN (') !== false && strpos($list_sql, 'ORDER BY record_id ASC, id DESC') !== false);

$wpdb->get_results_calls = 0;
ac_assert('list bulk ids vacíos → [] sin query',
    ExpedienteAdjuntosRepository::list_by_record_ids([], 3) === [] && $wpdb->get_results_calls === 0);
ac_assert('list bulk client inválido → []',
    ExpedienteAdjuntosRepository::list_by_record_ids([10], 0) === [] && $wpdb->get_results_calls === 0);

// ── MC5d2: sum_byte_size_total (bytes contabilizados por metadata local) ──
$wpdb->sum_value = '0';
$sum0 = ExpedienteAdjuntosRepository::sum_byte_size_total();
ac_assert('sum sin adjuntos → 0 entero', $sum0 === 0);

$wpdb->sum_value = '874289';
$sum1 = ExpedienteAdjuntosRepository::sum_byte_size_total();
ac_assert('sum varios adjuntos → entero correcto', $sum1 === 874289);
ac_assert('sum sobre tabla del prefijo del blog actual',
    strpos((string) $wpdb->last_var_query, 'wp_5_aa_expediente_adjuntos') !== false,
    'sql=' . (string) $wpdb->last_var_query);
ac_assert('sum SQL sin WHERE (alcance = instalación/blog completo)',
    strpos((string) $wpdb->last_var_query, 'WHERE') === false);

$wpdb->sum_value = '-10';
ac_assert('sum nunca negativo', ExpedienteAdjuntosRepository::sum_byte_size_total() === 0);

$wpdb->last_error = 'simulated db error';
$wpdb->sum_value = '999';
ac_assert('sum con last_error → null', ExpedienteAdjuntosRepository::sum_byte_size_total() === null);
$wpdb->last_error = '';

$wpdb->sum_value = null;
ac_assert('sum con get_var null → null', ExpedienteAdjuntosRepository::sum_byte_size_total() === null);
$wpdb->sum_value = '0';

echo "\n";
if (count($failed) === 0) {
    echo "Passed {$passed}/{$total}\n";
    exit(0);
}

echo 'Failed ' . count($failed) . "/{$total}\n";
foreach ($failed as $label) {
    echo " - {$label}\n";
}
exit(1);
