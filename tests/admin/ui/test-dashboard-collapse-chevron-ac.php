<?php
/**
 * AC Cycle 2C — Dashboard collapse chevrons.
 *
 * Ejecutar: php tests/admin/ui/test-dashboard-collapse-chevron-ac.php
 *
 * Convención: cerrado → derecha (path M9…); abierto → abajo (rotate 90deg vía .is-open).
 * Combinador hijo: abrir exterior no rota chevrons de interiors.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$index_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/dashboard/index.php');
$css_src = file_get_contents($plugin_root . '/includes/admin/ui/assets/css/admin.source.css');

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
 * Extract the collapse toggle block that contains a given title.
 *
 * @return string|null
 */
function aa_extract_dash_toggle(string $src, string $title): ?string {
    $title_pos = strpos($src, '>' . $title . '<');
    if ($title_pos === false) {
        return null;
    }

    $toggle_start = strrpos(substr($src, 0, $title_pos), 'data-aa-dashboard-collapse-toggle');
    if ($toggle_start === false) {
        return null;
    }

    // Walk back to the opening <div of the toggle.
    $div_start = strrpos(substr($src, 0, $toggle_start), '<div');
    if ($div_start === false) {
        return null;
    }

    // Toggle ends at the closing of its outer div before the body sibling.
    // Find matching depth from div_start until we leave the toggle (first closing that returns to 0 after opening).
    $depth = 0;
    $pos = $div_start;
    $len = strlen($src);
    while ($pos < $len) {
        $next_open = strpos($src, '<div', $pos);
        $next_close = strpos($src, '</div>', $pos);

        if ($next_close === false) {
            return null;
        }

        if ($next_open !== false && $next_open < $next_close) {
            $depth++;
            $pos = $next_open + 4;
            continue;
        }

        $depth--;
        $pos = $next_close + 6;
        if ($depth === 0) {
            return substr($src, $div_start, $pos - $div_start);
        }
    }

    return null;
}

ac_assert('dashboard index readable', $index_src !== false);
ac_assert('admin.source.css readable', $css_src !== false);

$titles = [
    'Citas',
    'Próxima cita',
    'Citas de hoy',
    'Ingresos',
    'Resumen semanal',
    'Alertas',
];

foreach ($titles as $title) {
    $toggle = aa_extract_dash_toggle($index_src, $title);

    ac_assert(
        'Dashboard "' . $title . '" uses aa-dashboard-collapse-chevron',
        is_string($toggle) && strpos($toggle, 'aa-dashboard-collapse-chevron') !== false
    );
    ac_assert(
        'Dashboard "' . $title . '" chevron uses right path',
        is_string($toggle) && strpos($toggle, 'd="M9 5l7 7-7 7"') !== false
    );
    ac_assert(
        'Dashboard "' . $title . '" chevron keeps w-5 h-5 text-gray-400 flex-shrink-0',
        is_string($toggle)
        && preg_match(
            '/aa-dashboard-collapse-chevron[^"]*w-5 h-5 text-gray-400 transition-transform duration-200 flex-shrink-0/',
            $toggle
        ) === 1
    );
    ac_assert(
        'Dashboard "' . $title . '" collapse chevron does not use aa-chevron',
        is_string($toggle) && strpos($toggle, 'aa-chevron') === false
    );
}

ac_assert(
    'Citas header keeps Ir a agenda link',
    strpos($index_src, 'Ir a agenda') !== false
    && preg_match(
        '/data-aa-dashboard-collapse-toggle[\s\S]*?Ir a agenda[\s\S]*?aa-dash-citas-body/',
        $index_src
    ) === 1
);

ac_assert(
    'Próxima cita starts open (is-open + aria-expanded true + body without hidden)',
    preg_match(
        '/class="aa-dash-collapse[^"]*is-open"[^>]*data-aa-dashboard-collapse[\s\S]*?aria-expanded="true"[\s\S]*?aria-controls="aa-dash-collapse-next-body"[\s\S]*?<div id="aa-dash-collapse-next-body" data-aa-dashboard-collapse-body>/',
        $index_src
    ) === 1
);

ac_assert(
    'Citas de hoy starts closed (body hidden)',
    strpos($index_src, 'id="aa-dash-collapse-today-body" class="hidden"') !== false
);
ac_assert(
    'Ingresos starts closed (body hidden)',
    strpos($index_src, 'id="aa-dash-collapse-revenue-body" class="hidden"') !== false
);
ac_assert(
    'Resumen semanal starts closed (body hidden)',
    strpos($index_src, 'id="aa-dash-collapse-week-body" class="hidden"') !== false
);
ac_assert(
    'Citas outer starts closed (body hidden)',
    strpos($index_src, 'id="aa-dash-citas-body" class="hidden"') !== false
);
ac_assert(
    'Alertas section remains conditionally hidden + body hidden',
    strpos($index_src, 'id="aa-dash-alerts-section" class="hidden') !== false
    && strpos($index_src, 'id="aa-dash-alerts-body" class="hidden"') !== false
);

ac_assert(
    'Revenue select data-URI arrow intact',
    strpos($index_src, "d=%27m6 8 4 4 4-4%27") !== false
    && strpos($index_src, 'id="aa-dash-revenue-mode"') !== false
);
ac_assert(
    'Week metric select data-URI arrow intact',
    strpos($index_src, 'id="aa-dash-week-metric"') !== false
    && substr_count($index_src, "d=%27m6 8 4 4 4-4%27") >= 2
);

$dash_rule = null;
if (preg_match(
    '/\[data-aa-dashboard-collapse\]\.is-open\s*>\s*\[data-aa-dashboard-collapse-toggle\]\s*\.aa-dashboard-collapse-chevron\s*\{([^}]*)\}/',
    $css_src,
    $m
)) {
    $dash_rule = $m[1];
}

ac_assert(
    'CSS has scoped Dashboard .is-open child-combinator rule',
    is_string($dash_rule)
);
ac_assert(
    'Scoped Dashboard rule uses rotate(90deg)',
    is_string($dash_rule) && strpos($dash_rule, 'rotate(90deg)') !== false
);
ac_assert(
    'Legacy Dashboard rotate(180deg) on .aa-chevron is gone',
    preg_match(
        '/\[data-aa-dashboard-collapse\]\.is-open\s*>\s*\[data-aa-dashboard-collapse-toggle\]\s*\.aa-chevron[\s\S]*?rotate\(180deg\)/',
        $css_src
    ) !== 1
);
ac_assert(
    'No global details[open] ... .aa-chevron rule',
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
    'Module-section Settings/Assignments rule intact',
    strpos($css_src, 'details.aa-module-section-card[open] > summary .aa-module-section-chevron') !== false
);
ac_assert(
    'Dashboard section-icon rules intact',
    strpos($css_src, '[data-aa-dashboard-collapse].is-open > [data-aa-dashboard-collapse-toggle] .aa-dash-section-icon--blue') !== false
);

echo "\nResult: {$passed}/{$total} passed\n";
if (!empty($failed)) {
    echo "Failed:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}

exit(0);
