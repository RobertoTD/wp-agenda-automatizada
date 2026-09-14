<?php
/**
 * AC IMG-3b bloque 3 — confirmación TX, uncertain e expiración con reloj controlado.
 *
 * Estructural siempre. MySQL con prefijo temporal si AA_WP_ROOT está definido.
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-upload-canonical-record-image-confirmation-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/images/test-upload-canonical-record-image-confirmation-ac.php
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

$uc_src = file_get_contents($plugin_root . '/includes/application/canonical/images/UploadCanonicalRecordImageUseCase.php');
$store_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-record-image-confirmation-store.php');
$images_src = file_get_contents($plugin_root . '/includes/repositories/CanonicalRecordImagesRepository.php');

ac_assert('images insert_confirmed + content_sha256', strpos($images_src, 'function insert_confirmed') !== false
    && strpos($images_src, 'content_sha256') !== false);
ac_assert('store insert+delete+touch+commit', strpos($store_src, 'insert_confirmed') !== false
    && strpos($store_src, 'delete_by_operation_id') !== false
    && strpos($store_src, 'touch_container') !== false
    && strpos($store_src, 'COMMIT') !== false);
ac_assert('store reconsulta purge antes de INSERT', strpos($store_src, 'has_blocking_purge') !== false
    && strpos($store_src, 'purge_in_progress') !== false
    && strpos($store_src, 'insert_confirmed') !== false
    && strpos($store_src, 'blocking_purge_failure') !== false
    && preg_match('/blocking_purge_failure[\s\S]{0,400}insert_confirmed/', $store_src) === 1);
ac_assert('DELETE debe afectar 1 fila', strpos($store_src, '$deleted !== 1') !== false);
ac_assert('uncertain en COMMIT fallido', strpos($store_src, 'OUTCOME_UNCERTAIN') !== false
    || strpos($store_src, '::uncertain') !== false);
ac_assert('no cleanup rutinario tras uncertain en UC', strpos($uc_src, "OUTCOME_UNCERTAIN") !== false
    && !preg_match('/OUTCOME_UNCERTAIN[\s\S]{0,200}mark_owned_cleanup/', $uc_src));
ac_assert('admission_expired_after_finalize', strpos($uc_src, 'admission_expired_after_finalize') !== false);
ac_assert('reloj inyectable en store', strpos($store_src, 'clock_ms') !== false);
ac_assert('DTO sin path/sha en respuesta pública AJAX',
    strpos(file_get_contents($plugin_root . '/includes/http/ajax/CanonicalAttachRecordImageAjax.php'), 'content_sha256') === false);

require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadPersistenceFailed.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadSchemaNotReady.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadOperationConflict.php';
require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalRecordImageConfirmationResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalRecordImageConfirmationPort.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordImagesRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalImageUploadOperationsRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-record-image-confirmation-store.php';

if ($has_real_wp) {
    global $wpdb;
    $orig_prefix = $wpdb->prefix;
    $token = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
    $temp_prefix = $orig_prefix . 't3b' . $token . '_';
    $wpdb->prefix = $temp_prefix;

    try {
        AA_Canonical_Schema::install();

        $family_table = AA_Canonical_Schema::families_table_name();
        $containers = AA_Canonical_Schema::containers_table_name();
        $records = AA_Canonical_Schema::records_table_name();

        $now = gmdate('Y-m-d H:i:s');
        $wpdb->insert($family_table, [
            'family_key' => 'finance',
            'is_enabled' => 1,
            'seed_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $family_id = (int) $wpdb->insert_id;
        $wpdb->insert($containers, [
            'public_id' => '22222222-2222-4222-8222-222222222222',
            'family_id' => $family_id,
            'title' => 'L',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $container_id = (int) $wpdb->insert_id;
        $wpdb->insert($records, [
            'public_id' => '33333333-3333-4333-8333-333333333333',
            'container_id' => $container_id,
            'title' => 'R',
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $record_id = (int) $wpdb->insert_id;

        $op = '550e8400-e29b-41d4-a716-446655440099';
        $sha = str_repeat('cd', 32);
        $path = 'installations/11111111-1111-4111-8111-111111111111/canonical/records/' . $record_id . '/' . $op . '.jpg';
        $exp_ms = 9000000000000;

        $ops = new CanonicalImageUploadOperationsRepository($wpdb);
        $ops->insert_admitted([
            'upload_operation_id' => $op,
            'record_id' => $record_id,
            'storage_path' => $path,
            'content_sha256' => $sha,
            'mime_type' => 'image/jpeg',
            'byte_size' => 1200,
            'width' => 10,
            'height' => 8,
            'status' => AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED,
            'expires_at' => AA_Installation_Storage_Usage::expires_at_from_intent_exp_ms($exp_ms),
            'backend_intent_exp_ms' => $exp_ms,
            'upload_intent' => 'intent-token',
            'upload_objects_json' => '{"urls":{},"variant_byte_sizes":{"summary":1,"gallery":1,"display":1}}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $clock = static function (): int {
            return 1000;
        };
        $store = new AA_Canonical_Record_Image_Confirmation_Store(null, null, $wpdb, $clock);
        $payload = [
            'family_id' => $family_id,
            'container_id' => $container_id,
            'record_id' => $record_id,
            'upload_operation_id' => $op,
            'storage_path' => $path,
            'content_sha256' => $sha,
            'mime_type' => 'image/jpeg',
            'byte_size' => 1200,
            'width' => 10,
            'height' => 8,
        ];

        $confirmed = $store->confirm_after_remote_finalize($payload);
        ac_assert('MySQL: confirm OK', $confirmed->outcome() === CanonicalRecordImageConfirmationResult::OUTCOME_CONFIRMED);
        ac_assert('MySQL: ops eliminada', $ops->find_by_operation_id($op) === null);
        $images = new CanonicalRecordImagesRepository($wpdb);
        $row = $images->find_by_upload_operation_id($op);
        ac_assert('MySQL: imagen con sha', is_array($row) && ($row['content_sha256'] ?? '') === $sha);

        $again = $store->confirm_after_remote_finalize($payload);
        ac_assert('MySQL: idempotente por op', $again->outcome() === CanonicalRecordImageConfirmationResult::OUTCOME_CONFIRMED
            && (int) $again->image_id() === (int) $confirmed->image_id());

        // Expired clock after finalize semantics at store gate
        $op2 = '550e8400-e29b-41d4-a716-446655440098';
        $ops->insert_admitted([
            'upload_operation_id' => $op2,
            'record_id' => $record_id,
            'storage_path' => $path . '2',
            'content_sha256' => str_repeat('ef', 32),
            'mime_type' => 'image/jpeg',
            'byte_size' => 11,
            'width' => 2,
            'height' => 2,
            'status' => AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED,
            'expires_at' => '2020-01-01 00:00:00',
            'backend_intent_exp_ms' => 500,
            'upload_intent' => 'intent-2',
            'upload_objects_json' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $late = new AA_Canonical_Record_Image_Confirmation_Store(null, null, $wpdb, static function (): int {
            return 1000;
        });
        $expired = $late->confirm_after_remote_finalize([
            'family_id' => $family_id,
            'container_id' => $container_id,
            'record_id' => $record_id,
            'upload_operation_id' => $op2,
            'storage_path' => $path . '2',
            'content_sha256' => str_repeat('ef', 32),
            'mime_type' => 'image/jpeg',
            'byte_size' => 11,
            'width' => 2,
            'height' => 2,
        ]);
        ac_assert('MySQL: reloj avanzado rechaza confirm', $expired->outcome() === CanonicalRecordImageConfirmationResult::OUTCOME_FAILED
            && $expired->failure_code() === 'admission_expired');
        ac_assert('MySQL: ops expirada intacta (sin INSERT)', $ops->find_by_operation_id($op2) !== null
            && $images->find_by_upload_operation_id($op2) === null);
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
