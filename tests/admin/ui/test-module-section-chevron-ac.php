<?php
/**
 * AC Cycle 2B — module section header chevrons (Settings + Assignments).
 *
 * Ejecutar: php tests/admin/ui/test-module-section-chevron-ac.php
 *
 * Convención: cerrado → derecha (path M9…); abierto → abajo (rotate 90deg via [open]).
 * No alcanza chevrons internos de filas, Learning ni Dashboard.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$settings_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/settings/index.php');
$assignments_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/assignments/index.php');
$css_src = file_get_contents($plugin_root . '/includes/admin/ui/assets/css/admin.source.css');
$areas_js = file_get_contents($plugin_root . '/includes/admin/ui/modules/assignments/areas-section/areas.js');
$staff_js = file_get_contents($plugin_root . '/includes/admin/ui/modules/assignments/staff-section/staff.js');
$services_js = file_get_contents($plugin_root . '/includes/admin/ui/modules/assignments/services-section/servicesSection.js');

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

/**
 * Extract the <summary>…</summary> block for a section identified by title.
 *
 * @return string|null
 */
function aa_extract_section_summary(string $src, string $title): ?string {
    $title_pos = strpos($src, '>' . $title . '<');
    if ($title_pos === false) {
        return null;
    }

    $details_start = strrpos(substr($src, 0, $title_pos), '<details');
    if ($details_start === false) {
        return null;
    }

    $summary_start = strpos($src, '<summary', $details_start);
    $summary_end = strpos($src, '</summary>', $summary_start);
    if ($summary_start === false || $summary_end === false) {
        return null;
    }

    return substr($src, $summary_start, $summary_end - $summary_start + strlen('</summary>'));
}

/**
 * Extract the opening <details …> tag that contains a given title.
 *
 * @return string|null
 */
function aa_extract_details_open_tag(string $src, string $title): ?string {
    $title_pos = strpos($src, '>' . $title . '<');
    if ($title_pos === false) {
        return null;
    }

    $details_start = strrpos(substr($src, 0, $title_pos), '<details');
    if ($details_start === false) {
        return null;
    }

    $gt = strpos($src, '>', $details_start);
    if ($gt === false) {
        return null;
    }

    return substr($src, $details_start, $gt - $details_start + 1);
}

ac_assert('settings index readable', $settings_src !== false);
ac_assert('assignments index readable', $assignments_src !== false);
ac_assert('admin.source.css readable', $css_src !== false);
ac_assert('areas.js readable', $areas_js !== false);
ac_assert('staff.js readable', $staff_js !== false);
ac_assert('servicesSection.js readable', $services_js !== false);

$settings_titles = [
    'Horarios y Disponibilidad Fija',
    'Parámetros',
    'Datos del Negocio',
    'Google Calendar',
    'Notificaciones',
];

foreach ($settings_titles as $title) {
    $details_tag = aa_extract_details_open_tag($settings_src, $title);
    $summary = aa_extract_section_summary($settings_src, $title);

    ac_assert(
        'Settings "' . $title . '" uses aa-module-section-card',
        is_string($details_tag) && strpos($details_tag, 'aa-module-section-card') !== false
    );
    ac_assert(
        'Settings "' . $title . '" chevron uses aa-module-section-chevron',
        is_string($summary) && strpos($summary, 'aa-module-section-chevron') !== false
    );
    ac_assert(
        'Settings "' . $title . '" chevron has w-5 h-5 shrink-0',
        is_string($summary)
        && preg_match('/aa-module-section-chevron[^"]*w-5 h-5 shrink-0/', $summary) === 1
    );
    ac_assert(
        'Settings "' . $title . '" chevron uses right path',
        is_string($summary) && strpos($summary, 'd="M9 5l7 7-7 7"') !== false
    );
    ac_assert(
        'Settings "' . $title . '" header chevron does not use aa-chevron',
        is_string($summary) && strpos($summary, 'aa-chevron') === false
    );
}

ac_assert(
    'Settings Parámetros no longer uses group-open:rotate-180',
    strpos($settings_src, 'group-open:rotate-180') === false
);

$assignments_titles = [
    'Zonas de atención',
    'Personal',
    'Servicios',
];

foreach ($assignments_titles as $title) {
    $details_tag = aa_extract_details_open_tag($assignments_src, $title);
    $summary = aa_extract_section_summary($assignments_src, $title);

    ac_assert(
        'Assignments "' . $title . '" uses aa-module-section-card',
        is_string($details_tag) && strpos($details_tag, 'aa-module-section-card') !== false
    );
    ac_assert(
        'Assignments "' . $title . '" chevron uses aa-module-section-chevron',
        is_string($summary) && strpos($summary, 'aa-module-section-chevron') !== false
    );
    ac_assert(
        'Assignments "' . $title . '" chevron has w-5 h-5 shrink-0',
        is_string($summary)
        && preg_match('/aa-module-section-chevron[^"]*w-5 h-5 shrink-0/', $summary) === 1
    );
    ac_assert(
        'Assignments "' . $title . '" chevron uses right path',
        is_string($summary) && strpos($summary, 'd="M9 5l7 7-7 7"') !== false
    );
    ac_assert(
        'Assignments "' . $title . '" header chevron does not use aa-chevron',
        is_string($summary) && strpos($summary, 'aa-chevron') === false
    );
}

