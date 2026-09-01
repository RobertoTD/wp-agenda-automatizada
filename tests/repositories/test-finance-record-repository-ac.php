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
    public $update_result = 1;
    public $updated = null;

    public function prepare(string $query, ...$args): string {
        $flat_args = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                foreach ($arg as $sub) {
                    $flat_args[] = $sub;
                }
            } else {
                $flat_args[] = $arg;
            }
        }
        foreach ($flat_args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', (string) $val, $query, 1);
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
        return array_shift($this->results);
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

    public function update($table, array $data, array $where, $format = null, $where_format = null) {
        $this->updated = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'format' => $format,
            'where_format' => $where_format,
        ];
        $this->query_log[] = ['update' => $table, 'data' => $data, 'where' => $where];
        if ($this->last_error !== '') {
            return false;
        }
        return $this->update_result;
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

echo "\n=== 3. Pruebas de sum_amounts_by_container_ids() (Ciclo 3B2b) ===\n";

// 3.1 Entrada vacía devuelve [] sin consultar $wpdb
$wpdb->query_log = [];
$empty_batch = FinanceRecordRepository::sum_amounts_by_container_ids([]);
ac_assert('sum_amounts_by_container_ids([]) devuelve []', $empty_batch === []);
ac_assert('sum_amounts_by_container_ids([]) no ejecuta consultas SQL', count($wpdb->query_log) === 0);

// 3.2 Precondiciones: tipo no entero, ID < 1, strings numéricos, floats, booleans, arrays, objects
$caught_inv_type = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids(['1']);
} catch (\InvalidArgumentException $e) {
    $caught_inv_type = true;
}
ac_assert('sum_amounts_by_container_ids con string "1" lanza InvalidArgumentException', $caught_inv_type);

$caught_inv_zero = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([0]);
} catch (\InvalidArgumentException $e) {
    $caught_inv_zero = true;
}
ac_assert('sum_amounts_by_container_ids con ID 0 lanza InvalidArgumentException', $caught_inv_zero);

$caught_inv_neg = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1, -5]);
} catch (\InvalidArgumentException $e) {
    $caught_inv_neg = true;
}
ac_assert('sum_amounts_by_container_ids con ID negativo lanza InvalidArgumentException', $caught_inv_neg);

$caught_inv_float = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1, 2.5]);
} catch (\InvalidArgumentException $e) {
    $caught_inv_float = true;
}
ac_assert('sum_amounts_by_container_ids con float lanza InvalidArgumentException', $caught_inv_float);

$caught_inv_bool = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([true]);
} catch (\InvalidArgumentException $e) {
    $caught_inv_bool = true;
}
ac_assert('sum_amounts_by_container_ids con bool lanza InvalidArgumentException', $caught_inv_bool);

$caught_inv_arr = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([[1]]);
} catch (\InvalidArgumentException $e) {
    $caught_inv_arr = true;
}
ac_assert('sum_amounts_by_container_ids con array anidado lanza InvalidArgumentException', $caught_inv_arr);

$caught_inv_obj = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([(object)['id' => 1]]);
} catch (\InvalidArgumentException $e) {
    $caught_inv_obj = true;
}
ac_assert('sum_amounts_by_container_ids con object lanza InvalidArgumentException', $caught_inv_obj);

// 3.3 Límite máximo de 15 IDs únicos
$sixteen_ids = range(1, 16);
$caught_over_limit = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids($sixteen_ids);
} catch (\InvalidArgumentException $e) {
    $caught_over_limit = true;
}
ac_assert('sum_amounts_by_container_ids con 16 IDs únicos lanza InvalidArgumentException', $caught_over_limit);

// 15 IDs únicos es válido
$fifteen_ids = range(1, 15);
$wpdb->results[] = [];
$map_fifteen = FinanceRecordRepository::sum_amounts_by_container_ids($fifteen_ids);
ac_assert('sum_amounts_by_container_ids con 15 IDs únicos es exitoso y devuelve 15 claves', count($map_fifteen) === 15);

// 3.4 Deduplicación conservando orden y primera aparición
$wpdb->results[] = [
    ['container_id' => '10', 'amount_total' => '100.00'],
    ['container_id' => '5', 'amount_total' => '50.00'],
];
$map_dedup = FinanceRecordRepository::sum_amounts_by_container_ids([10, 5, 10, 5, 2]);
ac_assert('sum_amounts_by_container_ids deduplica IDs y devuelve mapa ordenado [10, 5, 2]', array_keys($map_dedup) === [10, 5, 2]);
ac_assert('sum_amounts_by_container_ids mapa contiene valores e ID 2 sin fila como null', $map_dedup[10] === '100.00' && $map_dedup[5] === '50.00' && $map_dedup[2] === null);

