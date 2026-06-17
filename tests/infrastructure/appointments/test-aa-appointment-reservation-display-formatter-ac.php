<?php
/**
 * AC — AA_Appointment_Reservation_Display_Formatter.
 *
 * Ejecutar: php tests/infrastructure/appointments/test-aa-appointment-reservation-display-formatter-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

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

$option_values = [
    'aa_timezone' => 'America/Mexico_City',
    'timezone_string' => 'UTC',
    'gmt_offset' => '0',
];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        global $option_values;

        if (array_key_exists($key, $option_values)) {
            return $option_values[$key];
        }

        return $default;
    }
}

if (!function_exists('wp_date')) {
    /**
     * Stub: respeta la timezone explícita (como wp_date real).
     */
    function wp_date($format, $timestamp, $timezone = null) {
        $datetime = new DateTime('@' . (int) $timestamp);

        if ($timezone instanceof DateTimeZone) {
            $datetime->setTimezone($timezone);
        }

        return $datetime->format($format);
    }
}

class AssignmentsModel {
    /** @var array<int, array{name:string}> */
    public static $services = [];

    /**
     * @param int $id
     * @return array<string,mixed>|false
     */
    public static function get_service_by_id($id) {
        $id = (int) $id;

        if ($id < 1 || !isset(self::$services[$id])) {
            return false;
        }

        return self::$services[$id];
    }
}

require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-confirmation-task-projector.php';
require_once $plugin_root . '/includes/infrastructure/appointments/class-aa-appointment-reservation-display-formatter.php';

$base_reservation = [
    'nombre' => 'Cliente Test',
    'telefono' => '525636299377',
    'fecha' => '2026-06-17 12:00:00',
    'servicio' => 'Corte clásico',
];

$display = AA_Appointment_Reservation_Display_Formatter::format($base_reservation);

ac_assert('time_label stays 12:00 with WP timezone UTC', ($display['time_label'] ?? '') === '12:00');
ac_assert('date_label uses aa_timezone context', ($display['date_label'] ?? '') === '17 Jun 2026');
ac_assert('legacy textual service is preserved', ($display['service'] ?? '') === 'Corte clásico');

$notes = AA_Appointment_Confirmation_Task_Projector::build_notes([
    'phone' => $display['phone'],
    'date_label' => $display['date_label'],
    'time_label' => $display['time_label'],
    'service' => $display['service'],
]);
ac_assert(
    'projector notes include Hora: 12:00',
    strpos($notes, "Hora: 12:00") !== false
        && strpos($notes, 'Hora: 18:00') === false
);

AssignmentsModel::$services[1] = ['name' => 'Corte premium'];
$numeric_display = AA_Appointment_Reservation_Display_Formatter::format(array_merge($base_reservation, [
    'servicio' => '1',
]));
ac_assert('numeric service resolves to name', ($numeric_display['service'] ?? '') === 'Corte premium');

$missing_numeric_display = AA_Appointment_Reservation_Display_Formatter::format(array_merge($base_reservation, [
    'servicio' => '999',
]));
ac_assert('unresolved numeric service becomes empty label', ($missing_numeric_display['service'] ?? '') === '');

$missing_notes = AA_Appointment_Confirmation_Task_Projector::build_notes([
    'phone' => $missing_numeric_display['phone'],
    'date_label' => $missing_numeric_display['date_label'],
    'time_label' => $missing_numeric_display['time_label'],
    'service' => $missing_numeric_display['service'],
]);
ac_assert('projector omits Servicio line for unresolved numeric id', strpos($missing_notes, 'Servicio:') === false);

$fixed_display = AA_Appointment_Reservation_Display_Formatter::format(array_merge($base_reservation, [
    'servicio' => 'fixed::Barba express',
]));
ac_assert('fixed:: service resolves to readable name', ($fixed_display['service'] ?? '') === 'Barba express');

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
