<?php
/**
 * AC IMG-5 incremento 2 — captura durable, tandas exactas y exclusión concurrente.
 *
 * Estructural siempre. MySQL con prefijo temporal si AA_WP_ROOT está definido.
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-capture-canonical-purge-inventory-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/images/test-capture-canonical-purge-inventory-ac.php
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

$uc_file = $plugin_root . '/includes/application/canonical/images/CaptureCanonicalPurgeInventoryUseCase.php';
$store_file = $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-capture-store.php';
$runs_file = $plugin_root . '/includes/repositories/CanonicalPurgeRunsRepository.php';
$inv_file = $plugin_root . '/includes/repositories/CanonicalPurgeInventoryItemsRepository.php';
$schema_file = $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
$identity_file = $plugin_root . '/includes/domain/canonical/class-aa-canonical-purge-inventory-identity.php';

$uc_src = (string) file_get_contents($uc_file);
$store_src = (string) file_get_contents($store_file);
$runs_src = (string) file_get_contents($runs_file);
$inv_src = (string) file_get_contents($inv_file);
$schema_src = (string) file_get_contents($schema_file);
$identity_src = (string) file_get_contents($identity_file);
$confirm_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-record-image-confirmation-store.php');
$images_src = (string) file_get_contents($plugin_root . '/includes/repositories/CanonicalRecordImagesRepository.php');
$ops_src = (string) file_get_contents($plugin_root . '/includes/repositories/CanonicalImageUploadOperationsRepository.php');
$write_rec_src = (string) file_get_contents($plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php');
$write_cont_src = (string) file_get_contents($plugin_root . '/includes/application/canonical/WriteCanonicalShellContainerUseCase.php');

ac_assert('archivos del incremento existen', is_file($uc_file) && is_file($store_file) && is_file($inv_file) && is_file($identity_file));
ac_assert('sin HTTP en captura', strpos($uc_src, 'aa_send_authenticated_request') === false
    && strpos($uc_src, 'accept_delete_batch') === false
    && strpos($uc_src, 'seal_delete_mandate') === false);
ac_assert('sin fingerprint local', strpos($uc_src, 'fingerprint') === false
    && strpos($store_src, 'fingerprint') === false
    && strpos($inv_src, 'fingerprint') === false);
ac_assert('checkpoint de fuentes, no cursor de inventario copiado', strpos($runs_src, 'images_read_after_id') !== false
    && strpos($runs_src, 'ops_read_after_operation_id') !== false
    && strpos($images_src, 'list_capture_page_after_id') !== false
    && strpos($ops_src, 'list_capture_page_after_operation_id') !== false);
ac_assert('página + checkpoint en la misma TX', strpos($store_src, 'START TRANSACTION') !== false
    && strpos($store_src, 'upsert_item') !== false
    && strpos($store_src, 'update_capture_progress') !== false
    && strpos($store_src, 'START TRANSACTION') < strpos($store_src, 'upsert_item')
    && strpos($store_src, 'upsert_item') < strpos($store_src, 'update_capture_progress')
    && strpos($store_src, 'update_capture_progress') < strpos($store_src, 'COMMIT'));
ac_assert('tandas solo tras capture_complete', strpos($store_src, 'Cannot prepare batches before capture_complete') !== false);
ac_assert('lock antes de INSERT de corrida', strpos($uc_src, 'SCOPE_CANONICAL_CONTAINER') !== false
    && strpos($uc_src, 'open_or_resume') !== false
    && strpos($uc_src, 'acquire') < strpos($uc_src, 'open_or_resume'));
ac_assert('solape contenedor/registro', strpos($runs_src, 'find_overlapping_open_run') !== false);
ac_assert('inventario sin FK a records', strpos($schema_src, 'aa_canonical_purge_inventory_items:purge_run_id') !== false
    && strpos($inv_src, 'wp_record_id') !== false);
ac_assert('confirmación reconsulta purge antes de INSERT', strpos($confirm_src, 'blocking_purge_failure') !== false
    && strpos($confirm_src, 'has_blocking_purge') !== false
    && strpos($confirm_src, 'purge_in_progress') !== false
    && preg_match('/blocking_purge_failure[\s\S]{0,400}insert_confirmed/', $confirm_src) === 1);
ac_assert('delete shell opcional: sin purge no bloquea', strpos($write_rec_src, 'if ($this->purge_runs === null)') !== false
    && strpos($write_cont_src, 'if ($this->purge_runs === null)') !== false);
ac_assert('AJAX productivo cablea guarda', strpos((string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalDeleteRecordAjax.php'), 'new CanonicalPurgeRunsRepository()') !== false
    && strpos((string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalDeleteContainerAjax.php'), 'new CanonicalPurgeRunsRepository()') !== false);
ac_assert('ops captura incluye admitted y cleanup_needed', strpos($ops_src, 'IMAGE_UPLOAD_STATUS_ADMITTED') !== false
    && strpos($ops_src, 'IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED') !== false
    && strpos($ops_src, 'list_capture_page_after_operation_id') !== false);
ac_assert('identidad UUID v4 / sha / byte_size del contrato accept', strpos($identity_src, 'UUID_V4_PATTERN') !== false
    && strpos($identity_src, '1048576') !== false
    && strpos($identity_src, 'new_uuid_v4') !== false);
ac_assert('last_accepted_batch_seq y sealed_at no se marcan en captura', strpos($uc_src, 'last_accepted_batch_seq') !== false
    && strpos($uc_src, 'sealed_at') !== false
    && strpos($store_src, 'last_accepted_batch_seq') === false
    && strpos($store_src, 'sealed_at') === false);

require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-purge-inventory-identity.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadPersistenceFailed.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadSchemaNotReady.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadOperationConflict.php';
require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage.php';
require_once $plugin_root . '/includes/repositories/CanonicalPurgeRunsRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalPurgeInventoryItemsRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordImagesRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalImageUploadOperationsRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-capture-store.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalPurgeCaptureResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CaptureCanonicalPurgeInventoryCommand.php';
require_once $plugin_root . '/includes/application/canonical/images/CaptureCanonicalPurgeInventoryUseCase.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalRecordImageConfirmationResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalRecordImageConfirmationPort.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalRecordImagePublicDto.php';
require_once $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-record-image-confirmation-store.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-expediente-aggregate-lock.php';

$sha_ok = AA_Canonical_Purge_Inventory_Identity::normalize_content_sha256(str_repeat('AB', 32));
$op_ok = AA_Canonical_Purge_Inventory_Identity::normalize_upload_operation_id('550e8400-E29B-41d4-a716-446655440000');
$bad_op = AA_Canonical_Purge_Inventory_Identity::normalize_upload_operation_id('not-a-uuid');
ac_assert('normaliza UUID v4 lower', $op_ok === '550e8400-e29b-41d4-a716-446655440000');
ac_assert('rechaza UUID inválido', $bad_op === null);
ac_assert('normaliza sha256 lower', $sha_ok === str_repeat('ab', 32));
ac_assert('byte_size 0 incompleto', AA_Canonical_Purge_Inventory_Identity::is_valid_byte_size(0) === false);
ac_assert('byte_size 1..1048576', AA_Canonical_Purge_Inventory_Identity::is_valid_byte_size(1)
    && AA_Canonical_Purge_Inventory_Identity::is_valid_byte_size(1048576)
    && !AA_Canonical_Purge_Inventory_Identity::is_valid_byte_size(1048577));
$gen = AA_Canonical_Purge_Inventory_Identity::new_uuid_v4();
ac_assert('genera mandate UUID v4', AA_Canonical_Purge_Inventory_Identity::normalize_upload_operation_id($gen) === $gen);

if (!$has_real_wp) {
    ac_assert('MySQL omitido (sin AA_WP_ROOT)', true);
    echo "\n";
    if (count($failed) === 0) {
        echo "Passed {$passed}/{$total}\n";
        exit(0);
    }
    echo 'Failed ' . count($failed) . "/{$total}\n";
    exit(1);
}

/**
 * @return object
 */
