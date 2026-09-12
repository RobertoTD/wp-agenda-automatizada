<?php
/**
 * AC Test — Enablement Nav available_families (enabled + authorized).
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-canonical-family-enablement-nav-ac.php
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

$user_logged_in = true;
$user_member_of_blog = true;
$current_caps = [];
$is_multisite_env = false;

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        global $user_logged_in;
        return (bool) $user_logged_in;
    }
}
if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        global $is_multisite_env;
        return (bool) $is_multisite_env;
    }
}
if (!function_exists('is_user_member_of_blog')) {
    function is_user_member_of_blog(): bool {
        global $user_member_of_blog;
        return (bool) $user_member_of_blog;
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can(string $cap): bool {
        global $current_caps;
        return !empty($current_caps[$cap]);
    }
}
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args): string {
        if (count($args) === 2 && is_array($args[0])) {
            $query = http_build_query($args[0]);
            $url = $args[1];
        } elseif (count($args) === 3) {
            $query = http_build_query([$args[0] => $args[1]]);
            $url = $args[2];
        } else {
            return '';
        }
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . $query;
    }
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementStatus.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalFamilyEnablementSnapshot.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-nav.php';

$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas', 'currency'));
$registry->register_family(new AA_Canonical_Family_Definition('archive', 'Archivo', 'folder'));
$registry->freeze();

$snapshot = new CanonicalFamilyEnablementSnapshot([
    'finance' => new CanonicalFamilyEnablementStatus('finance', true, true),
    'archive' => new CanonicalFamilyEnablementStatus('archive', true, true),
]);

$nav_src = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-nav.php'
);
ac_assert(
    'Nav expone available_families y build lo consume',
    strpos($nav_src, 'function available_families') !== false
    && strpos($nav_src, 'self::available_families') !== false
    && strpos($nav_src, 'AA_Canonical_Access_Policy::check_family_access') !== false
);

$enabled = AA_Canonical_Family_Enablement_Nav::enabled_families($registry, $snapshot);
ac_assert('enabled_families incluye ambas habilitadas', count($enabled) === 2);

$current_caps = [];
$available_no_admin = AA_Canonical_Family_Enablement_Nav::available_families($registry, $snapshot);
$available_no_admin_keys = array_map(static function ($f) {
    return $f->key();
}, $available_no_admin);
ac_assert(
    'Sin manage_options: archive enabled queda fuera; finance autorizado',
    $available_no_admin_keys === ['finance']
);

$build_no_admin = AA_Canonical_Family_Enablement_Nav::build($registry, $snapshot);
ac_assert(
    'build sin manage_options solo finance',
    count($build_no_admin) === 1 && ($build_no_admin[0]['family_key'] ?? '') === 'finance'
);
ac_assert(
    'build expone icon_key de la familia',
    ($build_no_admin[0]['icon_key'] ?? null) === 'currency'
);

$current_caps = ['manage_options' => true];
$available_admin = AA_Canonical_Family_Enablement_Nav::available_families($registry, $snapshot);
$available_admin_keys = array_map(static function ($f) {
    return $f->key();
}, $available_admin);
sort($available_admin_keys);
ac_assert(
    'Con manage_options: finance y archive disponibles',
    $available_admin_keys === ['archive', 'finance']
);

$snapshot_one = new CanonicalFamilyEnablementSnapshot([
    'finance' => new CanonicalFamilyEnablementStatus('finance', true, false),
    'archive' => new CanonicalFamilyEnablementStatus('archive', true, true),
]);
$current_caps = ['manage_options' => true];
$only_archive = AA_Canonical_Family_Enablement_Nav::available_families($registry, $snapshot_one);
ac_assert(
    'Solo archive enabled+autorizada cuenta como N=1',
    count($only_archive) === 1 && $only_archive[0]->key() === 'archive'
);

$empty_snap = new CanonicalFamilyEnablementSnapshot([
    'finance' => new CanonicalFamilyEnablementStatus('finance', true, false),
    'archive' => new CanonicalFamilyEnablementStatus('archive', true, false),
]);
ac_assert(
    'Ninguna enabled → available vacío',
    AA_Canonical_Family_Enablement_Nav::available_families($registry, $empty_snap) === []
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
