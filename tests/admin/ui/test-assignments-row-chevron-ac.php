<?php
/**
 * AC Cycle 2D — Assignments internal row chevrons.
 *
 * Ejecutar: php tests/admin/ui/test-assignments-row-chevron-ac.php
 *
 * Convención: panel cerrado → derecha; panel abierto → abajo (rotate-90).
 * Alcance exclusivo: toggles internos de filas en Zonas, Personal y Servicios.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$areas_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/assignments/areas-section/areas.js');
$staff_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/assignments/staff-section/staff.js');
$services_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/assignments/services-section/servicesSection.js');
$assignments_index_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/assignments/index.php');

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

function aa_extract_button_markup(string $src, string $button_class): ?string {
    $class_pos = strpos($src, $button_class);
    if ($class_pos === false) {
        return null;
    }

    $button_start = strrpos(substr($src, 0, $class_pos), "html += '<button");
    $button_end = strpos($src, "html += '</button>';", $class_pos);

    if ($button_start === false || $button_end === false) {
        return null;
    }

    return substr($src, $button_start, $button_end - $button_start + strlen("html += '</button>';"));
}

function aa_assert_row_chevron(
    string $label,
    string $src,
    string $button_class,
    string $data_attr,
    string $panel_class
): void {
    $button_markup = aa_extract_button_markup($src, $button_class);

    ac_assert(
        $label . ' button keeps specific class and common button utilities',
        is_string($button_markup)
        && strpos($button_markup, $button_class) !== false
        && strpos($button_markup, 'inline-flex items-center justify-center w-6 h-6 text-gray-500 hover:text-gray-700 transition-colors') !== false
        && strpos($button_markup, $data_attr) !== false
    );

    ac_assert(
        $label . ' chevron uses right path',
        is_string($button_markup) && strpos($button_markup, 'd="M9 5l7 7-7 7"') !== false
    );

    ac_assert(
        $label . ' chevron keeps common SVG utilities',
        is_string($button_markup)
        && strpos($button_markup, 'class="w-4 h-4 shrink-0 transition-transform duration-200"') !== false
    );

    ac_assert(
        $label . ' chevron no longer uses down path',
        is_string($button_markup) && strpos($button_markup, 'd="M19 9l-7 7-7-7"') === false
    );

    ac_assert(
        $label . ' internal disclosure does not use aa-chevron hook',
        is_string($button_markup) && strpos($button_markup, 'aa-chevron') === false
    );

    ac_assert(
        $label . ' panel still uses hidden class',
        strpos($src, $panel_class . ' hidden border-t border-gray-200 p-3') !== false
    );

    ac_assert(
        $label . ' handler still removes rotate-90 when closed',
        strpos($src, "chevron.classList.remove('rotate-90')") !== false
    );

    ac_assert(
        $label . ' handler still adds rotate-90 when open',
        strpos($src, "chevron.classList.add('rotate-90')") !== false
    );

    ac_assert(
        $label . ' handler still uses hidden as state source',
        strpos($src, "panel.classList.contains('hidden')") !== false
    );
}

ac_assert('areas.js readable', $areas_src !== false);
ac_assert('staff.js readable', $staff_src !== false);
ac_assert('servicesSection.js readable', $services_src !== false);
ac_assert('assignments index readable', $assignments_index_src !== false);

aa_assert_row_chevron(
    'Areas row',
    (string) $areas_src,
    'aa-area-toggle-details',
    'data-area-id',
    'aa-area-details-panel'
);
aa_assert_row_chevron(
    'Staff row',
    (string) $staff_src,
    'aa-staff-toggle-services',
    'data-staff-id',
    'aa-staff-services-panel'
);
aa_assert_row_chevron(
    'Services row',
    (string) $services_src,
    'aa-service-toggle-details',
    'data-service-id',
    'aa-service-details-panel'
);

ac_assert(
    'Only one right-chevron disclosure path in areas renderer',
    substr_count((string) $areas_src, 'd="M9 5l7 7-7 7"') === 1
);
ac_assert(
    'Only one right-chevron disclosure path in staff renderer',
    substr_count((string) $staff_src, 'd="M9 5l7 7-7 7"') === 1
);
ac_assert(
    'Only one right-chevron disclosure path in services renderer',
    substr_count((string) $services_src, 'd="M9 5l7 7-7 7"') === 1
);

ac_assert(
    'Area representative row icon remains unchanged',
    strpos((string) $areas_src, 'aa-area-color-indicator w-4 h-4 rounded-full') !== false
);
ac_assert(
    'Staff representative row icon remains unchanged',
    strpos((string) $staff_src, 'd="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"') !== false
);
ac_assert(
    'Service representative row icon remains unchanged',
    strpos((string) $services_src, 'd="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"') !== false
);

ac_assert(
    'Assignments section headers keep aa-module-section-chevron',
    substr_count((string) $assignments_index_src, 'aa-module-section-chevron') >= 3
);
ac_assert(
    'Assignments section headers keep right path',
    substr_count((string) $assignments_index_src, 'd="M9 5l7 7-7 7"') >= 3
);

echo "\nResult: {$passed}/{$total} passed\n";
if (!empty($failed)) {
    echo "Failed:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}

exit(0);
