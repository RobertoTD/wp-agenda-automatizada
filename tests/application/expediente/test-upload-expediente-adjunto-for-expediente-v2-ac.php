<?php
/**
 * AC — Upload canónico expediente_v2 permanente (P3).
 *
 * Ejecutar: php tests/application/expediente/test-upload-expediente-adjunto-for-expediente-v2-ac.php
 */

$plugin_root = dirname(__DIR__, 3);

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

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
if (!defined('UPLOAD_ERR_OK')) {
    define('UPLOAD_ERR_OK', 0);
}
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        public function __construct($code = '', $message = '') {
            $this->code = $code;
            $this->message = $message;
        }
        public function get_error_code() {
            return $this->code;
        }
        public function get_error_message() {
            return $this->message;
        }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}
if (!function_exists('current_time')) {
    function current_time($type) {
        return '2026-08-26 12:00:00';
    }
}

final class ExpedientesRepository {
    /** @var bool|null */
    public static $exists = true;
    /** @var array{id:int,client_id:?int}|null */
    public static $owner = ['id' => 7, 'client_id' => null];

    public static function exists_by_id(int $id) {
        return self::$exists;
    }

    public static function find_owner_context_by_id(int $id): ?array {
        return self::$owner;
    }
}

final class ExpedienteRegistrosRepository {
    /** @var array|false|null */
    public static $record = [
        'id' => 10,
        'expediente_id' => 7,
        'client_id' => null,
        'title' => 'G',
        'body' => 'B',
        'recorded_at' => '2026-08-20 12:00:00',
        'created_at' => '2026-08-20 12:00:00',
        'updated_at' => null,
    ];

    public static function find_by_id_for_expediente(int $record_id, int $expediente_id) {
        return self::$record;
    }
}

final class ExpedienteAdjuntosRepository {
    public static $inserts = [];
    public static $sum_bytes = 0;
    public static $by_op = null;
    public static $error = null;

    public static function sum_byte_size_total(): ?int {
        return self::$sum_bytes;
    }

    public static function find_by_upload_operation_id(string $op): ?array {
        return self::$by_op;
    }

    public static function insert_finalized(array $data) {
        self::$inserts[] = $data;
        if (self::$error !== null) {
            return self::$error;
        }
        return [
            'id' => 501,
            'record_id' => (int) $data['record_id'],
            'client_id' => $data['client_id'],
            'upload_operation_id' => (string) $data['upload_operation_id'],
            'storage_path' => (string) $data['storage_path'],
            'mime_type' => (string) $data['mime_type'],
            'byte_size' => (int) $data['byte_size'],
            'width' => (int) $data['width'],
            'height' => (int) $data['height'],
            'created_at' => '2026-08-26 12:00:00',
        ];
    }
}

require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage_Failed.php';
require_once $plugin_root . '/includes/application/storage/AA_Installation_Storage_Usage.php';
AA_Installation_Storage_Usage::set_default_for_tests(new AA_Installation_Storage_Usage(
    static function (): ?int {
        return ExpedienteAdjuntosRepository::$sum_bytes;
    },
    new class {
        public function sum_byte_size_total(): int {
            return 0;
        }
    },
    new class {
        public function sum_reserved_byte_size(int $now_ms, string $now_utc, ?string $exclude_operation_id = null): int {
            return 0;
        }
    }
));

final class FakeV2Transfer {
    public $calls = [];
    public $fail = null;
    public $path = '';
    public $finalize = [];

    public function transfer(array $input): array {
        $this->calls[] = $input;
        if ($this->fail !== null) {
            return $this->fail;
        }
        return [
            'ok' => true,
            'storage_path' => $this->path,
            'finalize' => $this->finalize,
        ];
    }
}

final class FakeCleanup {
    public $calls = [];
    public $response = ['ok' => true, 'result' => ['status' => 'deleted']];

    public function delete_object(string $path): array {
        $this->calls[] = $path;
        return $this->response;
    }
}

final class ExpedienteAdjuntoJpegValidator {
    public function validate(array $file): array {
        return [
            'ok' => true,
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'mime_type' => 'image/jpeg',
            'byte_size' => 713,
            'width' => 40,
            'height' => 30,
        ];
    }
}

