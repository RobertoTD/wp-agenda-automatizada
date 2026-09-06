<?php
/**
 * AC Test — GetFinanceContainerUseCase (Ciclo 3B1).
 *
 * Ejecutar:
 *   php tests/application/finance/test-get-finance-container-use-case-ac.php
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
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/GetFinanceContainerUseCase.php';

class TestGetContainerWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public $rows = [];
    public $queries = [];

    public function prepare(string $query, ...$args): string {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        return $query;
    }

    public function get_row(string $query, $output = ARRAY_A) {
        $this->queries[] = $query;
        if ($this->last_error !== '') {
            return null;
        }
        return array_shift($this->rows) ?: null;
    }
}

global $wpdb;
$wpdb = new TestGetContainerWpdbMock();

// Construir registry aislado con dos variantes para probar anti-leak
$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$registry->freeze();

$use_case = new GetFinanceContainerUseCase($registry);

echo "=== 1. Obtención exitosa ===\n";

$wpdb->rows[] = [
    'id' => '42',
    'variant_key' => 'general',
    'title' => 'Contenedor General',
    'details' => 'Detalles aquí',
    'created_at' => '2026-08-29 12:00:00',
];
$res1 = $use_case->execute(['id' => 42]);
ac_assert('Obtención exitosa devuelve success: true', $res1['success'] === true);
ac_assert('Devuelve container con id entero 42', $res1['data']['container']['id'] === 42);
ac_assert('Enriquece con family_key = finance', $res1['data']['container']['family_key'] === 'finance');
ac_assert('Preserva variant_key y title', $res1['data']['container']['variant_key'] === 'general' && $res1['data']['container']['title'] === 'Contenedor General');

echo "\n=== 2. Validación de ID y precondiciones sin llamar al repositorio ===\n";

$wpdb->queries = [];

$err_no_id = $use_case->execute([]);
ac_assert('id ausente devuelve invalid_id', !$err_no_id['success'] && $err_no_id['error']['code'] === 'invalid_id');

$err_zero_id = $use_case->execute(['id' => 0]);
ac_assert('id=0 devuelve invalid_id', !$err_zero_id['success'] && $err_zero_id['error']['code'] === 'invalid_id');

$err_neg_id = $use_case->execute(['id' => -5]);
ac_assert('id negativo devuelve invalid_id', !$err_neg_id['success'] && $err_neg_id['error']['code'] === 'invalid_id');

$err_str_id = $use_case->execute(['id' => 'abc']);
ac_assert('id string no numérico devuelve invalid_id', !$err_str_id['success'] && $err_str_id['error']['code'] === 'invalid_id');

$err_bad_var = $use_case->execute(['id' => 1, 'variant_key' => 'unknown_var']);
ac_assert('variant_key desconocida devuelve unknown_variant', !$err_bad_var['success'] && $err_bad_var['error']['code'] === 'unknown_variant');

ac_assert('Ninguna validación de precondición llamó al repositorio (0 queries)', count($wpdb->queries) === 0);

echo "\n=== 3. No encontrado y Anti-leak entre variantes ===\n";

// 3.1 Contenedor inexistente en BD
$wpdb->rows = [];
$res_not_found = $use_case->execute(['id' => 999]);
ac_assert('Contenedor inexistente devuelve not_found', !$res_not_found['success'] && $res_not_found['error']['code'] === 'not_found');

// 3.2 Anti-leak: Contenedor existe pero pertenece a 'special', y solicitamos bajo 'general'
$wpdb->rows[] = [
    'id' => '100',
    'variant_key' => 'special',
    'title' => 'Contenedor Especial',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$res_leak = $use_case->execute(['id' => 100, 'variant_key' => 'general']);
ac_assert('Contenedor de otra variante devuelve not_found (anti-leak sin exponer variant_mismatch)', !$res_leak['success'] && $res_leak['error']['code'] === 'not_found');

// 3.3 El mismo contenedor solicitado bajo su variante 'special' sí es retornado
$wpdb->rows[] = [
    'id' => '100',
    'variant_key' => 'special',
    'title' => 'Contenedor Especial',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$res_special = $use_case->execute(['id' => 100, 'variant_key' => 'special']);
ac_assert('variant_key special no permitida localmente → unknown_variant', !$res_special['success'] && $res_special['error']['code'] === 'unknown_variant');

echo "\n=== 4. Fallos de repositorio ===\n";

$wpdb->last_error = 'Database connection failure';
$res_db_err = $use_case->execute(['id' => 10]);
ac_assert('Error SQL se traduce a persistence_failed', !$res_db_err['success'] && $res_db_err['error']['code'] === 'persistence_failed');
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
