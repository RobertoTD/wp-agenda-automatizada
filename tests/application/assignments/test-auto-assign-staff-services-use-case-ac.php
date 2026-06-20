<?php
/**
 * AC — AutoAssignStaffServicesUseCase.
 *
 * Ejecutar: php tests/application/assignments/test-auto-assign-staff-services-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

$plugin_root = dirname(__DIR__, 3);
$use_case_file = $plugin_root . '/includes/application/assignments/AutoAssignStaffServicesUseCase.php';

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

require_once $use_case_file;

$use_case_src = file_get_contents($use_case_file);
ac_assert('Use case file readable', $use_case_src !== false);
ac_assert('Use case loads AssignmentsRepository', strpos($use_case_src, 'AssignmentsRepository') !== false);
ac_assert('Use case supports staff_created trigger', strpos($use_case_src, "'staff_created'") !== false);
ac_assert('Use case supports service_created trigger', strpos($use_case_src, 'service_created') !== false);

$staff_service_file = file_get_contents($plugin_root . '/includes/services/assignments/staffService.php');
$services_service_file = file_get_contents($plugin_root . '/includes/services/assignments/servicesService.php');
ac_assert('staffService wires AutoAssignStaffServicesUseCase', strpos($staff_service_file, 'AutoAssignStaffServicesUseCase') !== false);
ac_assert('staffService returns auto_assign payload', strpos($staff_service_file, "'auto_assign'") !== false);
ac_assert('servicesService wires AutoAssignStaffServicesUseCase', strpos($services_service_file, 'AutoAssignStaffServicesUseCase') !== false);
ac_assert('servicesService returns auto_assign payload', strpos($services_service_file, "'auto_assign'") !== false);

$links = [];

$disabled = (new AutoAssignStaffServicesUseCase(
    static function (): bool {
        return false;
    }
))->execute([
    'trigger' => 'staff_created',
    'staff_id' => 10,
]);

ac_assert(
    'Option OFF returns no-op',
    $disabled['enabled'] === false
    && $disabled['created'] === 0
    && $disabled['skipped'] === 0
    && $disabled['errors'] === []
);

$staff_created = (new AutoAssignStaffServicesUseCase(
    static function (): bool {
        return true;
    },
    static function (): array {
        return [101, 102];
    },
    static function (): array {
        return [];
    },
    static function (int $staff_id, int $service_id) use (&$links): string {
        $key = $staff_id . ':' . $service_id;

        if (isset($links[$key])) {
            return 'skipped';
        }

        $links[$key] = true;

        return 'created';
    }
))->execute([
    'trigger' => 'staff_created',
    'staff_id' => 5,
]);

ac_assert(
    'Option ON staff_created assigns all assignable services',
    $staff_created['enabled'] === true
    && $staff_created['created'] === 2
    && $staff_created['skipped'] === 0
    && $staff_created['errors'] === []
);

$service_created = (new AutoAssignStaffServicesUseCase(
    static function (): bool {
        return true;
    },
    static function (): array {
        return [201];
    },
    static function (): array {
        return [11, 12];
    },
    static function (int $staff_id, int $service_id) use (&$links): string {
        $key = $staff_id . ':' . $service_id;

        if (isset($links[$key])) {
            return 'skipped';
        }

        $links[$key] = true;

        return 'created';
    },
    static function (int $service_id): bool {
        return $service_id === 201;
    }
))->execute([
    'trigger' => 'service_created',
    'service_id' => 201,
]);

ac_assert(
    'Option ON service_created assigns to all active staff',
    $service_created['enabled'] === true
    && $service_created['created'] === 2
    && $service_created['skipped'] === 0
    && $service_created['errors'] === []
);

$second_run = (new AutoAssignStaffServicesUseCase(
    static function (): bool {
        return true;
    },
    static function (): array {
        return [101, 102];
    },
    static function (): array {
        return [];
    },
    static function (int $staff_id, int $service_id) use (&$links): string {
        $key = $staff_id . ':' . $service_id;

        if (isset($links[$key])) {
            return 'skipped';
        }

        $links[$key] = true;

        return 'created';
    }
))->execute([
    'trigger' => 'staff_created',
    'staff_id' => 5,
]);

ac_assert(
    'Second execution does not duplicate links',
    $second_run['enabled'] === true
    && $second_run['created'] === 0
    && $second_run['skipped'] === 2
    && $second_run['errors'] === []
);

$non_assignable_service = (new AutoAssignStaffServicesUseCase(
    static function (): bool {
        return true;
    },
    static function (): array {
        return [301];
    },
    static function (): array {
        return [21];
    },
    static function (): string {
        return 'created';
    },
    static function (): bool {
        return false;
    }
))->execute([
    'trigger' => 'service_created',
    'service_id' => 999,
]);

ac_assert(
    'Non-assignable service_created is no-op without errors',
    $non_assignable_service['enabled'] === true
    && $non_assignable_service['created'] === 0
    && $non_assignable_service['skipped'] === 0
    && $non_assignable_service['errors'] === []
);

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
