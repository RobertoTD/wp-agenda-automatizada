<?php
/**
 * AC Test — UpdateFinanceRecordUseCase (Ciclo 3E2A).
 *
 * Ejecutar:
 *   php tests/application/finance/test-update-finance-record-use-case-ac.php
 */

$plugin_root = dirname(__DIR__, 3);

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

if (!function_exists('current_time')) {
    function current_time(string $type = 'mysql'): string {
        return '2026-08-29 18:30:00';
    }
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/repositories/FinanceRecordRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/UpdateFinanceRecordUseCase.php';

class TestUpdateRecordWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public $update_queries = [];
    public $update_result = 1;
    public $containers = [
        1 => [
            'id' => 1,
            'variant_key' => 'general',
            'title' => 'Caja Principal',
            'details' => null,
            'created_at' => '2026-08-29 10:00:00',
        ],
        2 => [
            'id' => 2,
            'variant_key' => 'other_variant',
            'title' => 'Caja Ajena',
            'details' => null,
            'created_at' => '2026-08-29 11:00:00',
        ],
    ];
    public $records = [
        501 => [
            'id' => 501,
            'container_id' => 1,
            'title' => 'Registro Original',
            'details' => 'Detalle previo',
            'amount' => '100.00',
            'created_at' => '2026-08-29 12:00:00',
        ],
        502 => [
            'id' => 502,
            'container_id' => 2,
            'title' => 'Registro Ajeno',
            'details' => null,
            'amount' => '50.00',
            'created_at' => '2026-08-29 12:00:00',
        ],
    ];
    public $rows = [];
    public $delete_record_after_update = null;
    public $corrupt_record_id_after_update = null;

    public function prepare(string $query, ...$args) {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[dfs]/', (string) $val, $query, 1);
        }
        return $query;
    }

    public function get_row(string $query, string $output = ARRAY_A) {
        if ($this->last_error !== '') {
            return null;
        }
        if (preg_match('/FROM\s+\w+aa_finance_containers\s+WHERE\s+id\s*=\s*(\d+)/i', $query, $m)) {
            $id = (int) $m[1];
            return isset($this->containers[$id]) ? $this->containers[$id] : null;
        }
        if (preg_match('/FROM\s+\w+aa_finance_records\s+WHERE\s+id\s*=\s*(\d+)\s+AND\s+container_id\s*=\s*(\d+)/i', $query, $m)) {
            $rid = (int) $m[1];
            $cid = (int) $m[2];
            if (isset($this->records[$rid]) && (int) $this->records[$rid]['container_id'] === $cid) {
                return $this->records[$rid];
            }
            return null;
        }
        return array_shift($this->rows) ?: null;
    }

    public function update($table, array $data, array $where, $format = null, $where_format = null) {
        $this->update_queries[] = ['table' => $table, 'data' => $data, 'where' => $where];
        if ($this->last_error !== '') {
            return false;
        }
        $rid = (int) ($where['id'] ?? 0);
        $cid = (int) ($where['container_id'] ?? 0);
        if (isset($this->records[$rid]) && (int) $this->records[$rid]['container_id'] === $cid) {
            $this->records[$rid] = array_merge($this->records[$rid], $data);
        }
        if ($this->delete_record_after_update === $rid) {
            unset($this->records[$rid]);
        } elseif ($this->corrupt_record_id_after_update === $rid && isset($this->records[$rid])) {
            $this->records[$rid]['id'] = $rid + 1;
        }
        return $this->update_result;
    }

    public function query(string $query) {
        if ($this->last_error !== '') {
            return false;
        }
        return 1;
    }
}

global $wpdb;
$wpdb = new TestUpdateRecordWpdbMock();

$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$registry->freeze();

$use_case = new UpdateFinanceRecordUseCase($registry);

echo "=== 1. Actualización exitosa con DTO completo ===\n";

