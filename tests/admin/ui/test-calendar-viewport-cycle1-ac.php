<?php
/**
 * AC — Calendar viewport Ciclo 1 (standalone shell + markup).
 *
 * Ejecutar: php tests/admin/ui/test-calendar-viewport-cycle1-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$index_php = file_get_contents($plugin_root . '/includes/admin/ui/modules/calendar/index.php');
$layout_php = file_get_contents($plugin_root . '/includes/admin/ui/shared/layout.php');
$css_source = file_get_contents($plugin_root . '/includes/admin/ui/assets/css/admin.source.css');
$appointments_js = file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/calendar/calendar-section/calendar-appointments.js'
);

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

ac_assert('calendar index.php readable', $index_php !== false);
ac_assert('layout.php readable', $layout_php !== false);
ac_assert('admin.source.css readable', $css_source !== false);

ac_assert(
    'index expone aa-calendar-shell',
    is_string($index_php) && strpos($index_php, 'aa-calendar-shell') !== false
);

ac_assert(
    'index expone aa-calendar-toolbar',
    is_string($index_php) && strpos($index_php, 'aa-calendar-toolbar') !== false
);

ac_assert(
    'index reutiliza p-0 transition-all como aa-day-timeline-viewport',
    is_string($index_php)
    && strpos($index_php, 'aa-day-timeline-viewport p-0 transition-all duration-200') !== false
);

ac_assert(
    'jerarquía viewport → aa-day-timeline → aa-time-grid',
    is_string($index_php)
    && preg_match(
        '/aa-day-timeline-viewport[\s\S]*aa-day-timeline[\s\S]*id="aa-time-grid"/',
        $index_php
    ) === 1
);

ac_assert(
    'layout marca aa-standalone / aa-embedded en documentElement',
    is_string($layout_php)
    && strpos($layout_php, 'aa-standalone') !== false
    && strpos($layout_php, 'aa-embedded') !== false
    && strpos($layout_php, 'document.documentElement') !== false
);

ac_assert(
    'layout expone data-aa-module en body',
    is_string($layout_php)
    && strpos($layout_php, 'data-aa-module="<?php echo esc_attr($active_module); ?>"') !== false
);

ac_assert(
    'CSS scoped a html.aa-standalone body[data-aa-module="calendar"]',
    is_string($css_source)
    && strpos($css_source, 'html.aa-standalone body[data-aa-module="calendar"]') !== false
);

ac_assert(
    'CSS viewport con overflow-y auto y overflow-x hidden',
    is_string($css_source)
    && strpos($css_source, '.aa-day-timeline-viewport') !== false
    && strpos($css_source, 'overflow-y: auto') !== false
    && strpos($css_source, 'overflow-x: hidden') !== false
);

ac_assert(
    'CSS sin reglas de altura viewport bajo html.aa-embedded calendar',
    is_string($css_source)
    && strpos($css_source, 'html.aa-embedded body[data-aa-module="calendar"]') === false
);

ac_assert(
    'padding compara contra naturalBottomViewport descontando padding vigente',
    is_string($appointments_js)
    && strpos($appointments_js, 'naturalBottomViewport') !== false
    && strpos($appointments_js, 'currentPaddingBottom') !== false
    && strpos($appointments_js, 'sectionRect.bottom - currentPaddingBottom') !== false
);

echo "\n{$passed}/{$total} passed\n";

if ($failed !== []) {
    exit(1);
}

exit(0);
