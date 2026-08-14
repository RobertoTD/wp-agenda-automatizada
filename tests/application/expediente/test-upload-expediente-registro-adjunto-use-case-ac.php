<?php
/**
 * AC — UploadExpedienteRegistroAdjuntoUseCase (MC4b / 5B2).
 *
 * Ejecutar: php tests/application/expediente/test-upload-expediente-registro-adjunto-use-case-ac.php
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
if (!defined('IMAGETYPE_JPEG')) {
    define('IMAGETYPE_JPEG', 2);
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
        return '2026-07-30 19:00:00';
    }
}

$src = file_get_contents($plugin_root . '/includes/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php');
ac_assert('use case file exists', is_string($src) && $src !== '');
ac_assert(
    'orden validate → used_bytes → transfer → finalize_matches → insert',
    strpos($src, 'validator->validate') !== false
    && strpos($src, 'sum_byte_size_total') !== false
    && strpos($src, 'transfer->transfer') !== false
    && strpos($src, 'finalize_matches_expectation') !== false
    && strpos($src, 'ExpedienteAdjuntosRepository::insert_finalized') !== false
    && strpos($src, 'validator->validate') < strpos($src, 'sum_byte_size_total')
    && strpos($src, 'sum_byte_size_total') < strpos($src, 'transfer->transfer')
    && strpos($src, 'transfer->transfer') < strpos($src, 'finalize_matches_expectation')
    && strpos($src, 'finalize_matches_expectation') < strpos($src, 'ExpedienteAdjuntosRepository::insert_finalized')
);
ac_assert('verifica installation_id', strpos($src, 'installation_id') !== false);
ac_assert('verifica client/record en path', strpos($src, 'storage_path_matches_context') !== false);
ac_assert('cleanup finally', strpos($src, 'cleanup_tmp') !== false && strpos($src, 'finally') !== false);
ac_assert('sin transacción SQL alrededor HTTP', !preg_match('/START TRANSACTION|BEGIN\b/', $src));
ac_assert('usa ExpedienteAdjuntoUploadTransfer', strpos($src, 'ExpedienteAdjuntoUploadTransfer') !== false);
ac_assert('sin authorize_upload', strpos($src, 'authorize_upload') === false);
ac_assert('sin put_jpeg', strpos($src, 'put_jpeg') === false);
ac_assert('sin file_get_contents', strpos($src, 'file_get_contents') === false);
ac_assert('sin signed_url', strpos($src, 'signed_url') === false);
ac_assert('sin pending_upload', strpos($src, 'pending_upload') === false);
ac_assert('sin FakeAttachmentsBackend', strpos($src, 'FakeAttachmentsBackend') === false);
ac_assert('sin propiedades backend/uploader', strpos($src, '$this->backend') === false && strpos($src, '$this->uploader') === false);

final class ClientsRepository {
    public static $client = ['id' => 7, 'nombre' => 'A', 'telefono' => '', 'correo' => ''];
    public static function find_by_id(int $client_id): ?array {
        return $client_id === 7 ? self::$client : null;
    }
}

final class ExpedienteRegistrosRepository {
    public static $record = [
        'id' => 11,
        'client_id' => 7,
        'title' => 'T',
        'body' => 'B',
        'recorded_at' => '2026-07-30 10:00:00',
        'created_at' => '2026-07-30 10:00:00',
        'updated_at' => null,
    ];
    public static function find_by_id_for_client(int $record_id, int $client_id): ?array {
        if ($record_id === 11 && $client_id === 7) {
            return self::$record;
        }
        return null;
    }
}

final class ExpedienteAdjuntosRepository {
    public static $inserts = [];
    public static $return_row = null;
    public static $error = null;
    /** @var int|null */
    public static $sum_bytes = 0;

    public static function sum_byte_size_total(): ?int {
        return self::$sum_bytes;
    }

    public static function insert_finalized(array $data) {
        self::$inserts[] = $data;
        if (self::$error !== null) {
            return self::$error;
        }
        return self::$return_row ?: [
            'id' => 99,
            'record_id' => (int) $data['record_id'],
            'client_id' => (int) $data['client_id'],
            'upload_operation_id' => (string) $data['upload_operation_id'],
            'storage_path' => (string) $data['storage_path'],
            'mime_type' => (string) $data['mime_type'],
            'byte_size' => (int) $data['byte_size'],
            'width' => (int) $data['width'],
            'height' => (int) $data['height'],
            'created_at' => '2026-07-30 19:00:00',
        ];
    }
}

final class FakeAdjuntoUploadTransfer {
    public $calls = [];
    public $inputs = [];
    public $fail = null;
    public $storage_path = '';
    public $finalize = [];
    public $installation_id = '11111111-1111-4111-8111-111111111111';
    public $op = '22222222-2222-4222-8222-222222222222';

