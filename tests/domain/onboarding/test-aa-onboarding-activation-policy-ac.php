<?php
/**
 * AC para AA_Onboarding_Activation_Policy.
 *
 * Ejecutar: php tests/domain/onboarding/test-aa-onboarding-activation-policy-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/onboarding/class-aa-onboarding-activation-policy.php';

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo "[ OK ] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo "[FAIL] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
}

/**
 * @param array<string,int> $overrides
 */
function onboarding_result(array $overrides): array {
    $facts = array_merge([
        'registered_client_count' => 0,
        'active_service_count' => 0,
        'active_staff_count' => 0,
        'active_staff_with_active_service_count' => 0,
        'active_area_count' => 0,
        'created_reservation_count' => 0,
    ], $overrides);

    return (new AA_Onboarding_Activation_Policy())->evaluate($facts);
}

// AC1: Todo en cero.
$r = onboarding_result([]);
ac_assert('AC1 all pending', $r['next_step'] === 'client' && $r['show_activation_guide'] === true);
ac_assert(
    'AC1 all steps incomplete',
    $r['steps']['client']['completed'] === false
    && $r['steps']['service']['completed'] === false
    && $r['steps']['staff']['completed'] === false
    && $r['steps']['area']['completed'] === false
    && $r['steps']['first_appointment']['completed'] === false
);

// AC2: Solo cliente completo.
$r = onboarding_result(['registered_client_count' => 1]);
ac_assert(
    'AC2 client complete next service',
    $r['steps']['client']['completed'] === true
    && $r['next_step'] === 'service'
);

// AC3: Cliente + servicio + area, pero staff activo sin servicio asignado.
$r = onboarding_result([
    'registered_client_count' => 1,
    'active_service_count' => 1,
    'active_staff_count' => 1,
    'active_area_count' => 1,
]);
ac_assert(
    'AC3 staff active without service assignment',
    $r['steps']['staff']['completed'] === false
    && $r['steps']['staff']['reason'] === 'missing_staff_service_assignment'
    && $r['next_step'] === 'staff'
);

// AC4: Sin staff activo.
$r = onboarding_result([
    'registered_client_count' => 1,
    'active_service_count' => 1,
    'active_area_count' => 1,
]);
ac_assert(
    'AC4 missing active staff reason',
    $r['steps']['staff']['completed'] === false
    && $r['steps']['staff']['reason'] === 'missing_active_staff'
);

// AC5: Setup completo sin citas.
$r = onboarding_result([
    'registered_client_count' => 1,
    'active_service_count' => 1,
    'active_staff_count' => 1,
    'active_staff_with_active_service_count' => 1,
    'active_area_count' => 1,
]);
ac_assert(
    'AC5 setup complete without appointment',
    $r['setup_complete'] === true
    && $r['activation_complete'] === false
    && $r['next_step'] === 'first_appointment'
);

// AC6: Todo completo con una cita.
$r = onboarding_result([
    'registered_client_count' => 1,
    'active_service_count' => 1,
    'active_staff_count' => 1,
    'active_staff_with_active_service_count' => 1,
    'active_area_count' => 1,
    'created_reservation_count' => 1,
]);
ac_assert(
    'AC6 activation complete',
    $r['activation_complete'] === true
    && $r['show_activation_guide'] === false
    && $r['next_step'] === null
);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo "Failed: " . implode(', ', $failed) . "\n";
    exit(1);
}
