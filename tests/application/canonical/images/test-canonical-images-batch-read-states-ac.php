<?php
/**
 * AC IMG-4 bloque 1 — lote de imágenes y estados ReadState.
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-canonical-images-batch-read-states-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/images/test-canonical-images-batch-read-states-ac.php
 */

$plugin_root = dirname(__DIR__, 4);

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

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
$has_real_wp = ($wp_load !== '' && is_readable($wp_load));

if ($has_real_wp) {
    require_once $wp_load;
} else {
    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin_root . '/');
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
}

require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalRecordImagePublicDto.php';
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadPersistenceFailed.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadSchemaNotReady.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordImagesRepository.php';

$scalar = CanonicalCapabilityRecordReadState::known_value('12.50');
ac_assert('known_value serialización', $scalar->to_array() === ['status' => 'known_value', 'value' => '12.50']);
$absent = CanonicalCapabilityRecordReadState::known_absent();
ac_assert('known_absent serialización', $absent->to_array() === ['status' => 'known_absent']);
$failed_state = CanonicalCapabilityRecordReadState::read_failed();
ac_assert('read_failed serialización', $failed_state->to_array() === ['status' => 'read_failed']);

$items = [
    ['id' => 2, 'width' => 10, 'height' => 8, 'byte_size' => 100, 'created_at' => '2026-01-02 00:00:00'],
    ['id' => 1, 'width' => 10, 'height' => 8, 'byte_size' => 90, 'created_at' => '2026-01-01 00:00:00'],
];
$collection = CanonicalCapabilityRecordReadState::known_collection($items);
ac_assert('known_collection status', $collection->status() === CanonicalCapabilityRecordReadState::STATUS_KNOWN_COLLECTION);
ac_assert('known_collection items()', $collection->items() === $items);
ac_assert('known_collection to_array', $collection->to_array() === [
    'status' => 'known_collection',
    'items' => $items,
]);
ac_assert('known_collection value null', $collection->value() === null);

$empty_rejected = false;
try {
    CanonicalCapabilityRecordReadState::known_collection([]);
} catch (\InvalidArgumentException $e) {
    $empty_rejected = strpos($e->getMessage(), 'known_absent') !== false;
}
ac_assert('known_collection([]) inválido', $empty_rejected);

$dto = CanonicalRecordImagePublicDto::from_row([
    'id' => 9,
    'width' => 1,
    'height' => 2,
    'byte_size' => 3,
    'created_at' => 't',
    'storage_path' => 'secret/path',
    'content_sha256' => str_repeat('a', 64),
]);
ac_assert('DTO sin path/hash', $dto === [
    'id' => 9,
    'width' => 1,
    'height' => 2,
    'byte_size' => 3,
    'created_at' => 't',
] && !isset($dto['storage_path']));

$repo_src = file_get_contents($plugin_root . '/includes/repositories/CanonicalRecordImagesRepository.php');
ac_assert('lote método presente', strpos($repo_src, 'find_public_rows_by_record_ids_for_container') !== false);
ac_assert('lote JOIN container', strpos($repo_src, 'container_id') !== false && strpos($repo_src, 'INNER JOIN') !== false);
ac_assert('lote orden id DESC', strpos($repo_src, 'ORDER BY i.record_id ASC, i.id DESC') !== false);
ac_assert('lote IDs vacíos sin SQL', strpos($repo_src, 'if ($ids === [])') !== false);

