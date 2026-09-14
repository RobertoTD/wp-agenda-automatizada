<?php
/**
 * AC IMG-5 incremento 3 — retiro de un registro canónico vía mandatos.
 *
 * Estructural siempre. MySQL con prefijo temporal si AA_WP_ROOT está definido.
 * HTTP doblado (sin Storage/Render).
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-retire-canonical-record-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/images/test-retire-canonical-record-ac.php
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

$uc_file = $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordUseCase.php';
$store_file = $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-local-retire-store.php';
$ajax_file = $plugin_root . '/includes/http/ajax/CanonicalDeleteRecordAjax.php';
$capture_uc = $plugin_root . '/includes/application/canonical/images/CaptureCanonicalPurgeInventoryUseCase.php';
$capture_store = $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-capture-store.php';

$uc_src = (string) file_get_contents($uc_file);
$store_src = (string) file_get_contents($store_file);
$ajax_src = (string) file_get_contents($ajax_file);
$capture_src = (string) file_get_contents($capture_uc);
$cstore_src = (string) file_get_contents($capture_store);

$adv_file = $plugin_root . '/includes/application/canonical/images/CanonicalPurgeRemoteMandateAdvancer.php';
$adv_src = (string) file_get_contents($adv_file);

ac_assert('archivos del incremento 3 existen', is_file($uc_file) && is_file($store_file));
ac_assert('AJAX solo cablea Retire de registro', strpos($ajax_src, 'RetireCanonicalRecordUseCase') !== false
    && strpos($ajax_src, 'WriteCanonicalShellRecordUseCase') === false
    && strpos($ajax_src, "'mandate_id'") === false
    && strpos($ajax_src, '"mandate_id"') === false);
ac_assert('contenedor no cableado a Retire de registro', strpos((string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalDeleteContainerAjax.php'), 'RetireCanonicalRecordUseCase') === false);
ac_assert('captura bajo lock ya adquirido, sin acquire interno', strpos($capture_src, 'function execute_with_held_lock') !== false
    && strpos($uc_src, 'execute_with_held_lock') !== false
    && strpos($uc_src, 'START TRANSACTION') === false);
ac_assert('intención de envío antes de HMAC', strpos($adv_src, 'persist_accept_intent') !== false
    && strpos($adv_src, 'persist_accept_intent') < strpos($adv_src, 'accept_delete_batch')
    && strpos($adv_src, 'persist_seal_intent') !== false
    && strpos($adv_src, 'persist_seal_intent') < strpos($adv_src, 'seal_delete_mandate'));
ac_assert('tandas 0-based en preparación', strpos($cstore_src, 'intdiv($index, $max) + 1') === false
    && strpos($cstore_src, 'intdiv($index, $max)') !== false);
ac_assert('TX local DELETE+touch+completed', strpos($store_src, 'delete_by_upload_operation_id') !== false
    && strpos($store_src, 'delete_by_operation_id') !== false
    && strpos($store_src, 'live_rows_outside_inventory') !== false
    && strpos($store_src, 'mark_completed') !== false
    && strpos($store_src, 'START TRANSACTION') !== false);
ac_assert('vacío skip HMAC', strpos($uc_src, 'prepared_batch_count') !== false
    && strpos($uc_src, 'accept_delete_batch') !== false);
ac_assert('cancelación sin envío remoto', strpos($uc_src, 'mark_cancelled') !== false
    && strpos($uc_src, 'has_attempted_remote_dispatch') !== false);
ac_assert('Write::delete corto sigue sin guarda obligatoria', strpos((string) file_get_contents($plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php'), 'if ($this->purge_runs === null)') !== false);

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
require_once $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';
require_once $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-capture-store.php';
require_once $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-local-retire-store.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalPurgeCaptureResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CaptureCanonicalPurgeInventoryCommand.php';
require_once $plugin_root . '/includes/application/canonical/images/CaptureCanonicalPurgeInventoryUseCase.php';
require_once $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordCommand.php';
require_once $plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordResult.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalPurgeLocalRetireResult.php';
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

final class AA_Test_Delete_Mandate_Client extends AA_Expediente_Attachments_Backend_Client {
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

        return aa_retire_hmac_fail('unreachable');
    }

    public function seal_delete_mandate(array $input): array {
        $this->calls[] = ['seal', $input];
        if (is_callable($this->seal_fn)) {
            return ($this->seal_fn)($input);
        }

        return aa_retire_hmac_fail('unreachable');
    }

    public function get_delete_mandate_status(array $input): array {
        $this->calls[] = ['status', $input];
        if (is_callable($this->status_fn)) {
            return ($this->status_fn)($input);
        }

        return aa_retire_hmac_fail('unreachable');
    }
}

final class AA_Test_Uncertain_Retire_Store extends AA_Canonical_Purge_Local_Retire_Store {
    /** @var bool */
    public $lie_after_commit = false;

    protected function commit_transaction(): bool {
        if ($this->lie_after_commit) {
            parent::commit_transaction();

            return false;
        }

        return false;
    }
}