require_once $plugin_root . '/tests/support/aa-test-expediente-aggregate-lock-passthrough.php';
$lock = aa_test_install_passthrough_expediente_lock();
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoPublicDto.php';
require_once $plugin_root . '/includes/application/expediente/UploadExpedienteAdjuntoForExpedienteUseCase.php';

ac_assert(
    'sin archivo enablement',
    !is_file($plugin_root . '/includes/domain/expediente/class-aa-expediente-attachments-v2-enablement.php')
);
ac_assert(
    'sin constante en UC',
    strpos(
        (string) file_get_contents($plugin_root . '/includes/application/expediente/UploadExpedienteAdjuntoForExpedienteUseCase.php'),
        'AA_EXPEDIENTE_ATTACHMENTS_V2_ENABLED'
    ) === false
);

$op = '550e8400-e29b-41d4-a716-446655440000';
$inst = '11111111-1111-4111-8111-111111111111';
$path_v2 = "installations/{$inst}/expedientes/7/records/10/{$op}.jpg";
$tmp = tempnam(sys_get_temp_dir(), 'aa_v2_');
file_put_contents($tmp, 'jpeg');

function aa_v2_reset(FakeV2Transfer $transfer, string $path_v2, string $op, string $inst): void {
    global $lock;
    ExpedientesRepository::$exists = true;
    ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
    ExpedienteRegistrosRepository::$record = [
        'id' => 10,
        'expediente_id' => 7,
        'client_id' => null,
        'title' => 'G',
        'body' => 'B',
        'recorded_at' => '2026-08-20 12:00:00',
        'created_at' => '2026-08-20 12:00:00',
        'updated_at' => null,
    ];
    ExpedienteAdjuntosRepository::$inserts = [];
    ExpedienteAdjuntosRepository::$sum_bytes = 100;
    ExpedienteAdjuntosRepository::$by_op = null;
    ExpedienteAdjuntosRepository::$error = null;
    $lock->acquire_calls = [];
    $lock->release_calls = 0;
    $lock->release_order = [];
    $lock->acquire_sequence = null;
    $lock->acquire_sequence_index = 0;
    $lock->next_acquire = null;
    $transfer->calls = [];
    $transfer->fail = null;
    $transfer->path = $path_v2;
    $transfer->finalize = [
        'installation_id' => $inst,
        'upload_operation_id' => $op,
        'storage_path' => $path_v2,
        'mime_type' => 'image/jpeg',
        'byte_size' => 713,
        'width' => 40,
        'height' => 30,
    ];
}

$transfer = new FakeV2Transfer();
$input = [
    'expediente_id' => 7,
    'record_id' => 10,
    'upload_operation_id' => $op,
    'file' => [
        'tmp_name' => $tmp,
        'name' => 'a.jpg',
        'type' => 'image/jpeg',
        'size' => 713,
        'error' => UPLOAD_ERR_OK,
    ],
    'client_id' => 999,
];

aa_v2_reset($transfer, $path_v2, $op, $inst);
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $lock);
$out = $uc->execute($input);
ac_assert('general v2 ok', ($out['success'] ?? false) === true);
ac_assert('DTO público', array_keys($out['data']['adjunto'] ?? []) === ['id', 'width', 'height', 'byte_size', 'created_at']);
ac_assert('metadata client NULL', array_key_exists('client_id', ExpedienteAdjuntosRepository::$inserts[0] ?? [])
    && ExpedienteAdjuntosRepository::$inserts[0]['client_id'] === null);
ac_assert('identity expediente_v2', ($transfer->calls[0]['identity']['contract'] ?? '') === 'expediente_v2');
ac_assert('identity sin client path', array_key_exists('client_id', $transfer->calls[0]['identity'] ?? [])
    && $transfer->calls[0]['identity']['client_id'] === null);
ac_assert('used_bytes bajo lock', ($transfer->calls[0]['used_bytes'] ?? -1) === 100);
ac_assert('aggregate expediente primero', ($lock->acquire_calls[0]['scope_kind'] ?? '') === 'expediente'
    && (int) ($lock->acquire_calls[0]['scope_id'] ?? 0) === 7);
