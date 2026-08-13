<?php
/**
 * AC — UpdateStaffUseCase (edición atómica nombre + servicios).
 *
 * Ejecutar: php tests/application/assignments/test-update-staff-use-case-ac.php
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
$use_case_file = $plugin_root . '/includes/application/assignments/UpdateStaffUseCase.php';
$repo_file = $plugin_root . '/includes/repositories/AssignmentsRepository.php';

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
$repo_src = file_get_contents($repo_file);

ac_assert('Use case file readable', $use_case_src !== false && $use_case_src !== '');
ac_assert('Use case loads AssignmentsRepository', strpos($use_case_src, 'AssignmentsRepository') !== false);
ac_assert('Use case skips write when nothing changed', strpos($use_case_src, 'name_unchanged') !== false);
ac_assert('Repository defines find_staff_by_id', strpos($repo_src, 'function find_staff_by_id') !== false);
ac_assert('Repository defines update_staff_name_and_services', strpos($repo_src, 'function update_staff_name_and_services') !== false);
ac_assert('Repository starts transaction', strpos($repo_src, "START TRANSACTION") !== false);
ac_assert('Repository commits', strpos($repo_src, "COMMIT") !== false);
ac_assert('Repository rollbacks', strpos($repo_src, 'ROLLBACK') !== false);
ac_assert(
    'Repository treats only update false as SQL error',
    preg_match(
        "/function update_staff_name_and_services[\s\S]*?\\\$updated === false[\s\S]*?ROLLBACK/",
        $repo_src
    ) === 1
);
ac_assert(
    'Repository does not treat update === 0 as error',
    preg_match(
        "/function update_staff_name_and_services[\s\S]*?\\\$updated === 0/",
        $repo_src
    ) !== 1
);
ac_assert(
    'Repository update only writes name',
    preg_match(
        "/function update_staff_name_and_services[\s\S]*?\\\$wpdb->update\([\s\S]*?\[[\s\S]*?'name'[\s\S]*?\]/",
        $repo_src
    ) === 1
);
ac_assert(
    'Repository update does not write active or is_hidden values',
    preg_match(
        "/function update_staff_name_and_services[\s\S]*?\\\$wpdb->update\([\s\S]*?'active'\s*=>[\s\S]*?\);/",
        $repo_src
    ) !== 1
);

$staff = [
    5 => [
        'id' => 5,
        'name' => 'Ana López',
        'active' => 1,
        'created_at' => '2024-01-01 00:00:00',
    ],
];
$links = [
    5 => [10, 20],
];
$service_names = [
    10 => 'Consulta',
    20 => 'Control',
    30 => 'Vacuna',
    40 => 'Inactivo histórico',
];
$assignable = [10, 20, 30];
$update_calls = [];

$make_use_case = static function (
    ?callable $update = null
) use (&$staff, &$links, &$service_names, &$assignable, &$update_calls): UpdateStaffUseCase {
    return new UpdateStaffUseCase(
        static function (int $id) use (&$staff): ?array {
            return $staff[$id] ?? null;
        },
        static function (int $id) use (&$links): array {
            return $links[$id] ?? [];
        },
        static function () use (&$assignable): array {
            return $assignable;
        },
        static function (int $id) use (&$links, &$service_names): array {
            $services = [];
            foreach ($links[$id] ?? [] as $service_id) {
                $services[] = [
                    'id' => $service_id,
                    'name' => $service_names[$service_id] ?? ('Servicio ' . $service_id),
                ];
            }
            return $services;
        },
        $update ?? static function (int $id, string $name, array $to_add, array $to_remove) use (&$staff, &$links, &$update_calls): bool {
            $update_calls[] = [
                'id' => $id,
                'name' => $name,
                'to_add' => $to_add,
                'to_remove' => $to_remove,
            ];
            $staff[$id]['name'] = $name;
            $current = $links[$id] ?? [];
            foreach ($to_remove as $service_id) {
                $current = array_values(array_filter($current, static function ($existing) use ($service_id) {
                    return (int) $existing !== (int) $service_id;
                }));
            }
            foreach ($to_add as $service_id) {
                if (!in_array((int) $service_id, $current, true)) {
                    $current[] = (int) $service_id;
                }
            }
            $links[$id] = $current;
            return true;
        }
    );
};

$use_case = $make_use_case();

$invalid_id = $use_case->execute(['id' => 0, 'name' => 'Ana', 'service_ids' => [10]]);
ac_assert('invalid id fails', ($invalid_id['success'] ?? true) === false && ($invalid_id['error']['code'] ?? '') === 'invalid_id');

$empty_name = $use_case->execute(['id' => 5, 'name' => '   ', 'service_ids' => [10]]);
ac_assert('empty name fails', ($empty_name['success'] ?? true) === false && ($empty_name['error']['code'] ?? '') === 'invalid_name');

$missing = $use_case->execute(['id' => 99, 'name' => 'Ana', 'service_ids' => [10]]);
ac_assert('missing staff fails', ($missing['success'] ?? true) === false && ($missing['error']['code'] ?? '') === 'not_found');

$update_calls = [];
$identical = $use_case->execute([
    'id' => 5,
    'name' => 'Ana López',
    'service_ids' => [20, 10, 10],
]);
ac_assert('identical save succeeds', ($identical['success'] ?? false) === true);
ac_assert('identical save does not write', $update_calls === []);
ac_assert('identical save keeps name', ($identical['data']['staff']['name'] ?? '') === 'Ana López');
ac_assert('identical save keeps services', ($identical['data']['staff']['services'][0]['id'] ?? 0) === 10
    && ($identical['data']['staff']['services'][1]['id'] ?? 0) === 20);
ac_assert('identical added_count is 0', ($identical['data']['added_count'] ?? -1) === 0);
ac_assert('identical in-memory links unchanged', $links[5] === [10, 20]);

$update_calls = [];
$services_only = $use_case->execute([
    'id' => 5,
    'name' => 'Ana López',
    'service_ids' => [10, 30],
]);
ac_assert('same name with service changes succeeds', ($services_only['success'] ?? false) === true);
ac_assert('services-only performs one write', count($update_calls) === 1);
ac_assert('services-only adds 30', ($update_calls[0]['to_add'] ?? []) === [30]);
ac_assert('services-only removes 20', ($update_calls[0]['to_remove'] ?? []) === [20]);
ac_assert('services-only keeps name', ($services_only['data']['staff']['name'] ?? '') === 'Ana López');
ac_assert('services-only added_count is 1', ($services_only['data']['added_count'] ?? 0) === 1);

$staff[5]['name'] = 'Ana López';
$links[5] = [10, 40];
$update_calls = [];
$keep_historical = $use_case->execute([
    'id' => 5,
    'name' => 'Ana López',
    'service_ids' => [10, 40],
]);
ac_assert('keeps inactive assigned service without write', ($keep_historical['success'] ?? false) === true && $update_calls === []);
ac_assert('historical service still linked', $links[5] === [10, 40]);

$update_calls = [];
$remove_historical = $use_case->execute([
    'id' => 5,
    'name' => 'Ana López',
    'service_ids' => [10],
]);
ac_assert('can remove inactive assigned service', ($remove_historical['success'] ?? false) === true);
ac_assert('remove historical writes to_remove 40', ($update_calls[0]['to_remove'] ?? []) === [40]);
ac_assert('remove historical added_count 0', ($remove_historical['data']['added_count'] ?? -1) === 0);

$staff[5]['name'] = 'Ana López';
$links[5] = [10, 20];
$snapshot_staff = $staff[5];
$snapshot_links = $links[5];
$update_calls = [];
$invalid_new = $use_case->execute([
    'id' => 5,
    'name' => 'Ana Nueva',
    'service_ids' => [10, 99],
]);
ac_assert('new non-assignable id fails', ($invalid_new['success'] ?? true) === false && ($invalid_new['error']['code'] ?? '') === 'invalid_service_ids');
ac_assert('new non-assignable does not write', $update_calls === []);
ac_assert('new non-assignable keeps name', $staff[5] === $snapshot_staff);
ac_assert('new non-assignable keeps links', $links[5] === $snapshot_links);

$write_failed = false;
$failing = $make_use_case(static function (int $id, string $name, array $to_add, array $to_remove) use (&$write_failed): bool {
    $write_failed = true;
    return false;
});
$before_staff = $staff[5];
$before_links = $links[5];
$failed_write = $failing->execute([
    'id' => 5,
    'name' => 'Ana Rollback',
    'service_ids' => [10, 30],
]);
ac_assert('write failure returns persistence_failed', ($failed_write['success'] ?? true) === false && ($failed_write['error']['code'] ?? '') === 'persistence_failed');
ac_assert('write failure attempted update', $write_failed === true);
ac_assert('write failure rolls back name', $staff[5] === $before_staff);
ac_assert('write failure rolls back links', $links[5] === $before_links);

$empty_services = $use_case->execute([
    'id' => 5,
    'name' => 'Ana López',
    'service_ids' => [],
]);
ac_assert('empty service_ids is valid', ($empty_services['success'] ?? false) === true);
ac_assert('empty service_ids removes all', $links[5] === []);

$staff[5]['name'] = 'Ana López';
$links[5] = [10];
$name_only = $use_case->execute([
    'id' => 5,
    'name' => 'Ana Actualizada',
    'service_ids' => [10],
]);
ac_assert('name-only change succeeds', ($name_only['success'] ?? false) === true);
ac_assert('name-only keeps services', $links[5] === [10]);
ac_assert('name-only added_count 0', ($name_only['data']['added_count'] ?? -1) === 0);

$reload_calls = 0;
$reload_fallback = new UpdateStaffUseCase(
    static function (int $id) use (&$staff, &$reload_calls): ?array {
        $reload_calls++;
        if ($reload_calls > 1) {
            return null;
        }
        return $staff[$id] ?? null;
    },
    static function (int $id) use (&$links): array {
        return $links[$id] ?? [];
    },
    static function () use (&$assignable): array {
        return $assignable;
    },
    static function (int $id) use (&$links, &$service_names): array {
        $services = [];
        foreach ($links[$id] ?? [] as $service_id) {
            $services[] = [
                'id' => $service_id,
                'name' => $service_names[$service_id] ?? ('Servicio ' . $service_id),
            ];
        }
        return $services;
    },
    static function (int $id, string $name, array $to_add, array $to_remove) use (&$staff, &$links): bool {
        $staff[$id]['name'] = $name;
        $current = $links[$id] ?? [];
        foreach ($to_remove as $service_id) {
            $current = array_values(array_filter($current, static function ($existing) use ($service_id) {
                return (int) $existing !== (int) $service_id;
            }));
        }
        foreach ($to_add as $service_id) {
            if (!in_array((int) $service_id, $current, true)) {
                $current[] = (int) $service_id;
            }
        }
        $links[$id] = $current;
        return true;
    }
);

$staff[5]['name'] = 'Ana López';
$links[5] = [10];
$reload_miss = $reload_fallback->execute([
    'id' => 5,
    'name' => 'Ana Recargada',
    'service_ids' => [10, 30],
]);
ac_assert('reload miss after write still succeeds', ($reload_miss['success'] ?? false) === true);
ac_assert('reload miss is not persistence_failed', ($reload_miss['error']['code'] ?? '') !== 'persistence_failed');
ac_assert('reload miss keeps committed name', $staff[5]['name'] === 'Ana Recargada');
ac_assert('reload miss keeps committed links', $links[5] === [10, 30]);
ac_assert('reload miss added_count 1', ($reload_miss['data']['added_count'] ?? 0) === 1);

echo "\n";
if (count($failed) === 0) {
    echo "Passed {$passed}/{$total}\n";
    exit(0);
}

echo 'Failed ' . count($failed) . "/{$total}\n";
foreach ($failed as $label) {
    echo " - {$label}\n";
}
exit(1);
