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
$header_src = file_get_contents($plugin_root . '/includes/admin/ui/shared/header.php');
$sidebar_js_src = file_get_contents($plugin_root . '/includes/admin/ui/assets/js/sidebar.js');

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
    'AA_ADMIN_CONTEXT incluye authSessionId opaco',
    strpos($layout_src, 'authSessionId:') !== false
    && strpos($layout_src, 'wp_json_encode($aa_auth_session_id)') !== false
);
ac_assert(
    'authSessionId deriva de wp_get_session_token con hash_hmac',
    strpos($layout_src, 'wp_get_session_token') !== false
    && strpos($layout_src, 'hash_hmac') !== false
    && strpos($layout_src, "wp_salt('auth')") !== false
);
ac_assert(
    'layout no expone wp_get_session_token en JS',
    strpos($layout_src, 'wp_get_session_token') < strpos($layout_src, 'window.AA_ADMIN_CONTEXT')
    || strpos(substr($layout_src, (int) strpos($layout_src, 'window.AA_ADMIN_CONTEXT')), 'wp_get_session_token') === false
);
ac_assert(
    'layout carga tutorialSessionSuppression antes del coordinator',
    strpos($layout_src, 'tutorialSessionSuppression.js') !== false
    && strpos($layout_src, 'tutorialSessionSuppression.js') < strpos($layout_src, 'tutorialCoordinator.js')
);
ac_assert(
    'sidebar Agenda tiene data-aa-nav-module calendar',
    preg_match('/data-aa-nav-module="calendar"[\s\S]*module=calendar/', $sidebar_src) === 1
    || preg_match('/module=calendar[\s\S]*data-aa-nav-module="calendar"/', $sidebar_src) === 1
);
ac_assert(
    'sidebar marca el activo con aria-current=page',
    strpos($sidebar_src, 'aria-current="page"') !== false
    && substr_count($sidebar_src, 'aria-current="page"') >= 7
    && preg_match(
        '/\$active_module === \'calendar\'\) \? \'aria-current="page"/',
        $sidebar_src
    ) === 1
);
ac_assert(
    'header expone nodo aa-page-title truncable',
    strpos($header_src, 'id="aa-page-title"') !== false
    && strpos($header_src, 'truncate') !== false
);
ac_assert(
    'sidebar.js sincroniza título con data-aa-page-title antes que aria-current',
    strpos($sidebar_js_src, 'syncHeaderPageTitle') !== false
    && strpos($sidebar_js_src, 'data-aa-page-title') !== false
    && strpos($sidebar_js_src, 'aria-current="page"') !== false
    && strpos($sidebar_js_src, 'data-aa-page-title') < strpos($sidebar_js_src, 'aria-current="page"')
);
ac_assert(
    'layout carga tutorial.js antes del coordinator',
    strpos($layout_src, 'includes/admin/ui/tutorials/tutorial.js') !== false
    && strpos($layout_src, 'tutorial.js') < strpos($layout_src, 'onboardingActivationCoordinator.js')
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