ac_assert('quota segundo', ($lock->acquire_calls[1]['scope_kind'] ?? '') === 'storage_quota');
ac_assert('release inverso', count($lock->release_order) === 2
    && $lock->release_order[0]->scope_kind() === 'storage_quota'
    && $lock->release_order[1]->scope_kind() === 'expediente');

aa_v2_reset($transfer, $path_v2, $op, $inst);
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => 55];
ExpedienteRegistrosRepository::$record['client_id'] = 55;
$path_rel = "installations/{$inst}/expedientes/7/records/10/{$op}.jpg";
$transfer->path = $path_rel;
$transfer->finalize['storage_path'] = $path_rel;
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $lock);
$out = $uc->execute($input);
ac_assert('relacionado v2 ok', ($out['success'] ?? false) === true);
ac_assert('snapshot client derivado', (ExpedienteAdjuntosRepository::$inserts[0]['client_id'] ?? 0) === 55);
ac_assert('aggregate client scope', ($lock->acquire_calls[0]['scope_kind'] ?? '') === 'client'
    && (int) ($lock->acquire_calls[0]['scope_id'] ?? 0) === 55);
ac_assert('path sigue v2', ($transfer->calls[0]['identity']['contract'] ?? '') === 'expediente_v2');

aa_v2_reset($transfer, $path_v2, $op, $inst);
$lock->acquire_sequence = [null, new WP_Error('resource_busy', 'busy')];
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $lock);
$out = $uc->execute($input);
ac_assert('quota busy → resource_busy', ($out['error']['code'] ?? '') === 'resource_busy');
ac_assert('quota busy sin transfer', $transfer->calls === []);
ac_assert('quota busy release aggregate', $lock->release_calls === 1);

aa_v2_reset($transfer, $path_v2, $op, $inst);
$transfer->fail = ['ok' => false, 'code' => 'storage_quota_exceeded', 'message' => 'No queda espacio.'];
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $lock);
$out = $uc->execute($input);
ac_assert('cuota excedida propaga', ($out['error']['code'] ?? '') === 'storage_quota_exceeded');
ac_assert('mensaje comercial', strpos((string) ($out['error']['message'] ?? ''), 'espacio') !== false);

aa_v2_reset($transfer, $path_v2, $op, $inst);
$flip = new class extends AA_Test_Passthrough_Expediente_Aggregate_Lock {
    public $n = 0;
    public function assert_held($lease) {
        $this->n++;
        if ($this->n >= 3) {
            return new WP_Error('coordination_lost', 'lost');
        }
        return true;
    }
};
AA_Expediente_Aggregate_Lock::set_default_for_tests($flip);
$cleanup = new FakeCleanup();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $flip, $cleanup);
$out = $uc->execute($input);
ac_assert('lost → coordination_lost', ($out['error']['code'] ?? '') === 'coordination_lost');
ac_assert('compensa', count($cleanup->calls) === 1);
ac_assert('cero insert tras lost', ExpedienteAdjuntosRepository::$inserts === []);

aa_v2_reset($transfer, $path_v2, $op, $inst);
ExpedienteAdjuntosRepository::$by_op = [
    'id' => 501,
    'record_id' => 10,
    'client_id' => null,
    'upload_operation_id' => $op,
    'storage_path' => $path_v2,
    'mime_type' => 'image/jpeg',
    'byte_size' => 713,
    'width' => 40,
    'height' => 30,
    'created_at' => '2026-08-26 12:00:00',
];
$flip2 = new class extends AA_Test_Passthrough_Expediente_Aggregate_Lock {
    public $n = 0;
    public function assert_held($lease) {
        $this->n++;
        if ($this->n >= 3) {
            return new WP_Error('coordination_lost', 'lost');
        }
        return true;
    }
};
$cleanup2 = new FakeCleanup();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $flip2, $cleanup2);
$out = $uc->execute($input);
ac_assert('same-op → éxito idempotente', ($out['success'] ?? false) === true);
ac_assert('same-op sin compensate', $cleanup2->calls === []);

AA_Expediente_Aggregate_Lock::set_default_for_tests($lock);
AA_Installation_Storage_Usage::set_default_for_tests(null);
@unlink($tmp);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
