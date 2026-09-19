<?php
/**
 * AC IMG-3a bloque 2 — persistencia ops + migración columnas credenciales.
 *
 * Estructural siempre. MySQL con prefijos temporales si AA_WP_ROOT está definido.
 *
 * Ejecutar:
 *   php tests/repositories/test-canonical-image-upload-operations-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/repositories/test-canonical-image-upload-operations-ac.php
 */

$plugin_root = dirname(__DIR__, 2);

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

require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadOperationConflict.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadPersistenceFailed.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadSchemaNotReady.php';
require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage.php';
require_once $plugin_root . '/includes/repositories/CanonicalImageUploadOperationsRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordImagesRepository.php';

$ops_src = file_get_contents($plugin_root . '/includes/repositories/CanonicalImageUploadOperationsRepository.php');
ac_assert('insert_admitted presente', strpos($ops_src, 'function insert_admitted') !== false);
ac_assert('find_admitted_resumable presente', strpos($ops_src, 'function find_admitted_resumable') !== false);
ac_assert('mark_cleanup anula credenciales', strpos($ops_src, 'upload_intent = NULL') !== false
    && strpos($ops_src, 'upload_objects_json = NULL') !== false);
ac_assert('conflict distinguible', strpos($ops_src, 'CanonicalImageUploadOperationConflict') !== false);
ac_assert('sin START TRANSACTION', !preg_match('/START TRANSACTION|COMMIT\b/', $ops_src));
ac_assert('suma reservas usa backend_intent_exp_ms', strpos($ops_src, 'backend_intent_exp_ms >') !== false);