function aa_purge_test_second_wpdb(string $prefix) {
    $db = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $db->prefix = $prefix;
    if (isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb']) && isset($GLOBALS['wpdb']->base_prefix)) {
        $db->base_prefix = $GLOBALS['wpdb']->base_prefix;
    }

    return $db;
}

/**
 * @param object $db
 */
function aa_purge_test_lock($db): AA_Expediente_Aggregate_Lock {
    return new AA_Expediente_Aggregate_Lock(
        static function (string $sql, array $args = []) use ($db) {
            if ($args === []) {
                return $db->get_var($sql);
            }
            $prepared = $db->prepare($sql, ...$args);

            return $db->get_var($prepared);
        },
        static function () use ($db) {
            return (int) $db->get_var('SELECT CONNECTION_ID()');
        }
    );
}

/**
 * @param object $db
 */
function aa_purge_test_uc($db, AA_Expediente_Aggregate_Lock $lock): CaptureCanonicalPurgeInventoryUseCase {
    $runs = new CanonicalPurgeRunsRepository($db);
    $images = new CanonicalRecordImagesRepository($db);
    $ops = new CanonicalImageUploadOperationsRepository($db);
    $inv = new CanonicalPurgeInventoryItemsRepository($db);
    $store = new AA_Canonical_Purge_Capture_Store($runs, $inv, $db);

    return new CaptureCanonicalPurgeInventoryUseCase($runs, $images, $ops, $inv, $store, $lock);
}

