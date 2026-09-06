<?php
/**
 * AC Test — GetFinanceRecordUseCase (Ciclo 3B2a).
 *
 * Ejecutar:
 *   php tests/application/finance/test-get-finance-record-use-case-ac.php
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

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/repositories/FinanceRecordRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/GetFinanceRecordUseCase.php';

// Mock de $wpdb
class TestGetRecordWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public $record_queries = 0;
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
        '10_1' => [
            'id' => 10,
            'container_id' => 1,
            'title' => 'Registro Diez',
            'details' => 'Detalle del diez',
            'amount' => '100.50',
            'created_at' => '2026-08-29 12:00:00',
        ],
    ];

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
            $this->record_queries++;
            $key = $m[1] . '_' . $m[2];
            return isset($this->records[$key]) ? $this->records[$key] : null;
        }
        return null;
    }
}

global $wpdb;
$wpdb = new TestGetRecordWpdbMock();

// Construir registry de dominio aislado
$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$registry->freeze();

$use_case = new GetFinanceRecordUseCase($registry);

echo "=== 1. Obtención contextual exitosa ===\n";

$res1 = $use_case->execute([
    'container_id' => 1,
    'record_id' => 10,
]);
ac_assert('Obtención exitosa devuelve success: true', $res1['success'] === true);
ac_assert('Devuelve record con id 10', $res1['data']['record']['id'] === 10);
ac_assert('Enriquece con family_key = finance', $res1['data']['record']['family_key'] === 'finance');
ac_assert('Enriquece con variant_key = general', $res1['data']['record']['variant_key'] === 'general');
ac_assert('Preserva container_id = 1', $res1['data']['record']['container_id'] === 1);
ac_assert('Amount es "100.50"', $res1['data']['record']['amount'] === '100.50');
ac_assert('Consultó repositorio de registros exactamente 1 vez', $wpdb->record_queries === 1);

echo "\n=== 2. Comprobación contextual de contenedor padre (anti-leak) ===\n";

$wpdb->record_queries = 0;

// 2.1 Contenedor inexistente
$err_no_c = $use_case->execute(['container_id' => 999, 'record_id' => 10]);
ac_assert('Contenedor inexistente devuelve container_not_found', !$err_no_c['success'] && $err_no_c['error']['code'] === 'container_not_found');

// 2.2 Contenedor de otra variante
$err_wrong_var = $use_case->execute(['container_id' => 2, 'record_id' => 10]);
ac_assert('Contenedor de otra variante devuelve container_not_found', !$err_wrong_var['success'] && $err_wrong_var['error']['code'] === 'container_not_found');

// 2.3 Container ID inválido
$err_bad_cid = $use_case->execute(['container_id' => 0, 'record_id' => 10]);
ac_assert('container_id inválido devuelve invalid_container_id', !$err_bad_cid['success'] && $err_bad_cid['error']['code'] === 'invalid_container_id');

ac_assert('Fallo de contenedor no ejecutó ninguna consulta a registros', $wpdb->record_queries === 0);

echo "\n=== 3. Validación de record_id y record_not_found ===\n";

$wpdb->record_queries = 0;

// 3.1 Record ID inválido
$err_bad_rid = $use_case->execute(['container_id' => 1, 'record_id' => 'no_int']);
ac_assert('record_id inválido devuelve invalid_record_id', !$err_bad_rid['success'] && $err_bad_rid['error']['code'] === 'invalid_record_id');
ac_assert('record_id inválido no consultó la tabla de registros', $wpdb->record_queries === 0);

// 3.2 Registro inexistente o ajeno al contenedor
$err_no_rec = $use_case->execute(['container_id' => 1, 'record_id' => 99]);
ac_assert('Registro inexistente devuelve record_not_found', !$err_no_rec['success'] && $err_no_rec['error']['code'] === 'record_not_found');
ac_assert('Registro inexistente ejecutó 1 consulta a la tabla de registros', $wpdb->record_queries === 1);

echo "\n=== 4. Fallos de persistencia SQL ===\n";

$wpdb->last_error = 'Error de conexión';
$err_db = $use_case->execute(['container_id' => 1, 'record_id' => 10]);
ac_assert('Fallo SQL en find se traduce a persistence_failed', !$err_db['success'] && $err_db['error']['code'] === 'persistence_failed');
$wpdb->last_error = '';

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