$canonical_src = file_get_contents($plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php');
ac_assert('DDL upload_intent mediumtext', strpos($canonical_src, 'upload_intent mediumtext DEFAULT NULL') !== false);
ac_assert('DDL upload_objects_json', strpos($canonical_src, 'upload_objects_json mediumtext DEFAULT NULL') !== false);
ac_assert('ensure v27', strpos($canonical_src, 'ensure_image_upload_operations_credentials_v27') !== false);

$schema_src = file_get_contents($plugin_root . '/includes/infrastructure/wp/Schema.php');
ac_assert("DB_VERSION es '35'", strpos($schema_src, "DB_VERSION = '35'") !== false);

ac_assert(
    'expires_at derivado de intent ms',
    AA_Installation_Storage_Usage::expires_at_from_intent_exp_ms(1_700_000_000_500)
        === gmdate('Y-m-d H:i:s', intdiv(1_700_000_000_500, 1000))
);

if ($has_real_wp) {
    global $wpdb;
    $token = 'img3a' . substr(bin2hex(random_bytes(4)), 0, 8);
    $prefix = $wpdb->prefix . $token . '_';
    $prev_prefix = $wpdb->prefix;
    $wpdb->prefix = $prefix;

    try {
        AA_Canonical_Schema::install();
        $ops_table = AA_Canonical_Schema::image_upload_operations_table_name();
        $cols = $wpdb->get_col("SHOW COLUMNS FROM `{$ops_table}`", 0);
        ac_assert('MySQL: upload_intent columna', in_array('upload_intent', $cols, true));
        ac_assert('MySQL: upload_objects_json columna', in_array('upload_objects_json', $cols, true));

        // Reejecución idempotente
        AA_Canonical_Schema::ensure_image_upload_operations_credentials_v27();
        ac_assert('MySQL: ensure v27 reentrante', true);

        // Seed mínimo: family/container/record para FK
        $now = '2026-09-12 12:00:00';
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
            'public_id' => '11111111-1111-4111-8111-111111111111',
            'family_id' => $family_id,
            'title' => 'L',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $container_id = (int) $wpdb->insert_id;
        $wpdb->insert($records, [
            'public_id' => '22222222-2222-4222-8222-222222222222',
            'container_id' => $container_id,
            'title' => 'R',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $record_id = (int) $wpdb->insert_id;

        $repo = new CanonicalImageUploadOperationsRepository($wpdb);
        $op = '33333333-3333-4333-8333-333333333333';
        $exp_ms = 1_800_000_000_000;
        $intent = 'test-intent-credential-not-for-logs';
        $objects = '{"original":"https://example.invalid/u","summary":"https://example.invalid/s","gallery":"https://example.invalid/g","display":"https://example.invalid/d"}';

        $repo->insert_admitted([
            'upload_operation_id' => $op,
            'record_id' => $record_id,
            'storage_path' => 'installations/x/canonical/records/' . $record_id . '/' . $op . '.jpg',
            'content_sha256' => str_repeat('a', 64),
            'mime_type' => 'image/jpeg',
            'byte_size' => 400,
            'width' => 10,
            'height' => 10,
            'expires_at' => AA_Installation_Storage_Usage::expires_at_from_intent_exp_ms($exp_ms),
            'backend_intent_exp_ms' => $exp_ms,
            'upload_intent' => $intent,
            'upload_objects_json' => $objects,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $found = $repo->find_by_operation_id($op);
        ac_assert('MySQL: round-trip intent', is_array($found) && ($found['upload_intent'] ?? '') === $intent);
        ac_assert('MySQL: round-trip objects', ($found['upload_objects_json'] ?? '') === $objects);
        ac_assert('MySQL: round-trip exp ms', (int) ($found['backend_intent_exp_ms'] ?? 0) === $exp_ms);

        $resumable = $repo->find_admitted_resumable($op, $exp_ms - 1);
        ac_assert('MySQL: resumable antes de vencimiento', is_array($resumable));
        ac_assert('MySQL: no resumable en/ tras vencimiento', $repo->find_admitted_resumable($op, $exp_ms) === null);

        $dup_ok = false;
        try {
            $repo->insert_admitted([
                'upload_operation_id' => $op,
                'record_id' => $record_id,
                'storage_path' => 'installations/x/canonical/records/' . $record_id . '/' . $op . '.jpg',
                'content_sha256' => str_repeat('b', 64),
                'mime_type' => 'image/jpeg',
                'byte_size' => 999,
                'width' => 1,
                'height' => 1,
                'expires_at' => $now,
                'backend_intent_exp_ms' => $exp_ms + 10,
                'upload_intent' => 'other',
                'upload_objects_json' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (CanonicalImageUploadOperationConflict $e) {
            $dup_ok = true;
        }
        ac_assert('MySQL: duplicado no sobrescribe', $dup_ok);
        $again = $repo->find_by_operation_id($op);
        ac_assert('MySQL: identidad intacta tras conflicto', (int) ($again['byte_size'] ?? 0) === 400
            && ($again['upload_intent'] ?? '') === $intent);

        // Histórica incompleta: cuenta en reserva hasta vencimiento, no resumible
        $op_hist = '44444444-4444-4444-8444-444444444444';
        $wpdb->insert($ops_table, [
            'upload_operation_id' => $op_hist,
            'record_id' => $record_id,
            'storage_path' => 'installations/x/canonical/records/' . $record_id . '/' . $op_hist . '.jpg',
            'content_sha256' => str_repeat('c', 64),
            'mime_type' => 'image/jpeg',
            'byte_size' => 77,
            'width' => 1,
            'height' => 1,
            'status' => 'admitted',
            'expires_at' => '2099-01-01 00:00:00',
            'backend_intent_exp_ms' => null,
            'upload_intent' => null,
            'upload_objects_json' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        ac_assert('MySQL: histórica no resumible', $repo->find_admitted_resumable($op_hist, 1) === null);
        $reserved = $repo->sum_reserved_byte_size(1, '2020-01-01 00:00:00', null);
        ac_assert('MySQL: histórica incompleta reserva por expires_at', $reserved >= 77 + 400);

        $repo->mark_cleanup_needed($op, '2026-09-12 13:00:00');
        $cleaned = $repo->find_by_operation_id($op);
        ac_assert('MySQL: cleanup status', ($cleaned['status'] ?? '') === 'cleanup_needed');
        ac_assert('MySQL: cleanup anula intent', ($cleaned['upload_intent'] ?? 'x') === null || ($cleaned['upload_intent'] ?? '') === '');
        ac_assert('MySQL: cleanup conserva path', strpos((string) ($cleaned['storage_path'] ?? ''), $op) !== false);
        ac_assert('MySQL: cleanup no reserva', $repo->sum_reserved_byte_size(1, '2020-01-01 00:00:00', null) === 77);

        $repo->delete_by_operation_id($op);
        ac_assert('MySQL: delete elimina fila', $repo->find_by_operation_id($op) === null);

        // Verify fail-closed: drop column then verify must throw (restore after)
        $wpdb->query("ALTER TABLE `{$ops_table}` DROP COLUMN upload_intent");
        $threw = false;
        try {
            AA_Canonical_Schema::verify();
        } catch (\Throwable $e) {
            $threw = true;
        }
        ac_assert('MySQL: verify falla sin upload_intent', $threw);
        $wpdb->query("ALTER TABLE `{$ops_table}` ADD COLUMN upload_intent mediumtext DEFAULT NULL");
        AA_Canonical_Schema::ensure_image_upload_operations_credentials_v27();
        AA_Canonical_Schema::verify();
        ac_assert('MySQL: verify OK tras restore', true);
    } finally {
        $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($prefix) . '%'));
        if (is_array($tables)) {
            foreach ($tables as $t) {
                $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t) . '`');
            }
        }
        $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        $wpdb->prefix = $prev_prefix;
    }
} else {
    echo "[skip] MySQL integration (set AA_WP_ROOT)\n";
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
