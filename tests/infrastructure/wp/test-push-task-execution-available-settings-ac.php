<?php
/**
 * AC — Push task execution_available settings option.
 *
 * Ejecutar: php tests/infrastructure/wp/test-push-task-execution-available-settings-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);

$GLOBALS['aa_test_options'] = [];

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

function aa_test_sanitize_push_task_execution_available_enabled($value): int {
    return !empty($value) ? 1 : 0;
}

$settings_src = file_get_contents($plugin_root . '/views/admin-controls.php');
$settings_ui_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/settings/index.php');

ac_assert('admin-controls readable', $settings_src !== false);
ac_assert('settings UI readable', $settings_ui_src !== false);

// ─── Defaults when option absent ───────────────────────────────────

ac_assert(
    'absent task push enabled defaults to true',
    (int) get_option('aa_push_task_execution_available_enabled', 1) === 1
);

// ─── Enabled sanitize ──────────────────────────────────────────────

ac_assert('enabled 0 saves 0', aa_test_sanitize_push_task_execution_available_enabled(0) === 0);
ac_assert("enabled '0' saves 0", aa_test_sanitize_push_task_execution_available_enabled('0') === 0);
ac_assert('enabled 1 saves 1', aa_test_sanitize_push_task_execution_available_enabled(1) === 1);
ac_assert("enabled '1' saves 1", aa_test_sanitize_push_task_execution_available_enabled('1') === 1);
ac_assert('enabled empty saves 0', aa_test_sanitize_push_task_execution_available_enabled('') === 0);

// ─── Registration wiring ─────────────────────────────────────────────

ac_assert(
    'registers aa_push_task_execution_available_enabled',
    strpos($settings_src, "register_setting('agenda_automatizada_settings', 'aa_push_task_execution_available_enabled'") !== false
);
ac_assert(
    'task enabled sanitize uses truthy pattern',
    strpos($settings_src, "register_setting('agenda_automatizada_settings', 'aa_push_task_execution_available_enabled'") !== false
    && strpos($settings_src, 'return !empty($value) ? 1 : 0;') !== false
);

// ─── UI wiring ─────────────────────────────────────────────────────

ac_assert(
    'notifications subtitle mentions citas y tareas',
    strpos($settings_ui_src, 'Avisos push para citas y tareas') !== false
);
ac_assert(
    'does not render old citas-only subtitle',
    strpos($settings_ui_src, 'Avisos push para citas confirmadas') === false
);
ac_assert(
    'renders task enabled hidden fallback 0',
    strpos($settings_ui_src, 'name="aa_push_task_execution_available_enabled" value="0"') !== false
);
ac_assert(
    'renders task enabled checkbox',
    strpos($settings_ui_src, 'id="aa-push-task-execution-available-enabled"') !== false
);
ac_assert(
    'renders task toggle copy',
    strpos($settings_ui_src, 'Notificar cuando sea momento de realizar una tarea') !== false
);
ac_assert(
    'task enabled default read uses 1',
    strpos($settings_ui_src, "get_option('aa_push_task_execution_available_enabled', 1)") !== false
);

// ─── Independence from appointment push setting ─────────────────────

ac_assert(
    'appointment push setting still registered',
    strpos($settings_src, "register_setting('agenda_automatizada_settings', 'aa_push_upcoming_confirmed_enabled'") !== false
);
ac_assert(
    'appointment push toggle still present',
    strpos($settings_ui_src, 'id="aa-push-upcoming-confirmed-enabled"') !== false
);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}

echo "All tests passed.\n";
exit(0);