$wpdb->update_result = 1;
$wpdb->update_queries = [];
$res_ok = $use_case->execute([
    'container_id' => 1,
    'record_id' => 501,
    'title' => 'Registro Actualizado',
    'details' => "Detalle\nnuevo",
    'amount' => '-25.50',
]);
ac_assert('Actualización exitosa devuelve success: true', $res_ok['success'] === true);
ac_assert('Enriquece family_key = finance', ($res_ok['data']['record']['family_key'] ?? '') === 'finance');
ac_assert('Enriquece variant_key = general', ($res_ok['data']['record']['variant_key'] ?? '') === 'general');
ac_assert('DTO conserva id solicitado', ($res_ok['data']['record']['id'] ?? null) === 501);
ac_assert('DTO conserva container_id', ($res_ok['data']['record']['container_id'] ?? null) === 1);
ac_assert('DTO incluye title actualizado', ($res_ok['data']['record']['title'] ?? '') === 'Registro Actualizado');
ac_assert('DTO incluye amount normalizado', ($res_ok['data']['record']['amount'] ?? '') === '-25.50');
ac_assert('DTO incluye created_at', ($res_ok['data']['record']['created_at'] ?? '') === '2026-08-29 12:00:00');
ac_assert('Ejecutó update de registro y touch del padre', count($wpdb->update_queries) === 2);

echo "\n=== 2. Idempotencia ===\n";

$wpdb->records[503] = [
    'id' => 503,
    'container_id' => 1,
    'title' => 'Sin cambios',
    'details' => null,
    'amount' => '0.00',
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->update_result = 0;
$wpdb->update_queries = [];
$res_idem = $use_case->execute([
    'container_id' => 1,
    'record_id' => 503,
    'title' => 'Sin cambios',
    'details' => null,
    'amount' => '0.00',
]);
ac_assert('Idempotencia devuelve success: true', $res_idem['success'] === true);
ac_assert('Idempotencia conserva título autoritativo', ($res_idem['data']['record']['title'] ?? '') === 'Sin cambios');

echo "\n=== 3. Validaciones sin escritura ===\n";

$wpdb->update_queries = [];

$err_missing_title = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'details' => null, 'amount' => null]);
ac_assert('title ausente devuelve missing_title', !$err_missing_title['success'] && $err_missing_title['error']['code'] === 'missing_title');

$err_missing_details = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'amount' => null]);
ac_assert('clave details ausente devuelve missing_details', !$err_missing_details['success'] && $err_missing_details['error']['code'] === 'missing_details');

$err_missing_amount = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null]);
ac_assert('clave amount ausente devuelve missing_amount', !$err_missing_amount['success'] && $err_missing_amount['error']['code'] === 'missing_amount');

$err_invalid_record = $use_case->execute(['container_id' => 1, 'record_id' => 'abc', 'title' => 'T', 'details' => null, 'amount' => null]);
ac_assert('record_id inválido devuelve invalid_record_id', !$err_invalid_record['success'] && $err_invalid_record['error']['code'] === 'invalid_record_id');

$err_invalid_title = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 123, 'details' => null, 'amount' => null]);
ac_assert('title no string devuelve invalid_title', !$err_invalid_title['success'] && $err_invalid_title['error']['code'] === 'invalid_title');

$err_long_title = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => str_repeat('a', 201), 'details' => null, 'amount' => null]);
ac_assert('title demasiado largo devuelve title_too_long', !$err_long_title['success'] && $err_long_title['error']['code'] === 'title_too_long');

$err_invalid_details = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => 99, 'amount' => null]);
ac_assert('details no string devuelve invalid_details', !$err_invalid_details['success'] && $err_invalid_details['error']['code'] === 'invalid_details');

$err_long_details = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => str_repeat('b', 65001), 'amount' => null]);
ac_assert('details demasiado largo devuelve details_too_long', !$err_long_details['success'] && $err_long_details['error']['code'] === 'details_too_long');

