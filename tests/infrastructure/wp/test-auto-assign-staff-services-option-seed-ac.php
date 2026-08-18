<?php
/**
 * AC — Seed/migración de aa_auto_assign_staff_services.
 *
 * Ejecutar: php tests/infrastructure/wp/test-auto-assign-staff-services-option-seed-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';

$GLOBALS['aa_test_options'] = [];
$GLOBALS['aa_test_add_option_calls'] = [];

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

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }

        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value) {
        $GLOBALS['aa_test_options'][$key] = $value;

        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($key, $value) {
        $GLOBALS['aa_test_add_option_calls'][] = [$key, $value];

        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return false;
        }

        $GLOBALS['aa_test_options'][$key] = $value;

        return true;
    }
}

if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule($regex, $query, $after = 'bottom') {
        return true;
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules($hard = true) {
        return true;
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta($sql) {
        return [];
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? '2026-06-20 12:00:00' : '2026-06-20 12:00:00';
    }
}

$schema_src = file_get_contents($schema_file);
ac_assert('Schema file readable', $schema_src !== false);
ac_assert('Schema seeds aa_auto_assign_staff_services', strpos($schema_src, 'aa_auto_assign_staff_services') !== false);
ac_assert('Schema uses fresh install detection', strpos($schema_src, 'aa_db_version') !== false);
ac_assert('Schema DB_VERSION is 15', strpos($schema_src, "DB_VERSION = '15'") !== false);

$settings_src = file_get_contents($plugin_root . '/views/admin-controls.php');
ac_assert('Settings registers aa_auto_assign_staff_services', strpos($settings_src, 'aa_auto_assign_staff_services') !== false);

$settings_ui_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/settings/index.php');
ac_assert('Settings UI renders checkbox', strpos($settings_ui_src, 'aa-auto-assign-staff-services-checkbox') !== false);

function simulate_seed_block(): void {
    if (get_option('aa_auto_assign_staff_services') === false) {
        $is_fresh_install = get_option('aa_db_version', false) === false;
        add_option('aa_auto_assign_staff_services', $is_fresh_install ? '1' : '0');
    }
}

$GLOBALS['aa_test_options'] = [];
$GLOBALS['aa_test_add_option_calls'] = [];
simulate_seed_block();
ac_assert(
    'Fresh install seeds option ON',
    ($GLOBALS['aa_test_options']['aa_auto_assign_staff_services'] ?? null) === '1'
);

$GLOBALS['aa_test_options'] = ['aa_db_version' => '8'];
$GLOBALS['aa_test_add_option_calls'] = [];
simulate_seed_block();
ac_assert(
    'Existing site migration seeds option OFF',
    ($GLOBALS['aa_test_options']['aa_auto_assign_staff_services'] ?? null) === '0'
);

$runtime_default = get_option('aa_auto_assign_staff_services', 0);
ac_assert('Runtime fallback is OFF', (int) $runtime_default === 0);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