    public function __construct() {
        $this->storage_path = 'installations/' . $this->installation_id . '/clients/7/records/11/' . $this->op . '.jpg';
    }

    public function transfer(array $input): array {
        $this->calls[] = 'transfer';
        $this->inputs[] = $input;
        if ($this->fail !== null) {
            return $this->fail;
        }
        return [
            'ok' => true,
            'storage_path' => $this->storage_path,
            'finalize' => $this->finalize,
        ];
    }
}

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoJpegValidator.php';
require_once $plugin_root . '/includes/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php';

function aa_uc_jpeg(int $w = 40, int $h = 30): string {
    $img = imagecreatetruecolor($w, $h);
    $c = imagecolorallocate($img, 10, 20, 30);
    imagefilledrectangle($img, 0, 0, $w, $h, $c);
    $path = tempnam(sys_get_temp_dir(), 'aa_uc_');
    imagejpeg($img, $path, 85);
    imagedestroy($img);
    return $path;
}

function aa_run_uc(FakeAdjuntoUploadTransfer $transfer, string $path, string $op): array {
    $size = (int) filesize($path);
    $info = getimagesize($path);
    $transfer->finalize = [
        'storage_path' => $transfer->storage_path,
        'upload_operation_id' => $op,
        'installation_id' => $transfer->installation_id,
        'mime_type' => 'image/jpeg',
        'byte_size' => $size,
        'width' => (int) $info[0],
        'height' => (int) $info[1],
    ];

    $validator = new ExpedienteAdjuntoJpegValidator(static function ($tmp) use ($path) {
        return $tmp === $path;
    });
    $uc = new UploadExpedienteRegistroAdjuntoUseCase($validator, $transfer);
    return $uc->execute([
        'client_id' => 7,
        'record_id' => 11,
        'upload_operation_id' => $op,
        'file' => [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $path,
            'name' => 'a.jpg',
            'type' => 'image/jpeg',
            'size' => $size,
        ],
    ]);
}

$op = '22222222-2222-4222-8222-222222222222';

ExpedienteAdjuntosRepository::$inserts = [];
ExpedienteAdjuntosRepository::$sum_bytes = 0;
ExpedienteAdjuntosRepository::$error = null;
$path = aa_uc_jpeg();
$size = (int) filesize($path);
$info = getimagesize($path);
$transfer = new FakeAdjuntoUploadTransfer();
$transfer->op = $op;
$transfer->storage_path = 'installations/' . $transfer->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($transfer, $path, $op);
ac_assert('happy path ok', !empty($res['ok']), json_encode($res));
ac_assert('llama transfer una vez', $transfer->calls === ['transfer']);
ac_assert('insert una vez', count(ExpedienteAdjuntosRepository::$inserts) === 1);
ac_assert(
    'input exacto al transfer',
    ($transfer->inputs[0] ?? null) === [
        'source_path' => $path,
        'mime_type' => 'image/jpeg',
        'byte_size' => $size,
        'width' => (int) $info[0],
        'height' => (int) $info[1],
        'upload_operation_id' => $op,
        'wp_client_id' => 7,
        'wp_record_id' => 11,
        'used_bytes' => 0,
    ]
);
ac_assert(
    'DTO público idéntico',
    ($res['attachment'] ?? null) === [
        'id' => 99,
        'record_id' => 11,
        'client_id' => 7,
        'upload_operation_id' => $op,
        'storage_path' => $transfer->storage_path,
        'mime_type' => 'image/jpeg',
        'byte_size' => $size,
        'width' => (int) $info[0],
        'height' => (int) $info[1],
        'created_at' => '2026-07-30 19:00:00',
    ]
);
ac_assert('tmp original limpiado', !is_file($path));

ExpedienteAdjuntosRepository::$inserts = [];
ExpedienteAdjuntosRepository::$sum_bytes = 0;
$path = aa_uc_jpeg();
$transfer = new FakeAdjuntoUploadTransfer();
$validator = new ExpedienteAdjuntoJpegValidator(static function () {
    return false;
});
$uc = new UploadExpedienteRegistroAdjuntoUseCase($validator, $transfer);
$res = $uc->execute([
    'client_id' => 7,
    'record_id' => 11,
    'upload_operation_id' => $op,
    'file' => [
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $path,
        'name' => 'a.jpg',
        'type' => 'image/jpeg',
        'size' => filesize($path),
    ],
]);
ac_assert('validación fallida cero transfer', $transfer->calls === [] && empty($res['ok']));
ac_assert('validación fallida cero insert', count(ExpedienteAdjuntosRepository::$inserts) === 0);
@unlink($path);