$err_bad_container = $use_case->execute(['container_id' => 'abc', 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => null]);
ac_assert('container_id inválido devuelve invalid_container_id', !$err_bad_container['success'] && $err_bad_container['error']['code'] === 'invalid_container_id');

ac_assert('Validaciones fallidas no ejecutaron update()', count($wpdb->update_queries) === 0);

$err_empty_amount = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => '   ']);
ac_assert('amount vacío normaliza a null en validación previa', $err_empty_amount['success'] === true && $err_empty_amount['data']['record']['amount'] === null);

$err_null_amount = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => null]);
ac_assert('amount null interno persiste null', $err_null_amount['success'] === true && $err_null_amount['data']['record']['amount'] === null);

$err_zero = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => '0']);
ac_assert('amount "0" normaliza a "0.00"', $err_zero['success'] === true && $err_zero['data']['record']['amount'] === '0.00');

$err_negative = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => '-0045.8']);
ac_assert('amount negativo normaliza a "-45.80"', $err_negative['success'] === true && $err_negative['data']['record']['amount'] === '-45.80');

$err_dec = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => '1.999']);
ac_assert('amount con 3 decimales devuelve amount_too_many_decimals', !$err_dec['success'] && $err_dec['error']['code'] === 'amount_too_many_decimals');

$err_range = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => '100000000000000000.00']);
ac_assert('amount fuera de rango devuelve amount_out_of_range', !$err_range['success'] && $err_range['error']['code'] === 'amount_out_of_range');

$err_sci = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => '1e2']);
ac_assert('amount notación científica devuelve invalid_amount', !$err_sci['success'] && $err_sci['error']['code'] === 'invalid_amount');

$err_nan = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => 'NaN']);
ac_assert('amount NaN devuelve invalid_amount', !$err_nan['success'] && $err_nan['error']['code'] === 'invalid_amount');

$err_inf = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => 'Infinity']);
ac_assert('amount Infinity devuelve invalid_amount', !$err_inf['success'] && $err_inf['error']['code'] === 'invalid_amount');

$err_type = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => 10.5]);
ac_assert('amount float devuelve invalid_amount', !$err_type['success'] && $err_type['error']['code'] === 'invalid_amount');

echo "\n=== 4. No encontrado y anti-leak ===\n";

$wpdb->update_queries = [];
$res_not_found = $use_case->execute(['container_id' => 999, 'record_id' => 501, 'title' => 'T', 'details' => null, 'amount' => null]);
ac_assert('Contenedor inexistente devuelve container_not_found', !$res_not_found['success'] && $res_not_found['error']['code'] === 'container_not_found');
ac_assert('Contenedor inexistente no ejecutó update()', count($wpdb->update_queries) === 0);

$wpdb->update_queries = [];
$res_wrong_variant = $use_case->execute(['container_id' => 2, 'record_id' => 502, 'variant_key' => 'general', 'title' => 'T', 'details' => null, 'amount' => null]);
ac_assert('Contenedor de otra variante devuelve container_not_found', !$res_wrong_variant['success'] && $res_wrong_variant['error']['code'] === 'container_not_found');

$wpdb->update_queries = [];
$res_wrong_container = $use_case->execute(['container_id' => 1, 'record_id' => 502, 'title' => 'T', 'details' => null, 'amount' => null]);
ac_assert('Registro en otro contenedor devuelve record_not_found', !$res_wrong_container['success'] && $res_wrong_container['error']['code'] === 'record_not_found');
ac_assert('Registro ajeno no ejecutó update()', count($wpdb->update_queries) === 0);

$wpdb->update_queries = [];
$res_missing_record = $use_case->execute(['container_id' => 1, 'record_id' => 9999, 'title' => 'T', 'details' => null, 'amount' => null]);
ac_assert('Registro inexistente devuelve record_not_found', !$res_missing_record['success'] && $res_missing_record['error']['code'] === 'record_not_found');