// 3.5 Valores semánticos: null, "0.00", positivo, negativo, sin fila
$wpdb->results[] = [
    ['container_id' => '1', 'amount_total' => null],
    ['container_id' => '2', 'amount_total' => '0.00'],
    ['container_id' => '3', 'amount_total' => '175.50'],
    ['container_id' => '4', 'amount_total' => '-45.25'],
];
$map_semantic = FinanceRecordRepository::sum_amounts_by_container_ids([1, 2, 3, 4, 5]);
ac_assert('ID 1 con amount_total null en fila es null', $map_semantic[1] === null);
ac_assert('ID 2 con amount_total "0.00" es "0.00"', $map_semantic[2] === '0.00');
ac_assert('ID 3 con amount_total "175.50" es "175.50"', $map_semantic[3] === '175.50');
ac_assert('ID 4 con amount_total "-45.25" es "-45.25"', $map_semantic[4] === '-45.25');
ac_assert('ID 5 sin fila en BD es null', $map_semantic[5] === null);

// 3.6 Fail-closed ante error SQL
$wpdb->last_error = 'Database timeout';
$caught_db_err = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_db_err = (strpos($e->getMessage(), 'Error al calcular la suma agregada de registros') !== false);
}
ac_assert('Fallo SQL en sum_amounts_by_container_ids lanza RuntimeException', $caught_db_err);
$wpdb->last_error = '';

// 3.7 Fail-closed ante filas SQL anómalas
// A) $rows no array
$wpdb->results[] = null;
$caught_non_arr = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_non_arr = true;
}
ac_assert('Filas no array lanza RuntimeException', $caught_non_arr);

// B) Fila no array
$wpdb->results[] = ['not_an_array_row'];
$caught_bad_row = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_bad_row = true;
}
ac_assert('Fila que no es array lanza RuntimeException', $caught_bad_row);

// C) Fila sin container_id
$wpdb->results[] = [['amount_total' => '10.00']];
$caught_missing_cid = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_missing_cid = true;
}
ac_assert('Fila sin container_id lanza RuntimeException', $caught_missing_cid);

// D) Fila sin amount_total
$wpdb->results[] = [['container_id' => 1]];
$caught_missing_amt = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_missing_amt = true;
}
ac_assert('Fila sin amount_total lanza RuntimeException', $caught_missing_amt);

// E) Fila con container_id inválido (cero, negativo, float o texto no numérico)
$wpdb->results[] = [['container_id' => '0', 'amount_total' => '10.00']];
$caught_invalid_cid_row = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_invalid_cid_row = true;
}
ac_assert('Fila con container_id "0" lanza RuntimeException', $caught_invalid_cid_row);

// F) Fila con container_id con ceros iniciales como "01"
$wpdb->results[] = [['container_id' => '01', 'amount_total' => '10.00']];
$caught_lead_zero_cid = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_lead_zero_cid = true;
}
ac_assert('Fila con container_id "01" lanza RuntimeException', $caught_lead_zero_cid);

// G) Fila con container_id no solicitado
$wpdb->results[] = [['container_id' => 99, 'amount_total' => '10.00']];
$caught_unrequested_cid = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_unrequested_cid = true;
}
ac_assert('Fila con ID no solicitado lanza RuntimeException', $caught_unrequested_cid);

// H) Fila duplicada para el mismo container_id
$wpdb->results[] = [
    ['container_id' => 1, 'amount_total' => '10.00'],
    ['container_id' => 1, 'amount_total' => '20.00'],
];
$caught_dup_cid = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_dup_cid = true;
}
ac_assert('Fila duplicada para el mismo ID lanza RuntimeException', $caught_dup_cid);

// I) Fila con amount_total numérico (int/float en lugar de string/null)
$wpdb->results[] = [['container_id' => 1, 'amount_total' => 10.50]];
$caught_numeric_amt = false;
try {
    FinanceRecordRepository::sum_amounts_by_container_ids([1]);
} catch (\RuntimeException $e) {
    $caught_numeric_amt = true;
}
ac_assert('Fila con amount_total tipo float lanza RuntimeException', $caught_numeric_amt);

echo "\n=== 3. update() ===\n";

// 3.1 Precondiciones locales
$caught_update_invalid_id = false;
try {
    FinanceRecordRepository::update(0, 10, 'Título', null, null);
} catch (\InvalidArgumentException $e) {
    $caught_update_invalid_id = true;
}
ac_assert('update() con id < 1 lanza InvalidArgumentException', $caught_update_invalid_id);
ac_assert('update() con id inválido no ejecutó UPDATE', $wpdb->updated === null);

