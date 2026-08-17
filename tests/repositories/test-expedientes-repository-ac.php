<?php
/**
 * AC — ExpedientesRepository (insert, count_matching, list_page).
 *
 * Ejecutar: php tests/repositories/test-expedientes-repository-ac.php
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

require_once $plugin_root . '/includes/repositories/ExpedientesRepository.php';

$src = file_get_contents($plugin_root . '/includes/repositories/ExpedientesRepository.php');
ac_assert('insert existe', strpos($src, 'function insert') !== false);
ac_assert('count_matching existe', strpos($src, 'function count_matching') !== false);
ac_assert('list_page existe', strpos($src, 'function list_page') !== false);
ac_assert('sin list_recent', strpos($src, 'list_recent') === false);
ac_assert('sin LIST_LIMIT 100', strpos($src, 'LIST_LIMIT') === false);
ac_assert('ORDER BY created_at DESC, id DESC', strpos($src, 'ORDER BY e.created_at DESC, e.id DESC') !== false);
ac_assert('usa esc_like', strpos($src, 'esc_like') !== false);
ac_assert('usa LIMIT y OFFSET preparados', strpos($src, 'LIMIT %d OFFSET %d') !== false);
ac_assert('JOIN categorías', strpos($src, 'aa_expediente_categories') !== false);
ac_assert('sin tablas legacy', strpos($src, 'aa_expediente_registros') === false
    && strpos($src, 'aa_expediente_adjuntos') === false
    && strpos($src, 'aa_clientes') === false);

global $wpdb;
$wpdb = new class {
    public $prefix = 'wp_5_';
    public $last_error = '';
    public $insert_id = 0;
    public $last_query = '';
    public $queries = [];
    public $var = 0;
    public $rows = [];
    public $insert_ok = true;
    public $inserted = null;

    public function esc_like($text) {
        return addcslashes((string) $text, '_%\\');
    }

    public function prepare($query, ...$args) {
        $this->last_query = $query;
        foreach ($args as $arg) {
            $this->last_query .= '|' . $arg;
        }
        $this->queries[] = $this->last_query;
        return $this->last_query;
    }

    public function get_var($query = null, $x = 0, $y = 0) {
        $this->last_query = (string) $query;
        $this->queries[] = $this->last_query;
        return $this->var;
    }

    public function get_results($query, $output = OBJECT) {
        $this->last_query = (string) $query;
        $this->queries[] = $this->last_query;
        if ($output === ARRAY_A) {
            return $this->rows;
        }
        return array_map(static function ($r) {
            return (object) $r;
        }, $this->rows);
    }

    public function insert($table, $data, $format = null) {
        $this->inserted = ['table' => $table, 'data' => $data, 'format' => $format];
        if (!$this->insert_ok) {
            $this->last_error = 'simulated failure';
            return false;
        }
        $this->insert_id = 42;
        return 1;
    }
};

$created = ExpedientesRepository::insert([
    'title' => 'Contrato laboral',
    'description' => null,
    'category_id' => 3,
    'created_at' => '2026-08-17 13:00:00',
]);
ac_assert('insert OK', is_array($created) && $created['id'] === 42);
ac_assert('insert tabla prefijada', ($wpdb->inserted['table'] ?? '') === 'wp_5_aa_expedientes');
ac_assert('insert description null', array_key_exists('description', $wpdb->inserted['data'] ?? []) && $wpdb->inserted['data']['description'] === null);
ac_assert('insert no escribe updated_at', !array_key_exists('updated_at', $wpdb->inserted['data'] ?? []));
ac_assert('insert no escribe client_id', !array_key_exists('client_id', $wpdb->inserted['data'] ?? []));

$bad = ExpedientesRepository::insert([
    'title' => '',
    'description' => null,
    'category_id' => 0,
    'created_at' => '',
]);
ac_assert('insert incompleto → null', $bad === null);

$wpdb->insert_ok = false;
$fail = ExpedientesRepository::insert([
    'title' => 'X',
    'description' => 'Y',
    'category_id' => 3,
    'created_at' => '2026-08-17 13:00:00',
]);
ac_assert('insert DB error → null', $fail === null);
$wpdb->insert_ok = true;
$wpdb->last_error = '';

$wpdb->var = 16;
$count_all = ExpedientesRepository::count_matching('');
ac_assert('count vacío sin LIKE', $count_all === 16 && strpos($wpdb->last_query, 'LIKE') === false);
ac_assert('count vacío usa prefijo', strpos($wpdb->last_query, 'wp_5_aa_expedientes') !== false);

$wpdb->var = 2;
$count_q = ExpedientesRepository::count_matching('Contrato');
ac_assert('count con query usa LIKE preparado', strpos($wpdb->last_query, 'LIKE %s') !== false && strpos($wpdb->last_query, '|%Contrato%') !== false);

$wpdb->var = 0;
ExpedientesRepository::count_matching('100%_ok');
ac_assert(
    'count esc_like % y _',
    strpos($wpdb->last_query, '|%100\\%\\_ok%') !== false,
    (string) $wpdb->last_query
);

$wpdb->rows = [
    [
        'id' => '16',
        'title' => 'A',
        'description' => '',
        'created_at' => '2026-08-17 12:00:00',
        'updated_at' => null,
        'category_slug' => 'general',
        'category_name' => 'General',
    ],
];
$page = ExpedientesRepository::list_page('', 15, 0);
ac_assert('list_page prefijo expedientes', strpos($wpdb->last_query, 'wp_5_aa_expedientes') !== false);
ac_assert('list_page prefijo categorías', strpos($wpdb->last_query, 'wp_5_aa_expediente_categories') !== false);
ac_assert('list_page LIMIT OFFSET preparados', strpos($wpdb->last_query, '|15|0') !== false);
ac_assert('list_page orden estable', strpos($wpdb->last_query, 'ORDER BY e.created_at DESC, e.id DESC') !== false);
ac_assert('list_page mapea categoría anidada', $page[0]['category']['slug'] === 'general' && $page[0]['category']['name'] === 'General');
ac_assert('list_page description vacía → null', $page[0]['description'] === null);

ExpedientesRepository::list_page('', 15, 15);
ac_assert('list_page página 2 OFFSET 15', strpos($wpdb->last_query, '|15|15') !== false);

ExpedientesRepository::list_page('Contrato%_x', 15, 0);
ac_assert(
    'list_page esc_like % y _',
    strpos($wpdb->last_query, '|%Contrato\\%\\_x%') !== false && strpos($wpdb->last_query, '|15|0') !== false,
    (string) $wpdb->last_query
);

$empty_limit = ExpedientesRepository::list_page('', 0, 0);
ac_assert('limit < 1 → []', $empty_limit === []);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
