<?php
/**
 * AC Test — FinanceRecordRepository (Ciclo 3A2).
 *
 * Ejecutar:
 *   php tests/repositories/test-finance-record-repository-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$repo_file = $plugin_root . '/includes/repositories/FinanceRecordRepository.php';

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

echo "=== 1. Análisis estático de FinanceRecordRepository ===\n";

ac_assert('Archivo FinanceRecordRepository.php existe y es legible', is_readable($repo_file));
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

class TestFinanceRecordWpdbMock {
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
        $this->insert_id = 101;
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
$wpdb = new TestFinanceRecordWpdbMock();

require_once $repo_file;

// 2.1 create()
$created = FinanceRecordRepository::create(42, 'Licencia Software', 'Pago anual', '150.00');
ac_assert('create() devuelve array con id poblado', is_array($created) && $created['id'] === 101);
ac_assert('create() conserva container_id y title', $created['container_id'] === 42 && $created['title'] === 'Licencia Software');
ac_assert('create() conserva details y amount string', $created['details'] === 'Pago anual' && $created['amount'] === '150.00');
ac_assert('create() inserta amount como string sin %f', end($wpdb->inserts)['data']['amount'] === '150.00');

// create() con amount null
$created_null_amount = FinanceRecordRepository::create(42, 'Nota informativa', null, null);
ac_assert('create() con amount null devuelve amount null', $created_null_amount['amount'] === null && $created_null_amount['details'] === null);

// Precondiciones de create()
$caught_invalid_container = false;
try {
    FinanceRecordRepository::create(0, 'Item');
} catch (\InvalidArgumentException $e) {
    $caught_invalid_container = true;
}
ac_assert('create() con container_id=0 lanza InvalidArgumentException', $caught_invalid_container);

$caught_empty_title = false;
try {
    FinanceRecordRepository::create(42, '');
} catch (\InvalidArgumentException $e) {
    $caught_empty_title = true;
}
ac_assert('create() con title vacío lanza InvalidArgumentException', $caught_empty_title);

// Error SQL en create()
$wpdb->last_error = 'FK constraint failed';
$caught_create_err = false;
try {
    FinanceRecordRepository::create(999, 'Test');
} catch (\RuntimeException $e) {
    $caught_create_err = (strpos($e->getMessage(), 'Error al crear el registro financiero') !== false);
}
ac_assert('create() ante error SQL/FK lanza RuntimeException', $caught_create_err);
$wpdb->last_error = '';

// 2.2 find_by_id_and_container()
$wpdb->rows[] = [
    'id' => '101',
    'container_id' => '42',
    'title' => 'Registro 101',
    'details' => null,
    'amount' => '-25.50',
    'created_at' => '2026-08-29 12:00:00',
];
$found = FinanceRecordRepository::find_by_id_and_container(101, 42);
ac_assert('find_by_id_and_container() devuelve registro', is_array($found) && $found['id'] === 101 && $found['amount'] === '-25.50');
ac_assert('find_by_id_and_container() incluye container_id en WHERE', strpos($wpdb->last_query, 'WHERE id = 101 AND container_id = 42') !== false);

// Inexistente devuelve null
$wpdb->rows = [];
$not_found = FinanceRecordRepository::find_by_id_and_container(999, 42);
ac_assert('find_by_id_and_container() para inexistente devuelve null sin lanzar excepción', $not_found === null);

// Precondición de IDs < 1
$caught_invalid_find_id = false;
try {
    FinanceRecordRepository::find_by_id_and_container(0, 42);
} catch (\InvalidArgumentException $e) {
    $caught_invalid_find_id = true;
}
ac_assert('find_by_id_and_container(0, 42) lanza InvalidArgumentException', $caught_invalid_find_id);

// 2.3 list_by_container()
$wpdb->results[] = [
    ['id' => '102', 'container_id' => '42', 'title' => 'R2', 'details' => 'D2', 'amount' => '50.00', 'created_at' => '2026-08-29 10:00:00'],
    ['id' => '101', 'container_id' => '42', 'title' => 'R1', 'details' => null, 'amount' => null, 'created_at' => '2026-08-29 09:00:00'],
];
$list = FinanceRecordRepository::list_by_container(42, 1);
ac_assert('list_by_container() devuelve lista de arrays', count($list) === 2 && $list[0]['id'] === 102 && $list[1]['id'] === 101);
ac_assert('list_by_container() preserva amount como string o null', $list[0]['amount'] === '50.00' && $list[1]['amount'] === null);
ac_assert('list_by_container() usa query con LIMIT 15 OFFSET 0', strpos($wpdb->last_query, 'LIMIT 15 OFFSET 0') !== false);

// 2.4 count_by_container()
$wpdb->vars[] = '12';
$count = FinanceRecordRepository::count_by_container(42);
ac_assert('count_by_container() devuelve int 12', $count === 12);

// 2.5 sum_amounts_by_container()
// Caso A: Contenedor con registros que suman '175.50'
$wpdb->rows[] = ['count_with_amount' => '3', 'total_amount' => '175.50'];
$sum_positive = FinanceRecordRepository::sum_amounts_by_container(42);
ac_assert('sum_amounts_by_container() con importes positivos devuelve "175.50"', $sum_positive === '175.50');

// Caso B: Contenedor con registros que suman exactamente cero ("0.00")
$wpdb->rows[] = ['count_with_amount' => '2', 'total_amount' => '0.00'];
$sum_zero = FinanceRecordRepository::sum_amounts_by_container(42);
ac_assert('sum_amounts_by_container() con suma cero devuelve "0.00"', $sum_zero === '0.00');

// Caso C: Contenedor con suma negativa ("-30.00")
$wpdb->rows[] = ['count_with_amount' => '1', 'total_amount' => '-30.00'];
$sum_negative = FinanceRecordRepository::sum_amounts_by_container(42);
ac_assert('sum_amounts_by_container() con suma negativa devuelve "-30.00"', $sum_negative === '-30.00');

// Caso D: Contenedor sin cantidades (count_with_amount = 0) devuelve null
$wpdb->rows[] = ['count_with_amount' => '0', 'total_amount' => null];
$sum_none = FinanceRecordRepository::sum_amounts_by_container(42);
ac_assert('sum_amounts_by_container() sin cantidades devuelve null', $sum_none === null);

// Caso E: Contenedor vacío (sin fila) devuelve null
$wpdb->rows = [];
$sum_empty = FinanceRecordRepository::sum_amounts_by_container(42);
ac_assert('sum_amounts_by_container() para contenedor vacío devuelve null', $sum_empty === null);

// 2.6 delete()
$wpdb->deleted_rows = 1;
$del_ok = FinanceRecordRepository::delete(101, 42);
ac_assert('delete(101, 42) con 1 fila eliminada devuelve true', $del_ok === true);
ac_assert('delete() incluye container_id en condición where', end($wpdb->query_log)['where']['container_id'] === 42);

$wpdb->deleted_rows = 0;
$del_zero = FinanceRecordRepository::delete(999, 42);
ac_assert('delete(999, 42) con 0 filas eliminadas devuelve false', $del_zero === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
