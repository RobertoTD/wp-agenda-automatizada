<?php
/**
 * AC — ReservationsRepository::probe_has_created_reservations().
 *
 * Ejecutar: php tests/repositories/test-reservations-probe-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

require_once __DIR__ . '/../../includes/repositories/ReservationsRepository.php';

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

ReservationsRepository::set_probe_has_created_reservations_override_for_tests(static function () {
    return ['ok' => true, 'exists' => true];
});
$exists = ReservationsRepository::probe_has_created_reservations();
ac_assert('override exists true', ($exists['ok'] ?? false) === true && ($exists['exists'] ?? false) === true);

ReservationsRepository::set_probe_has_created_reservations_override_for_tests(static function () {
    return ['ok' => true, 'exists' => false];
});
$empty = ReservationsRepository::probe_has_created_reservations();
ac_assert('override empty table', ($empty['ok'] ?? false) === true && ($empty['exists'] ?? false) === false);

ReservationsRepository::set_probe_has_created_reservations_override_for_tests(static function () {
    return ['ok' => false, 'exists' => false];
});
$error = ReservationsRepository::probe_has_created_reservations();
ac_assert('override sql error', ($error['ok'] ?? true) === false && ($error['exists'] ?? true) === false);

ReservationsRepository::set_probe_has_created_reservations_override_for_tests(null);

$src = file_get_contents(__DIR__ . '/../../includes/repositories/ReservationsRepository.php');
ac_assert('probe uses SELECT 1 LIMIT 1', strpos($src, 'SELECT 1 FROM {$table} LIMIT 1') !== false);
ac_assert('probe checks last_error', strpos($src, '$wpdb->last_error') !== false);
ac_assert('count_created_reservations unchanged', strpos($src, 'count_created_reservations') !== false);

echo "\nPassed {$passed}/{$total}\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
