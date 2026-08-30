<?php
/**
 * AC Test — FinanceContainerRepository (Ciclo 3A2).
 *
 * Ejecutar:
 *   php tests/repositories/test-finance-container-repository-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$repo_file = $plugin_root . '/includes/repositories/FinanceContainerRepository.php';

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

echo "=== 1. Análisis estático de FinanceContainerRepository ===\n";

ac_assert('Archivo FinanceContainerRepository.php existe y es legible', is_readable($repo_file));
$repo_src = file_get_contents($repo_file);
ac_assert('Contenido legible', is_string($repo_src) && $repo_src !== '');

ac_assert('Define constante PAGE_SIZE = 15', strpos($repo_src, 'public const PAGE_SIZE = 15;') !== false);
ac_assert('No contiene DTOs ni interfaces', strpos($repo_src, 'interface ') === false && strpos($repo_src, 'abstract class ') === false);
ac_assert('No contiene float casts ni %f', strpos($repo_src, '(float)') === false && strpos($repo_src, 'floatval(') === false && strpos($repo_src, '%f') === false);

echo "\n=== 2. Pruebas con doble de \$wpdb ===\n";

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!function_exists('current_time')) {
    function current_time(string $type = 'mysql'): string {
        return '2026-08-29 18:00:00';
    }
}

class TestFinanceContainerWpdbMock {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    public $last_query = '';
    public $inserts = [];
    public $rows = [];
    public $results = [];
    public $vars = [];
    public $deleted_rows = 1;
    public $query_log = [];

    public function prepare(string $query, ...$args): string {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        $this->last_query = $query;
        return $query;
    }

    public function insert(string $table, array $data, array $format = []) {
        $this->inserts[] = ['table' => $table, 'data' => $data, 'format' => $format];
        if ($this->last_error !== '') {
            return false;
        }
        $this->insert_id = 42;
        return 1;
    }

    public function get_row(string $query, $output = ARRAY_A) {
        $this->query_log[] = $query;
        if ($this->last_error !== '') {
            return null;
        }
        return array_shift($this->rows) ?: null;
    }

    public function get_results(string $query, $output = ARRAY_A) {
        $this->query_log[] = $query;
        if ($this->last_error !== '') {
            return [];
        }
        return array_shift($this->results) ?: [];
    }

    public function get_var(string $query) {
        $this->query_log[] = $query;
        if ($this->last_error !== '') {
            return null;
        }
        return array_shift($this->vars) ?? 0;
    }

    public function delete(string $table, array $where, array $where_format = []) {
        $this->query_log[] = ['delete' => $table, 'where' => $where];
        if ($this->last_error !== '') {
            return false;
        }
        return $this->deleted_rows;
    }
}

global $wpdb;
$wpdb = new TestFinanceContainerWpdbMock();

require_once $repo_file;

// 2.1 create()
$created = FinanceContainerRepository::create('general', 'Presupuesto Oficina', 'Detalles varios');
ac_assert('create() devuelve array con id poblado', is_array($created) && $created['id'] === 42);
ac_assert('create() conserva variant_key y title', $created['variant_key'] === 'general' && $created['title'] === 'Presupuesto Oficina');
ac_assert('create() conserva details y created_at', $created['details'] === 'Detalles varios' && $created['created_at'] === '2026-08-29 18:00:00');

// Precondiciones de create()
$caught_empty_variant = false;
try {
    FinanceContainerRepository::create('', 'Presupuesto');
} catch (\InvalidArgumentException $e) {
    $caught_empty_variant = true;
}
ac_assert('create() con variant_key vacía lanza InvalidArgumentException', $caught_empty_variant);

$caught_empty_title = false;
try {
    FinanceContainerRepository::create('general', '');
} catch (\InvalidArgumentException $e) {
    $caught_empty_title = true;
}
ac_assert('create() con title vacío lanza InvalidArgumentException', $caught_empty_title);

// Error SQL en create()
$wpdb->last_error = 'Table does not exist';
$caught_create_err = false;
try {
    FinanceContainerRepository::create('general', 'Test');
} catch (\RuntimeException $e) {
    $caught_create_err = (strpos($e->getMessage(), 'Error al crear el contenedor financiero') !== false);
}
ac_assert('create() ante error SQL lanza RuntimeException', $caught_create_err);
$wpdb->last_error = '';

// 2.2 find_by_id()
$wpdb->rows[] = [
    'id' => '42',
    'variant_key' => 'general',
    'title' => 'Contenedor 42',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$found = FinanceContainerRepository::find_by_id(42);
ac_assert('find_by_id(42) devuelve contenedor', is_array($found) && $found['id'] === 42 && $found['title'] === 'Contenedor 42');
ac_assert('find_by_id() hidrata details null', $found['details'] === null);

// Inexistente devuelve null
$wpdb->rows = [];
$not_found = FinanceContainerRepository::find_by_id(999);
ac_assert('find_by_id() para inexistente devuelve null sin lanzar excepción', $not_found === null);

// Precondición de ID < 1
$caught_invalid_id = false;
try {
    FinanceContainerRepository::find_by_id(0);
} catch (\InvalidArgumentException $e) {
    $caught_invalid_id = true;
}
ac_assert('find_by_id(0) lanza InvalidArgumentException', $caught_invalid_id);

// 2.3 list_by_variant()
$wpdb->results[] = [
    ['id' => '2', 'variant_key' => 'general', 'title' => 'C2', 'details' => 'D2', 'created_at' => '2026-08-29 10:00:00'],
    ['id' => '1', 'variant_key' => 'general', 'title' => 'C1', 'details' => null, 'created_at' => '2026-08-29 09:00:00'],
];
$list = FinanceContainerRepository::list_by_variant('general', 1);
ac_assert('list_by_variant() devuelve lista de arrays', count($list) === 2 && $list[0]['id'] === 2 && $list[1]['id'] === 1);
ac_assert('list_by_variant() usa query con LIMIT 15 OFFSET 0', strpos($wpdb->last_query, 'LIMIT 15 OFFSET 0') !== false);

// Paginación página 2
$wpdb->results[] = [];
$list_p2 = FinanceContainerRepository::list_by_variant('general', 2);
ac_assert('list_by_variant(general, 2) usa LIMIT 15 OFFSET 15', strpos($wpdb->last_query, 'LIMIT 15 OFFSET 15') !== false);
ac_assert('list_by_variant() página vacía devuelve []', $list_p2 === []);

// Precondición página < 1
$caught_invalid_page = false;
try {
    FinanceContainerRepository::list_by_variant('general', 0);
} catch (\InvalidArgumentException $e) {
    $caught_invalid_page = true;
}
ac_assert('list_by_variant() con page=0 lanza InvalidArgumentException', $caught_invalid_page);

// 2.4 count_by_variant()
$wpdb->vars[] = '5';
$count = FinanceContainerRepository::count_by_variant('general');
ac_assert('count_by_variant() devuelve int 5', $count === 5);

// 2.5 delete()
$wpdb->deleted_rows = 1;
$del_ok = FinanceContainerRepository::delete(42);
ac_assert('delete(42) con 1 fila eliminada devuelve true', $del_ok === true);

$wpdb->deleted_rows = 0;
$del_zero = FinanceContainerRepository::delete(999);
ac_assert('delete(999) con 0 filas eliminadas devuelve false', $del_zero === false);

$caught_del_invalid_id = false;
try {
    FinanceContainerRepository::delete(-1);
} catch (\InvalidArgumentException $e) {
    $caught_del_invalid_id = true;
}
ac_assert('delete(-1) lanza InvalidArgumentException', $caught_del_invalid_id);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
