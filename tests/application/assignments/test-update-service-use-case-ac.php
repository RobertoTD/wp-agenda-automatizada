<?php
/**
 * AC — UpdateServiceUseCase (edición atómica del modal de servicio).
 *
 * Ejecutar: php tests/application/assignments/test-update-service-use-case-ac.php
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
$use_case_file = $plugin_root . '/includes/application/assignments/UpdateServiceUseCase.php';
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
ac_assert('Use case skips write when snapshots equal', strpos($use_case_src, 'snapshots_equal') !== false);
ac_assert('Repository defines find_service_by_id', strpos($repo_src, 'function find_service_by_id') !== false);
ac_assert('Repository defines update_service_fields', strpos($repo_src, 'function update_service_fields') !== false);
ac_assert(
    'Repository find excludes description',
    preg_match(
        "/function find_service_by_id[\s\S]*?SELECT id, name, code, price, public_calendar, indicaciones_cita/",
        $repo_src
    ) === 1
);
ac_assert(
    'Repository find filters is_hidden = 0',
    preg_match(
        "/function find_service_by_id[\s\S]*?is_hidden = 0/",
        $repo_src
    ) === 1
);
ac_assert(
    'Repository update does not write description/active/created_at',
    preg_match(
        "/function update_service_fields[\s\S]*?'description'\s*=>/",
        $repo_src
    ) !== 1
    && preg_match(
        "/function update_service_fields[\s\S]*?'active'\s*=>/",
        $repo_src
    ) !== 1
);
ac_assert(
    'Repository treats only update false as SQL error',
    preg_match(
        "/function update_service_fields[\s\S]*?\\\$result === false/",
        $repo_src
    ) === 1
);
ac_assert(
    'Repository update is not wrapped in a transaction',
    preg_match(
        "/function update_service_fields[\s\S]*?START TRANSACTION/",
        $repo_src
    ) !== 1
);

$store = [
    8 => [
        'id' => 8,
        'name' => 'Consulta',
        'code' => '',
        'price' => null,
        'public_calendar' => 0,
        'indicaciones_cita' => null,
        'duration_minutes' => null,
        'attendance_type' => null,
        'virtual_channel' => null,
        'description' => 'Histórica intacta',
        'active' => 1,
        'is_hidden' => 0,
        'created_at' => '2024-01-01 00:00:00',
    ],
];
$update_calls = [];

$make_use_case = static function (
    ?callable $update = null
) use (&$store, &$update_calls): UpdateServiceUseCase {
    return new UpdateServiceUseCase(
        static function (int $id) use (&$store): ?array {
            if (!isset($store[$id])) {
                return null;
            }
            $row = $store[$id];
            unset($row['description'], $row['active'], $row['is_hidden'], $row['created_at']);
            return $row;
        },
        $update ?? static function (int $id, array $fields) use (&$store, &$update_calls): bool {
            $update_calls[] = $fields;
            foreach ($fields as $key => $value) {
                $store[$id][$key] = $value;
            }
            return true;
        }
    );
};

$base_input = static function (array $overrides = []) use (&$store): array {
    $row = $store[8];
    return array_merge([
        'id' => 8,
        'name' => $row['name'],
        'code' => $row['code'],
        'price' => $row['price'] === null ? '' : (string) $row['price'],
        'public_calendar' => (string) $row['public_calendar'],
        'indicaciones_cita' => $row['indicaciones_cita'] ?? '',
        'duration_minutes' => $row['duration_minutes'] === null ? '' : (string) $row['duration_minutes'],
        'attendance_type' => $row['attendance_type'] ?? '',
        'virtual_channel' => $row['virtual_channel'] ?? '',
    ], $overrides);
};

$use_case = $make_use_case();

$invalid_id = $use_case->execute($base_input(['id' => 0]));
ac_assert('invalid id fails', ($invalid_id['success'] ?? true) === false && ($invalid_id['error']['code'] ?? '') === 'invalid_id');

$empty_name = $use_case->execute($base_input(['name' => '   ']));
ac_assert('empty name fails', ($empty_name['success'] ?? true) === false && ($empty_name['error']['code'] ?? '') === 'invalid_name');

$missing = $use_case->execute($base_input(['id' => 99, 'name' => 'X']));
ac_assert('missing service fails', ($missing['success'] ?? true) === false && ($missing['error']['code'] ?? '') === 'not_found');

$update_calls = [];
$identical = $use_case->execute($base_input());
ac_assert('identical save succeeds', ($identical['success'] ?? false) === true);
ac_assert('identical save does not write', $update_calls === []);
ac_assert('identical keeps null type', array_key_exists('attendance_type', $identical['data']['service'] ?? []) && $identical['data']['service']['attendance_type'] === null);
ac_assert('identical keeps null channel', array_key_exists('virtual_channel', $identical['data']['service'] ?? []) && $identical['data']['service']['virtual_channel'] === null);
ac_assert('identical keeps null price', array_key_exists('price', $identical['data']['service'] ?? []) && $identical['data']['service']['price'] === null);

$update_calls = [];
$name_only = $use_case->execute($base_input(['name' => 'Consulta actualizada']));
ac_assert('name-only with null type succeeds', ($name_only['success'] ?? false) === true);
ac_assert('name-only writes once', count($update_calls) === 1);
ac_assert('name-only preserves null attendance_type', $store[8]['attendance_type'] === null);
ac_assert('name-only preserves null virtual_channel', $store[8]['virtual_channel'] === null);
ac_assert('name-only does not write description', ($store[8]['description'] ?? '') === 'Histórica intacta');
ac_assert('name-only keeps active/created_at', ($store[8]['active'] ?? 0) === 1 && ($store[8]['created_at'] ?? '') === '2024-01-01 00:00:00');

$store[8]['name'] = 'Consulta';
$store[8]['attendance_type'] = 'virtual';
$store[8]['virtual_channel'] = null;
$update_calls = [];
$price_only_virtual = $use_case->execute($base_input([
    'price' => '25.50',
    'attendance_type' => 'virtual',
    'virtual_channel' => '',
]));
ac_assert('virtual undefined channel + price change succeeds', ($price_only_virtual['success'] ?? false) === true);
ac_assert('virtual undefined channel stays null', $store[8]['virtual_channel'] === null);
ac_assert('virtual type stays virtual', ($store[8]['attendance_type'] ?? '') === 'virtual');
ac_assert('price 25.50 normalized', ($store[8]['price'] ?? '') === '25.50');

$store[8]['price'] = null;
$store[8]['attendance_type'] = null;
$store[8]['virtual_channel'] = null;
$update_calls = [];
$zero_price = $use_case->execute($base_input(['price' => '0.00']));
ac_assert('zero price succeeds', ($zero_price['success'] ?? false) === true);
ac_assert('zero price is 0.00 not null', ($store[8]['price'] ?? null) === '0.00');
ac_assert('zero price keeps null type/channel', $store[8]['attendance_type'] === null && $store[8]['virtual_channel'] === null);

$update_calls = [];
$empty_price = $use_case->execute($base_input(['price' => '']));
ac_assert('empty price succeeds', ($empty_price['success'] ?? false) === true);
ac_assert('empty price returns to null', $store[8]['price'] === null);

$before_negative = $store[8];
$update_calls = [];
$negative = $use_case->execute($base_input(['price' => '-10.00']));
ac_assert('negative price fails', ($negative['success'] ?? true) === false && ($negative['error']['code'] ?? '') === 'invalid_price');
ac_assert('negative price does not write', $update_calls === []);
ac_assert('negative price leaves store intact', $store[8] === $before_negative);

$store[8]['virtual_channel'] = 'whatsapp';
$store[8]['attendance_type'] = 'virtual';
$update_calls = [];
$to_physical = $use_case->execute($base_input([
    'attendance_type' => 'physical',
    'virtual_channel' => 'whatsapp',
]));
ac_assert('explicit physical succeeds', ($to_physical['success'] ?? false) === true);
ac_assert('explicit physical stores physical', ($store[8]['attendance_type'] ?? '') === 'physical');
ac_assert('explicit physical clears channel', $store[8]['virtual_channel'] === null);

$store[8]['attendance_type'] = 'virtual';
$store[8]['virtual_channel'] = 'google_meet';
$update_calls = [];
$to_undefined = $use_case->execute($base_input([
    'attendance_type' => '',
    'virtual_channel' => 'google_meet',
]));
ac_assert('back to undefined type succeeds', ($to_undefined['success'] ?? false) === true);
ac_assert('undefined type stores null', $store[8]['attendance_type'] === null);
ac_assert('undefined type clears channel', $store[8]['virtual_channel'] === null);

$store[8]['duration_minutes'] = 45;
$store[8]['attendance_type'] = null;
$store[8]['virtual_channel'] = null;
$update_calls = [];
$keep_historical_duration = $use_case->execute($base_input([
    'duration_minutes' => '45',
    'indicaciones_cita' => 'Llegar 10 minutos antes',
]));
ac_assert('historical duration intact succeeds', ($keep_historical_duration['success'] ?? false) === true);
ac_assert('historical duration stays 45', ($store[8]['duration_minutes'] ?? 0) === 45);
ac_assert('indicaciones updated', ($store[8]['indicaciones_cita'] ?? '') === 'Llegar 10 minutos antes');

$before_bad_duration = $store[8];
$update_calls = [];
$bad_duration = $use_case->execute($base_input([
    'duration_minutes' => '15',
    'indicaciones_cita' => 'Llegar 10 minutos antes',
]));
ac_assert('new invalid duration fails', ($bad_duration['success'] ?? true) === false && ($bad_duration['error']['code'] ?? '') === 'invalid_duration');
ac_assert('new invalid duration does not write', $update_calls === []);
ac_assert('new invalid duration leaves store intact', $store[8] === $before_bad_duration);

$store[8]['duration_minutes'] = 45;
$update_calls = [];
$duration_60 = $use_case->execute($base_input(['duration_minutes' => '60']));
ac_assert('switching historical duration to 60 succeeds', ($duration_60['success'] ?? false) === true);
ac_assert('duration becomes 60', ($store[8]['duration_minutes'] ?? 0) === 60);

$write_failed = false;
$failing = $make_use_case(static function () use (&$write_failed): bool {
    $write_failed = true;
    return false;
});
$before_fail = $store[8];
$failed_write = $failing->execute($base_input(['name' => 'Consulta rollback']));
ac_assert('write failure returns persistence_failed', ($failed_write['success'] ?? true) === false && ($failed_write['error']['code'] ?? '') === 'persistence_failed');
ac_assert('write failure attempted update', $write_failed === true);
ac_assert('write failure leaves in-memory store unchanged', $store[8] === $before_fail);

$reload_calls = 0;
$reload_fallback = new UpdateServiceUseCase(
    static function (int $id) use (&$store, &$reload_calls): ?array {
        $reload_calls++;
        if ($reload_calls > 1) {
            return null;
        }
        $row = $store[$id] ?? null;
        if (!is_array($row)) {
            return null;
        }
        unset($row['description'], $row['active'], $row['is_hidden'], $row['created_at']);
        return $row;
    },
    static function (int $id, array $fields) use (&$store): bool {
        foreach ($fields as $key => $value) {
            $store[$id][$key] = $value;
        }
        return true;
    }
);
$reload_miss = $reload_fallback->execute($base_input(['name' => 'Consulta recargada']));
ac_assert('reload miss after write still succeeds', ($reload_miss['success'] ?? false) === true);
ac_assert('reload miss is not persistence_failed', ($reload_miss['error']['code'] ?? '') !== 'persistence_failed');
ac_assert('reload miss keeps committed name', ($store[8]['name'] ?? '') === 'Consulta recargada');

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