$unknown_registry = new AA_Canonical_Registry();
$unknown_registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$unknown_registry->freeze();
$unknown_uc = new UpdateFinanceRecordUseCase($unknown_registry);
$err_unknown = $unknown_uc->execute(['container_id' => 1, 'record_id' => 501, 'variant_key' => 'missing', 'title' => 'T', 'details' => null, 'amount' => null]);
ac_assert('variante desconocida devuelve unknown_variant', !$err_unknown['success'] && $err_unknown['error']['code'] === 'unknown_variant');

echo "\n=== 5. Carrera, persistencia y fila discordante ===\n";

$wpdb->records[504] = [
    'id' => 504,
    'container_id' => 1,
    'title' => 'Concurrente',
    'details' => null,
    'amount' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->update_queries = [];
unset($wpdb->records[504]);
$res_race = $use_case->execute(['container_id' => 1, 'record_id' => 504, 'title' => 'Concurrente', 'details' => null, 'amount' => null]);
ac_assert('Registro eliminado antes de update devuelve record_not_found', !$res_race['success'] && $res_race['error']['code'] === 'record_not_found');
ac_assert('Prelectura fallida no ejecuta update', count($wpdb->update_queries) === 0);

$wpdb->records[505] = [
    'id' => 505,
    'container_id' => 1,
    'title' => 'Borrado tras update',
    'details' => null,
    'amount' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->update_result = 1;
$wpdb->delete_record_after_update = 505;
$wpdb->update_queries = [];
$res_race_one = $use_case->execute(['container_id' => 1, 'record_id' => 505, 'title' => 'Borrado tras update', 'details' => null, 'amount' => null]);
ac_assert('Carrera post-update (update 1 → null) devuelve record_not_found', !$res_race_one['success'] && $res_race_one['error']['code'] === 'record_not_found');
$wpdb->delete_record_after_update = null;

$wpdb->records[506] = [
    'id' => 506,
    'container_id' => 1,
    'title' => 'Fallo DB',
    'details' => null,
    'amount' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->last_error = 'Deadlock';
$res_db_err = $use_case->execute(['container_id' => 1, 'record_id' => 506, 'title' => 'Fallo DB', 'details' => null, 'amount' => null]);
ac_assert('Error SQL en update se traduce a persistence_failed', !$res_db_err['success'] && $res_db_err['error']['code'] === 'persistence_failed');
$wpdb->last_error = '';

$wpdb->records[507] = [
    'id' => 507,
    'container_id' => 1,
    'title' => 'Identidad',
    'details' => null,
    'amount' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->update_result = 1;
$wpdb->corrupt_record_id_after_update = 507;
$res_identity = $use_case->execute(['container_id' => 1, 'record_id' => 507, 'title' => 'Identidad', 'details' => null, 'amount' => null]);
ac_assert('Fila retornada con id discordante devuelve persistence_failed', !$res_identity['success'] && $res_identity['error']['code'] === 'persistence_failed');
$wpdb->corrupt_record_id_after_update = null;

$wpdb->records[509] = [
    'id' => 509,
    'container_id' => 1,
    'title' => 'Estructura',
    'details' => null,
    'amount' => null,
    'created_at' => '',
];
$wpdb->update_result = 1;
$wpdb->rows = [[
    'id' => 509,
    'container_id' => 1,
    'title' => 'Estructura',
    'details' => null,
    'amount' => null,
    'created_at' => '',
]];
$res_structure = $use_case->execute(['container_id' => 1, 'record_id' => 509, 'title' => 'Estructura', 'details' => null, 'amount' => null]);
ac_assert('Fila con created_at vacío devuelve persistence_failed', !$res_structure['success'] && $res_structure['error']['code'] === 'persistence_failed');

$res_clear_details = $use_case->execute(['container_id' => 1, 'record_id' => 501, 'title' => 'Registro Actualizado', 'details' => '   ', 'amount' => null]);
ac_assert('details whitespace devuelve success con details null', $res_clear_details['success'] === true && $res_clear_details['data']['record']['details'] === null);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
