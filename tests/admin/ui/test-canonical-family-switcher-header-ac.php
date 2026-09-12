<?php
/**
 * AC Test — Canonical family switcher in shared header.
 *
 * Ejecutar: php tests/admin/ui/test-canonical-family-switcher-header-ac.php
 */

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

$header = (string) file_get_contents($plugin_root . '/includes/admin/ui/shared/header.php');
$layout = (string) file_get_contents($plugin_root . '/includes/admin/ui/shared/canonical-layout.php');
$sidebar = (string) file_get_contents($plugin_root . '/includes/admin/ui/shared/sidebar.php');
$sidebar_js = (string) file_get_contents($plugin_root . '/includes/admin/ui/assets/js/sidebar.js');
$switcher_js = (string) file_get_contents($plugin_root . '/includes/admin/ui/assets/js/canonical-family-switcher.js');
$nav_src = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-enablement-nav.php'
);
$css_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/assets/css/admin.source.css');

ac_assert(
    'Layout iza Enablement_Nav antes del header',
    strpos($layout, 'AA_Canonical_Family_Enablement_Nav::build') !== false
    && strpos($layout, 'AA_Canonical_Family_Enablement_Nav::build') < strpos($layout, 'header.php')
);

ac_assert(
    'Layout carga switcher JS solo en canonical_shell',
    strpos($layout, "canonical-family-switcher.js") !== false
    && strpos($layout, "\$active_module === 'canonical_shell'") !== false
    && preg_match(
        '/\$active_module === \'canonical_shell\'[\s\S]*?canonical-family-switcher\.js/',
        $layout
    ) === 1
);

ac_assert(
    'Sidebar reutiliza nav izado sin forzar rebuild',
    strpos($sidebar, '!isset($aa_canonical_record_types_nav)') !== false
    || strpos($sidebar, '!isset($aa_canonical_record_types_nav) || !is_array') !== false
);

ac_assert(
    'Header gates switcher a canonical_shell + resolved',
    strpos($header, "\$active_module === 'canonical_shell'") !== false
    && strpos($header, "\$aa_shell_route_state === 'resolved'") !== false
    && strpos($header, 'AA_Canonical_Family_Definition') !== false
);

ac_assert(
    'Header emite modos family-switcher y family-static',
    strpos($header, 'data-aa-title-mode="family-switcher"') !== false
    && strpos($header, 'data-aa-title-mode="family-static"') !== false
);

ac_assert(
    'Switcher es disclosure (button + panel + aria), sin role=menu',
    strpos($header, 'id="aa-family-switcher"') !== false
    && strpos($header, 'aria-controls="aa-family-switcher-panel"') !== false
    && strpos($header, 'data-aa-title-mode="family-switcher"') !== false
    && strpos($header, 'aria-current="page"') !== false
    && preg_match(
        '/id="aa-family-switcher"[\s\S]*?id="aa-family-switcher-panel"[\s\S]*?<\/div>\s*<\/div>/',
        $header,
        $switcher_block
    ) === 1
    && strpos($switcher_block[0], 'role="menu"') === false
    && strpos($switcher_block[0], 'role="menuitem"') === false
    && strpos($switcher_block[0], 'aria-haspopup="menu"') === false
    && strpos($switcher_block[0], 'aria-expanded="false"') !== false
);

ac_assert(
    'Header aria-label del panel es Filtrar listas',
    strpos($header, 'aria-label="Filtrar listas"') !== false
    && strpos($header, 'aria-label="Tipos de registros"') === false
);

ac_assert(
    'Header incluye opción Todas las listas vía build_module_url (N≥2)',
    strpos($header, 'Todas las listas') !== false
    && strpos($header, 'AA_Canonical_Shell_Base_Url_Policy::build_module_url') !== false
    && strpos($header, '$aa_switcher_all_key') !== false
    && strpos($header, '$aa_family_nav_count === 1') !== false
);

ac_assert(
    'Header no hardcodea finance/archive en el switcher',
    !preg_match('/family-switcher[\s\S]*[\'"]finance[\'"]/', $header)
    && !preg_match('/family-switcher[\s\S]*[\'"]archive[\'"]/', $header)
    && strpos($header, '$aa_canonical_record_types_nav') !== false
);

