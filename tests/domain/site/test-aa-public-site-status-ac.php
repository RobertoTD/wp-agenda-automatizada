<?php
/**
 * AC para AA_Public_Site_Status.
 *
 * Ejecutar: php tests/domain/site/test-aa-public-site-status-ac.php
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

require_once __DIR__ . '/../../../includes/domain/site/class-aa-public-site-status.php';

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

// normalize()
ac_assert(
    'normalize absent resolves active',
    AA_Public_Site_Status::normalize(null) === AA_Public_Site_Status::STATUS_ACTIVE
);
ac_assert(
    'normalize empty resolves active',
    AA_Public_Site_Status::normalize('') === AA_Public_Site_Status::STATUS_ACTIVE
);
ac_assert(
    'normalize invalid resolves active',
    AA_Public_Site_Status::normalize('coming_soon') === AA_Public_Site_Status::STATUS_ACTIVE
);
ac_assert(
    'normalize active resolves active',
    AA_Public_Site_Status::normalize('active') === AA_Public_Site_Status::STATUS_ACTIVE
);
ac_assert(
    'normalize maintenance resolves maintenance',
    AA_Public_Site_Status::normalize('maintenance') === AA_Public_Site_Status::STATUS_MAINTENANCE
);

// current() via get_option mock
reset_options();
ac_assert(
    'current option absent resolves active',
    AA_Public_Site_Status::current() === AA_Public_Site_Status::STATUS_ACTIVE
);

reset_options();
$GLOBALS['aa_test_options'][AA_Public_Site_Status::OPTION] = '';
ac_assert(
    'current option empty resolves active',
    AA_Public_Site_Status::current() === AA_Public_Site_Status::STATUS_ACTIVE
);

reset_options();
$GLOBALS['aa_test_options'][AA_Public_Site_Status::OPTION] = 'invalid';
ac_assert(
    'current option invalid resolves active',
    AA_Public_Site_Status::current() === AA_Public_Site_Status::STATUS_ACTIVE
);

reset_options();
$GLOBALS['aa_test_options'][AA_Public_Site_Status::OPTION] = AA_Public_Site_Status::STATUS_ACTIVE;
ac_assert(
    'current option active resolves active',
    AA_Public_Site_Status::current() === AA_Public_Site_Status::STATUS_ACTIVE
);
ac_assert(
    'is_maintenance false when active',
    AA_Public_Site_Status::is_maintenance() === false
);

reset_options();
$GLOBALS['aa_test_options'][AA_Public_Site_Status::OPTION] = AA_Public_Site_Status::STATUS_MAINTENANCE;
ac_assert(
    'current option maintenance resolves maintenance',
    AA_Public_Site_Status::current() === AA_Public_Site_Status::STATUS_MAINTENANCE
);
ac_assert(
    'is_maintenance true when maintenance',
    AA_Public_Site_Status::is_maintenance() === true
);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
