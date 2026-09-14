<?php
/**
 * AC IMG-5 incremento 4 — retiro de una lista canónica vía mandatos.
 *
 * Estructural siempre. MySQL con prefijo temporal si AA_WP_ROOT está definido.
 * HTTP doblado (sin Storage/Render).
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-retire-canonical-container-ac.php
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/images/test-retire-canonical-container-ac.php
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

$uc_file = $plugin_root . '/includes/application/canonical/images/RetireCanonicalContainerUseCase.php';
$store_file = $plugin_root . '/includes/infrastructure/canonical/images/class-aa-canonical-purge-local-retire-store.php';
$ajax_file = $plugin_root . '/includes/http/ajax/CanonicalDeleteContainerAjax.php';
$adv_file = $plugin_root . '/includes/application/canonical/images/CanonicalPurgeRemoteMandateAdvancer.php';
$inv_file = $plugin_root . '/includes/repositories/CanonicalPurgeInventoryItemsRepository.php';
$rel_file = $plugin_root . '/includes/repositories/CanonicalRelationalRepository.php';

$uc_src = (string) file_get_contents($uc_file);
$store_src = (string) file_get_contents($store_file);
$ajax_src = (string) file_get_contents($ajax_file);
$adv_src = (string) file_get_contents($adv_file);
$inv_src = (string) file_get_contents($inv_file);
$rel_src = (string) file_get_contents($rel_file);

ac_assert('archivos del incremento 4 existen', is_file($uc_file) && is_file($store_file));
ac_assert('AJAX cablea Retire de contenedor', strpos($ajax_src, 'new RetireCanonicalContainerUseCase()') !== false
    && strpos($ajax_src, 'new WriteCanonicalShellContainerUseCase') === false
    && strpos($ajax_src, "'mandate_id'") === false
    && strpos($ajax_src, '"mandate_id"') === false);
ac_assert('UseCase sin START TRANSACTION', strpos($uc_src, 'START TRANSACTION') === false
    && strpos($uc_src, 'execute_with_held_lock') !== false);
ac_assert('intención de envío antes de HMAC', strpos($adv_src, 'persist_accept_intent') !== false
    && strpos($adv_src, 'persist_accept_intent') < strpos($adv_src, 'accept_delete_batch')
    && strpos($adv_src, 'persist_seal_intent') !== false
    && strpos($adv_src, 'persist_seal_intent') < strpos($adv_src, 'seal_delete_mandate')
    && strpos($adv_src, 'expected_batch_count') !== false
    && strpos($adv_src, 'puede ser 0') !== false);
ac_assert('chunks keyset sin OFFSET', strpos($store_src, 'function retire_container_chunk') !== false
    && strpos($store_src, 'OFFSET %') === false
    && strpos($inv_src, 'function list_page_after_id') !== false
    && strpos($inv_src, 'id > %d') !== false
    && strpos($rel_src, 'function list_record_ids_after') !== false
    && preg_match('/function list_record_ids_after[\s\S]{0,500}id > %d/', $rel_src) === 1);
ac_assert('seal también con inventario vacío', strpos($uc_src, 'advance_remote') !== false
    && preg_match('/\$prepared === 0/', $uc_src) === 1
    && strpos($uc_src, 'live_rows_outside_empty_inventory') !== false);
ac_assert('cancelación sin envío remoto', strpos($uc_src, 'mark_cancelled') !== false
    && strpos($uc_src, 'has_attempted_remote_dispatch') !== false);
ac_assert('DELETE contenedor + completed misma TX', strpos($store_src, 'container_delete_failed') !== false
    && strpos($store_src, 'mark_completed') !== false
    && strpos($store_src, 'commit_chunk_incomplete') !== false);

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
require_once $plugin_root . '/includes/application/canonical/images/RetireCanonicalContainerCommand.php';
require_once $plugin_root . '/includes/application/canonical/images/RetireCanonicalContainerResult.php';
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

        return aa_c_hmac_fail('unreachable');
    }

    public function seal_delete_mandate(array $input): array {
        $this->calls[] = ['seal', $input];
        if (is_callable($this->seal_fn)) {
            return ($this->seal_fn)($input);
        }

        return aa_c_hmac_fail('unreachable');
    }

    public function get_delete_mandate_status(array $input): array {
        $this->calls[] = ['status', $input];
        if (is_callable($this->status_fn)) {
            return ($this->status_fn)($input);
        }

        return aa_c_hmac_fail('unreachable');
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
function aa_c_hmac_fail(string $class): array {
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
function aa_c_hmac_items(array $rows): array {
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
function aa_c_accept_ok(string $mandate_id, int $seq, array $rows, string $acceptance = 'accepted'): array {
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
            'items' => aa_c_hmac_items($rows),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function aa_c_seal_ok(string $mandate_id, int $count): array {
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
function aa_c_status_credit_ok(string $mandate_id, int $seq, array $rows): array {
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
                'items' => aa_c_hmac_items($rows),
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function aa_c_status_sealed_ok(string $mandate_id, int $count): array {
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
function aa_c_second_wpdb(string $prefix) {
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
function aa_c_lock($db): AA_Expediente_Aggregate_Lock {
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
function aa_c_uc(
    $db,
    AA_Expediente_Aggregate_Lock $lock,
    AA_Test_Delete_Mandate_Client $client,
    ?AA_Canonical_Purge_Local_Retire_Store $local = null
): RetireCanonicalContainerUseCase {
    $runs = new CanonicalPurgeRunsRepository($db);
    $inv = new CanonicalPurgeInventoryItemsRepository($db);
    $images = new CanonicalRecordImagesRepository($db);
    $ops = new CanonicalImageUploadOperationsRepository($db);
    $cstore = new AA_Canonical_Purge_Capture_Store($runs, $inv, $db);
    $capture = new CaptureCanonicalPurgeInventoryUseCase($runs, $images, $ops, $inv, $cstore, $lock);
    $rel = new CanonicalRelationalRepository($db);
    $local_store = $local ?: new AA_Canonical_Purge_Local_Retire_Store($runs, $inv, $images, $ops, $db);

    return new RetireCanonicalContainerUseCase(
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

function aa_c_uuid(int $n): string {
    return sprintf('00000000-0000-4000-8000-%012d', $n);
}

function aa_c_public(int $n): string {
    return sprintf('11111111-1111-4111-8111-%012d', $n);
}

function aa_c_cmd(int $container_id, string $intent = RetireCanonicalContainerCommand::INTENT_RETIRE, string $family = 'finance'): RetireCanonicalContainerCommand {
    return new RetireCanonicalContainerCommand($family, $container_id, $intent);
}

/**
 * @param object $wpdb
 */