ac_assert(
    'Header resuelve iconos de familia vía markup canónico (no Todas)',
    strpos($header, 'AA_Canonical_Family_Icon_Markup') !== false
    && strpos($header, '$aa_switcher_all_key') !== false
    && strpos($header, 'icon_key') !== false
);

ac_assert(
    'Nav builder usa Shell Base URL Policy',
    strpos($nav_src, 'AA_Canonical_Shell_Base_Url_Policy::build_url') !== false
    && strpos($nav_src, 'variant') === false
);

ac_assert(
    'syncHeaderPageTitle respeta modos SSR',
    strpos($sidebar_js, "family-switcher") !== false
    && strpos($sidebar_js, "family-static") !== false
    && strpos($sidebar_js, 'data-aa-title-mode') !== false
);

ac_assert(
    'Switcher JS es disclosure sin role menu',
    strpos($switcher_js, 'aa-family-switcher') !== false
    && strpos($switcher_js, 'Escape') !== false
    && strpos($switcher_js, "aria-current=\"page\"") !== false
    && strpos($switcher_js, 'role="menu"') === false
    && strpos($switcher_js, 'menuitem') === false
);

ac_assert(
    'CSS del switcher sin caret permanente',
    strpos($css_src, '.aa-family-switcher-trigger') !== false
    && !preg_match('/aa-family-switcher-trigger[\s\S]{0,200}chevron/', $css_src)
);

// Runtime render: ≥1 familia → switcher (Todas + familias); 0 sin familia → static Todas.
if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
if (!function_exists('esc_html')) {
    function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($t) { return (string) $t; }
}
if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args): string {
        if (count($args) === 2 && is_array($args[0])) {
            $query = http_build_query($args[0]);
            $url = $args[1];
        } elseif (count($args) === 3) {
            $query = http_build_query([$args[0] => $args[1]]);
            $url = $args[2];
        } else {
            return '';
        }
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . $query;
    }
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/class-aa-canonical-family-icon-markup.php';

function aa_render_header_fixture(array $vars): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__, 3) . '/includes/admin/ui/shared/header.php';
    return (string) ob_get_clean();
}

$family_archive = new AA_Canonical_Family_Definition('archive', 'Archivo', 'folder');
$nav_two = [
    [
        'family_key' => 'finance',
        'label' => 'Finanzas',
        'url' => 'https://example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=finance',
        'icon_key' => 'currency',
    ],
    [
        'family_key' => 'archive',
        'label' => 'Archivo',
        'url' => 'https://example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=archive',
        'icon_key' => 'folder',
    ],
];
$nav_one = [
    [
        'family_key' => 'archive',
        'label' => 'Archivo',
        'url' => 'https://example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&family=archive',
        'icon_key' => 'folder',
    ],
];

$html_switcher = aa_render_header_fixture([
    'active_module' => 'canonical_shell',
    'aa_shell_route_state' => 'resolved',
    'aa_canonical_family' => $family_archive,
    'aa_canonical_record_types_nav' => $nav_two,
]);
ac_assert(
    'Runtime ≥2 familias → switcher con Todas + current familia',
    strpos($html_switcher, 'data-aa-title-mode="family-switcher"') !== false
    && strpos($html_switcher, 'id="aa-family-switcher-panel"') !== false
    && strpos($html_switcher, '>Archivo</button>') !== false
    && strpos($html_switcher, '>Todas las listas</span>') !== false
    && strpos($html_switcher, 'family=finance') !== false
    && strpos($html_switcher, 'family=archive') !== false
    && strpos($html_switcher, 'variant=') === false
    && strpos($html_switcher, 'aria-label="Filtrar listas"') !== false
    && preg_match('/aria-current="page"[^>]*>[\s\S]*?Archivo</', $html_switcher) === 1
);

$currency_path = 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2';
$folder_path = 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z';
ac_assert(
    'Runtime switcher: familias llevan SVG; Todas no',
    strpos($html_switcher, $currency_path) !== false
    && strpos($html_switcher, $folder_path) !== false
    && preg_match(
        '/aa-family-switcher-link[^>]*>[\s\S]*?Todas las listas<\/span>/',
        $html_switcher,
        $todas_link
    ) === 1
    && strpos($todas_link[0], '<svg') === false
);

