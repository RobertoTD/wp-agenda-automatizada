<?php
/**
 * AC — UpdateServiceAreaUseCase (edición atómica nombre + color).
 *
 * Ejecutar: php tests/application/assignments/test-update-service-area-use-case-ac.php
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
$use_case_file = $plugin_root . '/includes/application/assignments/UpdateServiceAreaUseCase.php';
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
ac_assert('Use case does not read description from input', strpos($use_case_src, "\$input['description']") === false);
ac_assert('Use case calls update_service_area_name_and_color', strpos($use_case_src, 'update_service_area_name_and_color') !== false);
ac_assert('Repository defines find_service_area_by_id', strpos($repo_src, 'function find_service_area_by_id') !== false);
ac_assert('Repository defines update_service_area_name_and_color', strpos($repo_src, 'function update_service_area_name_and_color') !== false);
ac_assert(
    'Repository update array has name and color only',
    preg_match(
        "/update_service_area_name_and_color[\s\S]*?\\\$wpdb->update\([\s\S]*?\[[\s\S]*?'name'[\s\S]*?'color'[\s\S]*?\]/",
        $repo_src
    ) === 1
);
ac_assert(
    'Repository update array does not include description',
    preg_match(
        "/function update_service_area_name_and_color[\s\S]*?\\\$wpdb->update\([\s\S]*?'description'[\s\S]*?\);/",
        $repo_src
    ) !== 1
);

$stored = [
    7 => [
        'id' => 7,
        'name' => 'Consultorio 1',
        'description' => 'Descripción histórica',
        'color' => '#112233',
        'active' => 1,
        'created_at' => '2024-01-01 00:00:00',
    ],
];
$update_payload = null;

$use_case = new UpdateServiceAreaUseCase(
    static function (int $id) use (&$stored): ?array {
        return $stored[$id] ?? null;
    },
    static function (int $id, string $name, string $color) use (&$stored, &$update_payload): bool {
        $update_payload = [
            'id' => $id,
            'name' => $name,
            'color' => $color,
        ];
        $stored[$id]['name'] = $name;
        $stored[$id]['color'] = $color;
        return true;
    }
);

$invalid_id = $use_case->execute(['id' => 0, 'name' => 'Zona', 'color' => '#aabbcc']);
ac_assert('invalid id fails', ($invalid_id['success'] ?? true) === false && ($invalid_id['error']['code'] ?? '') === 'invalid_id');

$empty_name = $use_case->execute(['id' => 7, 'name' => '   ', 'color' => '#aabbcc']);
ac_assert('empty name fails', ($empty_name['success'] ?? true) === false && ($empty_name['error']['code'] ?? '') === 'invalid_name');

$invalid_color = $use_case->execute(['id' => 7, 'name' => 'Zona', 'color' => 'blue']);
ac_assert('invalid color fails', ($invalid_color['success'] ?? true) === false && ($invalid_color['error']['code'] ?? '') === 'invalid_color');

$missing = $use_case->execute(['id' => 99, 'name' => 'Zona', 'color' => '#aabbcc']);
ac_assert('missing area fails', ($missing['success'] ?? true) === false && ($missing['error']['code'] ?? '') === 'not_found');

$ok = $use_case->execute([
    'id' => 7,
    'name' => 'Consultorio 3',
    'color' => '#aabbcc',
    'description' => 'NO DEBE APLICARSE',
]);

ac_assert('update succeeds', ($ok['success'] ?? false) === true);
ac_assert('returns updated name', ($ok['data']['area']['name'] ?? '') === 'Consultorio 3');
ac_assert('returns updated color', ($ok['data']['area']['color'] ?? '') === '#aabbcc');
ac_assert(
    'description remains unchanged',
    ($ok['data']['area']['description'] ?? '') === 'Descripción histórica'
);
ac_assert('update payload has no description', is_array($update_payload) && !array_key_exists('description', $update_payload));
ac_assert('update payload has name and color', is_array($update_payload) && $update_payload['name'] === 'Consultorio 3' && $update_payload['color'] === '#aabbcc');
ac_assert('in-memory description still historical', $stored[7]['description'] === 'Descripción histórica');

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
