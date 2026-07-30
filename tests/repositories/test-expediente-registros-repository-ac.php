<?php
/**
 * AC — ExpedienteRegistrosRepository (MC2).
 *
 * Ejecutar: php tests/repositories/test-expediente-registros-repository-ac.php
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

require_once $plugin_root . '/includes/repositories/ExpedienteRegistrosRepository.php';

$src = file_get_contents($plugin_root . '/includes/repositories/ExpedienteRegistrosRepository.php');
ac_assert('repo file exists', is_string($src) && $src !== '');
ac_assert('list_by_client_id existe', strpos($src, 'function list_by_client_id') !== false);
ac_assert('insert existe', strpos($src, 'function insert') !== false);
ac_assert('ORDER BY recorded_at DESC, id DESC', strpos($src, 'ORDER BY recorded_at DESC, id DESC') !== false);
ac_assert('usa $wpdb->prefix', strpos($src, "aa_expediente_registros") !== false);
ac_assert('sin update/delete en MC2', !preg_match('/function (update|delete)\b/', $src));

global $wpdb;
$wpdb = new class {
    public $prefix = 'wp_5_';
    public $last_error = '';
    public $insert_id = 0;
    public $last_query = '';
    public $rows = [];
    public $insert_ok = true;
    public $inserted = null;

    public function prepare($query, ...$args) {
        $this->last_query = $query;
        foreach ($args as $arg) {
            $this->last_query .= '|' . $arg;
        }
        return $this->last_query;
    }

    public function get_results($query, $output = OBJECT) {
        if ($output === ARRAY_A) {
            return $this->rows;
        }
        return array_map(static function ($r) {
            return (object) $r;
        }, $this->rows);
    }

    public function insert($table, $data, $format = null) {
        $this->inserted = ['table' => $table, 'data' => $data];
        if (!$this->insert_ok) {
            $this->last_error = 'simulated failure';
            return false;
        }
        $this->insert_id = 77;
        return 1;
    }
};

ac_assert('list client_id inválido → []', ExpedienteRegistrosRepository::list_by_client_id(0) === []);

$wpdb->rows = [
    [
        'id' => '2',
        'client_id' => '9',
        'title' => 'B',
        'body' => 'body b',
        'recorded_at' => '2026-07-30 12:00:00',
        'created_at' => '2026-07-30 12:00:00',
        'updated_at' => null,
    ],
    [
        'id' => '1',
        'client_id' => '9',
        'title' => 'A',
        'body' => 'body a',
        'recorded_at' => '2026-07-29 10:00:00',
        'created_at' => '2026-07-29 10:00:00',
        'updated_at' => '',
    ],
];

$list = ExpedienteRegistrosRepository::list_by_client_id(9);
ac_assert('list usa prefijo blog', strpos($wpdb->last_query, 'wp_5_aa_expediente_registros') !== false);
ac_assert('list mapea 2 filas', count($list) === 2);
ac_assert('list campos normalizados', $list[0]['id'] === 2 && $list[0]['title'] === 'B' && $list[0]['updated_at'] === null);
ac_assert('list updated_at vacío → null', $list[1]['updated_at'] === null);

$created = ExpedienteRegistrosRepository::insert([
    'client_id' => 9,
    'title' => 'Nota',
    'body' => 'Texto',
    'recorded_at' => '2026-07-30 15:00:00',
    'created_at' => '2026-07-30 15:00:00',
]);
ac_assert('insert OK', is_array($created) && $created['id'] === 77);
ac_assert('insert tabla prefijada', ($wpdb->inserted['table'] ?? '') === 'wp_5_aa_expediente_registros');
ac_assert('insert no escribe updated_at', !array_key_exists('updated_at', $wpdb->inserted['data'] ?? []));

$bad = ExpedienteRegistrosRepository::insert([
    'client_id' => 0,
    'title' => '',
    'body' => '',
    'recorded_at' => '',
    'created_at' => '',
]);
ac_assert('insert inválido → WP_Error', is_wp_error($bad));

$wpdb->insert_ok = false;
$fail = ExpedienteRegistrosRepository::insert([
    'client_id' => 9,
    'title' => 'X',
    'body' => 'Y',
    'recorded_at' => '2026-07-30 15:00:00',
    'created_at' => '2026-07-30 15:00:00',
]);
ac_assert('insert SQL fail → WP_Error', is_wp_error($fail));

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