ExpedienteAdjuntosRepository::$inserts = [];
ExpedienteAdjuntosRepository::$sum_bytes = null;
$path = aa_uc_jpeg();
$transfer = new FakeAdjuntoUploadTransfer();
$transfer->op = $op;
$transfer->storage_path = 'installations/' . $transfer->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($transfer, $path, $op);
ac_assert('sum null → storage_usage_unavailable', empty($res['ok']) && ($res['code'] ?? '') === 'storage_usage_unavailable');
ac_assert('usage report no disponible cero transfer', $transfer->calls === []);
ac_assert('sum null no inserta', count(ExpedienteAdjuntosRepository::$inserts) === 0);
ExpedienteAdjuntosRepository::$sum_bytes = 0;

ExpedienteAdjuntosRepository::$inserts = [];
$path = aa_uc_jpeg();
$transfer = new FakeAdjuntoUploadTransfer();
$transfer->fail = [
    'ok' => false,
    'code' => 'storage_quota_exceeded',
    'message' => 'backend copy must not leak',
];
$transfer->op = $op;
$transfer->storage_path = 'installations/' . $transfer->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($transfer, $path, $op);
ac_assert(
    'storage_quota_exceeded comercial sin insert',
    empty($res['ok'])
    && ($res['code'] ?? '') === 'storage_quota_exceeded'
    && ($res['message'] ?? '') === 'No queda espacio de almacenamiento. Elimina alguna imagen para liberar espacio.'
    && count(ExpedienteAdjuntosRepository::$inserts) === 0
);
ac_assert('fallo transfer no inserta', $transfer->calls === ['transfer']);

ExpedienteAdjuntosRepository::$inserts = [];
$path = aa_uc_jpeg();
$transfer = new FakeAdjuntoUploadTransfer();
$transfer->fail = [
    'ok' => false,
    'code' => 'variant_generation_failed',
    'message' => 'No se pudieron generar las variantes.',
];
$transfer->op = $op;
$transfer->storage_path = 'installations/' . $transfer->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($transfer, $path, $op);
ac_assert(
    'mensaje seguro del transfer',
    empty($res['ok'])
    && ($res['code'] ?? '') === 'variant_generation_failed'
    && ($res['message'] ?? '') === 'No se pudieron generar las variantes.'
    && count(ExpedienteAdjuntosRepository::$inserts) === 0
);

ExpedienteAdjuntosRepository::$inserts = [];
$path = aa_uc_jpeg();
$size = (int) filesize($path);
$info = getimagesize($path);
$transfer = new FakeAdjuntoUploadTransfer();
$transfer->op = $op;
$transfer->storage_path = 'installations/' . $transfer->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$transfer->finalize = [
    'storage_path' => $transfer->storage_path,
    'upload_operation_id' => $op,
    'installation_id' => $transfer->installation_id,
    'mime_type' => 'image/jpeg',
    'byte_size' => $size + 1,
    'width' => (int) $info[0],
    'height' => (int) $info[1],
];
$validator = new ExpedienteAdjuntoJpegValidator(static function ($tmp) use ($path) {
    return $tmp === $path;
});
$uc = new UploadExpedienteRegistroAdjuntoUseCase($validator, $transfer);
$res = $uc->execute([
    'client_id' => 7,
    'record_id' => 11,
    'upload_operation_id' => $op,
    'file' => [
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $path,
        'name' => 'a.jpg',
        'type' => 'image/jpeg',
        'size' => $size,
    ],
]);
ac_assert(
    'finalize mismatch no inserta',
    empty($res['ok'])
    && ($res['code'] ?? '') === 'finalize_mismatch'
    && count(ExpedienteAdjuntosRepository::$inserts) === 0
    && $transfer->calls === ['transfer']
);

ExpedienteAdjuntosRepository::$inserts = [];
ExpedienteAdjuntosRepository::$error = new WP_Error('persist_failed', 'db');
$path = aa_uc_jpeg();
$transfer = new FakeAdjuntoUploadTransfer();
$transfer->op = $op;
$transfer->storage_path = 'installations/' . $transfer->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($transfer, $path, $op);
ac_assert(
    'fallo del repositorio',
    empty($res['ok']) && ($res['code'] ?? '') === 'persist_failed'
);
ExpedienteAdjuntosRepository::$error = null;

$path = aa_uc_jpeg();
$transfer = new FakeAdjuntoUploadTransfer();
$validator = new ExpedienteAdjuntoJpegValidator(static function () {
    return true;
});
$uc = new UploadExpedienteRegistroAdjuntoUseCase($validator, $transfer);
$res = $uc->execute([
    'client_id' => 7,
    'record_id' => 999,
    'upload_operation_id' => $op,
    'file' => [
        'error' => UPLOAD_ERR_OK,
        'tmp_name' => $path,
        'name' => 'a.jpg',
        'type' => 'image/jpeg',
        'size' => filesize($path),
    ],
]);
ac_assert(
    'record_id ajeno falla antes de transfer',
    empty($res['ok']) && ($res['code'] ?? '') === 'record_not_found' && $transfer->calls === []
);
@unlink($path);

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