$caught_update_invalid_container = false;
try {
    FinanceRecordRepository::update(1, 0, 'Título', null, null);
} catch (\InvalidArgumentException $e) {
    $caught_update_invalid_container = true;
}
ac_assert('update() con container_id < 1 lanza InvalidArgumentException', $caught_update_invalid_container);

$caught_update_empty_title = false;
try {
    FinanceRecordRepository::update(1, 10, '', null, null);
} catch (\InvalidArgumentException $e) {
    $caught_update_empty_title = true;
}
ac_assert('update() con title vacío lanza InvalidArgumentException', $caught_update_empty_title);

// 3.2 Camino feliz con retorno 1 + relectura
$wpdb->updated = null;
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '42',
    'container_id' => '10',
    'title' => 'Nuevo título',
    'details' => 'Nuevos detalles',
    'amount' => '-25.50',
    'created_at' => '2026-08-29 12:00:00',
];
$updated_ok = FinanceRecordRepository::update(42, 10, 'Nuevo título', 'Nuevos detalles', '-25.50');
ac_assert('update() con retorno 1 devuelve fila autoritativa', is_array($updated_ok) && $updated_ok['id'] === 42 && $updated_ok['title'] === 'Nuevo título');
ac_assert('update() SET exacto title/details/amount', ($wpdb->updated['data']['title'] ?? '') === 'Nuevo título' && ($wpdb->updated['data']['details'] ?? '') === 'Nuevos detalles' && ($wpdb->updated['data']['amount'] ?? '') === '-25.50');
ac_assert('update() WHERE exacto id/container_id', ($wpdb->updated['where']['id'] ?? null) === 42 && ($wpdb->updated['where']['container_id'] ?? null) === 10);
ac_assert('update() SET no contiene container_id', !array_key_exists('container_id', $wpdb->updated['data']));

// 3.3 amount null
$wpdb->updated = null;
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '43',
    'container_id' => '10',
    'title' => 'Sin monto',
    'details' => null,
    'amount' => null,
    'created_at' => '2026-08-29 12:00:00',
];
FinanceRecordRepository::update(43, 10, 'Sin monto', null, null);
ac_assert('update() amount null usa formato NULL', $wpdb->updated['format'][2] === null);

// 3.4 cero canónico
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '44',
    'container_id' => '10',
    'title' => 'Cero',
    'details' => null,
    'amount' => '0.00',
    'created_at' => '2026-08-29 12:00:00',
];
$updated_zero = FinanceRecordRepository::update(44, 10, 'Cero', null, '0.00');
ac_assert('update() amount "0.00" devuelve amount canónico', is_array($updated_zero) && $updated_zero['amount'] === '0.00');

// 3.5 details borrados
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '45',
    'container_id' => '10',
    'title' => 'Sin detalle',
    'details' => null,
    'amount' => '10.00',
    'created_at' => '2026-08-29 12:00:00',
];
$updated_no_details = FinanceRecordRepository::update(45, 10, 'Sin detalle', null, '10.00');
ac_assert('update() details null persiste borrado', is_array($updated_no_details) && $updated_no_details['details'] === null);

// 3.6 Scope incorrecto → null
$wpdb->update_result = 0;
$wpdb->rows[] = null;
$updated_wrong_scope = FinanceRecordRepository::update(99, 10, 'Título', null, null);
ac_assert('update() scope incorrecto devuelve null', $updated_wrong_scope === null);

// 3.7 Error SQL
$wpdb->last_error = 'Update failed';
$caught_update_sql = false;
try {
    FinanceRecordRepository::update(46, 10, 'Título', null, null);
} catch (\RuntimeException $e) {
    $caught_update_sql = (strpos($e->getMessage(), 'Error al actualizar el registro financiero') !== false);
}
ac_assert('update() ante error SQL lanza RuntimeException', $caught_update_sql);
$wpdb->last_error = '';

// 3.8 last_error tras false
$wpdb->last_error = 'Deadlock';
$caught_update_last_error = false;
try {
    FinanceRecordRepository::update(47, 10, 'Título', null, null);
} catch (\RuntimeException $e) {
    $caught_update_last_error = true;
}
ac_assert('update() con last_error lanza RuntimeException', $caught_update_last_error);
$wpdb->last_error = '';

// 3.9 Retorno string numérico
$wpdb->update_result = '1';
$caught_update_string = false;
try {
    FinanceRecordRepository::update(48, 10, 'Título', null, null);
} catch (\RuntimeException $e) {
    $caught_update_string = true;
}
ac_assert('update() con retorno string numérico lanza RuntimeException', $caught_update_string);

