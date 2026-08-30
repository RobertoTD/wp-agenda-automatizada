<?php
/**
 * AC Test — DeleteFinanceRecordUseCase (Ciclo 3B2a).
 *
 * Ejecutar:
 *   php tests/application/finance/test-delete-finance-record-use-case-ac.php
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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/repositories/FinanceRecordRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/DeleteFinanceRecordUseCase.php';

// Mock de $wpdb
class TestDeleteRecordWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public $delete_queries = 0;
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
        '10_1' => true,
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
        return null;
    }

    public function delete(string $table, array $where, array $where_format = []) {
        $this->delete_queries++;
        if ($this->last_error !== '') {
            return false;
        }
        $id = (int) ($where['id'] ?? 0);
        $cid = (int) ($where['container_id'] ?? 0);
        $key = $id . '_' . $cid;
        if (isset($this->records[$key])) {
            unset($this->records[$key]);
            return 1;
        }
        return 0;
    }
}

global $wpdb;
$wpdb = new TestDeleteRecordWpdbMock();

// Construir registry de dominio aislado
$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas', 'general'));
$registry->register_variant(new AA_Canonical_Variant_Definition('finance', 'general', 'General'));
$registry->freeze();

$use_case = new DeleteFinanceRecordUseCase($registry);

echo "=== 1. Eliminación contextual exitosa ===\n";

$res1 = $use_case->execute([
    'container_id' => 1,
    'record_id' => 10,
]);
ac_assert('Eliminación exitosa devuelve success: true', $res1['success'] === true);
ac_assert('Devuelve deleted: true', $res1['data']['deleted'] === true);
ac_assert('Devuelve id = 10 y container_id = 1', $res1['data']['id'] === 10 && $res1['data']['container_id'] === 1);
ac_assert('Ejecutó 1 query delete', $wpdb->delete_queries === 1);

echo "\n=== 2. Segunda eliminación / Registro inexistente (record_not_found) ===\n";

$res_again = $use_case->execute([
    'container_id' => 1,
    'record_id' => 10,
]);
ac_assert('Segunda eliminación devuelve record_not_found', !$res_again['success'] && $res_again['error']['code'] === 'record_not_found');

echo "\n=== 3. Comprobación contextual de contenedor padre (anti-leak) ===\n";

$wpdb->delete_queries = 0;

// 3.1 Contenedor inexistente
$err_no_c = $use_case->execute(['container_id' => 999, 'record_id' => 10]);
ac_assert('Contenedor inexistente devuelve container_not_found', !$err_no_c['success'] && $err_no_c['error']['code'] === 'container_not_found');

// 3.2 Contenedor de otra variante
$err_wrong_var = $use_case->execute(['container_id' => 2, 'record_id' => 10]);
ac_assert('Contenedor de otra variante devuelve container_not_found', !$err_wrong_var['success'] && $err_wrong_var['error']['code'] === 'container_not_found');

// 3.3 Container ID inválido
$err_bad_cid = $use_case->execute(['container_id' => -1, 'record_id' => 10]);
ac_assert('container_id inválido devuelve invalid_container_id', !$err_bad_cid['success'] && $err_bad_cid['error']['code'] === 'invalid_container_id');

ac_assert('Fallos de contenedor no intentaron delete en registros', $wpdb->delete_queries === 0);

echo "\n=== 4. Validación de record_id ===\n";

$wpdb->delete_queries = 0;
$err_bad_rid = $use_case->execute(['container_id' => 1, 'record_id' => 'abc']);
ac_assert('record_id inválido devuelve invalid_record_id', !$err_bad_rid['success'] && $err_bad_rid['error']['code'] === 'invalid_record_id');
ac_assert('record_id inválido no ejecutó delete en BD', $wpdb->delete_queries === 0);

echo "\n=== 5. Fallos de persistencia SQL ===\n";

$wpdb->last_error = 'Error de conexión';
$err_db = $use_case->execute(['container_id' => 1, 'record_id' => 10]);
ac_assert('Fallo SQL en delete se traduce a persistence_failed', !$err_db['success'] && $err_db['error']['code'] === 'persistence_failed');
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
