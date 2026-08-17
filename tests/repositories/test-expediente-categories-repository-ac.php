<?php
/**
 * AC — ExpedienteCategoriesRepository.
 *
 * Ejecutar: php tests/repositories/test-expediente-categories-repository-ac.php
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

require_once $plugin_root . '/includes/repositories/ExpedienteCategoriesRepository.php';

$src = file_get_contents($plugin_root . '/includes/repositories/ExpedienteCategoriesRepository.php');
ac_assert('find_by_slug existe', strpos($src, 'function find_by_slug') !== false);
ac_assert('list_all existe', strpos($src, 'function list_all') !== false);
ac_assert('usa $wpdb->prefix', strpos($src, "aa_expediente_categories") !== false);
ac_assert('sin tablas legacy', strpos($src, 'aa_expediente_registros') === false
    && strpos($src, 'aa_expediente_adjuntos') === false
    && strpos($src, 'aa_clientes') === false);

global $wpdb;
$wpdb = new class {
    public $prefix = 'wp_5_';
    public $last_error = '';
    public $last_query = '';
    public $row = null;
    public $rows = [];

    public function prepare($query, ...$args) {
        $this->last_query = $query;
        foreach ($args as $arg) {
            $this->last_query .= '|' . $arg;
        }
        return $this->last_query;
    }

    public function get_row($query, $output = OBJECT) {
        $this->last_query = (string) $query;
        if ($output === ARRAY_A) {
            return $this->row;
        }
        return $this->row ? (object) $this->row : null;
    }

    public function get_results($query, $output = OBJECT) {
        $this->last_query = (string) $query;
        if ($output === ARRAY_A) {
            return $this->rows;
        }
        return array_map(static function ($r) {
            return (object) $r;
        }, $this->rows);
    }
};

ac_assert('slug vacío → null', ExpedienteCategoriesRepository::find_by_slug('') === null);

$wpdb->row = [
    'id' => '3',
    'slug' => 'general',
    'name' => 'General',
    'created_at' => '2026-08-17 12:00:00',
];

$found = ExpedienteCategoriesRepository::find_by_slug('general');
ac_assert('find usa prefijo blog', strpos($wpdb->last_query, 'wp_5_aa_expediente_categories') !== false);
ac_assert('find prepara slug', strpos($wpdb->last_query, '|general') !== false);
ac_assert('find mapea fila', is_array($found) && $found['id'] === 3 && $found['slug'] === 'general' && $found['name'] === 'General');

$wpdb->row = null;
ac_assert('slug inexistente → null', ExpedienteCategoriesRepository::find_by_slug('no-existe') === null);

$wpdb->rows = [
    [
        'id' => '3',
        'slug' => 'general',
        'name' => 'General',
        'created_at' => '2026-08-17 12:00:00',
    ],
];
$list = ExpedienteCategoriesRepository::list_all();
ac_assert('list_all usa prefijo', strpos($wpdb->last_query, 'wp_5_aa_expediente_categories') !== false);
ac_assert('list_all mapea', count($list) === 1 && $list[0]['slug'] === 'general');

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
