<?php
/**
 * AC Test — Family icon markup + container-card compact options presentation.
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
    'card_announce_family' => true,
    'show_edit_container' => true,
    'card_container_id' => 7,
]);

ac_assert('All-lists card has SVG', strpos($html_all, '<svg') !== false);
ac_assert('All-lists card compact row', strpos($html_all, 'flex items-center gap-1 min-w-0') !== false);
ac_assert('All-lists card indigo wrapper', strpos($html_all, 'w-6 h-6 flex-shrink-0 text-indigo-600') !== false);
ac_assert('All-lists card icon box w-6 h-6', strpos($html_all, 'w-6 h-6') !== false);
ac_assert('All-lists card omits old gray icon offset', strpos($html_all, 'mt-0.5 text-gray-500') === false);
ac_assert('All-lists card omits visible family paragraph', strpos($html_all, 'text-xs text-gray-500 mb-1') === false);
ac_assert('All-lists accessible family inside link', (bool) preg_match(
    '/<a[^>]*>\s*<span class="sr-only">Catálogos: <\/span>/',
    $html_all
));
ac_assert('All-lists keeps records sr-only once', substr_count($html_all, ' — ver registros') === 1);
ac_assert('All-lists uses stretched card link class', (bool) preg_match(
    '/<a[^>]*class="[^"]*aa-shell-container-card-link[^"]*"[^>]*href="https:\/\/example\.com\/records"/',
    $html_all
) || (bool) preg_match(
    '/<a[^>]*href="https:\/\/example\.com\/records"[^>]*class="[^"]*aa-shell-container-card-link/',
    $html_all
));
ac_assert('All-lists link keeps hover underline without own focus ring', (bool) preg_match(
    '/aa-shell-container-card-link[^"]*hover:underline[^"]*focus:outline-none/',
    $html_all
) && !preg_match('/aa-shell-container-card-link[^"]*focus:ring-2/', $html_all)
    && !preg_match('/aa-shell-container-card-link[^"]*focus:ring-indigo-500/', $html_all)
    && !preg_match('/aa-shell-container-card-link[^"]*focus:ring-offset-2/', $html_all));
ac_assert('All-lists options sit above stretched link', strpos($html_all, 'aa-shell-container-options relative shrink-0 z-20') !== false);
ac_assert('All-lists options remain outside the title link', (bool) preg_match(
    '/<\/a>\s*<\/h4>\s*<div class="aa-shell-container-options/',
    $html_all
));
ac_assert('All-lists keeps edit button class', strpos($html_all, 'aa-shell-edit-container-btn') !== false);
ac_assert('All-lists keeps delete button class', strpos($html_all, 'aa-shell-delete-container-btn') !== false);
ac_assert('All-lists uses options trigger', strpos($html_all, 'aa-shell-container-options-trigger') !== false
    && strpos($html_all, 'aa-options-trigger-flat') !== false);
ac_assert('All-lists options popup without menu roles', strpos($html_all, 'aa-shell-container-options-popup') !== false
    && strpos($html_all, 'role="menu"') === false
    && strpos($html_all, 'role="menuitem"') === false);
ac_assert('All-lists options popup keeps fixed width without max-w-full collapse', (bool) preg_match(
    '/aa-shell-container-options-popup[^"]*w-\[12rem\]/',
    $html_all
) && strpos($html_all, 'aa-shell-container-options-popup') !== false
    && !preg_match('/aa-shell-container-options-popup[^"]*max-w-full/', $html_all)
    && !preg_match('/aa-shell-container-options-popup[^"]*min-w-0/', $html_all));
ac_assert('All-lists omits visible actions border-t', strpos($html_all, 'border-t border-gray-100') === false);
ac_assert('All-lists omits visible details paragraph', strpos($html_all, 'whitespace-pre-wrap') === false);
ac_assert('All-lists omits visible time', strpos($html_all, '<time') === false);
ac_assert('All-lists keeps details in edit payload', strpos($html_all, 'Detalle') !== false);
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
    'card_family_label' => 'Finanzas',
    'card_family_icon_key' => 'currency',
    'card_announce_family' => false,
    'show_edit_container' => true,
    'card_container_id' => 9,
]);

ac_assert('Monofamily card has SVG', strpos($html_mono, '<svg') !== false);
ac_assert('Monofamily card uses currency path', strpos($html_mono, $path_snippets['currency']) !== false);
ac_assert('Monofamily card has no family sr-only label', strpos($html_mono, 'sr-only">Finanzas') === false);
ac_assert('Monofamily card keeps title link', strpos($html_mono, 'Solo familia') !== false);
ac_assert('Monofamily card keeps options trigger', strpos($html_mono, 'aa-shell-container-options-trigger') !== false);
ac_assert('Monofamily keeps data-aa-container', strpos($html_mono, 'data-aa-container=') !== false);

$html_archive = $render_card([
    'card_title' => 'Archivo demo',
    'card_details' => '',
    'card_records_url' => 'https://example.com/records',
    'card_family_key' => 'archive',
    'card_family_label' => 'Archivo',
    'card_family_icon_key' => 'folder',
    'card_announce_family' => false,
]);

ac_assert('Archive filtered card has folder SVG', strpos($html_archive, $path_snippets['folder']) !== false);
ac_assert('Archive filtered omits family sr-only', strpos($html_archive, 'sr-only">Archivo') === false);

$html_no_url = $render_card([
    'card_title' => 'Sin URL',
    'card_details' => null,
    'card_records_url' => '',
    'card_family_key' => 'finance',
    'card_family_label' => 'Finanzas',
    'card_family_icon_key' => 'currency',
    'card_announce_family' => false,
    'show_edit_container' => true,
    'card_container_id' => 11,
]);
ac_assert('Without records_url omits stretched link class', strpos($html_no_url, 'aa-shell-container-card-link') === false
    && strpos($html_no_url, '<a ') === false);
ac_assert('Without records_url still allows options', strpos($html_no_url, 'aa-shell-container-options-trigger') !== false);

$card_src = (string) file_get_contents($card_partial);
ac_assert('Card no longer renders family name paragraph class', strpos($card_src, 'text-xs text-gray-500 mb-1') === false);
ac_assert('Card uses compact row gap-1', strpos($card_src, 'flex items-center gap-1') !== false);
ac_assert('Card uses indigo icon color', strpos($card_src, 'text-indigo-600') !== false);
ac_assert('Card omits role=menu', strpos($card_src, 'role="menu"') === false);
ac_assert('Card omits role=menuitem', strpos($card_src, 'role="menuitem"') === false);
ac_assert('Card has no onclick', strpos($card_src, 'onclick') === false);
$css_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/assets/css/admin.source.css');
ac_assert('Card CSS defines stretched link after', strpos($css_src, '.aa-shell-container-card-link::after') !== false);
ac_assert('Card CSS rings card on link focus via :has(:focus)', (bool) preg_match(
    '/\.aa-shell-container-card:has\(\.aa-shell-container-card-link:focus\)\s*\{[^}]*ring-2 ring-indigo-500\/40/',
    $css_src
)
    && strpos($css_src, '.aa-shell-container-card:has(.aa-shell-container-card-link:focus-visible)') === false
    && !preg_match('/\.aa-shell-container-card:focus-within\b/', $css_src)
    && !preg_match(
        '/\.aa-shell-container-card:has\(\.aa-shell-container-card-link:focus\)\s*\{[^}]*ring-offset-2/',
        $css_src
    )
    && !preg_match(
        '/\.aa-shell-container-card:has\(\.aa-shell-container-card-link:focus\)\s*\{[^}]*ring-indigo-500(?!\/)/',
        $css_src
    ));
ac_assert('Card focus ring matches record toggle utilities', strpos($css_src, 'focus:ring-2 focus:ring-indigo-500/40') !== false
    && (bool) preg_match(
        '/\.aa-shell-container-card:has\(\.aa-shell-container-card-link:focus\)\s*\{[^}]*@apply ring-2 ring-indigo-500\/40/',
        $css_src
    ));
ac_assert('Card CSS stacks options above stretch', strpos($css_src, '.aa-shell-container-options') !== false
    && (bool) preg_match(
        '/\.aa-shell-container-options\s*\{[^}]*z-20/',
        $css_src
    ));

$index_src = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
);
ac_assert('Index enqueues container options JS', strpos($index_src, 'canonical-shell-container-options.js') !== false);
ac_assert('Index no longer gates icon to all-lists only', strpos($index_src, '$is_all_lists_scope && isset($item[\'family_icon_key\'])') === false);

$composer_src = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php'
);
ac_assert('Composer family view projects family_icon_key', (bool) preg_match(
    "/'family_icon_key'\\s*=>\\s*\\\$manifest->family\\(\\)->icon_key\\(\\)/",
    $composer_src
));

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}
exit(0);