$hidden_details = null;
$hidden_id_pos = strpos($assignments_src, 'id="aa-assignments-section"');
if ($hidden_id_pos !== false) {
    $hidden_start = strrpos(substr($assignments_src, 0, $hidden_id_pos), '<details');
    $hidden_gt = strpos($assignments_src, '>', $hidden_id_pos);
    if ($hidden_start !== false && $hidden_gt !== false) {
        $hidden_details = substr($assignments_src, $hidden_start, $hidden_gt - $hidden_start + 1);
    }
}

ac_assert(
    'Hidden #aa-assignments-section was not migrated to aa-module-section-card',
    is_string($hidden_details)
    && strpos($hidden_details, 'aa-module-section-card') === false
    && strpos($hidden_details, 'hidden') !== false
);

$hidden_summary = null;
if ($hidden_id_pos !== false) {
    $summary_start = strpos($assignments_src, '<summary', $hidden_id_pos);
    $summary_end = strpos($assignments_src, '</summary>', $summary_start);
    if ($summary_start !== false && $summary_end !== false) {
        $hidden_summary = substr($assignments_src, $summary_start, $summary_end - $summary_start);
    }
}

ac_assert(
    'Hidden #aa-assignments-section keeps legacy aa-chevron header',
    is_string($hidden_summary)
    && strpos($hidden_summary, 'aa-chevron') !== false
    && strpos($hidden_summary, 'aa-module-section-chevron') === false
    && strpos($hidden_summary, 'd="M19 9l-7 7-7-7"') !== false
);

ac_assert(
    'Internal area toggle keeps aa-area-toggle-details + w-4 h-4 + right path',
    strpos($areas_js, 'aa-area-toggle-details') !== false
    && strpos($areas_js, 'class="w-4 h-4 shrink-0 transition-transform duration-200"') !== false
    && strpos($areas_js, 'd="M9 5l7 7-7 7"') !== false
    && strpos($areas_js, "chevron.classList.add('rotate-90')") !== false
);

ac_assert(
    'Internal staff toggle keeps aa-staff-toggle-services + w-4 h-4 + right path',
    strpos($staff_js, 'aa-staff-toggle-services') !== false
    && strpos($staff_js, 'class="w-4 h-4 shrink-0 transition-transform duration-200"') !== false
    && strpos($staff_js, 'd="M9 5l7 7-7 7"') !== false
    && strpos($staff_js, "chevron.classList.add('rotate-90')") !== false
);

ac_assert(
    'Internal service toggle keeps aa-service-toggle-details + w-4 h-4 + right path',
    strpos($services_js, 'aa-service-toggle-details') !== false
    && strpos($services_js, 'class="w-4 h-4 shrink-0 transition-transform duration-200"') !== false
    && strpos($services_js, 'd="M9 5l7 7-7 7"') !== false
    && strpos($services_js, "chevron.classList.add('rotate-90')") !== false
);

$module_rule = null;
if (preg_match(
    '/details\.aa-module-section-card\[open\]\s*>\s*summary\s*\.aa-module-section-chevron\s*\{([^}]*)\}/',
    $css_src,
    $m
)) {
    $module_rule = $m[1];
}

ac_assert(
    'CSS has scoped module-section [open] rule',
    is_string($module_rule)
);
ac_assert(
    'Scoped module-section rule uses rotate(90deg)',
    is_string($module_rule) && strpos($module_rule, 'rotate(90deg)') !== false
);
ac_assert(
    'No global details[open] ... .aa-chevron rule reintroduced',
    preg_match('/details\[open\][^{]*\.aa-chevron/', $css_src) !== 1
);

ac_assert(
    'Learning list-card rotate rule intact',
    strpos($css_src, 'details.aa-executable-list-card[open] > summary .aa-executable-list-chevron') !== false
);
ac_assert(
    'Learning item rotate rule intact',
    strpos($css_src, 'details.aa-executable-item[open] > summary .aa-executable-item-chevron') !== false
);
ac_assert(
    'Learning following-tasks rotate rule intact',
    strpos($css_src, 'details.aa-executable-following-tasks[open] > summary .aa-executable-following-tasks-chevron') !== false
);
ac_assert(
    'Dashboard .is-open .aa-dashboard-collapse-chevron rotate(90deg) intact',
    preg_match(
        '/\[data-aa-dashboard-collapse\]\.is-open\s*>\s*\[data-aa-dashboard-collapse-toggle\]\s*\.aa-dashboard-collapse-chevron[\s\S]*?rotate\(90deg\)/',
        $css_src
    ) === 1
);

echo "\nResult: {$passed}/{$total} passed\n";
if (!empty($failed)) {
    echo "Failed:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}

exit(0);
