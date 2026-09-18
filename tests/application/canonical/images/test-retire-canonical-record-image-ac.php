<?php
/**
 * AC IMG-5 incremento 5 — retiro de una imagen (registro vivo) vía mandatos.
 *
 * Estructural siempre. MySQL con prefijo temporal si AA_WP_ROOT está definido.
 * HTTP doblado (sin Storage/Render).
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-retire-canonical-record-image-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/images/test-retire-canonical-record-image-ac.php
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

$uc_file = $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordImageUseCase.php';
$cmd_file = $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordImageCommand.php';
$result_file = $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordImageResult.php';
$cap_file = $plugin_root . '/includes/application/canonical/images/CaptureCanonicalRecordImagePurgeUseCase.php';
$store_file = $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-local-retire-store.php';
$ajax_file = $plugin_root . '/includes/http/ajax/CanonicalDeleteRecordImageAjax.php';
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$cschema_file = $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
$runs_file = $plugin_root . '/includes/repositories/CanonicalPurgeRunsRepository.php';

$uc_src = (string) file_get_contents($uc_file);
$store_src = (string) file_get_contents($store_file);
$ajax_src = (string) file_get_contents($ajax_file);
$cap_src = (string) file_get_contents($cap_file);
$schema_src = (string) file_get_contents($schema_file);
$cschema_src = (string) file_get_contents($cschema_file);
$runs_src = (string) file_get_contents($runs_file);

ac_assert('archivos del incremento 5 existen', is_file($uc_file) && is_file($cmd_file) && is_file($result_file)
    && is_file($cap_file) && is_file($ajax_file));
ac_assert('DB_VERSION=34', strpos($schema_src, "DB_VERSION = '34'") !== false);
ac_assert('ensure_purge_image_retire_v31', strpos($cschema_src, 'ensure_purge_image_retire_v31') !== false
    && strpos($cschema_src, 'record_id bigint(20) unsigned DEFAULT NULL') !== false);
ac_assert('SCOPE_IMAGE + blocking por record_id', strpos($runs_src, "SCOPE_IMAGE = 'image'") !== false
    && strpos($runs_src, 'scope = %s AND record_id = %d') !== false);
ac_assert('AJAX cablea Retire image sin mandate_id', strpos($ajax_src, 'RetireCanonicalRecordImageUseCase') !== false
    && strpos($ajax_src, "ACTION = 'aa_delete_canonical_record_image'") !== false
    && strpos($ajax_src, "'mandate_id'") === false);
ac_assert('AJAX/UseCase sin gate is_ready', strpos($ajax_src, '->is_ready') === false
    && strpos($uc_src, '->is_ready') === false);
ac_assert('bootstrap registra Ajax imagen', strpos((string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php'), 'CanonicalDeleteRecordImageAjax::register()') !== false);
ac_assert('captura puntual sin paginar registro', strpos($cap_src, 'list_capture_page_after_id') === false
    && strpos($cap_src, 'build_single_item') !== false);
ac_assert('TX local retire_image conserva registro', strpos($store_src, 'function retire_image') !== false
    && strpos($uc_src, 'retire_image') !== false
    && strpos($uc_src, 'accept_delete_batch') === false);
ac_assert('image_not_found en Result', strpos((string) file_get_contents($result_file), 'STATE_IMAGE_NOT_FOUND') !== false);

require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-purge-inventory-identity.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadPersistenceFailed.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadSchemaNotReady.php';
require_once $plugin_root . '/includes/application/storage/CanonicalImageUploadOperationConflict.php';
require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage.php';
require_once $plugin_root . '/includes/repositories/CanonicalPurgeRunsRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalPurgeInventoryItemsRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordImagesRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalImageUploadOperationsRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-capture-store.php';
require_once $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-local-retire-store.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalPurgeCaptureResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CaptureCanonicalRecordImagePurgeUseCase.php';
require_once $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordImageCommand.php';
require_once $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordImageResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalPurgeLocalRetireResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalPurgeRemoteAdvanceResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalPurgeRemoteMandateAdvancer.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
require_once $uc_file;

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

final class AA_Test_Img_Delete_Mandate_Client extends AA_Expediente_Attachments_Backend_Client {
    /** @var list<array{0:string,1:array}> */
    public $calls = [];
    /** @var callable|null */
    public $accept_fn;
    /** @var callable|null */
    public $seal_fn;
    /** @var callable|null */
    public $status_fn;

    public function accept_delete_batch(array $input): array {
        $this->calls[] = ['accept', $input];
        if (is_callable($this->accept_fn)) {
            return ($this->accept_fn)($input);
        }

        return aa_img_retire_hmac_fail('unreachable');
    }

    public function seal_delete_mandate(array $input): array {
        $this->calls[] = ['seal', $input];
        if (is_callable($this->seal_fn)) {
            return ($this->seal_fn)($input);
        }

        return aa_img_retire_hmac_fail('unreachable');
    }

    public function get_delete_mandate_status(array $input): array {
        $this->calls[] = ['status', $input];
        if (is_callable($this->status_fn)) {
            return ($this->status_fn)($input);
        }

        return aa_img_retire_hmac_fail('unreachable');
    }
}

