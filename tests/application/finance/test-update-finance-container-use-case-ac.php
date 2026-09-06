<?php
/**
 * AC Test — UpdateFinanceContainerUseCase (Ciclo 3E1A).
 *
 * Ejecutar:
 *   php tests/application/finance/test-update-finance-container-use-case-ac.php
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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/UpdateFinanceContainerUseCase.php';

class TestUpdateContainerWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public $rows = [];
    public $update_queries = [];
    public $update_result = 1;

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

    public function update($table, array $data, array $where, $format = null, $where_format = null) {
        $this->update_queries[] = ['table' => $table, 'data' => $data, 'where' => $where];
        if ($this->last_error !== '') {
            return false;
        }
        return $this->update_result;
    }
}

global $wpdb;
$wpdb = new TestUpdateContainerWpdbMock();

$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$registry->freeze();

$use_case = new UpdateFinanceContainerUseCase($registry);

echo "=== 1. Actualización exitosa con cambio efectivo ===\n";

$wpdb->rows[] = [
    'id' => '15',
    'variant_key' => 'general',
    'title' => 'Título anterior',
    'details' => 'Detalle anterior',
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->rows[] = [
    'id' => '15',
    'variant_key' => 'general',
    'title' => 'Título nuevo',
    'details' => "Detalle\nmultilínea",
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->update_result = 1;
$wpdb->update_queries = [];

$res_ok = $use_case->execute([
    'id' => 15,
    'title' => 'Título nuevo',
    'details' => "Detalle\nmultilínea",
]);
ac_assert('Actualización exitosa devuelve success: true', $res_ok['success'] === true);
ac_assert('Enriquece family_key = finance', ($res_ok['data']['container']['family_key'] ?? '') === 'finance');
ac_assert('Retorno autoritativo con título actualizado', ($res_ok['data']['container']['title'] ?? '') === 'Título nuevo');
ac_assert('Retorno autoritativo con details actualizado', ($res_ok['data']['container']['details'] ?? '') === "Detalle\nmultilínea");
ac_assert('Ejecutó update en base de datos', count($wpdb->update_queries) === 1);

echo "\n=== 2. Idempotencia ===\n";

$wpdb->rows[] = [
    'id' => '20',
    'variant_key' => 'general',
    'title' => 'Sin cambios',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->rows[] = [
    'id' => '20',
    'variant_key' => 'general',
    'title' => 'Sin cambios',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->update_result = 0;
$wpdb->update_queries = [];

$res_idem = $use_case->execute([
    'id' => 20,
    'title' => 'Sin cambios',
    'details' => null,
]);
ac_assert('Idempotencia devuelve success: true', $res_idem['success'] === true);
ac_assert('Idempotencia conserva título autoritativo', ($res_idem['data']['container']['title'] ?? '') === 'Sin cambios');

echo "\n=== 3. Validaciones sin escritura ===\n";

$wpdb->update_queries = [];

$err_no_id = $use_case->execute(['title' => 'T', 'details' => null]);
ac_assert('id ausente devuelve invalid_id', !$err_no_id['success'] && $err_no_id['error']['code'] === 'invalid_id');

$err_bad_var = $use_case->execute(['id' => 15, 'variant_key' => '!!!', 'title' => 'T', 'details' => null]);
ac_assert('variant_key inválida devuelve invalid_variant_key', !$err_bad_var['success'] && $err_bad_var['error']['code'] === 'invalid_variant_key');

$unfrozen = new AA_Canonical_Registry();
$unfrozen->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$unfrozen_uc = new UpdateFinanceContainerUseCase($unfrozen);
$err_unfrozen = $unfrozen_uc->execute(['id' => 1, 'title' => 'T', 'details' => null]);
ac_assert('registry no sellado devuelve canonical_unavailable', !$err_unfrozen['success'] && $err_unfrozen['error']['code'] === 'canonical_unavailable');

$err_missing_details = $use_case->execute(['id' => 15, 'title' => 'Título']);
ac_assert('clave details ausente devuelve missing_details', !$err_missing_details['success'] && $err_missing_details['error']['code'] === 'missing_details');

$err_missing_title = $use_case->execute(['id' => 15, 'details' => 'D']);
ac_assert('title ausente devuelve missing_title', !$err_missing_title['success'] && $err_missing_title['error']['code'] === 'missing_title');

$err_invalid_title = $use_case->execute(['id' => 15, 'title' => 123, 'details' => null]);
ac_assert('title no string devuelve invalid_title', !$err_invalid_title['success'] && $err_invalid_title['error']['code'] === 'invalid_title');

$err_long_title = $use_case->execute(['id' => 15, 'title' => str_repeat('a', 201), 'details' => null]);
ac_assert('title demasiado largo devuelve title_too_long', !$err_long_title['success'] && $err_long_title['error']['code'] === 'title_too_long');

$err_invalid_details = $use_case->execute(['id' => 15, 'title' => 'Título', 'details' => 99]);
ac_assert('details no string devuelve invalid_details', !$err_invalid_details['success'] && $err_invalid_details['error']['code'] === 'invalid_details');

$err_long_details = $use_case->execute(['id' => 15, 'title' => 'Título', 'details' => str_repeat('b', 65001)]);
ac_assert('details demasiado largo devuelve details_too_long', !$err_long_details['success'] && $err_long_details['error']['code'] === 'details_too_long');

ac_assert('Validaciones fallidas no ejecutaron update()', count($wpdb->update_queries) === 0);

$wpdb->rows[] = [
    'id' => '21',
    'variant_key' => 'general',
    'title' => 'Título',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->rows[] = [
    'id' => '21',
    'variant_key' => 'general',
    'title' => 'Título',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb->update_result = 1;
$res_clear_details = $use_case->execute(['id' => 21, 'title' => 'Título', 'details' => '   ']);
ac_assert('details whitespace devuelve success con details null', $res_clear_details['success'] === true && $res_clear_details['data']['container']['details'] === null);

echo "\n=== 4. No encontrado y anti-leak ===\n";

$wpdb->rows = [];
$wpdb->update_queries = [];
$res_not_found = $use_case->execute(['id' => 999, 'title' => 'T', 'details' => null]);
ac_assert('Contenedor inexistente devuelve not_found', !$res_not_found['success'] && $res_not_found['error']['code'] === 'not_found');
ac_assert('Inexistente no ejecutó update()', count($wpdb->update_queries) === 0);

$wpdb->rows[] = [
    'id' => '100',
    'variant_key' => 'special',
    'title' => 'Otra variante',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$wpdb->update_queries = [];
$res_anti_leak = $use_case->execute(['id' => 100, 'variant_key' => 'general', 'title' => 'T', 'details' => null]);
ac_assert('Variante discordante devuelve not_found (anti-leak)', !$res_anti_leak['success'] && $res_anti_leak['error']['code'] === 'not_found');
ac_assert('Variante discordante no ejecutó update()', count($wpdb->update_queries) === 0);

$unknown_registry = new AA_Canonical_Registry();
$unknown_registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$unknown_registry->freeze();
$unknown_uc = new UpdateFinanceContainerUseCase($unknown_registry);
$err_unknown = $unknown_uc->execute(['id' => 1, 'variant_key' => 'missing', 'title' => 'T', 'details' => null]);
ac_assert('variante desconocida devuelve unknown_variant', !$err_unknown['success'] && $err_unknown['error']['code'] === 'unknown_variant');

echo "\n=== 5. Carrera, persistencia y fila discordante ===\n";

$wpdb->rows[] = [
    'id' => '50',
    'variant_key' => 'general',
    'title' => 'Concurrente',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$wpdb->update_result = 0;
$wpdb->rows[] = null;
$res_race = $use_case->execute(['id' => 50, 'title' => 'Concurrente', 'details' => null]);
ac_assert('Carrera de borrado (update 0 → null) devuelve not_found', !$res_race['success'] && $res_race['error']['code'] === 'not_found');

$wpdb->rows = [];
$wpdb->rows[] = [
    'id' => '55',
    'variant_key' => 'general',
    'title' => 'Borrado tras update',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$wpdb->update_result = 1;
$res_race_one = $use_case->execute(['id' => 55, 'title' => 'Borrado tras update', 'details' => null]);
ac_assert('Carrera de borrado (update 1 → null) devuelve not_found', !$res_race_one['success'] && $res_race_one['error']['code'] === 'not_found');

$wpdb->rows[] = [
    'id' => '51',
    'variant_key' => 'general',
    'title' => 'Fallo DB',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$wpdb->last_error = 'Deadlock';
$res_db_err = $use_case->execute(['id' => 51, 'title' => 'Fallo DB', 'details' => null]);
ac_assert('Error SQL en update se traduce a persistence_failed', !$res_db_err['success'] && $res_db_err['error']['code'] === 'persistence_failed');
$wpdb->last_error = '';

$wpdb->rows = [];
$wpdb->rows[] = [
    'id' => '52',
    'variant_key' => 'general',
    'title' => 'Identidad',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '53',
    'variant_key' => 'general',
    'title' => 'Identidad',
    'details' => null,
    'created_at' => '2026-08-29 10:00:00',
];
$res_identity = $use_case->execute(['id' => 52, 'title' => 'Identidad', 'details' => null]);
ac_assert('Fila retornada con id discordante devuelve persistence_failed', !$res_identity['success'] && $res_identity['error']['code'] === 'persistence_failed');

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