function aa_c_new_container($wpdb, int $family_id, string $now, int $n): int {
    $wpdb->insert(AA_Canonical_Schema::containers_table_name(), [
        'public_id' => aa_c_public($n),
        'family_id' => $family_id,
        'title' => 'C' . $n,
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $wpdb->insert_id;
}

/**
 * @param object $wpdb
 */
function aa_c_new_record($wpdb, int $container_id, string $now, int $n): int {
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => sprintf('22222222-2222-4222-8222-%012d', $n),
        'container_id' => $container_id,
        'title' => 'R' . $n,
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $wpdb->insert_id;
}

/**
 * @param object $wpdb
 */
function aa_c_insert_image($wpdb, int $record_id, string $op, string $sha, int $bytes, string $now): void {
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
function aa_c_insert_op($wpdb, int $record_id, string $op, string $sha, int $bytes, string $status, string $now, bool $expired = false): void {
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

function aa_c_wire_ok(AA_Test_Delete_Mandate_Client $client, CanonicalPurgeInventoryItemsRepository $inventory, int $container_id): void {
    $client->accept_fn = static function (array $input) use ($inventory, $container_id) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
            $container_id
        );
        $rows = is_array($run) ? $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']) : [];

        return aa_c_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows);
    };
    $client->seal_fn = static function (array $input) {
        return aa_c_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
}

/**
 * @param object $wpdb
 */
function aa_c_container_exists($wpdb, int $id): bool {
    return (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::containers_table_name()) . '` WHERE id = %d',
        $id
    )) === 1;
}

/**
 * @param object $wpdb
 */
function aa_c_record_count($wpdb, int $container_id): int {
    return (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE container_id = %d',
        $container_id
    ));
}

/**
 * @param object $wpdb
 * @return array<string, mixed>|null
 */