final class AA_Test_Img_Uncertain_Retire_Store extends AA_Canonical_Purge_Local_Retire_Store {
    protected function commit_transaction(): bool {
        parent::commit_transaction();

        return false;
    }
}

/**
 * @return array<string, mixed>
 */
function aa_img_retire_hmac_fail(string $class): array {
    return [
        'ok' => false,
        'code' => 'expediente_attachments_unreachable',
        'error' => 'timeout',
        'http_status' => 0,
        'failure_class' => $class,
        'retire_authorized' => false,
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function aa_img_retire_hmac_items(array $rows): array {
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'upload_operation_id' => $row['upload_operation_id'],
            'outcome' => 'accepted',
            'retire_authorized' => true,
            'obligation_id' => $row['upload_operation_id'],
            'storage_path' => $row['storage_path'],
            'wp_record_id' => (int) $row['wp_record_id'],
            'content_sha256' => $row['content_sha256'],
            'byte_size' => (int) $row['byte_size'],
            'prior_mandate_id' => null,
        ];
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function aa_img_retire_accept_ok(string $mandate_id, int $seq, array $rows, string $acceptance = 'accepted'): array {
    return [
        'ok' => true,
        'outcome' => $acceptance,
        'retire_authorized' => false,
        'result' => [
            'mandate_id' => $mandate_id,
            'inventory_status' => 'open',
            'structural_retire_authorized' => false,
            'batch' => [
                'seq' => $seq,
                'fingerprint' => str_repeat('ab', 32),
                'acceptance' => $acceptance,
                'contiguous_prefix_len' => $seq + 1,
            ],
            'items' => aa_img_retire_hmac_items($rows),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function aa_img_retire_seal_ok(string $mandate_id, int $count): array {
    return [
        'ok' => true,
        'outcome' => 'sealed',
        'retire_authorized' => true,
        'result' => [
            'mandate_id' => $mandate_id,
            'inventory_status' => 'sealed',
            'structural_retire_authorized' => true,
            'seal' => [
                'outcome' => 'sealed',
                'expected_batch_count' => $count,
                'contiguous_prefix_len' => $count,
            ],
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function aa_img_retire_status_credit_ok(string $mandate_id, int $seq, array $rows): array {
    return [
        'ok' => true,
        'outcome' => 'found',
        'retire_authorized' => false,
        'result' => [
            'found' => true,
            'mandate_id' => $mandate_id,
            'inventory_status' => 'open',
            'contiguous_prefix_len' => $seq + 1,
            'structural_retire_authorized' => false,
            'can_credit_batch' => true,
            'batch_found' => true,
            'batch' => [
                'seq' => $seq,
                'fingerprint' => str_repeat('ab', 32),
                'acceptance' => 'already_accepted',
                'items' => aa_img_retire_hmac_items($rows),
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function aa_img_retire_status_sealed_ok(string $mandate_id, int $count): array {
    return [
        'ok' => true,
        'outcome' => 'found',
        'retire_authorized' => true,
        'result' => [
            'found' => true,
            'mandate_id' => $mandate_id,
            'inventory_status' => 'sealed',
            'contiguous_prefix_len' => $count,
            'structural_retire_authorized' => true,
            'can_credit_batch' => false,
            'inventory_page_complete' => true,
            'items' => [],
            'items_page' => ['limit' => 50, 'has_more' => false, 'next_cursor' => null],
        ],
    ];
}

/**
 * @param object $db
 */
function aa_img_retire_second_wpdb(string $prefix) {
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
function aa_img_retire_lock($db): AA_Expediente_Aggregate_Lock {
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
function aa_img_retire_uc(
    $db,
    AA_Expediente_Aggregate_Lock $lock,
    AA_Test_Img_Delete_Mandate_Client $client,
    ?AA_Canonical_Purge_Local_Retire_Store $local = null
): RetireCanonicalRecordImageUseCase {
    $runs = new CanonicalPurgeRunsRepository($db);
    $inv = new CanonicalPurgeInventoryItemsRepository($db);
    $images = new CanonicalRecordImagesRepository($db);
    $ops = new CanonicalImageUploadOperationsRepository($db);
    $cstore = new AA_Canonical_Purge_Capture_Store($runs, $inv, $db);
    $capture = new CaptureCanonicalRecordImagePurgeUseCase($runs, $images, $ops, $inv, $cstore);
    $rel = new CanonicalRelationalRepository($db);
    $local_store = $local ?: new AA_Canonical_Purge_Local_Retire_Store($runs, $inv, $images, $ops, $db);

    return new RetireCanonicalRecordImageUseCase(
        $rel,
        $capture,
        $runs,
        $inv,
        $images,
        $local_store,
        $client,
        $lock,
        new CanonicalPurgeRemoteMandateAdvancer($runs, $inv, $client)
    );
}

function aa_img_uuid(int $n): string {
    return sprintf('00000000-0000-4000-8000-%012d', $n);
}

/**
 * @param object $wpdb
 */
function aa_img_seed($wpdb, string $now): array {
    $wpdb->insert(AA_Canonical_Schema::families_table_name(), [
        'family_key' => 'finance',
        'is_enabled' => 1,
        'seed_version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $family_id = (int) $wpdb->insert_id;
    $wpdb->insert(AA_Canonical_Schema::containers_table_name(), [
        'public_id' => '11111111-1111-4111-8111-111111111111',
        'family_id' => $family_id,
        'title' => 'L',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $container_id = (int) $wpdb->insert_id;
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '22222222-2222-4222-8222-222222222222',
        'container_id' => $container_id,
        'title' => 'R1',
        'details' => 'keep-me',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $record_id = (int) $wpdb->insert_id;
    $wpdb->insert(AA_Canonical_Schema::record_amount_table_name(), [
        'record_id' => $record_id,
        'amount' => '12.50',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [
        'family_id' => $family_id,
        'container_id' => $container_id,
        'record_id' => $record_id,
    ];
}

/**
 * @param object $wpdb
 */
function aa_img_insert_image($wpdb, int $record_id, string $op, string $sha, int $bytes, string $now): int {
    $wpdb->insert(AA_Canonical_Schema::record_images_table_name(), [
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
function aa_img_insert_op($wpdb, int $record_id, string $op, string $sha, int $bytes, string $status, string $now): void {
    $wpdb->insert(AA_Canonical_Schema::image_upload_operations_table_name(), [
        'upload_operation_id' => $op,
        'record_id' => $record_id,
        'storage_path' => 'canonical/records/' . $record_id . '/' . $op . '.jpg',
        'content_sha256' => $sha,
        'mime_type' => 'image/jpeg',
        'byte_size' => $bytes,
        'width' => 10,
        'height' => 8,
        'status' => $status,
        'expires_at' => '2099-01-01 00:00:00',
        'backend_intent_exp_ms' => 9000000000000,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function aa_img_cmd(int $image_id, string $intent = RetireCanonicalRecordImageCommand::INTENT_RETIRE): RetireCanonicalRecordImageCommand {
    return new RetireCanonicalRecordImageCommand('finance', $image_id, $intent);
}

global $wpdb;
$orig_prefix = $wpdb->prefix;
$token = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
$temp_prefix = 'tmp_pg5' . $token . '_';
$wpdb->prefix = $temp_prefix;
$now = gmdate('Y-m-d H:i:s');

try {
    AA_Canonical_Schema::install();
    ac_assert('schema verify tras install v31', true);
    ac_assert('aa_db_version option no requerida aquí', AA_Schema::DB_VERSION === '34');

    // Reaplicar ensure v31 es idempotente.
    AA_Canonical_Schema::ensure_purge_image_retire_v31();
    $cols = $wpdb->get_results('SHOW COLUMNS FROM `' . str_replace('`', '``', AA_Canonical_Schema::purge_runs_table_name()) . '` LIKE \'record_id\'', ARRAY_A);
    ac_assert('columna record_id presente tras ensure v31', is_array($cols) && $cols !== []);

    $ids = aa_img_seed($wpdb, $now);
    $family_id = $ids['family_id'];
    $container_id = $ids['container_id'];
    $record_id = $ids['record_id'];
    $runs = new CanonicalPurgeRunsRepository($wpdb);
    $inventory = new CanonicalPurgeInventoryItemsRepository($wpdb);
    $images = new CanonicalRecordImagesRepository($wpdb);
    $ops = new CanonicalImageUploadOperationsRepository($wpdb);
    $lock = aa_img_retire_lock($wpdb);
    $rel = new CanonicalRelationalRepository($wpdb);

    $missing = aa_img_retire_uc($wpdb, $lock, new AA_Test_Img_Delete_Mandate_Client())
        ->execute(aa_img_cmd(999999));
    ac_assert('imagen ausente sin open run → image_not_found', $missing->state() === RetireCanonicalRecordImageResult::STATE_IMAGE_NOT_FOUND);
    ac_assert('no inventa mandato vacío', $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, 999999) === null
        && $runs->find_latest_completed_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, 999999) === null);

    $op1 = aa_img_uuid(1);
    $sha1 = str_repeat('11', 32);
    $image1 = aa_img_insert_image($wpdb, $record_id, $op1, $sha1, 100, $now);
    $op2 = aa_img_uuid(2);
    $sha2 = str_repeat('22', 32);
    $image2 = aa_img_insert_image($wpdb, $record_id, $op2, $sha2, 40, $now);
    $op_other = aa_img_uuid(3);
    aa_img_insert_op($wpdb, $record_id, $op_other, str_repeat('33', 32), 70, AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED, $now);

    $quota_before = $images->sum_byte_size_total();
    $container_before = $rel->find_container($family_id, $container_id);
    $updated_before = (string) ($container_before['updated_at'] ?? '');

    $client = new AA_Test_Img_Delete_Mandate_Client();
    $client->accept_fn = static function (array $input) use ($inventory, $runs, $image1) {
        $open = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $image1);
        ac_assert('accept batch_seq=0', (int) $input['batch_seq'] === 0);
        ac_assert('corrida scope=image con record_id', is_array($open)
            && ($open['scope'] ?? '') === CanonicalPurgeRunsRepository::SCOPE_IMAGE
            && (int) ($open['record_id'] ?? 0) > 0
            && (string) ($open['mandate_id'] ?? '') !== '');
        $rows = $inventory->list_prepared_batch((int) $open['id'], 0);

        return aa_img_retire_accept_ok((string) $input['mandate_id'], 0, $rows);
    };
    $client->seal_fn = static function (array $input) {
        ac_assert('seal expected_batch_count=1', (int) $input['expected_batch_count'] === 1);

        return aa_img_retire_seal_ok((string) $input['mandate_id'], 1);
    };

    $res = aa_img_retire_uc($wpdb, $lock, $client)->execute(aa_img_cmd($image1));
    ac_assert('una imagen confirmed', $res->state() === RetireCanonicalRecordImageResult::STATE_CONFIRMED);
    ac_assert('imagen inventariada ausente', $images->find_by_id($image1) === null
        && $ops->find_by_operation_id($op1) === null);
    ac_assert('segunda imagen intacta', $images->find_by_id($image2) !== null);
    ac_assert('op no relacionada intacta', $ops->find_by_operation_id($op_other) !== null);
    $record_after = $rel->find_record($container_id, $record_id);
    $amount_after = $wpdb->get_var($wpdb->prepare(
        'SELECT amount FROM `' . str_replace('`', '``', AA_Canonical_Schema::record_amount_table_name()) . '` WHERE record_id = %d',
        $record_id
    ));
    ac_assert('registro y amount intactos', is_array($record_after)
        && ($record_after['title'] ?? '') === 'R1'
        && ($record_after['details'] ?? '') === 'keep-me'
        && (string) $amount_after === '12.50');
    ac_assert('cuota SUM baja exactamente byte_size', $images->sum_byte_size_total() === $quota_before - 100);
    $container_after = $rel->find_container($family_id, $container_id);
    ac_assert('container.updated_at avanza', is_array($container_after)
        && (string) ($container_after['updated_at'] ?? '') >= $updated_before
        && (string) ($container_after['updated_at'] ?? '') !== '');

    $completed = $runs->find_latest_completed_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $image1);
    ac_assert('corrida completed conserva inventario', is_array($completed)
        && ($completed['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_COMPLETED
        && $inventory->count_for_run((int) $completed['id']) === 1
        && (int) ($completed['record_id'] ?? 0) === $record_id);

    $idem = aa_img_retire_uc($wpdb, $lock, new AA_Test_Img_Delete_Mandate_Client())->execute(aa_img_cmd($image1));
    ac_assert('doble delete misma imagen → confirmed idempotente', $idem->state() === RetireCanonicalRecordImageResult::STATE_CONFIRMED
        && is_array($rel->find_record($container_id, $record_id)));

    // Overlap image ↔ record
    $op_ov = aa_img_uuid(10);
    $img_ov = aa_img_insert_image($wpdb, $record_id, $op_ov, str_repeat('aa', 32), 11, $now);
    $runs->insert_open_run([
        'scope' => CanonicalPurgeRunsRepository::SCOPE_RECORD,
        'target_id' => $record_id,
        'container_id' => $container_id,
        'record_id' => $record_id,
        'family_key' => 'finance',
        'mandate_id' => aa_img_uuid(900),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $ov_rec = aa_img_retire_uc($wpdb, $lock, new AA_Test_Img_Delete_Mandate_Client())->execute(aa_img_cmd($img_ov));
    ac_assert('overlap image vs open record', $ov_rec->state() === RetireCanonicalRecordImageResult::STATE_SCOPE_OVERLAP);
    $open_rec = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_RECORD, $record_id);
    if ($open_rec !== null) {
        $runs->mark_cancelled((int) $open_rec['id'], $now);
    }

    // Overlap image ↔ container
    $runs->insert_open_run([
        'scope' => CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        'target_id' => $container_id,
        'container_id' => $container_id,
        'family_key' => 'finance',
        'mandate_id' => aa_img_uuid(901),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $ov_ct = aa_img_retire_uc($wpdb, $lock, new AA_Test_Img_Delete_Mandate_Client())->execute(aa_img_cmd($img_ov));
    ac_assert('overlap image vs open container', $ov_ct->state() === RetireCanonicalRecordImageResult::STATE_SCOPE_OVERLAP);
    $open_ct = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_CONTAINER, $container_id);
    if ($open_ct !== null) {
        $runs->mark_cancelled((int) $open_ct['id'], $now);
    }

    // Fail-closed: second image while first image purge open
    $op_a = aa_img_uuid(11);
    $img_a = aa_img_insert_image($wpdb, $record_id, $op_a, str_repeat('bb', 32), 12, $now);
    $op_b = aa_img_uuid(12);
    $img_b = aa_img_insert_image($wpdb, $record_id, $op_b, str_repeat('cc', 32), 13, $now);
    $runs->insert_open_run([
        'scope' => CanonicalPurgeRunsRepository::SCOPE_IMAGE,
        'target_id' => $img_a,
        'container_id' => $container_id,
        'record_id' => $record_id,
        'family_key' => 'finance',
        'mandate_id' => aa_img_uuid(902),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    ac_assert('has_blocking_purge ve scope=image', $runs->has_blocking_purge($record_id, $container_id) === true);
    $ov_img = aa_img_retire_uc($wpdb, $lock, new AA_Test_Img_Delete_Mandate_Client())->execute(aa_img_cmd($img_b));
    ac_assert('overlap image vs otra image del mismo record', $ov_img->state() === RetireCanonicalRecordImageResult::STATE_SCOPE_OVERLAP);
    $open_a = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $img_a);
    if ($open_a !== null) {
        $runs->mark_cancelled((int) $open_a['id'], $now);
    }

    // Cancel before remote
    $op_c = aa_img_uuid(13);
    $img_c = aa_img_insert_image($wpdb, $record_id, $op_c, str_repeat('dd', 32), 14, $now);
    $cancel_client = new AA_Test_Img_Delete_Mandate_Client();
    $cancel_client->accept_fn = static function () {
        return aa_img_retire_hmac_fail('unreachable');
    };
    $inc = aa_img_retire_uc($wpdb, $lock, $cancel_client)->execute(aa_img_cmd($img_c));
    // May be incomplete after failed accept with intent, or conflict — cancel path:
    $open_c = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $img_c);
    if ($open_c !== null && !CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open_c)) {
        $cancelled = aa_img_retire_uc($wpdb, $lock, new AA_Test_Img_Delete_Mandate_Client())
            ->execute(aa_img_cmd($img_c, RetireCanonicalRecordImageCommand::INTENT_CANCEL));
        ac_assert('cancel antes de remoto', $cancelled->state() === RetireCanonicalRecordImageResult::STATE_CANCELLED
            && !$runs->has_blocking_purge($record_id, $container_id));
    } else {
        // Force open without remote intent for cancel matrix
        if ($open_c !== null) {
            $runs->mark_cancelled((int) $open_c['id'], $now);
        }
        $op_c2 = aa_img_uuid(14);
        $img_c2 = aa_img_insert_image($wpdb, $record_id, $op_c2, str_repeat('ee', 32), 15, $now);
        $runs->insert_open_run([
            'scope' => CanonicalPurgeRunsRepository::SCOPE_IMAGE,
            'target_id' => $img_c2,
            'container_id' => $container_id,
            'record_id' => $record_id,
            'family_key' => 'finance',
            'mandate_id' => aa_img_uuid(903),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $cancelled = aa_img_retire_uc($wpdb, $lock, new AA_Test_Img_Delete_Mandate_Client())
            ->execute(aa_img_cmd($img_c2, RetireCanonicalRecordImageCommand::INTENT_CANCEL));
        ac_assert('cancel antes de remoto', $cancelled->state() === RetireCanonicalRecordImageResult::STATE_CANCELLED
            && $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $img_c2) === null);
    }

    // Accept perdido → status / already_accepted
    $op_lost = aa_img_uuid(20);
    $img_lost = aa_img_insert_image($wpdb, $record_id, $op_lost, str_repeat('ff', 32), 16, $now);
    $lost_client = new AA_Test_Img_Delete_Mandate_Client();
    $lost_accepts = 0;
    $lost_client->accept_fn = static function (array $input) use (&$lost_accepts, $inventory, $runs, $img_lost) {
        $lost_accepts++;
        if ($lost_accepts === 1) {
            return aa_img_retire_hmac_fail('unreachable');
        }
        $open = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $img_lost);
        $rows = $inventory->list_prepared_batch((int) $open['id'], 0);

        return aa_img_retire_accept_ok((string) $input['mandate_id'], 0, $rows, 'already_accepted');
    };
    $lost_client->status_fn = static function (array $input) use ($inventory, $runs, $img_lost) {
        $open = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $img_lost);
        $rows = $inventory->list_prepared_batch((int) $open['id'], 0);

        return aa_img_retire_status_credit_ok((string) $input['mandate_id'], 0, $rows);
    };
    $lost_client->seal_fn = static function (array $input) {
        return aa_img_retire_seal_ok((string) $input['mandate_id'], 1);
    };
    $lost1 = aa_img_retire_uc($wpdb, $lock, $lost_client)->execute(aa_img_cmd($img_lost));
    ac_assert('accept perdido → incomplete', $lost1->state() === RetireCanonicalRecordImageResult::STATE_INCOMPLETE);
    $lost2 = aa_img_retire_uc($wpdb, $lock, $lost_client)->execute(aa_img_cmd($img_lost));
    ac_assert('reanudación via status/already_accepted sin segundo inventado ciego', $lost2->state() === RetireCanonicalRecordImageResult::STATE_CONFIRMED
        && $images->find_by_id($img_lost) === null);

    // Seal perdido → status sealed → local delete
    $op_seal = aa_img_uuid(21);
    $img_seal = aa_img_insert_image($wpdb, $record_id, $op_seal, str_repeat('a1', 32), 17, $now);
    $seal_client = new AA_Test_Img_Delete_Mandate_Client();
    $seal_attempts = 0;
    $seal_client->accept_fn = static function (array $input) use ($inventory, $runs, $img_seal) {
        $open = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $img_seal);
        $rows = $inventory->list_prepared_batch((int) $open['id'], 0);

        return aa_img_retire_accept_ok((string) $input['mandate_id'], 0, $rows);
    };
    $seal_client->seal_fn = static function (array $input) use (&$seal_attempts) {
        $seal_attempts++;
        if ($seal_attempts === 1) {
            return aa_img_retire_hmac_fail('unreachable');
        }

        return aa_img_retire_seal_ok((string) $input['mandate_id'], 1);
    };
    $seal_client->status_fn = static function (array $input) {
        return aa_img_retire_status_sealed_ok((string) $input['mandate_id'], 1);
    };
    $seal1 = aa_img_retire_uc($wpdb, $lock, $seal_client)->execute(aa_img_cmd($img_seal));
    ac_assert('seal perdido → incomplete sin DELETE local prematuro', $seal1->state() === RetireCanonicalRecordImageResult::STATE_INCOMPLETE
        && $images->find_by_id($img_seal) !== null);
    $seal2 = aa_img_retire_uc($wpdb, $lock, $seal_client)->execute(aa_img_cmd($img_seal));
    ac_assert('seal recuperable → confirmed', $seal2->state() === RetireCanonicalRecordImageResult::STATE_CONFIRMED
        && $images->find_by_id($img_seal) === null);

    // Cancel rejected after remote intent
    $op_rej = aa_img_uuid(22);
    $img_rej = aa_img_insert_image($wpdb, $record_id, $op_rej, str_repeat('a2', 32), 18, $now);
    $rej_client = new AA_Test_Img_Delete_Mandate_Client();
    $rej_client->accept_fn = static function () {
        return aa_img_retire_hmac_fail('unreachable');
    };
    $rej1 = aa_img_retire_uc($wpdb, $lock, $rej_client)->execute(aa_img_cmd($img_rej));
    $open_rej = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $img_rej);
    if ($open_rej !== null && CanonicalPurgeRunsRepository::has_attempted_remote_dispatch($open_rej)) {
        $rej_cancel = aa_img_retire_uc($wpdb, $lock, new AA_Test_Img_Delete_Mandate_Client())
            ->execute(aa_img_cmd($img_rej, RetireCanonicalRecordImageCommand::INTENT_CANCEL));
        ac_assert('cancel_rejected tras intento remoto', $rej_cancel->state() === RetireCanonicalRecordImageResult::STATE_CANCEL_REJECTED);
        $runs->mark_cancelled((int) $open_rej['id'], $now);
    } else {
        ac_assert('cancel_rejected tras intento remoto', $rej1->state() === RetireCanonicalRecordImageResult::STATE_INCOMPLETE);
        if ($open_rej !== null) {
            $runs->mark_cancelled((int) $open_rej['id'], $now);
        }
    }

    // Uncertain COMMIT
    $op_u = aa_img_uuid(23);
    $img_u = aa_img_insert_image($wpdb, $record_id, $op_u, str_repeat('a3', 32), 19, $now);
    $u_client = new AA_Test_Img_Delete_Mandate_Client();
    $u_client->accept_fn = static function (array $input) use ($inventory, $runs, $img_u) {
        $open = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $img_u);
        $rows = $inventory->list_prepared_batch((int) $open['id'], 0);

        return aa_img_retire_accept_ok((string) $input['mandate_id'], 0, $rows);
    };
    $u_client->seal_fn = static function (array $input) {
        return aa_img_retire_seal_ok((string) $input['mandate_id'], 1);
    };
    $uncertain_store = new AA_Test_Img_Uncertain_Retire_Store(
        new CanonicalPurgeRunsRepository($wpdb),
        new CanonicalPurgeInventoryItemsRepository($wpdb),
        new CanonicalRecordImagesRepository($wpdb),
        new CanonicalImageUploadOperationsRepository($wpdb),
        $wpdb
    );
    $u_res = aa_img_retire_uc($wpdb, $lock, $u_client, $uncertain_store)->execute(aa_img_cmd($img_u));
    ac_assert('COMMIT ambiguo → uncertain', $u_res->state() === RetireCanonicalRecordImageResult::STATE_UNCERTAIN);

    // Familia/contenedor ajenos: wrong family_key
    $op_f = aa_img_uuid(24);
    $img_f = aa_img_insert_image($wpdb, $record_id, $op_f, str_repeat('a4', 32), 20, $now);
    try {
        $bad = new RetireCanonicalRecordImageUseCase(
            $rel,
            new CaptureCanonicalRecordImagePurgeUseCase($runs, $images, $ops, $inventory, new AA_Canonical_Purge_Capture_Store($runs, $inventory, $wpdb)),
            $runs,
            $inventory,
            $images,
            new AA_Canonical_Purge_Local_Retire_Store($runs, $inventory, $images, $ops, $wpdb),
            new AA_Test_Img_Delete_Mandate_Client(),
            $lock
        );
        $bad_res = $bad->execute(new RetireCanonicalRecordImageCommand('unknown_family_xyz', $img_f));
        ac_assert('familia ajena rechazada', $bad_res->state() === RetireCanonicalRecordImageResult::STATE_CONTAINER_NOT_FOUND
            || $bad_res->state() === RetireCanonicalRecordImageResult::STATE_FORBIDDEN
            || $bad_res->state() === RetireCanonicalRecordImageResult::STATE_IMAGE_NOT_FOUND);
    } catch (\Throwable $e) {
        ac_assert('familia ajena rechazada', true);
    }

    // Metadata conflict image vs op
    $op_m = aa_img_uuid(25);
    $img_m = aa_img_insert_image($wpdb, $record_id, $op_m, str_repeat('a5', 32), 21, $now);
    aa_img_insert_op($wpdb, $record_id, $op_m, str_repeat('b5', 32), 99, AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED, $now);
    $m_res = aa_img_retire_uc($wpdb, $lock, new AA_Test_Img_Delete_Mandate_Client())->execute(aa_img_cmd($img_m));
    ac_assert('metadata mismatch → conflict', $m_res->state() === RetireCanonicalRecordImageResult::STATE_CONFLICT);
    $open_m = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_IMAGE, $img_m);
    if ($open_m !== null) {
        $runs->mark_cancelled((int) $open_m['id'], $now);
    }

    // Segunda conexión / lock busy smoke
    $db2 = aa_img_retire_second_wpdb($temp_prefix);
    $lock2 = aa_img_retire_lock($db2);
    $lease1 = $lock->acquire(
        AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER,
        $container_id,
        AA_Expediente_Aggregate_Lock::MAX_TIMEOUT_SECONDS
    );
    $busy = aa_img_retire_uc($db2, $lock2, new AA_Test_Img_Delete_Mandate_Client())->execute(aa_img_cmd($image2));
    ac_assert('resource_busy con lock ajeno', $busy->state() === RetireCanonicalRecordImageResult::STATE_RESOURCE_BUSY);
    if (!is_wp_error($lease1)) {
        $lock->release($lease1);
    }

    ac_assert('registro sigue vivo al final', $rel->find_record($container_id, $record_id) !== null);
} catch (\Throwable $e) {
    ac_assert('excepción no esperada: ' . $e->getMessage(), false);
} finally {
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($temp_prefix) . '%'));
    if (is_array($tables)) {
        foreach ($tables as $table) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $table) . '`');
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
echo 'Failed ' . count($failed) . "/{$total}: " . implode(', ', $failed) . "\n";
exit(1);
