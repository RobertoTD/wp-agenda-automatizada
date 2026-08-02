<?php
/**
 * AC — UploadExpedienteRegistroAdjuntoUseCase (MC4b).
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
ac_assert('orden validate antes de authorize', strpos($src, 'validator->validate') !== false
    && strpos($src, 'validator->validate') < strpos($src, 'authorize_upload'));
ac_assert('PUT solo pending_upload', strpos($src, "pending_upload") !== false);
ac_assert('finalize antes de insert', preg_match('/\$finalize\s*=\s*\$this->backend->finalize\(/', $src)
    && strpos($src, "\$finalize = \$this->backend->finalize(") < strpos($src, 'ExpedienteAdjuntosRepository::insert_finalized'));
ac_assert('finalize_matches_expectation', strpos($src, 'finalize_matches_expectation') !== false);
ac_assert('verifica installation_id', strpos($src, 'installation_id') !== false);
ac_assert('verifica client/record en path', strpos($src, 'storage_path_matches_context') !== false);
ac_assert('cleanup finally', strpos($src, 'cleanup_tmp') !== false && strpos($src, 'finally') !== false);
ac_assert('sin transacción SQL alrededor HTTP', !preg_match('/START TRANSACTION|BEGIN\b/', $src));
ac_assert('no loguea signed_url', !preg_match('/error_log\([^)]*signed_url/', $src));

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

final class FakeAttachmentsBackend {
    public $calls = [];
    public $authorize_inputs = [];
    public $authorize_status = 'pending_upload';
    public $authorize_fail = null;
    public $finalize_fail = null;
    public $finalize_override = null;
    public $installation_id = '11111111-1111-4111-8111-111111111111';
    public $op = '22222222-2222-4222-8222-222222222222';
    public $path = '';

    public function __construct() {
        $this->path = 'installations/' . $this->installation_id . '/clients/7/records/11/' . $this->op . '.jpg';
    }

    public function authorize_upload(array $input): array {
        $this->calls[] = 'authorize';
        $this->authorize_inputs[] = $input;
        if ($this->authorize_fail !== null) {
            return $this->authorize_fail;
        }
        $result = [
            'status' => $this->authorize_status,
            'upload_operation_id' => $this->op,
            'storage_path' => $this->path,
            'upload_intent' => 'intent.jwt.fake',
        ];
        if ($this->authorize_status === 'pending_upload') {
            $result['signed_url'] = 'https://example.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/' . $this->path . '?token=secret';
        }
        return ['ok' => true, 'result' => $result];
    }

    public function finalize(string $upload_intent): array {
        $this->calls[] = 'finalize';
        if ($this->finalize_fail !== null) {
            return $this->finalize_fail;
        }
        if ($this->finalize_override !== null) {
            return ['ok' => true, 'result' => $this->finalize_override];
        }
        return [
            'ok' => true,
            'result' => [
                'storage_path' => $this->path,
                'upload_operation_id' => $this->op,
                'installation_id' => $this->installation_id,
                'mime_type' => 'image/jpeg',
                'byte_size' => 0, // filled by test runner
                'width' => 0,
                'height' => 0,
            ],
        ];
    }
}

final class FakeSignedUploader {
    public $calls = [];
    public $fail = null;

    public function put_jpeg(string $signed_url, string $binary, string $expected_storage_path): array {
        $this->calls[] = 'put';
        if ($this->fail !== null) {
            return $this->fail;
        }
        return ['ok' => true];
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

function aa_run_uc(FakeAttachmentsBackend $backend, FakeSignedUploader $uploader, string $path, string $op): array {
    $size = (int) filesize($path);
    $info = getimagesize($path);
    $backend->finalize_override = [
        'storage_path' => $backend->path,
        'upload_operation_id' => $op,
        'installation_id' => $backend->installation_id,
        'mime_type' => 'image/jpeg',
        'byte_size' => $size,
        'width' => (int) $info[0],
        'height' => (int) $info[1],
    ];

    $validator = new ExpedienteAdjuntoJpegValidator(static function ($tmp) use ($path) {
        return $tmp === $path;
    });
    $uc = new UploadExpedienteRegistroAdjuntoUseCase($validator, $backend, $uploader);
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

// Happy path pending_upload
ExpedienteAdjuntosRepository::$inserts = [];
ExpedienteAdjuntosRepository::$sum_bytes = 0;
$path = aa_uc_jpeg();
$backend = new FakeAttachmentsBackend();
$uploader = new FakeSignedUploader();
$backend->op = $op;
$backend->path = 'installations/' . $backend->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($backend, $uploader, $path, $op);
ac_assert('happy path ok', !empty($res['ok']), json_encode($res));
ac_assert('orden authorize→put→finalize', $backend->calls === ['authorize', 'finalize'] && $uploader->calls === ['put']);
ac_assert('insert una vez', count(ExpedienteAdjuntosRepository::$inserts) === 1);
ac_assert('envía used_bytes en authorize', ($backend->authorize_inputs[0]['used_bytes'] ?? null) === 0);
ac_assert('tmp limpiado', !is_file($path));

// sum null → fallo cerrado local sin authorize
ExpedienteAdjuntosRepository::$inserts = [];
ExpedienteAdjuntosRepository::$sum_bytes = null;
$path = aa_uc_jpeg();
$backend = new FakeAttachmentsBackend();
$uploader = new FakeSignedUploader();
$backend->op = $op;
$backend->path = 'installations/' . $backend->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($backend, $uploader, $path, $op);
ac_assert('sum null → storage_usage_unavailable', empty($res['ok']) && ($res['code'] ?? '') === 'storage_usage_unavailable');
ac_assert('sum null no llama authorize', $backend->calls === []);
ac_assert('sum null no inserta', count(ExpedienteAdjuntosRepository::$inserts) === 0);
ExpedienteAdjuntosRepository::$sum_bytes = 0;

// storage_quota_exceeded se propaga sin insert
ExpedienteAdjuntosRepository::$inserts = [];
$path = aa_uc_jpeg();
$backend = new FakeAttachmentsBackend();
$uploader = new FakeSignedUploader();
$backend->authorize_fail = ['ok' => false, 'code' => 'storage_quota_exceeded'];
$backend->op = $op;
$backend->path = 'installations/' . $backend->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($backend, $uploader, $path, $op);
ac_assert(
    'storage_quota_exceeded se propaga',
    empty($res['ok'])
    && ($res['code'] ?? '') === 'storage_quota_exceeded'
    && count(ExpedienteAdjuntosRepository::$inserts) === 0
);
ac_assert('quota no hace PUT', $uploader->calls === []);

// already_uploaded: sin PUT
ExpedienteAdjuntosRepository::$inserts = [];
$path = aa_uc_jpeg();
$backend = new FakeAttachmentsBackend();
$uploader = new FakeSignedUploader();
$backend->op = $op;
$backend->authorize_status = 'already_uploaded';
$backend->path = 'installations/' . $backend->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($backend, $uploader, $path, $op);
ac_assert('already_uploaded ok', !empty($res['ok']));
ac_assert('sin PUT en retry', $uploader->calls === [] && $backend->calls === ['authorize', 'finalize']);
@unlink($path);

// PUT fail → sin insert
ExpedienteAdjuntosRepository::$inserts = [];
$path = aa_uc_jpeg();
$backend = new FakeAttachmentsBackend();
$uploader = new FakeSignedUploader();
$uploader->fail = ['ok' => false, 'code' => 'upload_failed'];
$backend->op = $op;
$backend->path = 'installations/' . $backend->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$res = aa_run_uc($backend, $uploader, $path, $op);
ac_assert('PUT fail no inserta', empty($res['ok']) && count(ExpedienteAdjuntosRepository::$inserts) === 0);
ac_assert('no finalize tras PUT fail', $backend->calls === ['authorize']);

// finalize mismatch → sin insert
ExpedienteAdjuntosRepository::$inserts = [];
$path = aa_uc_jpeg();
$size = (int) filesize($path);
$info = getimagesize($path);
$backend = new FakeAttachmentsBackend();
$uploader = new FakeSignedUploader();
$backend->op = $op;
$backend->path = 'installations/' . $backend->installation_id . '/clients/7/records/11/' . $op . '.jpg';
$backend->finalize_override = [
    'storage_path' => $backend->path,
    'upload_operation_id' => $op,
    'installation_id' => $backend->installation_id,
    'mime_type' => 'image/jpeg',
    'byte_size' => $size + 1,
    'width' => (int) $info[0],
    'height' => (int) $info[1],
];
$validator = new ExpedienteAdjuntoJpegValidator(static function ($tmp) use ($path) {
    return $tmp === $path;
});
$uc = new UploadExpedienteRegistroAdjuntoUseCase($validator, $backend, $uploader);
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
ac_assert('finalize mismatch no inserta', empty($res['ok']) && ($res['code'] ?? '') === 'finalize_mismatch'
    && count(ExpedienteAdjuntosRepository::$inserts) === 0);

// ownership fail
$path = aa_uc_jpeg();
$backend = new FakeAttachmentsBackend();
$uploader = new FakeSignedUploader();
$validator = new ExpedienteAdjuntoJpegValidator(static function () {
    return true;
});
$uc = new UploadExpedienteRegistroAdjuntoUseCase($validator, $backend, $uploader);
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
ac_assert('record_id ajeno falla antes de authorize', empty($res['ok']) && ($res['code'] ?? '') === 'record_not_found'
    && $backend->calls === []);
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
