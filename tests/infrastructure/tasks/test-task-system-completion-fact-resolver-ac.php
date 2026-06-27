<?php
/**
 * AC MC13O-E1 — TaskSystemCompletionFactResolver.
 *
 * Ejecutar: php tests/infrastructure/tasks/test-task-system-completion-fact-resolver-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$root = dirname(__DIR__, 3);
$resolver_file = $root . '/includes/infrastructure/tasks/TaskSystemCompletionFactResolver.php';

$GLOBALS['aa_test_options'] = [];
$GLOBALS['aa_test_assignment_counts'] = [
    'services' => 0,
    'areas' => 0,
    'staff_with_services' => 0,
];
$GLOBALS['aa_test_registered_clients'] = 0;
$GLOBALS['aa_test_google_connected'] = false;

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        if (array_key_exists($key, $GLOBALS['aa_test_options'])) {
            return $GLOBALS['aa_test_options'][$key];
        }

        return $default;
    }
}

if (!class_exists('SyncService')) {
    class SyncService {
        public static function has_google_connection(): bool {
            return (bool) $GLOBALS['aa_test_google_connected'];
        }
    }
}

if (!class_exists('AssignmentsRepository')) {
    class AssignmentsRepository {
        public static function count_active_services(): int {
            return (int) $GLOBALS['aa_test_assignment_counts']['services'];
        }

        public static function count_active_service_areas(): int {
            return (int) $GLOBALS['aa_test_assignment_counts']['areas'];
        }

        public static function count_active_staff_with_active_services(): int {
            return (int) $GLOBALS['aa_test_assignment_counts']['staff_with_services'];
        }
    }
}

if (!class_exists('ClientsRepository')) {
    class ClientsRepository {
        public static function count_registered_clients(): int {
            return (int) $GLOBALS['aa_test_registered_clients'];
        }
    }
}

require_once $resolver_file;

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

$resolver_src = file_get_contents($resolver_file);
ac_assert('Resolver file readable', $resolver_src !== false);
ac_assert('Resolver defines TaskSystemCompletionFactResolver', strpos($resolver_src, 'class TaskSystemCompletionFactResolver') !== false);
ac_assert('Resolver exposes resolve_all', strpos($resolver_src, 'function resolve_all') !== false);
ac_assert('Resolver does not touch LearningRecommendationStateRepository', strpos($resolver_src, 'LearningRecommendationStateRepository') === false);
ac_assert('Resolver exposes is_business_data_complete', strpos($resolver_src, 'function is_business_data_complete') !== false);
ac_assert('Resolver does not touch visibility policy', strpos($resolver_src, 'AA_Learning_Visibility_Policy') === false);

$facts = TaskSystemCompletionFactResolver::resolve_all();
$expected_keys = [
    'google_connected',
    'business_data_complete',
    'has_active_service',
    'has_active_area',
    'has_staff_with_service',
    'has_registered_client',
];
ac_assert('resolve_all returns array', is_array($facts));
ac_assert(
    'resolve_all contains expected fact keys',
    array_keys($facts) === $expected_keys
);

$GLOBALS['aa_test_google_connected'] = true;
$GLOBALS['aa_test_options'] = [
    'aa_business_name' => 'Negocio',
    'aa_business_address' => 'Calle 1',
];
$GLOBALS['aa_test_assignment_counts'] = [
    'services' => 2,
    'areas' => 1,
    'staff_with_services' => 3,
];
$GLOBALS['aa_test_registered_clients'] = 4;
$all_true = TaskSystemCompletionFactResolver::resolve_all();
ac_assert('google_connected resolves true when connected', ($all_true['google_connected'] ?? false) === true);
ac_assert('business_data_complete resolves true with name and address', ($all_true['business_data_complete'] ?? false) === true);
ac_assert('has_active_service resolves true with services', ($all_true['has_active_service'] ?? false) === true);
ac_assert('has_registered_client resolves true with clients', ($all_true['has_registered_client'] ?? false) === true);

$GLOBALS['aa_test_options'] = [
    'aa_business_name' => 'Negocio Virtual',
    'aa_business_address' => '',
    'aa_is_virtual' => 1,
];
$name_and_virtual = TaskSystemCompletionFactResolver::resolve_all();
ac_assert(
    'business_data_complete resolves true with name and virtual',
    ($name_and_virtual['business_data_complete'] ?? false) === true
);

$GLOBALS['aa_test_options'] = [
    'aa_business_name' => 'Solo nombre',
    'aa_business_address' => '',
    'aa_is_virtual' => 0,
];
$name_only = TaskSystemCompletionFactResolver::resolve_all();
ac_assert(
    'business_data_complete resolves false with name only',
    ($name_only['business_data_complete'] ?? true) === false
);

$GLOBALS['aa_test_options'] = [
    'aa_business_name' => '',
    'aa_business_address' => '',
    'aa_is_virtual' => 1,
];
$virtual_without_name = TaskSystemCompletionFactResolver::resolve_all();
ac_assert(
    'business_data_complete resolves false with virtual without name',
    ($virtual_without_name['business_data_complete'] ?? true) === false
);

$GLOBALS['aa_test_google_connected'] = false;
$GLOBALS['aa_test_options'] = ['aa_business_name' => '', 'aa_business_address' => ''];
$GLOBALS['aa_test_assignment_counts'] = ['services' => 0, 'areas' => 0, 'staff_with_services' => 0];
$GLOBALS['aa_test_registered_clients'] = 0;
$all_false = TaskSystemCompletionFactResolver::resolve_all();
ac_assert('google_connected resolves false when disconnected', ($all_false['google_connected'] ?? true) === false);
ac_assert('business_data_complete resolves false without business data', ($all_false['business_data_complete'] ?? true) === false);
ac_assert('has_active_service resolves false without services', ($all_false['has_active_service'] ?? true) === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
