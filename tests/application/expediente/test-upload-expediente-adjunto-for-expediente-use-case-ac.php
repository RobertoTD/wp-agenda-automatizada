<?php
/**
 * AC — UploadExpedienteAdjuntoForExpedienteUseCase (P3 permanente v2).
 *
 * Sin enablement: general y relacionado siempre expediente_v2.
 * Ejecutar: php tests/application/expediente/test-upload-expediente-adjunto-for-expediente-use-case-ac.php
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
    public static $calls = 0;

    public static function find_by_id_for_expediente(int $record_id, int $expediente_id) {
        self::$calls++;
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

$src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/UploadExpedienteAdjuntoForExpedienteUseCase.php'
);
ac_assert('sin AA_EXPEDIENTE_ATTACHMENTS_V2_ENABLED', strpos($src, 'AA_EXPEDIENTE_ATTACHMENTS_V2_ENABLED') === false);
ac_assert('sin clase Enablement', strpos($src, 'Attachments_V2_Enablement') === false);
ac_assert('sin attachments_unavailable', strpos($src, 'attachments_unavailable') === false);
ac_assert('v2 permanente CONTRACT_EXPEDIENTE_V2', strpos($src, 'CONTRACT_EXPEDIENTE_V2') !== false);
ac_assert('enablement file ausente', !is_file(
    $plugin_root . '/includes/domain/expediente/class-aa-expediente-attachments-v2-enablement.php'
));

$op = '550e8400-e29b-41d4-a716-446655440000';
$inst = '11111111-1111-4111-8111-111111111111';
$path_v2 = "installations/{$inst}/expedientes/7/records/10/{$op}.jpg";
$tmp = tempnam(sys_get_temp_dir(), 'aa_perm_v2_');
file_put_contents($tmp, 'jpeg');

$transfer = new FakeV2Transfer();
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

// General sin constante/config → v2
ExpedientesRepository::$exists = true;
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
ExpedienteRegistrosRepository::$record['client_id'] = null;
ExpedienteAdjuntosRepository::$inserts = [];
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $lock);
$out = $uc->execute($input);
ac_assert('general sin config → ok v2', ($out['success'] ?? false) === true);
ac_assert('general identity v2', ($transfer->calls[0]['identity']['contract'] ?? '') === 'expediente_v2');
ac_assert('general metadata NULL', array_key_exists('client_id', ExpedienteAdjuntosRepository::$inserts[0] ?? [])
    && ExpedienteAdjuntosRepository::$inserts[0]['client_id'] === null);

// Relacionado canónico sin config → v2
$transfer->calls = [];
ExpedienteAdjuntosRepository::$inserts = [];
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => 55];
ExpedienteRegistrosRepository::$record['client_id'] = 55;
$lock->acquire_calls = [];
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $lock);
$out = $uc->execute($input);
ac_assert('relacionado sin config → ok v2', ($out['success'] ?? false) === true);
ac_assert('relacionado identity v2', ($transfer->calls[0]['identity']['contract'] ?? '') === 'expediente_v2');
ac_assert('relacionado snapshot cliente', (ExpedienteAdjuntosRepository::$inserts[0]['client_id'] ?? 0) === 55);
ac_assert('aggregate client scope', ($lock->acquire_calls[0]['scope_kind'] ?? '') === 'client');

// Errores de pertenencia
$transfer->calls = [];
ExpedientesRepository::$exists = false;
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $lock);
$res = $uc->execute($input);
ac_assert('inexistente → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin transfer', $transfer->calls === []);

ExpedientesRepository::$exists = true;
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => 55];
ExpedienteRegistrosRepository::$record = false;
$res = $uc->execute($input);
ac_assert('registro ajeno → not_found', ($res['error']['code'] ?? '') === 'not_found');

@unlink($tmp);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
