<?php
/**
 * AC LEGACY-X bloque 3 — regresión canónica: familia finance, Access Policy, amount.
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-legacy-x-canonical-regression-ac.php
 */

$plugin_root = dirname(__DIR__, 4);

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

echo "=== Independencia estática ===\n";
$norm = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Amount_Normalizer.php'
);
$access = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php'
);
$boot = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php'
);
$core = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php'
);

ac_assert('Amount Normalizer existe', is_readable(
    $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Amount_Normalizer.php'
));
ac_assert('Normalizer sin FinanceUseCaseSupport', strpos($norm, 'FinanceUseCaseSupport') === false);
ac_assert('Normalizer sin aa_finance_', stripos($norm, 'aa_finance_') === false);
ac_assert('Access Policy conserva privilegio finance', strpos($access, "family_key !== 'finance'") !== false
    || strpos($access, "!== 'finance'") !== false);
ac_assert('Catálogo amount ready', preg_match("/SCOPE_RECORD\s*,\s*true\s*\)/", $boot) === 1);
ac_assert('Core bootstrap declara familia finance', strpos($core, "'finance'") !== false || strpos($core, '"finance"') !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL harness ausente.\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Amount_Normalizer.php';

echo "\n=== Runtime canónico ===\n";
AA_Canonical_Core_Bootstrap::bootstrap();
AA_Canonical_Capability_Registry_Bootstrap::reset_for_tests();
$caps = AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
$def = $caps->get('amount');
ac_assert('amount ready en runtime', $def->is_ready() === true);

$registry = AA_Canonical_Core_Bootstrap::instance();
try {
    $family = $registry->family('finance');
    ac_assert('Familia finance en registry', $family->key() === 'finance');
} catch (\Throwable $e) {
    ac_assert('Familia finance en registry', false, $e->getMessage());
}

$zero = AA_Canonical_Amount_Normalizer::normalize('0');
ac_assert('Normalizer cero válido', !empty($zero['ok']) && $zero['value'] === '0.00');

$admin_id = 0;
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (is_array($admins) && isset($admins[0])) {
    $admin_id = (int) $admins[0];
}
if ($admin_id < 1) {
    ac_assert('Admin de harness disponible', false);
} else {
    $prev = get_current_user_id();
    wp_set_current_user($admin_id);
    $access_ok = AA_Canonical_Access_Policy::check_family_access('finance');
    ac_assert('Access Policy finance autoriza admin', !empty($access_ok['authorized']));
    wp_set_current_user(0);
    $denied = AA_Canonical_Access_Policy::check_family_access('finance');
    ac_assert('Access Policy finance exige autenticación', empty($denied['authorized']));
    wp_set_current_user($prev > 0 ? $prev : 0);
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
