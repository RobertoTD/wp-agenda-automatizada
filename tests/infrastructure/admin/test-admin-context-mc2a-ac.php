<?php
/**
 * AC — MC2A admin context + stable Agenda nav selector.
 *
 * Ejecutar: php tests/infrastructure/admin/test-admin-context-mc2a-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$layout_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/layout.php');
$sidebar_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/sidebar.php');

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

ac_assert(
    'layout expone window.AA_ADMIN_CONTEXT',
    strpos($layout_src, 'window.AA_ADMIN_CONTEXT') !== false
);
ac_assert(
    'AA_ADMIN_CONTEXT incluye currentModule desde active_module',
    strpos($layout_src, 'currentModule:') !== false
    && strpos($layout_src, 'wp_json_encode($active_module)') !== false
);
ac_assert(
    'AA_ADMIN_CONTEXT incluye blogId site-scoped',
    strpos($layout_src, 'blogId:') !== false
    && strpos($layout_src, 'get_current_blog_id()') !== false
);
ac_assert(
    'AA_ADMIN_CONTEXT incluye installationSlug desde AA_Installation_Display_Slug',
    strpos($layout_src, 'installationSlug:') !== false
    && strpos($layout_src, 'wp_json_encode($aa_installation_slug)') !== false
);
ac_assert(
    'sidebar Agenda tiene data-aa-nav-module calendar',
    preg_match('/data-aa-nav-module="calendar"[\s\S]*module=calendar/', $sidebar_src) === 1
    || preg_match('/module=calendar[\s\S]*data-aa-nav-module="calendar"/', $sidebar_src) === 1
);

echo "\n{$passed}/{$total} passed.\n";

if ($failed !== []) {
    echo "Failed:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