// 3.10 Retorno float
$wpdb->update_result = 1.0;
$caught_update_float = false;
try {
    FinanceRecordRepository::update(49, 10, 'Título', null, null);
} catch (\RuntimeException $e) {
    $caught_update_float = true;
}
ac_assert('update() con retorno float lanza RuntimeException', $caught_update_float);

// 3.11 Retorno booleano
$wpdb->update_result = true;
$caught_update_bool = false;
try {
    FinanceRecordRepository::update(50, 10, 'Título', null, null);
} catch (\RuntimeException $e) {
    $caught_update_bool = true;
}
ac_assert('update() con retorno booleano lanza RuntimeException', $caught_update_bool);

// 3.12 Retorno negativo
$wpdb->update_result = -1;
$caught_update_negative = false;
try {
    FinanceRecordRepository::update(51, 10, 'Título', null, null);
} catch (\RuntimeException $e) {
    $caught_update_negative = true;
}
ac_assert('update() con retorno negativo lanza RuntimeException', $caught_update_negative);

// 3.13 Retorno > 1
$wpdb->update_result = 2;
$caught_update_multi = false;
try {
    FinanceRecordRepository::update(52, 10, 'Título', null, null);
} catch (\RuntimeException $e) {
    $caught_update_multi = (strpos($e->getMessage(), 'más de una fila') !== false);
}
ac_assert('update() con retorno > 1 lanza RuntimeException', $caught_update_multi);

// 3.14 Idempotencia affected 0
$wpdb->update_result = 0;
$wpdb->rows[] = [
    'id' => '53',
    'container_id' => '10',
    'title' => 'Igual',
    'details' => null,
    'amount' => '5.00',
    'created_at' => '2026-08-29 12:00:00',
];
$updated_idempotent = FinanceRecordRepository::update(53, 10, 'Igual', null, '5.00');
ac_assert('update() retorno 0 con valores iguales devuelve fila idempotente', is_array($updated_idempotent) && $updated_idempotent['title'] === 'Igual');

// 3.15 affected 0 valores distintos
$wpdb->update_result = 0;
$wpdb->rows[] = [
    'id' => '54',
    'container_id' => '10',
    'title' => 'Distinto',
    'details' => 'X',
    'amount' => '1.00',
    'created_at' => '2026-08-29 12:00:00',
];
$caught_update_mismatch = false;
try {
    FinanceRecordRepository::update(54, 10, 'Intento', null, '9.99');
} catch (\RuntimeException $e) {
    $caught_update_mismatch = (strpos($e->getMessage(), 'sin efecto con valores distintos') !== false);
}
ac_assert('update() retorno 0 con valores distintos lanza RuntimeException', $caught_update_mismatch);

// 3.16 affected 0 fila ausente
$wpdb->update_result = 0;
$wpdb->rows[] = null;
$updated_gone = FinanceRecordRepository::update(55, 10, 'Título', null, null);
ac_assert('update() retorno 0 con fila ausente devuelve null', $updated_gone === null);

// 3.17 affected 1 relectura ausente
$wpdb->update_result = 1;
$wpdb->rows[] = null;
$updated_gone_after_one = FinanceRecordRepository::update(56, 10, 'Ausente', null, null);
ac_assert('update() retorno 1 con relectura ausente devuelve null', $updated_gone_after_one === null);

// 3.18 affected 1 ID discordante
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '999',
    'container_id' => '10',
    'title' => 'Discordante',
    'details' => null,
    'amount' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$caught_update_identity = false;
try {
    FinanceRecordRepository::update(57, 10, 'Discordante', null, null);
} catch (\RuntimeException $e) {
    $caught_update_identity = (strpos($e->getMessage(), 'Identidad discordante') !== false);
}
ac_assert('update() retorno 1 con id discordante lanza RuntimeException', $caught_update_identity);

// 3.19 affected 1 container_id discordante
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '58',
    'container_id' => '99',
    'title' => 'Discordante',
    'details' => null,
    'amount' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$caught_update_container_mismatch = false;
try {
    FinanceRecordRepository::update(58, 10, 'Discordante', null, null);
} catch (\RuntimeException $e) {
    $caught_update_container_mismatch = (strpos($e->getMessage(), 'Identidad discordante') !== false);
}
ac_assert('update() retorno 1 con container_id discordante lanza RuntimeException', $caught_update_container_mismatch);

// 3.20 Fila corrupta
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'container_id' => '10',
    'title' => 'Sin id',
    'details' => null,
    'amount' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$caught_update_corrupt = false;
try {
    FinanceRecordRepository::update(59, 10, 'Sin id', null, null);
} catch (\RuntimeException $e) {
    $caught_update_corrupt = (strpos($e->getMessage(), 'corrupta') !== false);
}
ac_assert('update() retorno 1 con fila corrupta lanza RuntimeException', $caught_update_corrupt);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
