<?php
/**
 * AC Test — DeleteFinanceContainerUseCase (Ciclo 3B1).
 *
 * Ejecutar:
 *   php tests/application/finance/test-delete-finance-container-use-case-ac.php
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
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/DeleteFinanceContainerUseCase.php';

class TestDeleteContainerWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public $rows = [];
    public $delete_queries = [];
    public $deleted_rows = 1;

    public function prepare(string $query, ...$args): string {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        return $query;
    }

    public function get_row(string $query, $output = ARRAY_A) {
        if ($this->last_error !== '') {
            return null;
        }
        return array_shift($this->rows) ?: null;
    }

    public function delete(string $table, array $where, array $where_format = []) {
        $this->delete_queries[] = ['table' => $table, 'where' => $where];
        if ($this->last_error !== '') {
            return false;
        }
        return $this->deleted_rows;
    }
}

global $wpdb;
$wpdb = new TestDeleteContainerWpdbMock();

$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas', 'general'));
$registry->register_variant(new AA_Canonical_Variant_Definition('finance', 'general', 'General'));
$registry->register_variant(new AA_Canonical_Variant_Definition('finance', 'special', 'Special'));
$registry->freeze();

$use_case = new DeleteFinanceContainerUseCase($registry);

echo "=== 1. Eliminación exitosa ===\n";

$wpdb->rows[] = [
    'id' => '15',
    'variant_key' => 'general',
    'title' => 'Contenedor a borrar',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->deleted_rows = 1;
$wpdb->delete_queries = [];

$res1 = $use_case->execute(['id' => 15]);
ac_assert('Eliminación exitosa devuelve success: true', $res1['success'] === true);
ac_assert('Devuelve deleted: true e id: 15', $res1['data']['deleted'] === true && $res1['data']['id'] === 15);
ac_assert('Ejecutó delete en base de datos', count($wpdb->delete_queries) === 1);

echo "\n=== 2. Validación de ID y precondiciones sin llamar al repositorio ===\n";

$wpdb->delete_queries = [];

$err_no_id = $use_case->execute([]);
ac_assert('id ausente devuelve invalid_id', !$err_no_id['success'] && $err_no_id['error']['code'] === 'invalid_id');

$err_neg_id = $use_case->execute(['id' => -1]);
ac_assert('id negativo devuelve invalid_id', !$err_neg_id['success'] && $err_neg_id['error']['code'] === 'invalid_id');

$err_bad_var = $use_case->execute(['id' => 15, 'variant_key' => 'invalid_v_key!']);
ac_assert('variant_key inválida devuelve invalid_variant_key', !$err_bad_var['success'] && $err_bad_var['error']['code'] === 'invalid_variant_key');

ac_assert('Precondiciones inválidas no ejecutaron delete()', count($wpdb->delete_queries) === 0);

echo "\n=== 3. No encontrado y Anti-leak (sin ejecutar delete) ===\n";

// 3.1 Contenedor inexistente
$wpdb->rows = [];
$wpdb->delete_queries = [];
$res_not_found = $use_case->execute(['id' => 999]);
ac_assert('Contenedor inexistente devuelve not_found', !$res_not_found['success'] && $res_not_found['error']['code'] === 'not_found');
ac_assert('No ejecutó delete() ante contenedor inexistente', count($wpdb->delete_queries) === 0);

// 3.2 Contenedor pertenece a otra variante ('special') y se solicita borrar bajo 'general'
$wpdb->rows[] = [
    'id' => '100',
    'variant_key' => 'special',
    'title' => 'Contenedor de otra variante',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$wpdb->delete_queries = [];
$res_anti_leak = $use_case->execute(['id' => 100, 'variant_key' => 'general']);
ac_assert('Contenedor de otra variante devuelve not_found (anti-leak)', !$res_anti_leak['success'] && $res_anti_leak['error']['code'] === 'not_found');
ac_assert('NUNCA ejecuta delete() cuando la variante no coincide', count($wpdb->delete_queries) === 0);

echo "\n=== 4. Carrera concurrente y errores de persistencia ===\n";

// 4.1 Carrera: find_by_id tuvo éxito pero delete() afectó 0 filas (ya borrado concurrentemente)
$wpdb->rows[] = [
    'id' => '50',
    'variant_key' => 'general',
    'title' => 'Contenedor Concurrente',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$wpdb->deleted_rows = 0;
$res_race = $use_case->execute(['id' => 50]);
ac_assert('Carrera de eliminación (0 filas borradas) devuelve not_found', !$res_race['success'] && $res_race['error']['code'] === 'not_found');

// 4.2 Error SQL en delete()
$wpdb->rows[] = [
    'id' => '51',
    'variant_key' => 'general',
    'title' => 'Contenedor Fallo DB',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$wpdb->last_error = 'Deadlock / Query failure';
$res_db_err = $use_case->execute(['id' => 51]);
ac_assert('Error SQL en delete() se traduce a persistence_failed', !$res_db_err['success'] && $res_db_err['error']['code'] === 'persistence_failed');
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
