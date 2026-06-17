<?php
/**
 * AC MC1 — AA_Appointment_Actions_List_Seed_Lifecycle.
 *
 * Ejecutar: php tests/infrastructure/wp/test-appointment-actions-list-seed-lifecycle-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$root = dirname(__DIR__, 3);
$lifecycle_file = $root . '/includes/infrastructure/wp/AppointmentActionsListSeedLifecycle.php';

$GLOBALS['aa_test_options'] = [];
$GLOBALS['aa_test_transients'] = [];
$GLOBALS['aa_test_doing_ajax'] = false;
$GLOBALS['aa_test_doing_cron'] = false;
$GLOBALS['aa_test_sync_calls'] = 0;

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

if (!function_exists('delete_option')) {
    function delete_option($key) {
        unset($GLOBALS['aa_test_options'][$key]);

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        if (!array_key_exists($key, $GLOBALS['aa_test_transients'])) {
            return false;
        }

        $entry = $GLOBALS['aa_test_transients'][$key];

        if (($entry['expires_at'] ?? 0) < time()) {
            unset($GLOBALS['aa_test_transients'][$key]);

            return false;
        }

        return $entry['value'];
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration) {
        $GLOBALS['aa_test_transients'][$key] = [
            'value' => $value,
            'expires_at' => time() + (int) $expiration,
        ];

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['aa_test_transients'][$key]);

        return true;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool {
        return (bool) $GLOBALS['aa_test_doing_ajax'];
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return $type === 'mysql' ? '2026-06-17 12:00:00' : '2026-06-17 12:00:00';
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10) {
        $GLOBALS['aa_test_registered_actions'][$hook][] = [
            'callback' => $callback,
            'priority' => (int) $priority,
        ];
    }
}

require_once $root . '/includes/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once $root . '/includes/repositories/SeededTaskRepository.php';
require_once $root . '/includes/application/tasks/SyncAppointmentActionsListUseCase.php';
require_once $lifecycle_file;

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

function lifecycle_reset_test_state(): void {
    $GLOBALS['aa_test_options'] = [
        'aa_db_version' => '7',
    ];
    $GLOBALS['aa_test_transients'] = [];
    $GLOBALS['aa_test_doing_ajax'] = false;
    $GLOBALS['aa_test_doing_cron'] = false;
    $GLOBALS['aa_test_sync_calls'] = 0;
    AA_Appointment_Actions_List_Seed_Lifecycle::set_sync_executor_for_tests(null);
}

function lifecycle_set_success_override(): void {
    AA_Appointment_Actions_List_Seed_Lifecycle::set_sync_executor_for_tests(static function (): array {
        $GLOBALS['aa_test_sync_calls']++;

        return [
            'lists_created' => 1,
            'lists_updated' => 0,
            'list_id' => 0,
        ];
    });
}

$lifecycle_src = file_get_contents($lifecycle_file);
ac_assert('Lifecycle file readable', $lifecycle_src !== false);
ac_assert('Lifecycle defines AA_Appointment_Actions_List_Seed_Lifecycle', strpos($lifecycle_src, 'class AA_Appointment_Actions_List_Seed_Lifecycle') !== false);
ac_assert('Lifecycle registers admin_init', strpos($lifecycle_src, "add_action('admin_init'") !== false);
ac_assert('Lifecycle uses admin_init priority 20', strpos($lifecycle_src, "maybe_sync'], 20") !== false);
ac_assert('Lifecycle guards DOING_AJAX', strpos($lifecycle_src, 'wp_doing_ajax') !== false);
ac_assert('Lifecycle guards DOING_CRON', strpos($lifecycle_src, 'DOING_CRON') !== false);
ac_assert('Lifecycle checks aa_db_version', strpos($lifecycle_src, 'aa_db_version') !== false);
ac_assert('Lifecycle checks seed version option', strpos($lifecycle_src, 'OPTION_SEED_VERSION') !== false);
ac_assert('Lifecycle uses transient lock', strpos($lifecycle_src, 'LOCK_KEY') !== false);
ac_assert('Lifecycle does not use AA_Learning_Catalog', strpos($lifecycle_src, 'AA_Learning_Catalog') === false);
ac_assert('Lifecycle stores last_error option', strpos($lifecycle_src, 'OPTION_LAST_ERROR') !== false);

$bootstrap_src = file_get_contents($root . '/wp-agenda-automatizada.php');
ac_assert('Plugin bootstrap requires AppointmentActionsListSeedLifecycle', strpos($bootstrap_src, 'AppointmentActionsListSeedLifecycle.php') !== false);
ac_assert('Plugin bootstrap registers appointment actions lifecycle', strpos($bootstrap_src, 'AA_Appointment_Actions_List_Seed_Lifecycle::register') !== false);

lifecycle_reset_test_state();
lifecycle_set_success_override();
AA_Appointment_Actions_List_Seed_Lifecycle::maybe_sync();
ac_assert(
    'Sync without DB does not persist version when list validation fails',
    (int) $GLOBALS['aa_test_sync_calls'] === 1
    && !array_key_exists(AA_Appointment_Actions_List_Seed_Lifecycle::OPTION_SEED_VERSION, $GLOBALS['aa_test_options'])
    && ($GLOBALS['aa_test_options'][AA_Appointment_Actions_List_Seed_Lifecycle::OPTION_LAST_ERROR] ?? '') !== ''
);

lifecycle_reset_test_state();
$GLOBALS['aa_test_options'][AA_Appointment_Actions_List_Seed_Lifecycle::OPTION_SEED_VERSION] = AA_Appointment_Actions_Catalog::SEED_VERSION;
lifecycle_set_success_override();
AA_Appointment_Actions_List_Seed_Lifecycle::maybe_sync();
ac_assert('Sync skips when seed version is current', (int) $GLOBALS['aa_test_sync_calls'] === 0);

lifecycle_reset_test_state();
lifecycle_set_success_override();
$GLOBALS['aa_test_options']['aa_db_version'] = '6';
AA_Appointment_Actions_List_Seed_Lifecycle::maybe_sync();
ac_assert('Sync skips when aa_db_version is below 7', (int) $GLOBALS['aa_test_sync_calls'] === 0);

lifecycle_reset_test_state();
lifecycle_set_success_override();
$GLOBALS['aa_test_doing_ajax'] = true;
AA_Appointment_Actions_List_Seed_Lifecycle::maybe_sync();
ac_assert('Sync skips during AJAX', (int) $GLOBALS['aa_test_sync_calls'] === 0);

lifecycle_reset_test_state();
lifecycle_set_success_override();
set_transient(AA_Appointment_Actions_List_Seed_Lifecycle::LOCK_KEY, '1', 60);
AA_Appointment_Actions_List_Seed_Lifecycle::maybe_sync();
ac_assert('Sync skips when lock is held', (int) $GLOBALS['aa_test_sync_calls'] === 0);

lifecycle_reset_test_state();
AA_Appointment_Actions_List_Seed_Lifecycle::set_sync_executor_for_tests(static function (): array {
    throw new RuntimeException('sync exploded');
});
AA_Appointment_Actions_List_Seed_Lifecycle::maybe_sync();
ac_assert(
    'Sync exception stores last_error and does not persist version',
    ($GLOBALS['aa_test_options'][AA_Appointment_Actions_List_Seed_Lifecycle::OPTION_LAST_ERROR] ?? '') === 'sync exploded'
    && !array_key_exists(AA_Appointment_Actions_List_Seed_Lifecycle::OPTION_SEED_VERSION, $GLOBALS['aa_test_options'])
);

lifecycle_reset_test_state();
$GLOBALS['aa_test_registered_actions'] = [];
AA_Appointment_Actions_List_Seed_Lifecycle::register('/tmp/wp-agenda-automatizada.php');
$admin_init = $GLOBALS['aa_test_registered_actions']['admin_init'][0] ?? null;
ac_assert(
    'register wires maybe_sync on admin_init priority 20',
    is_array($admin_init)
    && ($admin_init['priority'] ?? 0) === 20
    && is_array($admin_init['callback'] ?? null)
    && ($admin_init['callback'][1] ?? '') === 'maybe_sync'
);

if (!defined('DOING_CRON')) {
    lifecycle_reset_test_state();
    define('DOING_CRON', true);
    lifecycle_set_success_override();
    AA_Appointment_Actions_List_Seed_Lifecycle::maybe_sync();
    ac_assert('Sync skips during DOING_CRON', (int) $GLOBALS['aa_test_sync_calls'] === 0);
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
