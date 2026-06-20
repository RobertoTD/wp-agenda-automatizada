<?php
/**
 * AC — Hide buttons UI wiring for staff and service areas (MC2).
 *
 * Ejecutar: php tests/application/assignments/test-hide-staff-and-areas-ui-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$staff_js = $plugin_root . '/includes/admin/ui/modules/assignments/staff-section/staff.js';
$areas_js = $plugin_root . '/includes/admin/ui/modules/assignments/areas-section/areas.js';

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

$staff_src = file_get_contents($staff_js);
$areas_src = file_get_contents($areas_js);

ac_assert('staff.js readable', $staff_src !== false);
ac_assert('areas.js readable', $areas_src !== false);

ac_assert(
    'Staff renders aa-staff-delete button',
    strpos($staff_src, 'class="aa-staff-delete') !== false
    && strpos($staff_src, '>Ocultar</button>') !== false
);
ac_assert(
    'Staff hide button inside services panel flow',
    strpos($staff_src, 'aa-staff-services-panel') !== false
    && strpos($staff_src, 'aa-staff-delete') !== false
    && strpos($staff_src, 'aa-staff-services-selected') !== false
);

ac_assert(
    'Areas render aa-area-delete button',
    strpos($areas_src, 'class="aa-area-delete') !== false
    && strpos($areas_src, '>Ocultar</button>') !== false
);
ac_assert(
    'Areas hide button after color picker block',
    preg_match('/aa-area-color-picker[\s\S]*?aa-area-delete/', $areas_src) === 1
);

ac_assert(
    'Staff uses aa_delete_staff_db action',
    strpos($staff_src, "formData.append('action', 'aa_delete_staff_db')") !== false
);
ac_assert(
    'Areas uses aa_delete_service_area_db action',
    strpos($areas_src, "formData.append('action', 'aa_delete_service_area_db')") !== false
);

ac_assert(
    'Staff hide confirms before AJAX',
    strpos($staff_src, "confirm('¿Ocultar este personal?')") !== false
);
ac_assert(
    'Areas hide confirms before AJAX',
    strpos($areas_src, "confirm('¿Ocultar esta zona de atención?')") !== false
);

ac_assert(
    'Staff reloads list on success',
    preg_match('/function hideStaff[\s\S]*?loadStaff\(staffRoot\)/', $staff_src) === 1
);
ac_assert(
    'Areas reload list on success',
    preg_match('/function hideArea[\s\S]*?loadServiceAreas\(areasRoot\)/', $areas_src) === 1
);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
