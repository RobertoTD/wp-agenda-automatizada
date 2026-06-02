<?php
/**
 * AC para AA_Installation_Provisioning_Detector.
 *
 * Ejecutar: php tests/domain/tenant/test-aa-installation-provisioning-detector-ac.php
 *
 * No carga WordPress ni BD.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$GLOBALS['aa_test_options'] = [];

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }

        return $default;
    }
}

require_once __DIR__ . '/../../../includes/domain/tenant/class-aa-installation-provisioning-detector.php';

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo "[ OK ] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo "[FAIL] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
}

function reset_options(): void {
    $GLOBALS['aa_test_options'] = [];
}

reset_options();
ac_assert(
    'no signals returns false',
    AA_Installation_Provisioning_Detector::is_provisioned() === false
);

reset_options();
$GLOBALS['aa_test_options']['deoia_platform_provisioned_at'] = '2026-06-01 12:00:00';
ac_assert(
    'provisioned_at present returns true',
    AA_Installation_Provisioning_Detector::is_provisioned() === true
);

reset_options();
$GLOBALS['aa_test_options']['deoia_platform_slug'] = 'mi-agenda';
$GLOBALS['aa_test_options']['deoia_subscription_request_id'] = 'req-123';
ac_assert(
    'slug and subscription_request_id present returns true',
    AA_Installation_Provisioning_Detector::is_provisioned() === true
);

reset_options();
$GLOBALS['aa_test_options']['deoia_platform_slug'] = 'mi-agenda';
ac_assert(
    'only slug returns false',
    AA_Installation_Provisioning_Detector::is_provisioned() === false
);

reset_options();
$GLOBALS['aa_test_options']['deoia_public_site_status'] = 'maintenance';
ac_assert(
    'only public_site_status maintenance returns false',
    AA_Installation_Provisioning_Detector::is_provisioned() === false
);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
