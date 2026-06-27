<?php
/**
 * AC MC2 — AA_Appointment_Confirmation_Task_Projector.
 *
 * Ejecutar: php tests/domain/appointments/test-aa-appointment-confirmation-task-projector-ac.php
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

require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-actions-catalog.php';
require_once $plugin_root . '/includes/domain/appointments/class-aa-appointment-confirmation-task-projector.php';

ac_assert(
    'task_origin_key formats reservation id',
    AA_Appointment_Confirmation_Task_Projector::task_origin_key(42) === 'appointment_confirmation:42'
);
ac_assert(
    'build_title uses client name',
    AA_Appointment_Confirmation_Task_Projector::build_title('Ana López') === 'Confirmar cita con Ana López'
);
ac_assert(
    'build_title falls back to cliente',
    AA_Appointment_Confirmation_Task_Projector::build_title('   ') === 'Confirmar cita con cliente'
);
ac_assert(
    'build_notes includes phone date time service',
    strpos(
        AA_Appointment_Confirmation_Task_Projector::build_notes([
            'phone' => '555',
            'date_label' => '20 Jun 2026',
            'time_label' => '10:30',
            'service' => 'Corte',
        ]),
        "Teléfono: 555\nFecha: 20 Jun 2026\nHora: 10:30\nServicio: Corte"
    ) === 0
);
ac_assert(
    'build_notes omits empty service',
    strpos(
        AA_Appointment_Confirmation_Task_Projector::build_notes([
            'phone' => '555',
            'date_label' => '20 Jun 2026',
            'time_label' => '10:30',
            'service' => '',
        ]),
        'Servicio:'
    ) === false
);
ac_assert(
    'truncate_text respects max length',
    strlen(AA_Appointment_Confirmation_Task_Projector::truncate_text(str_repeat('a', 20), 10)) === 10
);
ac_assert(
    'action_definition uses appointment.confirm',
    (AA_Appointment_Confirmation_Task_Projector::action_definition()['action_key'] ?? '') === 'appointment.confirm'
    && (AA_Appointment_Confirmation_Task_Projector::action_definition()['handler'] ?? '') === 'appointment.confirm'
    && (AA_Appointment_Confirmation_Task_Projector::action_definition()['label'] ?? '') === 'Confirmar'
);
ac_assert(
    'resolve_due_at normalizes valid reservation fecha',
    AA_Appointment_Confirmation_Task_Projector::resolve_due_at('2026-06-21 11:00:00') === '2026-06-21 11:00:00'
);
ac_assert(
    'resolve_due_at returns null for empty fecha',
    AA_Appointment_Confirmation_Task_Projector::resolve_due_at('') === null
);
ac_assert(
    'resolve_due_at returns null for invalid fecha',
    AA_Appointment_Confirmation_Task_Projector::resolve_due_at('not-a-date') === null
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