if ($has_real_wp) {
    global $wpdb;
    $orig_prefix = $wpdb->prefix;
    $temp_prefix = $orig_prefix . 't4b' . substr(md5(uniqid((string) mt_rand(), true)), 0, 8) . '_';
    $wpdb->prefix = $temp_prefix;

    try {
        AA_Canonical_Schema::install();
        $now = gmdate('Y-m-d H:i:s');
        $families = AA_Canonical_Schema::families_table_name();
        $containers = AA_Canonical_Schema::containers_table_name();
        $records = AA_Canonical_Schema::records_table_name();
        $wpdb->insert($families, [
            'family_key' => 'finance',
            'is_enabled' => 1,
            'seed_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $family_id = (int) $wpdb->insert_id;
        $wpdb->insert($containers, [
            'public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'family_id' => $family_id,
            'title' => 'L',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $container_id = (int) $wpdb->insert_id;
        $wpdb->insert($containers, [
            'public_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'family_id' => $family_id,
            'title' => 'Other',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $other_container = (int) $wpdb->insert_id;

        $wpdb->insert($records, [
            'public_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'container_id' => $container_id,
            'title' => 'R1',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $r1 = (int) $wpdb->insert_id;
        $wpdb->insert($records, [
            'public_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'container_id' => $container_id,
            'title' => 'R2',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $r2 = (int) $wpdb->insert_id;
        $wpdb->insert($records, [
            'public_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'container_id' => $other_container,
            'title' => 'RX',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $rx = (int) $wpdb->insert_id;

        $images = new CanonicalRecordImagesRepository($wpdb);
        $empty = $images->find_public_rows_by_record_ids_for_container($container_id, []);
        ac_assert('MySQL: IDs vacíos', $empty === []);

        $op1 = '550e8400-e29b-41d4-a716-446655440001';
        $op2 = '550e8400-e29b-41d4-a716-446655440002';
        $opx = '550e8400-e29b-41d4-a716-446655440003';
        $images->insert_confirmed([
            'record_id' => $r1,
            'upload_operation_id' => $op1,
            'storage_path' => 'installations/11111111-1111-4111-8111-111111111111/canonical/records/' . $r1 . '/' . $op1 . '.jpg',
            'content_sha256' => str_repeat('11', 32),
            'mime_type' => 'image/jpeg',
            'byte_size' => 10,
            'width' => 1,
            'height' => 1,
            'created_at' => '2026-01-01 00:00:00',
        ]);
        $id_old = (int) $wpdb->insert_id;
        $images->insert_confirmed([
            'record_id' => $r1,
            'upload_operation_id' => $op2,
            'storage_path' => 'installations/11111111-1111-4111-8111-111111111111/canonical/records/' . $r1 . '/' . $op2 . '.jpg',
            'content_sha256' => str_repeat('22', 32),
            'mime_type' => 'image/jpeg',
            'byte_size' => 20,
            'width' => 2,
            'height' => 2,
            'created_at' => '2026-01-02 00:00:00',
        ]);
        $id_new = (int) $wpdb->insert_id;
        $images->insert_confirmed([
            'record_id' => $rx,
            'upload_operation_id' => $opx,
            'storage_path' => 'installations/11111111-1111-4111-8111-111111111111/canonical/records/' . $rx . '/' . $opx . '.jpg',
            'content_sha256' => str_repeat('33', 32),
            'mime_type' => 'image/jpeg',
            'byte_size' => 30,
            'width' => 3,
            'height' => 3,
            'created_at' => $now,
        ]);

        $batch = $images->find_public_rows_by_record_ids_for_container($container_id, [$r1, $r2, $rx]);
        ac_assert('MySQL: r1 tiene 2', isset($batch[$r1]) && count($batch[$r1]) === 2);
        ac_assert('MySQL: orden id DESC', (int) $batch[$r1][0]['id'] === $id_new && (int) $batch[$r1][1]['id'] === $id_old);
        ac_assert('MySQL: r2 ausente vacío', isset($batch[$r2]) && $batch[$r2] === []);
        ac_assert('MySQL: rx excluido por contenedor', !isset($batch[$rx]) || $batch[$rx] === []);
        ac_assert('MySQL: columnas públicas', !isset($batch[$r1][0]['storage_path']) && !isset($batch[$r1][0]['content_sha256']));
    } finally {
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        $like = $wpdb->esc_like($temp_prefix) . '%';
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
        if (is_array($tables)) {
            foreach ($tables as $t) {
                $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t) . '`');
            }
        }
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        $wpdb->prefix = $orig_prefix;
    }
} else {
    ac_assert('MySQL omitido (sin AA_WP_ROOT)', true);
}

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