function aa_c_latest_run($wpdb, int $container_id): ?array {
    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM `' . str_replace('`', '``', AA_Canonical_Schema::purge_runs_table_name()) . '`
         WHERE scope = %s AND target_id = %d ORDER BY id DESC LIMIT 1',
        CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        $container_id
    ), ARRAY_A);

    return is_array($row) ? $row : null;
}

global $wpdb;
$orig_prefix = $wpdb->prefix;
$token = substr(md5(uniqid((string) mt_rand(), true)), 0, 8);
$temp_prefix = 'tmp_pg4' . $token . '_';
$wpdb->prefix = $temp_prefix;
$now = gmdate('Y-m-d H:i:s');
$seq = 1;

try {
    AA_Canonical_Schema::install();
    $wpdb->insert(AA_Canonical_Schema::families_table_name(), [
        'family_key' => 'finance',
        'is_enabled' => 1,
        'seed_version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $family_id = (int) $wpdb->insert_id;
    $wpdb->insert(AA_Canonical_Schema::families_table_name(), [
        'family_key' => 'archive',
        'is_enabled' => 1,
        'seed_version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $runs = new CanonicalPurgeRunsRepository($wpdb);
    $inventory = new CanonicalPurgeInventoryItemsRepository($wpdb);
    $images = new CanonicalRecordImagesRepository($wpdb);
    $ops = new CanonicalImageUploadOperationsRepository($wpdb);
    $lock = aa_c_lock($wpdb);
    $cap = new CaptureCanonicalPurgeInventoryUseCase(
        $runs,
        $images,
        $ops,
        $inventory,
        new AA_Canonical_Purge_Capture_Store($runs, $inventory, $wpdb),
        $lock
    );

    $empty_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $empty_client = new AA_Test_Delete_Mandate_Client();
    $empty_seals = [];
    $empty_client->seal_fn = static function (array $input) use (&$empty_seals) {
        $empty_seals[] = (int) $input['expected_batch_count'];

        return aa_c_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $empty_res = aa_c_uc($wpdb, $lock, $empty_client)->execute(aa_c_cmd($empty_id));
    $empty_run = aa_c_latest_run($wpdb, $empty_id);
    $empty_accepts = 0;
    foreach ($empty_client->calls as $call) {
        if ($call[0] === 'accept') {
            $empty_accepts++;
        }
    }
    ac_assert('lista vacía: seal count=0 obligatorio', $empty_res->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && $empty_seals === [0]
        && $empty_accepts === 0
        && is_array($empty_run)
        && ($empty_run['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_COMPLETED
        && (int) ($empty_run['prepared_batch_count'] ?? -1) === 0
        && CanonicalPurgeRunsRepository::nullable_string($empty_run['seal_intent_at'] ?? null) !== null
        && CanonicalPurgeRunsRepository::nullable_string($empty_run['sealed_at'] ?? null) !== null
        && $inventory->count_for_run((int) $empty_run['id']) === 0
        && !aa_c_container_exists($wpdb, $empty_id));
    $empty_again = aa_c_uc($wpdb, $lock, $empty_client)->execute(aa_c_cmd($empty_id));
    ac_assert('repetición tras completed → container_not_found', $empty_again->state() === RetireCanonicalContainerResult::STATE_CONTAINER_NOT_FOUND);

    $plain_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    aa_c_new_record($wpdb, $plain_id, $now, $seq++);
    aa_c_new_record($wpdb, $plain_id, $now, $seq++);
    $plain_client = new AA_Test_Delete_Mandate_Client();
    $plain_client->seal_fn = static function (array $input) {
        ac_assert('records sin imágenes: seal 0', (int) $input['expected_batch_count'] === 0);

        return aa_c_seal_ok((string) $input['mandate_id'], 0);
    };
    $plain_res = aa_c_uc($wpdb, $lock, $plain_client)->execute(aa_c_cmd($plain_id));
    ac_assert('lista con registros vacíos: confirmed y contenedor ausente', $plain_res->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && !aa_c_container_exists($wpdb, $plain_id)
        && aa_c_record_count($wpdb, $plain_id) === 0);

    $mix_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $mix_rec = aa_c_new_record($wpdb, $mix_id, $now, $seq++);
    aa_c_insert_image($wpdb, $mix_rec, aa_c_uuid($seq), str_repeat('11', 32), 100, $now);
    $seq++;
    aa_c_insert_op($wpdb, $mix_rec, aa_c_uuid($seq), str_repeat('22', 32), 50, AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_ADMITTED, $now, true);
    $seq++;
    aa_c_insert_op($wpdb, $mix_rec, aa_c_uuid($seq), str_repeat('33', 32), 60, AA_Canonical_Schema::IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED, $now);
    $seq++;
    $mix_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($mix_client, $inventory, $mix_id);
    $mix_res = aa_c_uc($wpdb, $lock, $mix_client)->execute(aa_c_cmd($mix_id));
    $mix_run = aa_c_latest_run($wpdb, $mix_id);
    ac_assert('imágenes y ops no confirmadas se retiran', $mix_res->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && $images->count_for_container($mix_id) === 0
        && $ops->count_for_container($mix_id) === 0
        && !aa_c_container_exists($wpdb, $mix_id)
        && is_array($mix_run)
        && $inventory->count_for_run((int) $mix_run['id']) >= 2
        && ($mix_run['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_COMPLETED);

    $big_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $big_rec = aa_c_new_record($wpdb, $big_id, $now, $seq++);
    for ($i = 1; $i <= 51; $i++) {
        aa_c_insert_image($wpdb, $big_rec, aa_c_uuid(400 + $i), str_repeat('aa', 32), 10, $now);
    }
    $cap_done = $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        $big_id,
        $big_id,
        'finance',
        8
    ));
    ac_assert('51 ítems preparan tandas 0 y 1', $cap_done->is_capture_complete()
        && $cap_done->prepared_batch_count() === 2
        && count($inventory->list_prepared_batch((int) $cap_done->purge_run_id(), 0)) === 50
        && count($inventory->list_prepared_batch((int) $cap_done->purge_run_id(), 1)) === 1);
    ac_assert('escritores bloqueados durante captura completa', $runs->has_blocking_purge_for_container($big_id)
        && $runs->has_blocking_purge($big_rec, $big_id));
    $big_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($big_client, $inventory, $big_id);
    $big1 = aa_c_uc($wpdb, $lock, $big_client)->execute(aa_c_cmd($big_id));
    $seqs = [];
    foreach ($big_client->calls as $call) {
        if ($call[0] === 'accept') {
            $seqs[] = (int) $call[1]['batch_seq'];
        }
    }
    ac_assert('51: primer accept deja incomplete', $big1->state() === RetireCanonicalContainerResult::STATE_INCOMPLETE
        && $seqs === [0]
        && aa_c_container_exists($wpdb, $big_id));
    $big2 = aa_c_uc($wpdb, $lock, $big_client)->execute(aa_c_cmd($big_id));
    $big3 = $big2;
    if ($big2->state() === RetireCanonicalContainerResult::STATE_INCOMPLETE) {
        ac_assert('51: post-sello no confirmed con trabajo local', aa_c_container_exists($wpdb, $big_id));
        $big3 = aa_c_uc($wpdb, $lock, $big_client)->execute(aa_c_cmd($big_id));
    }
    $seqs = [];
    $seal_counts = [];
    foreach ($big_client->calls as $call) {
        if ($call[0] === 'accept') {
            $seqs[] = (int) $call[1]['batch_seq'];
        }
        if ($call[0] === 'seal') {
            $seal_counts[] = (int) $call[1]['expected_batch_count'];
        }
    }
    ac_assert('51: accept 0 y 1, seal 2 y confirmed', $big3->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && $seqs === [0, 1]
        && $seal_counts === [2]
        && !aa_c_container_exists($wpdb, $big_id));

    $lost_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $lost_rec = aa_c_new_record($wpdb, $lost_id, $now, $seq++);
    aa_c_insert_image($wpdb, $lost_rec, aa_c_uuid($seq++), str_repeat('44', 32), 40, $now);
    $lost_client = new AA_Test_Delete_Mandate_Client();
    $lost_accepts = 0;
    $lost_client->accept_fn = static function (array $input) use (&$lost_accepts, $inventory, $lost_id) {
        $lost_accepts++;
        if ($lost_accepts === 1) {
            return aa_c_hmac_fail('unreachable');
        }
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
            $lost_id
        );
        $rows = is_array($run) ? $inventory->list_prepared_batch((int) $run['id'], (int) $input['batch_seq']) : [];

        return aa_c_accept_ok((string) $input['mandate_id'], (int) $input['batch_seq'], $rows, 'already_accepted');
    };
    $lost_client->status_fn = static function () {
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
        return aa_c_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $lost1 = aa_c_uc($wpdb, $lock, $lost_client)->execute(aa_c_cmd($lost_id));
    $lost_run = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_CONTAINER, $lost_id);
    ac_assert('accept perdido deja intención', $lost1->state() === RetireCanonicalContainerResult::STATE_INCOMPLETE
        && CanonicalPurgeRunsRepository::nullable_int($lost_run['accept_intent_batch_seq'] ?? null) === 0
        && CanonicalPurgeRunsRepository::nullable_int($lost_run['last_accepted_batch_seq'] ?? null) === null);
    $cancel_lost = aa_c_uc($wpdb, $lock, $lost_client)->execute(aa_c_cmd($lost_id, RetireCanonicalContainerCommand::INTENT_CANCEL));
    ac_assert('no cancelar con resultado remoto desconocido', $cancel_lost->state() === RetireCanonicalContainerResult::STATE_CANCEL_REJECTED);
    $lost2 = aa_c_uc($wpdb, $lock, $lost_client)->execute(aa_c_cmd($lost_id));
    $replay = null;
    foreach ($lost_client->calls as $call) {
        if ($call[0] === 'accept') {
            $replay = $call[1];
        }
    }
    ac_assert('recuperación exacta already_accepted', $lost2->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && $lost_accepts === 2
        && is_array($replay)
        && (string) $lost_client->calls[0][1]['mandate_id'] === (string) $replay['mandate_id']
        && (int) $lost_client->calls[0][1]['batch_seq'] === (int) $replay['batch_seq']);

    $st_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $st_rec = aa_c_new_record($wpdb, $st_id, $now, $seq++);
    aa_c_insert_image($wpdb, $st_rec, aa_c_uuid($seq++), str_repeat('13', 32), 13, $now);
    $st_client = new AA_Test_Delete_Mandate_Client();
    $st_accepts = 0;
    $st_client->accept_fn = static function () use (&$st_accepts) {
        $st_accepts++;

        return aa_c_hmac_fail('unreachable');
    };
    $st_client->status_fn = static function (array $input) use ($inventory, $st_id) {
        global $wpdb;
        $run = (new CanonicalPurgeRunsRepository($wpdb))->find_open_by_scope_target(
            CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
            $st_id
        );
        $seqn = (int) ($input['batch_seq'] ?? -1);
        $rows = is_array($run) ? $inventory->list_prepared_batch((int) $run['id'], $seqn) : [];

        return aa_c_status_credit_ok((string) $input['mandate_id'], $seqn, $rows);
    };
    $st_client->seal_fn = static function (array $input) {
        return aa_c_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $st1 = aa_c_uc($wpdb, $lock, $st_client)->execute(aa_c_cmd($st_id));
    $st2 = aa_c_uc($wpdb, $lock, $st_client)->execute(aa_c_cmd($st_id));
    ac_assert('status acredita tanda sin segundo accept', $st1->state() === RetireCanonicalContainerResult::STATE_INCOMPLETE
        && $st2->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && $st_accepts === 1);

    $seal_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $seal_rec = aa_c_new_record($wpdb, $seal_id, $now, $seq++);
    aa_c_insert_image($wpdb, $seal_rec, aa_c_uuid($seq++), str_repeat('55', 32), 55, $now);
    $seal_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($seal_client, $inventory, $seal_id);
    $seal_tries = 0;
    $seal_client->seal_fn = static function (array $input) use (&$seal_tries) {
        $seal_tries++;
        if ($seal_tries === 1) {
            return aa_c_hmac_fail('unreachable');
        }

        return aa_c_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
    };
    $seal_client->status_fn = static function () {
        return [
            'ok' => true,
            'outcome' => 'found',
            'retire_authorized' => false,
            'result' => [
                'found' => true,
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
    $seal1 = aa_c_uc($wpdb, $lock, $seal_client)->execute(aa_c_cmd($seal_id));
    $seal_run = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_CONTAINER, $seal_id);
    ac_assert('seal perdido deja intención y no borra', $seal1->state() === RetireCanonicalContainerResult::STATE_INCOMPLETE
        && CanonicalPurgeRunsRepository::nullable_string($seal_run['seal_intent_at'] ?? null) !== null
        && CanonicalPurgeRunsRepository::nullable_string($seal_run['sealed_at'] ?? null) === null
        && aa_c_container_exists($wpdb, $seal_id));
    $seal2 = aa_c_uc($wpdb, $lock, $seal_client)->execute(aa_c_cmd($seal_id));
    ac_assert('seal recuperado y retiro local', $seal2->state() === RetireCanonicalContainerResult::STATE_CONFIRMED && $seal_tries === 2);

    $seal_st_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $seal_st_rec = aa_c_new_record($wpdb, $seal_st_id, $now, $seq++);
    aa_c_insert_image($wpdb, $seal_st_rec, aa_c_uuid($seq++), str_repeat('14', 32), 14, $now);
    $seal_st_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($seal_st_client, $inventory, $seal_st_id);
    $seal_st_tries = 0;
    $seal_st_client->seal_fn = static function () use (&$seal_st_tries) {
        $seal_st_tries++;

        return aa_c_hmac_fail('unreachable');
    };
    $seal_st_client->status_fn = static function (array $input) {
        return aa_c_status_sealed_ok((string) $input['mandate_id'], 1);
    };
    $seal_st1 = aa_c_uc($wpdb, $lock, $seal_st_client)->execute(aa_c_cmd($seal_st_id));
    $seal_st2 = aa_c_uc($wpdb, $lock, $seal_st_client)->execute(aa_c_cmd($seal_st_id));
    ac_assert('seal status acredita sin segundo POST seal', $seal_st1->state() === RetireCanonicalContainerResult::STATE_INCOMPLETE
        && $seal_st2->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && $seal_st_tries === 1);

    $conf_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $conf_rec = aa_c_new_record($wpdb, $conf_id, $now, $seq++);
    aa_c_insert_image($wpdb, $conf_rec, aa_c_uuid($seq++), str_repeat('77', 32), 70, $now);
    $cap_conf = $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        $conf_id,
        $conf_id,
        'finance',
        8
    ));
    $cancel_pre = aa_c_uc($wpdb, $lock, new AA_Test_Delete_Mandate_Client())->execute(
        aa_c_cmd($conf_id, RetireCanonicalContainerCommand::INTENT_CANCEL)
    );
    $conf_run = $runs->find_by_id((int) $cap_conf->purge_run_id());
    ac_assert('cancelación previa al envío', $cancel_pre->state() === RetireCanonicalContainerResult::STATE_CANCELLED
        && is_array($conf_run)
        && ($conf_run['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_CANCELLED
        && aa_c_container_exists($wpdb, $conf_id)
        && $images->count_for_record($conf_rec) === 1
        && !$runs->has_blocking_purge_for_container($conf_id));
    $new_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($new_client, $inventory, $conf_id);
    $new_res = aa_c_uc($wpdb, $lock, $new_client)->execute(aa_c_cmd($conf_id));
    ac_assert('nueva eliminación no reutiliza corrida cancelada', $new_res->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && (string) $new_client->calls[0][1]['mandate_id'] !== (string) $cap_conf->mandate_id());

    $ov_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $ov_rec = aa_c_new_record($wpdb, $ov_id, $now, $seq++);
    aa_c_insert_image($wpdb, $ov_rec, aa_c_uuid($seq++), str_repeat('88', 32), 8, $now);
    $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $ov_rec,
        $ov_id,
        'finance',
        8
    ));
    $ov_res = aa_c_uc($wpdb, $lock, new AA_Test_Delete_Mandate_Client())->execute(aa_c_cmd($ov_id));
    ac_assert('purga registro hijo luego lista → overlap', $ov_res->state() === RetireCanonicalContainerResult::STATE_SCOPE_OVERLAP);
    $ov_open = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_RECORD, $ov_rec);
    if (is_array($ov_open)) {
        $runs->mark_cancelled((int) $ov_open['id'], $now);
    }

    $ov2_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $ov2_rec = aa_c_new_record($wpdb, $ov2_id, $now, $seq++);
    aa_c_insert_image($wpdb, $ov2_rec, aa_c_uuid($seq++), str_repeat('89', 32), 9, $now);
    $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        $ov2_id,
        $ov2_id,
        'finance',
        8
    ));
    $ov2_rec_cap = $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_RECORD,
        $ov2_rec,
        $ov2_id,
        'finance',
        8
    ));
    ac_assert('purga lista luego registro hijo → overlap', $ov2_rec_cap->state() === CanonicalPurgeCaptureResult::STATE_SCOPE_OVERLAP);
    $ov2_open = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_CONTAINER, $ov2_id);
    if (is_array($ov2_open)) {
        $runs->mark_cancelled((int) $ov2_open['id'], $now);
    }

    $chunk_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $chunk_rec = aa_c_new_record($wpdb, $chunk_id, $now, $seq++);
    for ($i = 0; $i < 4; $i++) {
        aa_c_insert_image($wpdb, $chunk_rec, aa_c_uuid($seq++), str_repeat('10', 32), 10, $now);
    }
    $quota_before = $images->sum_byte_size_total();
    $chunk_store = new AA_Canonical_Purge_Local_Retire_Store($runs, $inventory, $images, $ops, $wpdb, 2);
    $chunk_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($chunk_client, $inventory, $chunk_id);
    $chunk_uc = aa_c_uc($wpdb, $lock, $chunk_client, $chunk_store);
    $ch1 = $chunk_uc->execute(aa_c_cmd($chunk_id));
    ac_assert('chunk 1: incomplete, no confirmed prematuro', $ch1->state() === RetireCanonicalContainerResult::STATE_INCOMPLETE
        && aa_c_container_exists($wpdb, $chunk_id)
        && $images->count_for_container($chunk_id) === 2
        && $images->sum_byte_size_total() === $quota_before - 20
        && $runs->has_blocking_purge_for_container($chunk_id));
    $ch_run = $runs->find_open_by_scope_target(CanonicalPurgeRunsRepository::SCOPE_CONTAINER, $chunk_id);
    ac_assert('checkpoint inventario avanzó sin OFFSET', is_array($ch_run)
        && (int) ($ch_run['local_retire_after_inventory_id'] ?? 0) > 0
        && ($ch_run['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_INCOMPLETE);
    $ch2 = $chunk_uc->execute(aa_c_cmd($chunk_id));
    ac_assert('chunk 2: identidades agotadas, aún incomplete', $ch2->state() === RetireCanonicalContainerResult::STATE_INCOMPLETE
        && aa_c_container_exists($wpdb, $chunk_id)
        && $images->count_for_container($chunk_id) === 0
        && $images->sum_byte_size_total() === $quota_before - 40);
    $ch3 = $chunk_uc->execute(aa_c_cmd($chunk_id));
    $ch_done = aa_c_latest_run($wpdb, $chunk_id);
    ac_assert('chunk 3: contenedor retirado y cuota por SUM', $ch3->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && !aa_c_container_exists($wpdb, $chunk_id)
        && is_array($ch_done)
        && ($ch_done['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_COMPLETED
        && $inventory->count_for_run((int) $ch_done['id']) === 4
        && $images->sum_byte_size_total() === $quota_before - 40);

    $move_a = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $move_b = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $moved = aa_c_new_record($wpdb, $move_a, $now, $seq++);
    $stay = aa_c_new_record($wpdb, $move_a, $now, $seq++);
    aa_c_insert_image($wpdb, $moved, aa_c_uuid($seq++), str_repeat('ab', 32), 15, $now);
    $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        $move_a,
        $move_a,
        'finance',
        8
    ));
    $wpdb->update(AA_Canonical_Schema::records_table_name(), ['container_id' => $move_b], ['id' => $moved], ['%d'], ['%d']);
    $move_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($move_client, $inventory, $move_a);
    $move_res = aa_c_uc($wpdb, $lock, $move_client)->execute(aa_c_cmd($move_a));
    ac_assert('pertenencia sintética: lista A se retira; registro movido sobrevive', $move_res->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && !aa_c_container_exists($wpdb, $move_a)
        && aa_c_container_exists($wpdb, $move_b)
        && (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d AND container_id = %d',
            $moved,
            $move_b
        )) === 1
        && (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM `' . str_replace('`', '``', AA_Canonical_Schema::records_table_name()) . '` WHERE id = %d',
            $stay
        )) === 0);

    $unc_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $unc_rec = aa_c_new_record($wpdb, $unc_id, $now, $seq++);
    for ($i = 0; $i < 3; $i++) {
        aa_c_insert_image($wpdb, $unc_rec, aa_c_uuid($seq++), str_repeat('cd', 32), 5, $now);
    }
    $unc_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($unc_client, $inventory, $unc_id);
    $unc_store = new AA_Test_Uncertain_Retire_Store($runs, $inventory, $images, $ops, $wpdb, 2);
    $unc_store->lie_after_commit = true;
    $unc1 = aa_c_uc($wpdb, $lock, $unc_client, $unc_store)->execute(aa_c_cmd($unc_id));
    ac_assert('commit ambiguo de chunk → uncertain', $unc1->state() === RetireCanonicalContainerResult::STATE_UNCERTAIN
        && aa_c_container_exists($wpdb, $unc_id));
    $unc2 = aa_c_uc($wpdb, $lock, $unc_client)->execute(aa_c_cmd($unc_id));
    $unc3 = $unc2;
    if ($unc2->state() === RetireCanonicalContainerResult::STATE_INCOMPLETE) {
        $unc3 = aa_c_uc($wpdb, $lock, $unc_client)->execute(aa_c_cmd($unc_id));
    }
    ac_assert('reintento tras uncertain completa', $unc3->state() === RetireCanonicalContainerResult::STATE_CONFIRMED
        && !aa_c_container_exists($wpdb, $unc_id));

    $gone_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $gone_rec = aa_c_new_record($wpdb, $gone_id, $now, $seq++);
    aa_c_insert_image($wpdb, $gone_rec, aa_c_uuid($seq++), str_repeat('ef', 32), 11, $now);
    $gone_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($gone_client, $inventory, $gone_id);
    $gone_sealed = 0;
    $gone_client->seal_fn = static function (array $input) use (&$gone_sealed, $gone_id, $gone_rec, $wpdb) {
        $gone_sealed++;
        $ok = aa_c_seal_ok((string) $input['mandate_id'], (int) $input['expected_batch_count']);
        if ($gone_sealed === 1) {
            $wpdb->query($wpdb->prepare(
                'DELETE FROM `' . str_replace('`', '``', AA_Canonical_Schema::record_images_table_name()) . '` WHERE record_id = %d',
                $gone_rec
            ));
            $wpdb->query($wpdb->prepare(
                'DELETE FROM `' . str_replace('`', '``', AA_Canonical_Schema::image_upload_operations_table_name()) . '` WHERE record_id = %d',
                $gone_rec
            ));
            $wpdb->delete(AA_Canonical_Schema::records_table_name(), ['id' => $gone_rec], ['%d']);
            $wpdb->delete(AA_Canonical_Schema::containers_table_name(), ['id' => $gone_id], ['%d']);
        }

        return $ok;
    };
    $gone1 = aa_c_uc($wpdb, $lock, $gone_client)->execute(aa_c_cmd($gone_id));
    ac_assert('retiro con contenedor ya ausente confirma cierre', $gone1->state() === RetireCanonicalContainerResult::STATE_CONFIRMED);
    $gone_run = aa_c_latest_run($wpdb, $gone_id);
    ac_assert('corrida e inventario conservados', is_array($gone_run)
        && ($gone_run['status'] ?? '') === CanonicalPurgeRunsRepository::STATUS_COMPLETED
        && $inventory->count_for_run((int) $gone_run['id']) >= 1
        && (int) ($gone_run['container_id'] ?? 0) === $gone_id
        && (string) ($gone_run['family_key'] ?? '') === 'finance');
    $gone2 = aa_c_uc($wpdb, $lock, $gone_client)->execute(aa_c_cmd($gone_id));
    ac_assert('repetición tras contenedor eliminado → container_not_found', $gone2->state() === RetireCanonicalContainerResult::STATE_CONTAINER_NOT_FOUND);

    $busy_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $lease_hold = $lock->acquire(AA_Expediente_Aggregate_Lock::SCOPE_CANONICAL_CONTAINER, $busy_id, 1);
    $wpdb2 = aa_c_second_wpdb($temp_prefix);
    $lock2 = aa_c_lock($wpdb2);
    $busy_client = new AA_Test_Delete_Mandate_Client();
    $busy_res = aa_c_uc($wpdb2, $lock2, $busy_client)->execute(aa_c_cmd($busy_id));
    $cancel_busy = aa_c_uc($wpdb2, $lock2, $busy_client)->execute(
        aa_c_cmd($busy_id, RetireCanonicalContainerCommand::INTENT_CANCEL)
    );
    ac_assert('continuar concurrente → resource_busy', !is_wp_error($lease_hold)
        && $busy_res->state() === RetireCanonicalContainerResult::STATE_RESOURCE_BUSY);
    ac_assert('cancelar frente a lock ajeno → resource_busy', $cancel_busy->state() === RetireCanonicalContainerResult::STATE_RESOURCE_BUSY);
    $lock->release($lease_hold);

    $foreign_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $foreign_open = $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        $foreign_id,
        $foreign_id,
        'finance',
        8
    ));
    $foreign = aa_c_uc($wpdb, $lock, new AA_Test_Delete_Mandate_Client())->execute(aa_c_cmd($foreign_id, RetireCanonicalContainerCommand::INTENT_RETIRE, 'archive'));
    ac_assert('acceso con familia distinta rechazado', $foreign->state() === RetireCanonicalContainerResult::STATE_FORBIDDEN
        || $foreign->state() === RetireCanonicalContainerResult::STATE_CONTAINER_NOT_FOUND);
    if ($foreign_open->purge_run_id() !== null) {
        $runs->mark_cancelled((int) $foreign_open->purge_run_id(), $now);
    }

    $extra_id = aa_c_new_container($wpdb, $family_id, $now, $seq++);
    $extra_rec = aa_c_new_record($wpdb, $extra_id, $now, $seq++);
    aa_c_insert_image($wpdb, $extra_rec, aa_c_uuid($seq++), str_repeat('99', 32), 90, $now);
    $cap->execute(new CaptureCanonicalPurgeInventoryCommand(
        CanonicalPurgeRunsRepository::SCOPE_CONTAINER,
        $extra_id,
        $extra_id,
        'finance',
        8
    ));
    aa_c_insert_image($wpdb, $extra_rec, aa_c_uuid($seq++), str_repeat('9a', 32), 91, $now);
    $extra_client = new AA_Test_Delete_Mandate_Client();
    aa_c_wire_ok($extra_client, $inventory, $extra_id);
    $extra_res = aa_c_uc($wpdb, $lock, $extra_client)->execute(aa_c_cmd($extra_id));
    ac_assert('fila viva no inventariada: fail-closed', $extra_res->state() === RetireCanonicalContainerResult::STATE_INTERVENTION_REQUIRED
        && aa_c_container_exists($wpdb, $extra_id));
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