$html_one = aa_render_header_fixture([
    'active_module' => 'canonical_shell',
    'aa_shell_route_state' => 'resolved',
    'aa_canonical_family' => $family_archive,
    'aa_canonical_record_types_nav' => $nav_one,
]);
ac_assert(
    'Runtime 1 familia → título estático de esa familia (sin Todas ni switcher)',
    strpos($html_one, 'data-aa-title-mode="family-static"') !== false
    && strpos($html_one, 'aa-family-switcher') === false
    && strpos($html_one, '>Archivo</span>') !== false
    && strpos($html_one, '>Todas las listas<') === false
);

$html_all_scope = aa_render_header_fixture([
    'active_module' => 'canonical_shell',
    'aa_shell_route_state' => 'resolved',
    'aa_shell_view' => ['lists_scope' => 'all'],
    'aa_canonical_record_types_nav' => $nav_two,
]);
ac_assert(
    'Runtime alcance Todas → current Todas las listas',
    strpos($html_all_scope, 'data-aa-title-mode="family-switcher"') !== false
    && strpos($html_all_scope, '>Todas las listas</button>') !== false
    && preg_match('/aria-current="page"[^>]*>[\s\S]*?Todas las listas</', $html_all_scope) === 1
);

$html_records_return = aa_render_header_fixture([
    'active_module' => 'canonical_shell',
    'aa_shell_route_state' => 'resolved',
    'aa_shell_view' => ['lists_scope' => 'all'],
    'aa_canonical_family' => $family_archive,
    'aa_canonical_record_types_nav' => $nav_two,
]);
ac_assert(
    'Runtime records con lists_scope=all → current familia (no Todas)',
    strpos($html_records_return, '>Archivo</button>') !== false
    && preg_match('/aria-current="page"[^>]*>[\s\S]*?Archivo</', $html_records_return) === 1
    && preg_match('/aria-current="page"[^>]*>[\s\S]*?Todas las listas</', $html_records_return) !== 1
);

$html_preview = aa_render_header_fixture([
    'active_module' => 'canonical_shell',
    'aa_shell_route_state' => 'preview',
    'aa_canonical_family' => $family_archive,
    'aa_canonical_record_types_nav' => $nav_two,
]);
ac_assert(
    'Runtime preview → sync span (sin SSR family)',
    strpos($html_preview, 'data-aa-title-mode') === false
    && strpos($html_preview, 'aa-family-switcher') === false
    && preg_match('/id="aa-page-title"[^>]*hidden/', $html_preview) === 1
);

$html_disabled = aa_render_header_fixture([
    'active_module' => 'canonical_shell',
    'aa_shell_route_state' => 'family_disabled',
    'aa_canonical_family' => $family_archive,
    'aa_canonical_record_types_nav' => $nav_two,
]);
ac_assert(
    'Runtime family_disabled → sync span',
    strpos($html_disabled, 'data-aa-title-mode') === false
    && strpos($html_disabled, 'aa-family-switcher') === false
);

$html_calendar = aa_render_header_fixture([
    'active_module' => 'calendar',
    'aa_shell_route_state' => 'resolved',
    'aa_canonical_family' => $family_archive,
    'aa_canonical_record_types_nav' => $nav_two,
]);
ac_assert(
    'Runtime calendar → título sync intacto',
    strpos($html_calendar, 'data-aa-title-mode') === false
    && strpos($html_calendar, 'aa-family-switcher') === false
    && preg_match('/id="aa-page-title"[^>]*hidden/', $html_calendar) === 1
);

$html_zero = aa_render_header_fixture([
    'active_module' => 'canonical_shell',
    'aa_shell_route_state' => 'resolved',
    'aa_shell_view' => ['lists_scope' => 'all'],
    'aa_canonical_record_types_nav' => [],
]);
ac_assert(
    'Runtime 0 familias en alcance general → título estático Todas',
    strpos($html_zero, 'data-aa-title-mode="family-static"') !== false
    && strpos($html_zero, 'aa-family-switcher') === false
    && strpos($html_zero, '>Todas las listas</span>') !== false
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
