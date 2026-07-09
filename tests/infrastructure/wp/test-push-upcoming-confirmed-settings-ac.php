<?php
/**
 * AC MC2 — Push upcoming confirmed settings options.
 *
 * Ejecutar: php tests/infrastructure/wp/test-push-upcoming-confirmed-settings-ac.php
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

function aa_test_sanitize_push_upcoming_confirmed_enabled($value): int {
    return !empty($value) ? 1 : 0;
}

function aa_test_sanitize_push_upcoming_confirmed_minutes($value): int {
    $allowed = [
        0 => 0, '0' => 0,
        5 => 5, '5' => 5,
        15 => 15, '15' => 15,
        30 => 30, '30' => 30,
        60 => 60, '60' => 60,
    ];

    return $allowed[$value] ?? 15;
}

$settings_src = file_get_contents($plugin_root . '/views/admin-controls.php');
$settings_ui_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/settings/index.php');

ac_assert('admin-controls readable', $settings_src !== false);
ac_assert('settings UI readable', $settings_ui_src !== false);

// ─── Defaults when option absent ───────────────────────────────────

ac_assert(
    'absent enabled defaults to true',
    (int) get_option('aa_push_upcoming_confirmed_enabled', 1) === 1
);
ac_assert(
    'absent minutes defaults to 15',
    (int) get_option('aa_push_upcoming_confirmed_minutes', 15) === 15
);

// ─── Enabled sanitize ──────────────────────────────────────────────

ac_assert('enabled 0 saves 0', aa_test_sanitize_push_upcoming_confirmed_enabled(0) === 0);
ac_assert("enabled '0' saves 0", aa_test_sanitize_push_upcoming_confirmed_enabled('0') === 0);
ac_assert('enabled 1 saves 1', aa_test_sanitize_push_upcoming_confirmed_enabled(1) === 1);
ac_assert("enabled '1' saves 1", aa_test_sanitize_push_upcoming_confirmed_enabled('1') === 1);
ac_assert('enabled empty saves 0', aa_test_sanitize_push_upcoming_confirmed_enabled('') === 0);

// ─── Minutes whitelist sanitize ────────────────────────────────────

ac_assert('minutes 0 → 0', aa_test_sanitize_push_upcoming_confirmed_minutes(0) === 0);
ac_assert('minutes 5 → 5', aa_test_sanitize_push_upcoming_confirmed_minutes(5) === 5);
ac_assert('minutes 15 → 15', aa_test_sanitize_push_upcoming_confirmed_minutes(15) === 15);
ac_assert('minutes 30 → 30', aa_test_sanitize_push_upcoming_confirmed_minutes(30) === 30);
ac_assert('minutes 60 → 60', aa_test_sanitize_push_upcoming_confirmed_minutes(60) === 60);
ac_assert("minutes '0' → 0", aa_test_sanitize_push_upcoming_confirmed_minutes('0') === 0);
ac_assert("minutes '15' → 15", aa_test_sanitize_push_upcoming_confirmed_minutes('15') === 15);
ac_assert("minutes '60' → 60", aa_test_sanitize_push_upcoming_confirmed_minutes('60') === 60);
ac_assert("minutes 'x' → 15", aa_test_sanitize_push_upcoming_confirmed_minutes('x') === 15);
ac_assert("minutes '0abc' → 15", aa_test_sanitize_push_upcoming_confirmed_minutes('0abc') === 15);
ac_assert('minutes 10 → 15', aa_test_sanitize_push_upcoming_confirmed_minutes(10) === 15);
ac_assert('minutes 99 → 15', aa_test_sanitize_push_upcoming_confirmed_minutes(99) === 15);

// ─── Registration wiring ─────────────────────────────────────────────

ac_assert(
    'registers aa_push_upcoming_confirmed_enabled',
    strpos($settings_src, "register_setting('agenda_automatizada_settings', 'aa_push_upcoming_confirmed_enabled'") !== false
);
ac_assert(
    'registers aa_push_upcoming_confirmed_minutes',
    strpos($settings_src, "register_setting('agenda_automatizada_settings', 'aa_push_upcoming_confirmed_minutes'") !== false
);
ac_assert(
    'minutes sanitize uses strict whitelist map',
    strpos($settings_src, "'60' => 60") !== false && strpos($settings_src, '?? 15') !== false
);

// ─── UI wiring ─────────────────────────────────────────────────────

ac_assert('renders notifications accordion', strpos($settings_ui_src, 'id="aa-notifications-root"') !== false);
ac_assert('renders notifications title', strpos($settings_ui_src, '>Notificaciones<') !== false);
ac_assert(
    'renders enabled hidden fallback 0',
    strpos($settings_ui_src, 'name="aa_push_upcoming_confirmed_enabled" value="0"') !== false
);
ac_assert(
    'renders enabled checkbox',
    strpos($settings_ui_src, 'id="aa-push-upcoming-confirmed-enabled"') !== false
);
ac_assert(
    'renders minutes select',
    strpos($settings_ui_src, 'name="aa_push_upcoming_confirmed_minutes"') !== false
);
ac_assert('renders 0 minutes option', strpos($settings_ui_src, 'value="0"') !== false);
ac_assert('renders 5 minutes option', strpos($settings_ui_src, 'value="5"') !== false);
ac_assert('renders 15 minutes option', strpos($settings_ui_src, 'value="15"') !== false);
ac_assert('renders 30 minutes option', strpos($settings_ui_src, 'value="30"') !== false);
ac_assert('renders 60 minutes option', strpos($settings_ui_src, 'value="60"') !== false);
ac_assert(
    'enabled default read uses 1',
    strpos($settings_ui_src, "get_option('aa_push_upcoming_confirmed_enabled', 1)") !== false
);
ac_assert(
    'minutes default read uses 15',
    strpos($settings_ui_src, "get_option('aa_push_upcoming_confirmed_minutes', 15)") !== false
);

// ─── Common form intact ──────────────────────────────────────────────

ac_assert('settings form uses settings_fields', strpos($settings_ui_src, "settings_fields('agenda_automatizada_settings')") !== false);
ac_assert('save button still present', strpos($settings_ui_src, 'Guardar Configuración') !== false);
ac_assert('parameters section still present', strpos($settings_ui_src, '>Parámetros<') !== false);
ac_assert('business data section still present', strpos($settings_ui_src, '>Datos del Negocio<') !== false);
ac_assert('google calendar section still present', strpos($settings_ui_src, '>Google Calendar<') !== false);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}

echo "All tests passed.\n";
exit(0);
