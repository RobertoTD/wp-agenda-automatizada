<?php
/**
 * AC Test — Family icon markup + container-card all-lists presentation.
 *
 * Ejecutar: php tests/admin/ui/test-canonical-family-icons-card-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('esc_url')) {
    function esc_url($url) {
        return (string) $url;
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0) {
        return json_encode($data, $options);
    }
}

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

require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/class-aa-canonical-family-icon-markup.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';

$registry = AA_Canonical_Core_Bootstrap::build_registry();
$expected = [
    'archive' => 'folder',
    'finance' => 'currency',
    'catalog' => 'grid',
    'contact' => 'contact_card',
];
$path_snippets = [
    'folder' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    'currency' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2',
    'grid' => 'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5z',
    'contact_card' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857',
];
foreach ($expected as $family_key => $icon_key) {
    $def = $registry->family($family_key);
    ac_assert("Registry {$family_key} icon_key", $def->icon_key() === $icon_key);
    $svg = AA_Canonical_Family_Icon_Markup::svg($icon_key);
    ac_assert("SVG for {$icon_key} non-empty", $svg !== '');
    ac_assert("SVG for {$icon_key} decorative", strpos($svg, 'aria-hidden="true"') !== false);
    ac_assert("SVG for {$icon_key} is svg", strpos($svg, '<svg') === 0);
    ac_assert("SVG for {$icon_key} size w-5 h-5", strpos($svg, 'class="w-5 h-5"') !== false);
    ac_assert("SVG for {$icon_key} stroke currentColor", strpos($svg, 'stroke="currentColor"') !== false);
    ac_assert("SVG for {$icon_key} stroke-width 2", strpos($svg, 'stroke-width="2"') !== false);
    ac_assert("SVG for {$icon_key} reference path", strpos($svg, $path_snippets[$icon_key]) !== false);
}
ac_assert('Folder has no interior sheet path', strpos(AA_Canonical_Family_Icon_Markup::svg('folder'), 'M10 11.5h4.5v6') === false);
ac_assert('Contact is people group not card rect', strpos(AA_Canonical_Family_Icon_Markup::svg('contact_card'), 'rect x="3.5"') === false);
ac_assert('Unknown icon_key empty', AA_Canonical_Family_Icon_Markup::svg('unknown_icon') === '');
ac_assert('Empty icon_key empty', AA_Canonical_Family_Icon_Markup::svg('') === '');

$markup_src = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/class-aa-canonical-family-icon-markup.php'
);
ac_assert('Markup does not require sidebar', strpos($markup_src, 'sidebar.php') === false);
ac_assert('Markup does not require clients module', strpos($markup_src, 'modules/clients') === false);
ac_assert('Markup does not require expedientes module', strpos($markup_src, 'modules/expedientes') === false);

$card_partial = $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/container-card.php';

$render_card = static function (array $vars) use ($card_partial): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    include $card_partial;
    return (string) ob_get_clean();
};

$html_all = $render_card([
    'card_title' => 'Lista de prueba muy larga que debería poder envolver sin romper el icono',
    'card_details' => 'Detalle',
    'card_iso' => '2026-09-12T15:00:00Z',
    'card_display' => '12 Sep 2026, 15:00',
    'card_records_url' => 'https://example.com/records',
    'card_family_key' => 'catalog',
    'card_family_label' => 'Catálogos',
    'card_family_icon_key' => 'grid',
    'show_edit_container' => true,
    'card_container_id' => 7,
]);

ac_assert('All-lists card has SVG', strpos($html_all, '<svg') !== false);
ac_assert('All-lists card centers icon to h4 block', strpos($html_all, 'flex items-center gap-1') !== false);
ac_assert('All-lists card omits items-start', strpos($html_all, 'items-start') === false);
ac_assert('All-lists card indigo wrapper', strpos($html_all, 'w-6 h-6 flex-shrink-0 text-indigo-600') !== false);
ac_assert('All-lists card icon box w-6 h-6', strpos($html_all, 'w-6 h-6') !== false);
ac_assert('All-lists card omits old gray icon offset', strpos($html_all, 'mt-0.5 text-gray-500') === false);
ac_assert('All-lists card omits visible family paragraph', strpos($html_all, 'text-xs text-gray-500 mb-1') === false);
ac_assert('All-lists accessible family inside link', (bool) preg_match(
    '/<a[^>]*>\s*<span class="sr-only">Catálogos: <\/span>/',
    $html_all
));
ac_assert('All-lists keeps records sr-only once', substr_count($html_all, ' — ver registros') === 1);
ac_assert('All-lists keeps edit button', strpos($html_all, 'aa-shell-edit-container-btn') !== false);
ac_assert('All-lists keeps delete button', strpos($html_all, 'aa-shell-delete-container-btn') !== false);
ac_assert('All-lists keeps details', strpos($html_all, 'Detalle') !== false);
ac_assert('All-lists keeps time', strpos($html_all, 'datetime="2026-09-12T15:00:00Z"') !== false);
ac_assert('Icon wrapper is decorative', strpos($html_all, 'aria-hidden="true"') !== false);
ac_assert('Title uses break-words', strpos($html_all, 'break-words') !== false);
ac_assert('SVG uses w-5 h-5', strpos($html_all, 'class="w-5 h-5"') !== false);

$html_mono = $render_card([
    'card_title' => 'Solo familia',
    'card_details' => null,
    'card_iso' => '2026-09-12T15:00:00Z',
    'card_display' => '12 Sep 2026, 15:00',
    'card_records_url' => 'https://example.com/records',
    'card_family_key' => 'finance',
    'card_family_label' => '',
    'card_family_icon_key' => '',
]);

ac_assert('Monofamily card has no family SVG row', strpos($html_mono, 'flex items-center gap-1') === false);
ac_assert('Monofamily card has no family sr-only label', strpos($html_mono, 'sr-only">Finanzas') === false);
ac_assert('Monofamily card keeps title link', strpos($html_mono, 'Solo familia') !== false);

$card_src = (string) file_get_contents($card_partial);
ac_assert('Card no longer renders family name paragraph class', strpos($card_src, 'text-xs text-gray-500 mb-1') === false);
ac_assert('Card uses items-center', strpos($card_src, 'flex items-center gap-1') !== false);
ac_assert('Card uses indigo icon color', strpos($card_src, 'text-indigo-600') !== false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}
exit(0);
