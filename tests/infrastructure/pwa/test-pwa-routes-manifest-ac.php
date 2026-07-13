<?php
/**
 * AC — PWA manifest branding (name / short_name).
 *
 * Ejecutar: php tests/infrastructure/pwa/test-pwa-routes-manifest-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$pwa_routes_src = file_get_contents($plugin_root . '/includes/infrastructure/pwa/PwaRoutes.php');
$layout_src     = file_get_contents($plugin_root . '/includes/admin/ui/shared/layout.php');

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
    'manifest name is DEOIA',
    preg_match("/'name'\s*=>\s*'DEOIA'/", $pwa_routes_src) === 1
);
ac_assert(
    'manifest short_name is DEOIA',
    preg_match("/'short_name'\s*=>\s*'DEOIA'/", $pwa_routes_src) === 1
);
ac_assert(
    'manifest name is not legacy DEOIA Citas',
    strpos($pwa_routes_src, "'name'             => 'DEOIA Citas'") === false
);
ac_assert(
    'manifest short_name is not legacy Citas',
    strpos($pwa_routes_src, "'short_name'       => 'Citas'") === false
);
ac_assert(
    'layout exposes application-name meta',
    strpos($layout_src, '<meta name="application-name" content="DEOIA">') !== false
);
ac_assert(
    'layout exposes apple-mobile-web-app-title meta',
    strpos($layout_src, '<meta name="apple-mobile-web-app-title" content="DEOIA">') !== false
);
ac_assert(
    'service worker injects __AA_PUSH_API_BASE__ via wp_json_encode',
    strpos($pwa_routes_src, 'self.__AA_PUSH_API_BASE__') !== false
    && strpos($pwa_routes_src, 'wp_json_encode($api_base') !== false
);
ac_assert(
    'service worker resolves push API base from AA_API_BASE_URL',
    strpos($pwa_routes_src, 'resolve_push_api_base_for_sw') !== false
    && strpos($pwa_routes_src, 'AA_API_BASE_URL') !== false
);
ac_assert(
    'service worker encodes API base with wp_json_encode before echo',
    preg_match(
        '/echo\s+[\'"]self\.__AA_PUSH_API_BASE__\s*=\s*[\'"]\s*\.\s*wp_json_encode\(\$api_base/',
        $pwa_routes_src
    ) === 1
    && strpos($pwa_routes_src, 'echo \'self.__AA_PUSH_API_BASE__ = \' . AA_API_BASE_URL') === false
    && strpos($pwa_routes_src, 'echo "self.__AA_PUSH_API_BASE__ = " . AA_API_BASE_URL') === false
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