function aa_purge_test_uuid(int $n): string {
    return sprintf('00000000-0000-4000-8000-%012d', $n);
}

/**
 * @param object $wpdb
 */
function aa_purge_test_seed_shell($wpdb, string $now): array {
    $family_table = AA_Canonical_Schema::families_table_name();
    $containers = AA_Canonical_Schema::containers_table_name();
    $records = AA_Canonical_Schema::records_table_name();
    $wpdb->insert($family_table, [
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
        'title' => 'R1',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $record_id = (int) $wpdb->insert_id;
    $wpdb->insert($records, [
        'public_id' => '33333333-3333-4333-8333-333333333333',
        'container_id' => $container_id,
        'title' => 'R2',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $record_id_2 = (int) $wpdb->insert_id;

    return [
        'family_id' => $family_id,
        'container_id' => $container_id,
        'record_id' => $record_id,
        'record_id_2' => $record_id_2,
    ];
}

/**
 * @param object $wpdb
 */
function aa_purge_test_insert_image($wpdb, int $record_id, string $op, string $sha, int $bytes, string $now): int {
    $table = AA_Canonical_Schema::record_images_table_name();
    $wpdb->insert($table, [
        'record_id' => $record_id,
        'upload_operation_id' => $op,
        'storage_path' => 'canonical/records/' . $record_id . '/' . $op . '.jpg',
        'content_sha256' => $sha,
        'mime_type' => 'image/jpeg',
        'byte_size' => $bytes,
        'width' => 10,
        'height' => 8,
        'created_at' => $now,
    ]);

    return (int) $wpdb->insert_id;
}

/**
 * @param object $wpdb
 */
function aa_purge_test_insert_op($wpdb, int $record_id, string $op, string $sha, int $bytes, string $status, string $now, bool $expired = false): void {
    $ops = new CanonicalImageUploadOperationsRepository($wpdb);
    if ($status === AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED) {
        $exp_ms = $expired ? 1 : 9000000000000;
        $ops->insert_admitted([
            'upload_operation_id' => $op,
            'record_id' => $record_id,
            'storage_path' => 'canonical/records/' . $record_id . '/' . $op . '.jpg',
            'content_sha256' => $sha,
            'mime_type' => 'image/jpeg',
            'byte_size' => $bytes,
            'width' => 10,
            'height' => 8,
            'status' => $status,
            'expires_at' => $expired ? '2020-01-01 00:00:00' : AA_Installation_Storage_Usage::expires_at_from_intent_exp_ms($exp_ms),
            'backend_intent_exp_ms' => $exp_ms,
            'upload_intent' => 'intent',
            'upload_objects_json' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return;
    }

    $table = AA_Canonical_Schema::image_upload_operations_table_name();
    $wpdb->insert($table, [
        'upload_operation_id' => $op,
        'record_id' => $record_id,
        'storage_path' => 'canonical/records/' . $record_id . '/' . $op . '.jpg',
        'content_sha256' => $sha,
        'mime_type' => 'image/jpeg',
        'byte_size' => $bytes,
        'width' => 10,
        'height' => 8,
        'status' => $status,
        'expires_at' => $expired ? '2020-01-01 00:00:00' : '2099-01-01 00:00:00',
        'backend_intent_exp_ms' => $expired ? 1 : 9000000000000,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

global $wpdb;
$orig_prefix = $wpdb->prefix;
$token = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
$temp_prefix = 'tmp_pg2' . $token . '_';
$wpdb->prefix = $temp_prefix;
$now = gmdate('Y-m-d H:i:s');

try {
    AA_Canonical_Schema::install();

    $v27_runs = $wpdb->prefix . 'aa_canonical_purge_runs_v27fix';
    $charset = $wpdb->get_charset_collate();
    $real_runs = AA_Canonical_Schema::purge_runs_table_name();
    $wpdb->query("DROP TABLE IF EXISTS `{$v27_runs}`");
    $wpdb->query("CREATE TABLE `{$v27_runs}` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        scope varchar(32) NOT NULL,
        target_id bigint(20) unsigned NOT NULL,
        family_key varchar(64) NOT NULL,
        status varchar(32) NOT NULL,
        cursor_kind varchar(32) NOT NULL,
        cursor_id bigint(20) unsigned NOT NULL DEFAULT 0,
        cursor_operation_id char(36) DEFAULT NULL,
        deleted_ok int unsigned NOT NULL DEFAULT 0,
        failed_count int unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY idx_purge_scope_target_status (scope, target_id, status)
    ) ENGINE=InnoDB {$charset}");
    $wpdb->insert($v27_runs, [
        'scope' => 'record',
        'target_id' => 3,
        'family_key' => 'finance',
        'status' => 'in_progress',
        'cursor_kind' => 'image',
        'cursor_id' => 0,
        'deleted_ok' => 0,
        'failed_count' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $saved = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$v27_runs}`");
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $wpdb->query("DROP TABLE IF EXISTS `" . str_replace('`', '``', AA_Canonical_Schema::purge_inventory_items_table_name()) . "`");
    $wpdb->query("DROP TABLE IF EXISTS `" . str_replace('`', '``', $real_runs) . "`");
    $wpdb->query("RENAME TABLE `{$v27_runs}` TO `" . str_replace('`', '``', $real_runs) . "`");
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
    AA_Canonical_Schema::ensure_purge_capture_v28();
    AA_Canonical_Schema::ensure_foreign_keys();
    $mandate_after = $wpdb->get_row("SHOW COLUMNS FROM `{$real_runs}` LIKE 'mandate_id'", ARRAY_A);
    $count_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$real_runs}`");
    $mandate_null = $wpdb->get_var("SELECT mandate_id FROM `{$real_runs}` LIMIT 1");
    ac_assert(
        'MySQL: ALTER v27→v28 conserva fila y añade mandate_id NULL',
        is_array($mandate_after) && $count_after === $saved && $saved === 1 && ($mandate_null === null || $mandate_null === '')
    );
    $wpdb->query("DELETE FROM `{$real_runs}`");
    AA_Canonical_Schema::install();

    $ids = aa_purge_test_seed_shell($wpdb, $now);
    $container_id = $ids['container_id'];
    $record_id = $ids['record_id'];
    $record_id_2 = $ids['record_id_2'];
    $family_id = $ids['family_id'];
    $lock = aa_purge_test_lock($wpdb);
    $uc = aa_purge_test_uc($wpdb, $lock);
    $runs = new CanonicalPurgeRunsRepository($wpdb);
    $inventory = new CanonicalPurgeInventoryItemsRepository($wpdb);

    $empty = $uc->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $record_id,
        $container_id,
        'finance',
        1
    ));
    ac_assert('vacío: primera página no completa (ops pendiente)', $empty->state() === CanonicalPurgeCaptureResult::STATE_CAPTURE_PROGRESS
        && $empty->is_capture_complete() === false
        && $empty->mandate_id() !== null);
    $mandate_empty = $empty->mandate_id();
    $empty2 = $uc->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $record_id,
        $container_id,
        'finance',
        1
    ));
    ac_assert('vacío: segunda página completa con cero tandas', $empty2->state() === CanonicalPurgeCaptureResult::STATE_CAPTURE_COMPLETE
        && $empty2->mandate_id() === $mandate_empty
        && $empty2->batches_prepared() === true
        && $empty2->prepared_batch_count() === 0
        && $inventory->count_for_run((int) $empty2->purge_run_id()) === 0
        && ($empty2->payload()['last_accepted_batch_seq'] ?? null) === null
        && ($empty2->payload()['sealed_at'] ?? null) === null);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'container_id' => $container_id,
        'title' => 'Rmatch',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $match_record = (int) $wpdb->insert_id;

    $op_match = aa_purge_test_uuid(1);
    $sha_match = str_repeat('11', 32);
    aa_purge_test_insert_image($wpdb, $match_record, $op_match, $sha_match, 100, $now);
    aa_purge_test_insert_op($wpdb, $match_record, $op_match, $sha_match, 100, AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED, $now, false);

    $op_unconfirmed = aa_purge_test_uuid(2);
    $sha_unconfirmed = str_repeat('22', 32);
    aa_purge_test_insert_op(
        $wpdb,
        $match_record,
        $op_unconfirmed,
        $sha_unconfirmed,
        200,
        AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED,
        $now,
        true
    );

    $op_cleanup = aa_purge_test_uuid(3);
    aa_purge_test_insert_op(
        $wpdb,
        $match_record,
        $op_cleanup,
        str_repeat('33', 32),
        300,
        AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED,
        $now,
        true
    );

    $match_uc = aa_purge_test_uc($wpdb, $lock);
    $matched = $match_uc->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $match_record,
        $container_id,
        'finance',
        8
    ));
    ac_assert('imagen+op coincidentes y ops no confirmadas', $matched->state() === CanonicalPurgeCaptureResult::STATE_CAPTURE_COMPLETE
        && $matched->prepared_batch_count() === 1);
    $items = $inventory->list_ordered_for_run((int) $matched->purge_run_id());
    $by_op = [];
    foreach ($items as $item) {
        $by_op[$item['upload_operation_id']] = $item;
    }
    ac_assert('dedupe por upload_operation_id source=both', isset($by_op[$op_match])
        && ($by_op[$op_match]['source'] ?? '') === AA_Canonical_Schema::PURGE_INVENTORY_SOURCE_BOTH);
    ac_assert('incluye op admitted vencida', isset($by_op[$op_unconfirmed])
        && (int) $by_op[$op_unconfirmed]['byte_size'] === 200);
    ac_assert('incluye op cleanup_needed', isset($by_op[$op_cleanup]));
    $batch1 = $inventory->list_prepared_batch((int) $matched->purge_run_id(), 1);
    ac_assert('tanda 1 persistida 1..50', count($batch1) === 3
        && (int) $batch1[0]['batch_seq'] === 1
        && (int) $batch1[0]['position_in_batch'] === 1
        && $batch1[0]['upload_operation_id'] < $batch1[1]['upload_operation_id']);

    $conflict_record_table = AA_Canonical_Schema::records_table_name();
    $wpdb->insert($conflict_record_table, [
        'public_id' => '44444444-4444-4444-8444-444444444444',
        'container_id' => $container_id,
        'title' => 'Rconf',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $conflict_record = (int) $wpdb->insert_id;
    $op_conflict = aa_purge_test_uuid(4);
    aa_purge_test_insert_image($wpdb, $conflict_record, $op_conflict, str_repeat('44', 32), 400, $now);
    aa_purge_test_insert_op(
        $wpdb,
        $conflict_record,
        $op_conflict,
        str_repeat('55', 32),
        400,
        AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED,
        $now
    );
    $conflict_res = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $conflict_record,
        $container_id,
        'finance',
        8
    ));
    ac_assert('conflicto de metadatos no completa', $conflict_res->state() === CanonicalPurgeCaptureResult::STATE_CONFLICT
        && $conflict_res->is_capture_complete() === false
        && $conflict_res->capture_conflict_code() === CanonicalPurgeRunsRepository::CONFLICT_ITEM_METADATA);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'container_id' => $container_id,
        'title' => 'Rinc',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $incomplete_record_id = (int) $wpdb->insert_id;
    $op_incomplete = aa_purge_test_uuid(5);
    $images_table = AA_Canonical_Schema::record_images_table_name();
    $wpdb->insert($images_table, [
        'record_id' => $incomplete_record_id,
        'upload_operation_id' => $op_incomplete,
        'storage_path' => 'canonical/records/' . $incomplete_record_id . '/' . $op_incomplete . '.jpg',
        'content_sha256' => str_repeat('66', 32),
        'mime_type' => 'image/jpeg',
        'byte_size' => 0,
        'width' => 1,
        'height' => 1,
        'created_at' => $now,
    ]);
    $incomplete_res = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $incomplete_record_id,
        $container_id,
        'finance',
        8
    ));
    ac_assert('dato obligatorio ausente es error explícito', $incomplete_res->state() === CanonicalPurgeCaptureResult::STATE_CONFLICT
        && $incomplete_res->capture_conflict_code() === CanonicalPurgeRunsRepository::CONFLICT_ITEM_INCOMPLETE
        && $incomplete_res->is_capture_complete() === false);

    $big_public = '55555555-5555-4555-8555-555555555555';
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => $big_public,
        'container_id' => $container_id,
        'title' => 'Rbig',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $big_record = (int) $wpdb->insert_id;
    for ($i = 1; $i <= 51; $i++) {
        aa_purge_test_insert_image($wpdb, $big_record, aa_purge_test_uuid(100 + $i), str_repeat('aa', 32), 10 + $i, $now);
    }
    $page1 = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $big_record,
        $container_id,
        'finance',
        1
    ));
    ac_assert('>50: interrupción tras una página de imágenes', $page1->state() === CanonicalPurgeCaptureResult::STATE_CAPTURE_PROGRESS
        && $page1->is_capture_complete() === false
        && $inventory->count_for_run((int) $page1->purge_run_id()) === 50);
    $run_row = $runs->find_by_id((int) $page1->purge_run_id());
    ac_assert('checkpoint de lectura de images avanzó', is_array($run_row) && (int) $run_row['images_read_after_id'] > 0
        && (int) $run_row['images_source_exhausted'] === 0
        && (int) $run_row['ops_source_exhausted'] === 0);

    $stale_after = (int) $run_row['images_read_after_id'];
    $wpdb->update(AA_Canonical_Schema::purge_runs_table_name(), ['images_read_after_id' => 0], ['id' => (int) $page1->purge_run_id()]);
    $retry = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $big_record,
        $container_id,
        'finance',
        1
    ));
    ac_assert('repetir página no duplica', $inventory->count_for_run((int) $retry->purge_run_id()) === 50);
    $wpdb->update(
        AA_Canonical_Schema::purge_runs_table_name(),
        ['images_read_after_id' => $stale_after],
        ['id' => (int) $page1->purge_run_id()]
    );

    $page2 = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $big_record,
        $container_id,
        'finance',
        1
    ));
    ac_assert('reanudación nueva instancia captura resto de images; ops sigue pendiente', $inventory->count_for_run((int) $page2->purge_run_id()) === 51
        && $page2->is_capture_complete() === false
        && (int) ($runs->find_by_id((int) $page2->purge_run_id())['ops_source_exhausted'] ?? 1) === 0);
    $done_big = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $big_record,
        $container_id,
        'finance',
        1
    ));
    ac_assert('>50 completa en dos tandas', $done_big->is_capture_complete() === true
        && $done_big->prepared_batch_count() === 2);
    $prepared_first = $inventory->list_prepared_batch((int) $done_big->purge_run_id(), 1);
    $prepared_second = $inventory->list_prepared_batch((int) $done_big->purge_run_id(), 2);
    ac_assert('tandas 50+1 inmutables en contenido', count($prepared_first) === 50 && count($prepared_second) === 1);
    $again = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $big_record,
        $container_id,
        'finance',
        3
    ));
    $prepared_first_after = $inventory->list_prepared_batch((int) $done_big->purge_run_id(), 1);
    ac_assert('tanda preparada no cambia al recapturar', $again->mandate_id() === $done_big->mandate_id()
        && count($prepared_first_after) === 50
        && $prepared_first_after[0]['upload_operation_id'] === $prepared_first[0]['upload_operation_id']
        && $prepared_first_after[49]['upload_operation_id'] === $prepared_first[49]['upload_operation_id']
        && $inventory->count_for_run((int) $done_big->purge_run_id()) === 51);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        'container_id' => $container_id,
        'title' => 'Roverlap',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $overlap_target = (int) $wpdb->insert_id;
    $overlap_record = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $overlap_target,
        $container_id,
        'finance',
        1
    ));
    $overlap_container = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        $container_id,
        $container_id,
        'finance',
        1
    ));
    ac_assert('purga de registro bloquea contenedor', $overlap_record->mandate_id() !== null
        && $overlap_container->state() === CanonicalPurgeCaptureResult::STATE_SCOPE_OVERLAP);

    $other_rec = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $record_id_2,
        $container_id,
        'finance',
        1
    ));
    ac_assert('otro registro del mismo contenedor abre mandato propio', $other_rec->state() !== CanonicalPurgeCaptureResult::STATE_SCOPE_OVERLAP
        && $other_rec->mandate_id() !== null
        && $other_rec->mandate_id() !== $overlap_record->mandate_id());

    $wpdb2 = aa_purge_test_second_wpdb($temp_prefix);
    $lock2 = aa_purge_test_lock($wpdb2);
    $lease_a = $lock->acquire(AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER, $container_id, 1);
    $busy = $lock2->acquire(AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER, $container_id, 1);
    ac_assert('aperturas simultáneas: segunda conexión no obtiene lock', !is_wp_error($lease_a) && is_wp_error($busy)
        && $busy->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY);
    $lock->release($lease_a);
    $uc2 = aa_purge_test_uc($wpdb2, $lock2);
    $resume_same = $uc2->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $overlap_target,
        $container_id,
        'finance',
        1
    ));
    ac_assert('tras esperar, reanuda el mismo mandate_id', $resume_same->mandate_id() === $overlap_record->mandate_id());

    $container_first_public = '66666666-6666-4666-8666-666666666666';
    $wpdb->insert(AA_Canonical_Schema::containers_table_name(), [
        'public_id' => $container_first_public,
        'family_id' => $family_id,
        'title' => 'L2',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $c2 = (int) $wpdb->insert_id;
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '77777777-7777-4777-8777-777777777777',
        'container_id' => $c2,
        'title' => 'Rlock',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $rlock = (int) $wpdb->insert_id;
    $cont_open = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        $c2,
        $c2,
        'finance',
        1
    ));
    $rec_after_container = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $rlock,
        $c2,
        'finance',
        1
    ));
    ac_assert('purga de contenedor bloquea registro hijo', $cont_open->mandate_id() !== null
        && $rec_after_container->state() === CanonicalPurgeCaptureResult::STATE_SCOPE_OVERLAP);

    $confirm_public = '88888888-8888-4888-8888-888888888888';
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => $confirm_public,
        'container_id' => $container_id,
        'title' => 'Rconfirm',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $rconfirm = (int) $wpdb->insert_id;
    $op_before = aa_purge_test_uuid(9);
    $sha_before = str_repeat('99', 32);
    aa_purge_test_insert_op(
        $wpdb,
        $rconfirm,
        $op_before,
        $sha_before,
        900,
        AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED,
        $now
    );
    $lease_confirm = $lock->acquire(AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER, $container_id, 5);
    ac_assert('confirm-before-purge: A obtiene lock', !is_wp_error($lease_confirm));
    $purge_wait = $lock2->acquire(AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER, $container_id, 1);
    ac_assert('confirm-before-purge: purge espera el lock', is_wp_error($purge_wait)
        && $purge_wait->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY);
    $store = new AA_Canonical_Record_Image_Confirmation_Store(null, null, $wpdb, static function (): int {
        return 1000;
    }, $runs);
    $confirmed = $store->confirm_after_remote_finalize([
        'family_id' => $family_id,
        'container_id' => $container_id,
        'record_id' => $rconfirm,
        'upload_operation_id' => $op_before,
        'storage_path' => 'canonical/records/' . $rconfirm . '/' . $op_before . '.jpg',
        'content_sha256' => $sha_before,
        'mime_type' => 'image/jpeg',
        'byte_size' => 900,
        'width' => 10,
        'height' => 8,
    ]);
    ac_assert('confirm-before-purge: confirm escribe antes de abrir purge', $confirmed->outcome() === CanonicalRecordImageConfirmationResult::OUTCOME_CONFIRMED);
    $lock->release($lease_confirm);
    $captured_after_confirm = aa_purge_test_uc($wpdb2, $lock2)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $rconfirm,
        $container_id,
        'finance',
        8
    ));
    $captured_ops = [];
    foreach ($inventory->list_ordered_for_run((int) $captured_after_confirm->purge_run_id()) as $item) {
        $captured_ops[] = $item['upload_operation_id'];
    }
    ac_assert('captura incluye el resultado de la confirmación previa', $captured_after_confirm->is_capture_complete() === true
        && in_array($op_before, $captured_ops, true));

    $op_blocked = aa_purge_test_uuid(10);
    aa_purge_test_insert_op(
        $wpdb,
        $rconfirm,
        $op_blocked,
        str_repeat('ab', 32),
        50,
        AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED,
        $now
    );
    $blocked_confirm = $store->confirm_after_remote_finalize([
        'family_id' => $family_id,
        'container_id' => $container_id,
        'record_id' => $rconfirm,
        'upload_operation_id' => $op_blocked,
        'storage_path' => 'canonical/records/' . $rconfirm . '/' . $op_blocked . '.jpg',
        'content_sha256' => str_repeat('ab', 32),
        'mime_type' => 'image/jpeg',
        'byte_size' => 50,
        'width' => 10,
        'height' => 8,
    ]);
    ac_assert('escritor posterior a purge abierta queda bloqueado antes de INSERT', $blocked_confirm->outcome() === CanonicalRecordImageConfirmationResult::OUTCOME_FAILED
        && $blocked_confirm->failure_code() === 'purge_in_progress');
    $images_repo = new CanonicalRecordImagesRepository($wpdb);
    ac_assert('no insertó imagen tras exclusión', $images_repo->find_by_upload_operation_id($op_blocked) === null);

    ac_assert('has_blocking_purge ve la corrida abierta', $runs->has_blocking_purge($rconfirm, $container_id) === true);
    ac_assert('has_blocking_purge_for_container ve corridas del contenedor', $runs->has_blocking_purge_for_container($container_id) === true);

    $survive_public = '99999999-9999-4999-8999-999999999999';
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => $survive_public,
        'container_id' => $container_id,
        'title' => 'Rsurv',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $rsurv = (int) $wpdb->insert_id;
    $op_surv = aa_purge_test_uuid(11);
    aa_purge_test_insert_image($wpdb, $rsurv, $op_surv, str_repeat('cd', 32), 80, $now);
    $surv_cap = aa_purge_test_uc($wpdb, $lock)->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $rsurv,
        $container_id,
        'finance',
        8
    ));
    $surv_run = (int) $surv_cap->purge_run_id();
    $surv_count_before = $inventory->count_for_run($surv_run);
    $wpdb->last_error = '';
    $wpdb->query($wpdb->prepare('DELETE FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $rsurv));
    $record_still = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d',
        $rsurv
    ));
    ac_assert('RESTRICT impide borrar registro con imagen antes de drenar', $record_still === 1);
    $wpdb->query($wpdb->prepare(
        'DELETE FROM `' . str_replace('`', '``', AA_Canonical_Schema::record_images_table_name()) . '` WHERE record_id = %d',
        $rsurv
    ));
    $wpdb->query($wpdb->prepare('DELETE FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $rsurv));
    $record_gone = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d',
        $rsurv
    ));
    ac_assert('inventario sobrevive al DELETE del registro de prueba', $record_gone === 0
        && $inventory->count_for_run($surv_run) === $surv_count_before
        && $surv_count_before >= 1);
} finally {
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $like = $wpdb->esc_like($temp_prefix) . '%';
    $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    if (is_array($tables)) {
        foreach ($tables as $t) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
        }
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
    $wpdb->prefix = $orig_prefix;
}

echo "\n";
if (count($failed) === 0) {
    echo "Passed {$passed}/{$total}\n";
    exit(0);
}

echo 'Failed ' . count($failed) . "/{$total}\n";
foreach ($failed as $label) {
    echo "  - {$label}\n";
}
exit(1);