/**
 * @return array<string, mixed>
 */
function aa_retire_hmac_fail(string $class): array {
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
function aa_retire_hmac_items(array $rows): array {
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
function aa_retire_accept_ok(string $mandate_id, int $seq, array $rows, string $acceptance = 'accepted'): array {
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
            'items' => aa_retire_hmac_items($rows),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function aa_retire_seal_ok(string $mandate_id, int $count): array {
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
function aa_retire_status_credit_ok(string $mandate_id, int $seq, array $rows): array {
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
                'items' => aa_retire_hmac_items($rows),
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function aa_retire_status_sealed_ok(string $mandate_id, int $count): array {
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
 * @return object
 */
function aa_retire_second_wpdb(string $prefix) {
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
function aa_retire_lock($db): AA_Expediente_Aggregate_Lock {
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
function aa_retire_uc(
    $db,
    AA_Expediente_Aggregate_Lock $lock,
    AA_Test_Delete_Mandate_Client $client,
    ?AA_Canonical_Purge_Local_Retire_Store $local = null
): RetireCanonicalRecordUseCase {
    $runs = new CanonicalPurgeRunsRepository($db);
    $inv = new CanonicalPurgeInventoryItemsRepository($db);
    $images = new CanonicalRecordImagesRepository($db);
    $ops = new CanonicalImageUploadOperationsRepository($db);
    $cstore = new AA_Canonical_Purge_Capture_Store($runs, $inv, $db);
    $capture = new CaptureCanonicalPurgeInventoryUseCase($runs, $images, $ops, $inv, $cstore, $lock);
    $rel = new CanonicalRelationalRepository($db);
    $local_store = $local ?: new AA_Canonical_Purge_Local_Retire_Store($runs, $inv, $images, $ops, $db);

    return new RetireCanonicalRecordUseCase(
        $rel,
        $capture,
        $runs,
        $inv,
        $images,
        $ops,
        $local_store,
        $client,
        $lock
    );
}

function aa_retire_uuid(int $n): string {
    return sprintf('00000000-0000-4000-8000-%012d', $n);
}

/**
 * @param object $wpdb
 */
function aa_retire_seed($wpdb, string $now): array {
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
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $record_id = (int) $wpdb->insert_id;
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '33333333-3333-4333-8333-333333333333',
        'container_id' => $container_id,
        'title' => 'R2',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [
        'family_id' => $family_id,
        'container_id' => $container_id,
        'record_id' => $record_id,
        'empty_id' => (int) $wpdb->insert_id,
    ];
}

/**
 * @param object $wpdb
 */
function aa_retire_insert_image($wpdb, int $record_id, string $op, string $sha, int $bytes, string $now): void {
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
}

/**
 * @param object $wpdb
 */
function aa_retire_insert_op($wpdb, int $record_id, string $op, string $sha, int $bytes, string $status, string $now, bool $expired = false): void {
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
        'expires_at' => $expired ? '2020-01-01 00:00:00' : '2099-01-01 00:00:00',
        'backend_intent_exp_ms' => $expired ? 1 : 9000000000000,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function aa_retire_cmd(int $container_id, int $record_id, string $intent = RetireCanonicalRecordCommand::INTENT_RETIRE): RetireCanonicalRecordCommand {
    return new RetireCanonicalRecordCommand('finance', $container_id, $record_id, $intent);
}

global $wpdb;
$orig_prefix = $wpdb->prefix;
$token = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
$temp_prefix = 'tmp_pg3' . $token . '_';
$wpdb->prefix = $temp_prefix;
$now = gmdate('Y-m-d H:i:s');

try {
    AA_Canonical_Schema::install();
    $ids = aa_retire_seed($wpdb, $now);
    $family_id = $ids['family_id'];
    $container_id = $ids['container_id'];
    $record_id = $ids['record_id'];
    $empty_id = $ids['empty_id'];
    $runs = new CanonicalPurgeRunsRepository($wpdb);
    $inventory = new CanonicalPurgeInventoryItemsRepository($wpdb);
    $images = new CanonicalRecordImagesRepository($wpdb);
    $ops = new CanonicalImageUploadOperationsRepository($wpdb);
    $lock = aa_retire_lock($wpdb);

    $empty_client = new AA_Test_Delete_Mandate_Client();
    $empty_res = aa_retire_uc($wpdb, $lock, $empty_client)->execute(aa_retire_cmd($container_id, $empty_id));
    $empty_run = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_RECORD, $empty_id);
    $empty_completed = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM `' . str_replace('`', '``', AA_Canonical_Schema::purge_runs_table_name()) . '` WHERE target_id = %d ORDER BY id DESC LIMIT 1',
        $empty_id
    ), ARRAY_A);
    ac_assert('vacío sin HMAC', $empty_res->state() === RetireCanonicalRecordResult::STATE_CONFIRMED
        && $empty_client->calls === []
        && $empty_run === null
        && is_array($empty_completed)
        && ($empty_completed['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_COMPLETED
        && (int) ($empty_completed['prepared_batch_count'] ?? -1) === 0);
    ac_assert('vacío borra el registro', $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d',
        $empty_id
    )) === '0' || (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d',
        $empty_id
    )) === 0);

    $op1 = aa_retire_uuid(1);
    $sha1 = str_repeat('11', 32);
    aa_retire_insert_image($wpdb, $record_id, $op1, $sha1, 100, $now);
    $quota_before = $images->sum_byte_size_total();
    $one_client = new AA_Test_Delete_Mandate_Client();
    $one_client->accept_fn = static function (array $input) use ($inventory, $runs) {
        $open = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_RECORD, (int) $input['items'][0]['wp_record_id']);
        $rows = $inventory->list_prepared_batch((int) $open['id'], (int) $input['batch_seq']);
        ac_assert('primera tanda HMAC es 0', (int) $input['batch_seq'] === 0);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $one_client->seal_fn = static function (array $input) {
        ac_assert('seal count=1 para una tanda', (int) $input['expected_batch_count'] === 1);

        return aa_retire_seal_ok((string) $input['mandate_id'], 1);
    };
    $one_res = aa_retire_uc($wpdb, $lock, $one_client)->execute(aa_retire_cmd($container_id, $record_id));
    $one_run = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM `' . str_replace('`', '``', AA_Canonical_Schema::purge_runs_table_name()) . '` WHERE target_id = %d ORDER BY id DESC LIMIT 1',
        $record_id
    ), ARRAY_A);
    ac_assert('una imagen: confirmed y filas locales ausentes', $one_res->state() === RetireCanonicalRecordResult::STATE_CONFIRMED
        && $images->count_for_record($record_id) === 0
        && $ops->count_for_record($record_id) === 0
        && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $record_id)) === 0);
    ac_assert('inventario conservado y cuota SUM baja', is_array($one_run)
        && $inventory->count_for_run((int) $one_run['id']) === 1
        && ($one_run['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_COMPLETED
        && $images->sum_byte_size_total() === $quota_before - 100);
    ac_assert('last_accepted 0 no NULL', CanonicalPurgeRunsRepository::nullable_int($one_run['last_accepted_batch_seq'] ?? null) === 0);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '44444444-4444-4444-8444-444444444444',
        'container_id' => $container_id,
        'title' => 'Rops',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $ops_record = (int) $wpdb->insert_id;
    $op_exp = aa_retire_uuid(2);
    $op_clean = aa_retire_uuid(3);
    aa_retire_insert_op($wpdb, $ops_record, $op_exp, str_repeat('22', 32), 50, AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED, $now, true);
    aa_retire_insert_op($wpdb, $ops_record, $op_clean, str_repeat('33', 32), 60, AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED, $now);
    $reserved_before = $ops->sum_reserved_byte_size(2000, '2020-01-02 00:00:00');
    $ops_client = new AA_Test_Delete_Mandate_Client();
    $ops_client->accept_fn = static function (array $input) use ($inventory) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            (int) $input['items'][0]['wp_record_id']
        );
        $rows = $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $ops_client->seal_fn = static function (array $input) {
        return aa_retire_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $ops_res = aa_retire_uc($wpdb, $lock, $ops_client)->execute(aa_retire_cmd($container_id, $ops_record));
    ac_assert('ops no confirmadas se retiran', $ops_res->state() === RetireCanonicalRecordResult::STATE_CONFIRMED
        && $ops->count_for_record($ops_record) === 0
        && $ops->sum_reserved_byte_size(2000, '2020-01-02 00:00:00') <= $reserved_before);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '55555555-5555-4555-8555-555555555555',
        'container_id' => $container_id,
        'title' => 'R51',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $big_id = (int) $wpdb->insert_id;
    for ($i = 1; $i <= 51; $i++) {
        aa_retire_insert_image($wpdb, $big_id, aa_retire_uuid(200 + $i), str_repeat('aa', 32), 10, $now);
    }
    $cap = new CaptureCanonicalPurgeInventoryUseCase(
        $runs,
        $images,
        $ops,
        $inventory,
        new AA_Canonical_Purge_Capture_Store($runs, $inventory, $wpdb),
        $lock
    );
    $cap_done = $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $big_id,
        $container_id,
        'finance',
        8
    ));
    ac_assert('51 ítems preparan tandas 0 y 1', $cap_done->is_capture_complete()
        && $cap_done->prepared_batch_count() === 2
        && count($inventory->list_prepared_batch((int) $cap_done->purge_run_id(), 0)) === 50
        && count($inventory->list_prepared_batch((int) $cap_done->purge_run_id(), 1)) === 1);
    $big_client = new AA_Test_Delete_Mandate_Client();
    $big_client->accept_fn = static function (array $input) use ($inventory, $cap_done) {
        $rows = $inventory->list_prepared_batch((int) $cap_done->purge_run_id(), (int) $input['batch_seq']);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $big_client->seal_fn = static function (array $input) {
        ac_assert('51 ítems seal count=2', (int) $input['expected_batch_count'] === 2);

        return aa_retire_seal_ok((string) $input['mandate_id'], 2);
    };
    $big1 = aa_retire_uc($wpdb, $lock, $big_client)->execute(aa_retire_cmd($container_id, $big_id));
    ac_assert('51: primer accept deja incomplete', $big1->state() === RetireCanonicalRecordResult::STATE_INCOMPLETE
        && CanonicalPurgeRunsRepository::nullable_int($runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_RECORD, $big_id)['last_accepted_batch_seq'] ?? null) === 0);
    $seqs = [];
    foreach ($big_client->calls as $call) {
        if ($call[0] === 'accept') {
            $seqs[] = (int) $call[1]['batch_seq'];
        }
    }
    ac_assert('51: primer HMAC es índice 0', $seqs === [0]);
    $big2 = aa_retire_uc($wpdb, $lock, $big_client)->execute(aa_retire_cmd($container_id, $big_id));
    $seqs = [];
    foreach ($big_client->calls as $call) {
        if ($call[0] === 'accept') {
            $seqs[] = (int) $call[1]['batch_seq'];
        }
    }
    $seal_counts = [];
    foreach ($big_client->calls as $call) {
        if ($call[0] === 'seal') {
            $seal_counts[] = (int) $call[1]['expected_batch_count'];
        }
    }
    ac_assert('51: accept seq 0 y 1 y confirmed', $big2->state() === RetireCanonicalRecordResult::STATE_CONFIRMED
        && $seqs === [0, 1]
        && $seal_counts === [2]);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '66666666-6666-4666-8666-666666666666',
        'container_id' => $container_id,
        'title' => 'Rlost',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $lost_id = (int) $wpdb->insert_id;
    aa_retire_insert_image($wpdb, $lost_id, aa_retire_uuid(4), str_repeat('44', 32), 40, $now);
    $lost_client = new AA_Test_Delete_Mandate_Client();
    $lost_accepts = 0;
    $lost_client->accept_fn = static function (array $input) use (&$lost_accepts, $inventory) {
        $lost_accepts++;
        if ($lost_accepts === 1) {
            return aa_retire_hmac_fail('unreachable');
        }
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            (int) $input['items'][0]['wp_record_id']
        );
        $rows = $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows, 'already_accepted');
    };
    $lost_client->status_fn = static function (array $input) {
        return [
            'ok' => true,
            'outcome' => 'not_found',
            'retire_authorized' => false,
            'result' => [
                'found' => false,
                'can_credit_batch' => false,
                'inventory_page_complete' => false,
            ],
        ];
    };
    $lost_client->seal_fn = static function (array $input) {
        return aa_retire_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $lost1 = aa_retire_uc($wpdb, $lock, $lost_client)->execute(aa_retire_cmd($container_id, $lost_id));
    $lost_run = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_RECORD, $lost_id);
    ac_assert('interrupción tras intención de accept', $lost1->state() === RetireCanonicalRecordResult::STATE_INCOMPLETE
        && CanonicalPurgeRunsRepository::nullable_int($lost_run['accept_intent_batch_seq'] ?? null) === 0
        && CanonicalPurgeRunsRepository::nullable_int($lost_run['last_accepted_batch_seq'] ?? null) === null);
    $cancel_lost = aa_retire_uc($wpdb, $lock, $lost_client)->execute(aa_retire_cmd($container_id, $lost_id, RetireCanonicalRecordCommand::INTENT_CANCEL));
    ac_assert('no cancelar con resultado remoto desconocido', $cancel_lost->state() === RetireCanonicalRecordResult::STATE_CANCEL_REJECTED);
    $lost2 = aa_retire_uc($wpdb, $lock, $lost_client)->execute(aa_retire_cmd($container_id, $lost_id));
    $replay_accept = null;
    foreach ($lost_client->calls as $call) {
        if ($call[0] === 'accept') {
            $replay_accept = $call[1];
        }
    }
    ac_assert('recuperación exacta already_accepted', $lost2->state() === RetireCanonicalRecordResult::STATE_CONFIRMED
        && $lost_accepts === 2
        && is_array($replay_accept)
        && (string) $lost_client->calls[0][1]['mandate_id'] === (string) $replay_accept['mandate_id']
        && (int) $lost_client->calls[0][1]['batch_seq'] === (int) $replay_accept['batch_seq']
        && $lost_client->calls[0][1]['items'] === $replay_accept['items']);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '12121212-1212-4121-8121-121212121212',
        'container_id' => $container_id,
        'title' => 'Rstatus',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $st_id = (int) $wpdb->insert_id;
    aa_retire_insert_image($wpdb, $st_id, aa_retire_uuid(13), str_repeat('13', 32), 13, $now);
    $st_client = new AA_Test_Delete_Mandate_Client();
    $st_accepts = 0;
    $st_client->accept_fn = static function (array $input) use (&$st_accepts) {
        $st_accepts++;

        return aa_retire_hmac_fail('unreachable');
    };
    $st_client->status_fn = static function (array $input) use ($inventory, $st_id) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            $st_id
        );
        $seq = (int) ($input['batch_seq'] ?? -1);
        $rows = is_array($run) ? $inventory->list_prepared_batch((int) $run['id'], $seq) : [];

        return aa_retire_status_credit_ok((string) $input['mandate_id'], $seq, $rows);
    };
    $st_client->seal_fn = static function (array $input) {
        return aa_retire_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $st1 = aa_retire_uc($wpdb, $lock, $st_client)->execute(aa_retire_cmd($container_id, $st_id));
    ac_assert('status: primera petición deja intención', $st1->state() === RetireCanonicalRecordResult::STATE_INCOMPLETE
        && $st_accepts === 1);
    $st2 = aa_retire_uc($wpdb, $lock, $st_client)->execute(aa_retire_cmd($container_id, $st_id));
    $st_accept_calls = 0;
    $st_status_calls = 0;
    foreach ($st_client->calls as $call) {
        if ($call[0] === 'accept') {
            $st_accept_calls++;
        }
        if ($call[0] === 'status') {
            $st_status_calls++;
        }
    }
    ac_assert('status acredita tanda sin segundo accept', $st2->state() === RetireCanonicalRecordResult::STATE_CONFIRMED
        && $st_accepts === 1
        && $st_accept_calls === 1
        && $st_status_calls >= 1
        && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $st_id)) === 0);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '77777777-7777-4777-8777-777777777777',
        'container_id' => $container_id,
        'title' => 'Rseal',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $seal_id = (int) $wpdb->insert_id;
    aa_retire_insert_image($wpdb, $seal_id, aa_retire_uuid(5), str_repeat('55', 32), 55, $now);
    $seal_client = new AA_Test_Delete_Mandate_Client();
    $seal_tries = 0;
    $seal_client->accept_fn = static function (array $input) use ($inventory) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            (int) $input['items'][0]['wp_record_id']
        );
        $rows = $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $seal_client->seal_fn = static function (array $input) use (&$seal_tries) {
        $seal_tries++;
        if ($seal_tries === 1) {
            return aa_retire_hmac_fail('unreachable');
        }

        return aa_retire_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $seal_client->status_fn = static function (array $input) {
        return [
            'ok' => true,
            'outcome' => 'found',
            'retire_authorized' => false,
            'result' => [
                'found' => true,
                'mandate_id' => $input['mandate_id'],
                'inventory_status' => 'open',
                'contiguous_prefix_len' => 1,
                'structural_retire_authorized' => false,
                'can_credit_batch' => false,
                'inventory_page_complete' => true,
                'items' => [],
                'items_page' => ['limit' => 50, 'has_more' => false, 'next_cursor' => null],
            ],
        ];
    };
    $seal1 = aa_retire_uc($wpdb, $lock, $seal_client)->execute(aa_retire_cmd($container_id, $seal_id));
    $seal_run = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_RECORD, $seal_id);
    ac_assert('seal perdido deja intención y no borra', $seal1->state() === RetireCanonicalRecordResult::STATE_INCOMPLETE
        && CanonicalPurgeRunsRepository::nullable_string($seal_run['seal_intent_at'] ?? null) !== null
        && CanonicalPurgeRunsRepository::nullable_string($seal_run['sealed_at'] ?? null) === null
        && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $seal_id)) === 1);
    $seal2 = aa_retire_uc($wpdb, $lock, $seal_client)->execute(aa_retire_cmd($container_id, $seal_id));
    ac_assert('seal recuperado y retiro local', $seal2->state() === RetireCanonicalRecordResult::STATE_CONFIRMED && $seal_tries === 2);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '14141414-1414-4141-8141-141414141414',
        'container_id' => $container_id,
        'title' => 'Rsealst',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $seal_st_id = (int) $wpdb->insert_id;
    aa_retire_insert_image($wpdb, $seal_st_id, aa_retire_uuid(14), str_repeat('14', 32), 14, $now);
    $seal_st_client = new AA_Test_Delete_Mandate_Client();
    $seal_st_tries = 0;
    $seal_st_client->accept_fn = static function (array $input) use ($inventory) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            (int) $input['items'][0]['wp_record_id']
        );
        $rows = $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $seal_st_client->seal_fn = static function (array $input) use (&$seal_st_tries) {
        $seal_st_tries++;

        return aa_retire_hmac_fail('unreachable');
    };
    $seal_st_client->status_fn = static function (array $input) {
        return aa_retire_status_sealed_ok((string) $input['mandate_id'], 1);
    };
    $seal_st1 = aa_retire_uc($wpdb, $lock, $seal_st_client)->execute(aa_retire_cmd($container_id, $seal_st_id));
    ac_assert('seal status: intención sin sello local', $seal_st1->state() === RetireCanonicalRecordResult::STATE_INCOMPLETE
        && $seal_st_tries === 1);
    $seal_st2 = aa_retire_uc($wpdb, $lock, $seal_st_client)->execute(aa_retire_cmd($container_id, $seal_st_id));
    ac_assert('seal status acredita sin segundo POST seal', $seal_st2->state() === RetireCanonicalRecordResult::STATE_CONFIRMED
        && $seal_st_tries === 1
        && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $seal_st_id)) === 0);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '88888888-8888-4888-8888-888888888888',
        'container_id' => $container_id,
        'title' => 'Rbad',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $bad_id = (int) $wpdb->insert_id;
    aa_retire_insert_image($wpdb, $bad_id, aa_retire_uuid(6), str_repeat('66', 32), 66, $now);
    $bad_client = new AA_Test_Delete_Mandate_Client();
    $bad_client->accept_fn = static function (array $input) use ($inventory) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            (int) $input['items'][0]['wp_record_id']
        );
        $rows = $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']);
        $rows[0]['content_sha256'] = str_repeat('ff', 32);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $bad_res = aa_retire_uc($wpdb, $lock, $bad_client)->execute(aa_retire_cmd($container_id, $bad_id));
    ac_assert('payload de otra identidad no autoriza', $bad_res->state() === RetireCanonicalRecordResult::STATE_INTERVENTION_REQUIRED
        && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $bad_id)) === 1);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '99999999-9999-4999-8999-999999999999',
        'container_id' => $container_id,
        'title' => 'Rconf',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $conf_id = (int) $wpdb->insert_id;
    $op_conf = aa_retire_uuid(7);
    aa_retire_insert_image($wpdb, $conf_id, $op_conf, str_repeat('77', 32), 70, $now);
    $cap_conf = $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $conf_id,
        $container_id,
        'finance',
        8
    ));
    $cancel_pre = aa_retire_uc($wpdb, $lock, new AA_Test_Delete_Mandate_Client())->execute(
        aa_retire_cmd($container_id, $conf_id, RetireCanonicalRecordCommand::INTENT_CANCEL)
    );
    $conf_run = $runs->find_by_id((int) $cap_conf->purge_run_id());
    ac_assert('cancelación previa al envío', $cancel_pre->state() === RetireCanonicalRecordResult::STATE_CANCELLED
        && is_array($conf_run)
        && ($conf_run['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_CANCELLED
        && $inventory->count_for_run((int) $cap_conf->purge_run_id()) >= 1
        && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $conf_id)) === 1
        && $images->count_for_record($conf_id) === 1);
    $new_client = new AA_Test_Delete_Mandate_Client();
    $new_client->accept_fn = static function (array $input) use ($inventory) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            (int) $input['items'][0]['wp_record_id']
        );
        $rows = $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $new_client->seal_fn = static function (array $input) {
        return aa_retire_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $new_res = aa_retire_uc($wpdb, $lock, $new_client)->execute(aa_retire_cmd($container_id, $conf_id));
    $open_after_cancel = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_RECORD, $conf_id);
    ac_assert('nueva eliminación no reutiliza corrida cancelada', $new_res->state() === RetireCanonicalRecordResult::STATE_CONFIRMED
        && $open_after_cancel === null
        && (string) $new_client->calls[0][1]['mandate_id'] !== (string) $cap_conf->mandate_id());

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'container_id' => $container_id,
        'title' => 'Rextra',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $extra_id = (int) $wpdb->insert_id;
    aa_retire_insert_image($wpdb, $extra_id, aa_retire_uuid(8), str_repeat('88', 32), 80, $now);
    $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $extra_id,
        $container_id,
        'finance',
        8
    ));
    aa_retire_insert_image($wpdb, $extra_id, aa_retire_uuid(9), str_repeat('99', 32), 90, $now);
    $extra_client = new AA_Test_Delete_Mandate_Client();
    $extra_client->accept_fn = static function (array $input) use ($inventory) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            (int) $input['items'][0]['wp_record_id']
        );
        $rows = $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $extra_client->seal_fn = static function (array $input) {
        return aa_retire_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $extra_res = aa_retire_uc($wpdb, $lock, $extra_client)->execute(aa_retire_cmd($container_id, $extra_id));
    ac_assert('extra viva no inventariada: fail-closed', $extra_res->state() === RetireCanonicalRecordResult::STATE_INTERVENTION_REQUIRED
        && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $extra_id)) === 1);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'container_id' => $container_id,
        'title' => 'Runc',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unc_id = (int) $wpdb->insert_id;
    aa_retire_insert_image($wpdb, $unc_id, aa_retire_uuid(10), str_repeat('10', 32), 10, $now);
    $unc_client = new AA_Test_Delete_Mandate_Client();
    $unc_client->accept_fn = static function (array $input) use ($inventory) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            (int) $input['items'][0]['wp_record_id']
        );
        $rows = $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $unc_client->seal_fn = static function (array $input) {
        return aa_retire_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $unc_store = new AA_Test_Uncertain_Retire_Store($runs, $inventory, $images, $ops, $wpdb);
    $unc1 = aa_retire_uc($wpdb, $lock, $unc_client, $unc_store)->execute(aa_retire_cmd($container_id, $unc_id));
    ac_assert('commit ambiguo → uncertain', $unc1->state() === RetireCanonicalRecordResult::STATE_UNCERTAIN
        && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d', $unc_id)) === 1);
    $unc2 = aa_retire_uc($wpdb, $lock, $unc_client)->execute(aa_retire_cmd($container_id, $unc_id));
    ac_assert('reintento tras uncertain completa', $unc2->state() === RetireCanonicalRecordResult::STATE_CONFIRMED);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        'container_id' => $container_id,
        'title' => 'Rgone',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $gone_id = (int) $wpdb->insert_id;
    aa_retire_insert_image($wpdb, $gone_id, aa_retire_uuid(11), str_repeat('11', 32), 11, $now);
    $gone_client = new AA_Test_Delete_Mandate_Client();
    $gone_client->accept_fn = static function (array $input) use ($inventory) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_RECORD,
            (int) $input['items'][0]['wp_record_id']
        );
        $rows = $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']);

        return aa_retire_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $seal_then_fail = 0;
    $gone_client->seal_fn = static function (array $input) use (&$seal_then_fail, $gone_id, $wpdb) {
        $seal_then_fail++;
        $ok = aa_retire_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
        if ($seal_then_fail === 1) {
            $wpdb->query($wpdb->prepare(
                'DELETE FROM `' . str_replace('`', '``', AA_Canonical_Schema::record_images_table_name()) . '` WHERE record_id = %d',
                $gone_id
            ));
            $wpdb->delete(AA_Canonical_Schema::records_table_name(), ['id' => $gone_id], ['%d']);
        }

        return $ok;
    };
    $gone1 = aa_retire_uc($wpdb, $lock, $gone_client)->execute(aa_retire_cmd($container_id, $gone_id));
    ac_assert('retiro con registro ya ausente confirma cierre', $gone1->state() === RetireCanonicalRecordResult::STATE_CONFIRMED);
    $gone2 = aa_retire_uc($wpdb, $lock, $gone_client)->execute(aa_retire_cmd($container_id, $gone_id));
    ac_assert('repetición tras completed → record_not_found', $gone2->state() === RetireCanonicalRecordResult::STATE_RECORD_NOT_FOUND);

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
        'container_id' => $container_id,
        'title' => 'Rleg',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $leg_id = (int) $wpdb->insert_id;
    aa_retire_insert_image($wpdb, $leg_id, aa_retire_uuid(12), str_repeat('12', 32), 12, $now);
    $leg_cap = $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $leg_id,
        $container_id,
        'finance',
        8
    ));
    $wpdb->query($wpdb->prepare(
        'UPDATE `' . str_replace('`', '``', AA_Canonical_Schema::purge_inventory_items_table_name()) . '` SET batch_seq = batch_seq + 1 WHERE purge_run_id = %d',
        (int) $leg_cap->purge_run_id()
    ));
    $leg_res = aa_retire_uc($wpdb, $lock, new AA_Test_Delete_Mandate_Client())->execute(aa_retire_cmd($container_id, $leg_id));
    ac_assert('formato 1-based no se envía ni se renumera', $leg_res->state() === RetireCanonicalRecordResult::STATE_CONFLICT
        && $leg_res->conflict_code() === CanonicalPurgeRunsRepository::CONFLICT_LEGACY_BATCH_INDEX
        && $leg_res->can_cancel());

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
        'container_id' => $container_id,
        'title' => 'Rbusy',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $busy_id = (int) $wpdb->insert_id;
    $lease_hold = $lock->acquire(AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER, $container_id, 1);
    $wpdb2 = aa_retire_second_wpdb($temp_prefix);
    $lock2 = aa_retire_lock($wpdb2);
    $busy_client = new AA_Test_Delete_Mandate_Client();
    $busy_res = aa_retire_uc($wpdb2, $lock2, $busy_client)->execute(aa_retire_cmd($container_id, $busy_id));
    $cancel_busy = aa_retire_uc($wpdb2, $lock2, $busy_client)->execute(
        aa_retire_cmd($container_id, $busy_id, RetireCanonicalRecordCommand::INTENT_CANCEL)
    );
    ac_assert('continuar concurrente → resource_busy', !is_wp_error($lease_hold)
        && $busy_res->state() === RetireCanonicalRecordResult::STATE_RESOURCE_BUSY);
    ac_assert('cancelar frente a lock ajeno → resource_busy', $cancel_busy->state() === RetireCanonicalRecordResult::STATE_RESOURCE_BUSY);
    $lock->release($lease_hold);

    $foreign = aa_retire_uc($wpdb, $lock, new AA_Test_Delete_Mandate_Client())->execute(
        new RetireCanonicalRecordCommand('archive', $container_id, $busy_id)
    );
    ac_assert('acceso con familia distinta rechazado', $foreign->state() === RetireCanonicalRecordResult::STATE_CONTAINER_NOT_FOUND
        || $foreign->state() === RetireCanonicalRecordResult::STATE_FORBIDDEN);

    $write_src = (string) file_get_contents($plugin_root . '/includes/application/canonical/WriteCanonicalShellRecordUseCase.php');
    ac_assert('new Write($gateway) sigue sin exige purge', strpos($write_src, '?CanonicalPurgeRunsRepository $purge_runs = null') !== false);
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
