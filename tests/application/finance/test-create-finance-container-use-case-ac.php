<?php
/**
 * AC Test — CreateFinanceContainerUseCase (Ciclo 3B1).
 *
 * Ejecutar:
 *   php tests/application/finance/test-create-finance-container-use-case-ac.php
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
        return '2026-08-29 18:00:00';
    }
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/CreateFinanceContainerUseCase.php';

// Mock de $wpdb para interceptar llamadas de FinanceContainerRepository
class TestCreateContainerWpdbMock {
    public $prefix = 'wp_';
    public $insert_id = 10;
    public $last_error = '';
    public $inserts = [];

    public function insert(string $table, array $data, array $format = []) {
        $this->inserts[] = ['table' => $table, 'data' => $data, 'format' => $format];
        if ($this->last_error !== '') {
            return false;
        }
        return 1;
    }
}

global $wpdb;
$wpdb = new TestCreateContainerWpdbMock();

// Construir registry de dominio aislado
$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas', 'general'));
$registry->register_variant(new AA_Canonical_Variant_Definition('finance', 'general', 'General'));
$registry->freeze();

$use_case = new CreateFinanceContainerUseCase($registry);

echo "=== 1. Creación exitosa (explícita y predeterminada) ===\n";

// 1.1 Con variante predeterminada omitida
$res1 = $use_case->execute([
    'title' => 'Presupuesto Septiembre',
    'details' => "Detalle de gastos\nLínea 2",
]);
ac_assert('Creación exitosa devuelve success: true', $res1['success'] === true);
ac_assert('Devuelve container con id poblado', $res1['data']['container']['id'] === 10);
ac_assert('Enriquece con family_key = finance', $res1['data']['container']['family_key'] === 'finance');
ac_assert('Resuelve variant_key = general predeterminado', $res1['data']['container']['variant_key'] === 'general');
ac_assert('Preserva title y details multilínea', $res1['data']['container']['title'] === 'Presupuesto Septiembre' && $res1['data']['container']['details'] === "Detalle de gastos\nLínea 2");
ac_assert('Llamó a insert en BD', count($wpdb->inserts) === 1);

// 1.2 Con variante explícita
$wpdb->inserts = [];
$res2 = $use_case->execute([
    'variant_key' => 'general',
    'title' => '  Caja Chica 💰  ',
    'details' => '   ',
]);
ac_assert('Creación con variante explícita exitosa', $res2['success'] === true);
ac_assert('Title con emoji y trim', $res2['data']['container']['title'] === 'Caja Chica 💰');
ac_assert('Details de espacios se normaliza a null', $res2['data']['container']['details'] === null);

echo "\n=== 2. Rechazo de precondiciones sin llamar al repositorio ===\n";

$wpdb->inserts = [];

// 2.1 Error de título: missing_title
$err_missing_t = $use_case->execute(['title' => '   ']);
ac_assert('title vacío devuelve missing_title', !$err_missing_t['success'] && $err_missing_t['error']['code'] === 'missing_title');

// 2.2 Error de título: invalid_title (no string)
$err_invalid_t = $use_case->execute(['title' => ['Array']]);
ac_assert('title no string devuelve invalid_title', !$err_invalid_t['success'] && $err_invalid_t['error']['code'] === 'invalid_title');

// 2.3 Error de título: invalid_title (UTF-8 corrupto)
$err_bad_utf8_t = $use_case->execute(['title' => "Inválido \xC3\x28"]);
ac_assert('title con UTF-8 corrupto devuelve invalid_title', !$err_bad_utf8_t['success'] && $err_bad_utf8_t['error']['code'] === 'invalid_title');

// 2.4 Error de título: title_too_long
$long_title = str_repeat('a', 201);
$err_long_t = $use_case->execute(['title' => $long_title]);
ac_assert('title de 201 caracteres devuelve title_too_long', !$err_long_t['success'] && $err_long_t['error']['code'] === 'title_too_long');

// 2.5 Error de details: invalid_details (no string)
$err_invalid_d = $use_case->execute(['title' => 'Válido', 'details' => 12345]);
ac_assert('details no string devuelve invalid_details', !$err_invalid_d['success'] && $err_invalid_d['error']['code'] === 'invalid_details');

// 2.6 Error de details: details_too_long
$err_long_d = $use_case->execute(['title' => 'Válido', 'details' => str_repeat('B', 65001)]);
ac_assert('details de 65,001 bytes devuelve details_too_long', !$err_long_d['success'] && $err_long_d['error']['code'] === 'details_too_long');

// 2.7 Error de variante: invalid_variant_key
$err_invalid_var = $use_case->execute(['variant_key' => 'finance.general', 'title' => 'Válido']);
ac_assert('variant_key con formato inválido devuelve invalid_variant_key', !$err_invalid_var['success'] && $err_invalid_var['error']['code'] === 'invalid_variant_key');

// 2.8 Error de variante: unknown_variant
$err_unknown_var = $use_case->execute(['variant_key' => 'unregistered_var', 'title' => 'Válido']);
ac_assert('variant_key desconocida devuelve unknown_variant', !$err_unknown_var['success'] && $err_unknown_var['error']['code'] === 'unknown_variant');

ac_assert('Ninguna validación fallida llamó al repositorio (0 inserts)', count($wpdb->inserts) === 0);

echo "\n=== 3. Registry no sellado y fallo de persistencia ===\n";

// 3.1 Registry no sellado
$unfrozen_reg = new AA_Canonical_Registry();
$uc_unfrozen = new CreateFinanceContainerUseCase($unfrozen_reg);
$err_reg = $uc_unfrozen->execute(['title' => 'Válido']);
ac_assert('Registry no sellado devuelve canonical_unavailable', !$err_reg['success'] && $err_reg['error']['code'] === 'canonical_unavailable');
ac_assert('No llamó al repositorio ante registry no sellado', count($wpdb->inserts) === 0);

// 3.2 Error SQL en repository se traduce a persistence_failed
$wpdb->last_error = 'Disk full / connection lost';
$err_db = $use_case->execute(['title' => 'Presupuesto Fallido']);
ac_assert('Fallo SQL se traduce a persistence_failed', !$err_db['success'] && $err_db['error']['code'] === 'persistence_failed');
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
