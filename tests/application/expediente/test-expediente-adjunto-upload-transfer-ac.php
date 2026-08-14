<?php
/**
 * AC — ExpedienteAdjuntoUploadTransfer (5B1).
 *
 * Ejecutar: php tests/application/expediente/test-expediente-adjunto-upload-transfer-ac.php
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

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/application/expediente/ExpedienteAdjuntoUploadTransfer.php';

const SIGNED_URL_SECRET_SENTINEL = 'SIGNED_URL_SECRET_SENTINEL';
const UPLOAD_INTENT_SECRET_SENTINEL = 'UPLOAD_INTENT_SECRET_SENTINEL';
const TOKEN_SECRET_SENTINEL = 'TOKEN_SECRET_SENTINEL';

$log_file = tempnam(sys_get_temp_dir(), 'aa_xfer_log_');
ini_set('error_log', $log_file);

$src = file_get_contents($plugin_root . '/includes/application/expediente/ExpedienteAdjuntoUploadTransfer.php');
$bootstrap = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$use_case_src = file_get_contents($plugin_root . '/includes/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php');
$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteAdjuntosAjax.php');

ac_assert('clase existe', class_exists('ExpedienteAdjuntoUploadTransfer'));
ac_assert('OBJECT_KEYS', ExpedienteAdjuntoUploadTransfer::OBJECT_KEYS === ['original', 'summary', 'gallery', 'display']);
ac_assert(
    'UPLOAD_ORDER',
    ExpedienteAdjuntoUploadTransfer::UPLOAD_ORDER === ['summary', 'gallery', 'display', 'original']
);
ac_assert('sin error_log', is_string($src) && strpos($src, 'error_log') === false);
ac_assert('sin wpdb', is_string($src) && strpos($src, '$wpdb') === false);
ac_assert('sin repositorio', is_string($src) && strpos($src, 'ExpedienteAdjuntosRepository') === false);
ac_assert('sin delete_object', is_string($src) && strpos($src, 'delete_object') === false);
ac_assert('sin new generador real', is_string($src) && strpos($src, 'new AA_Expediente_Adjunto_Variant_Generator') === false);
ac_assert('sin new client real', is_string($src) && strpos($src, 'new AA_Expediente_Attachments_Backend_Client') === false);
ac_assert('sin new uploader real', is_string($src) && strpos($src, 'new AA_Expediente_Attachment_Signed_Uploader') === false);
ac_assert(
    'sin llamada estática delete_generated',
    is_string($src) && strpos($src, 'AA_Expediente_Adjunto_Variant_Generator::delete_generated') === false
);
ac_assert('usa puerto delete_generated', is_string($src) && strpos($src, 'generator->delete_generated') !== false);
ac_assert('bootstrap no menciona Transfer', is_string($bootstrap) && strpos($bootstrap, 'ExpedienteAdjuntoUploadTransfer') === false);
ac_assert('use case no menciona Transfer', is_string($use_case_src) && strpos($use_case_src, 'ExpedienteAdjuntoUploadTransfer') === false);
ac_assert('AJAX no menciona Transfer', is_string($ajax_src) && strpos($ajax_src, 'ExpedienteAdjuntoUploadTransfer') === false);

final class FakeTransferGenerator {
    /** @var array<string,mixed>|null */
    public $result = null;
    /** @var \Throwable|null */
    public $throw = null;
    /** @var list<array<string,mixed>> */
    public $deleted = [];

    public function generate(string $source_path): array {
        if ($this->throw !== null) {
            throw $this->throw;
        }

        return is_array($this->result) ? $this->result : ['ok' => false, 'code' => 'variant_failed', 'message' => 'x'];
    }

    public function delete_generated(array $variants): void {
        $this->deleted[] = $variants;
    }
}

final class FakeTransferBackend {
    /** @var array<string,mixed>|null */
    public $authorize_result = null;
    /** @var array<string,mixed>|null */
    public $finalize_result = null;
    /** @var list<string> */
    public $calls = [];
    /** @var list<array<string,mixed>> */
    public $authorize_inputs = [];
    /** @var \Throwable|null */
    public $throw_on_authorize = null;
    /** @var \Throwable|null */
    public $throw_on_finalize = null;

    public function authorize_upload(array $input): array {
        $this->calls[] = 'authorize';
        $this->authorize_inputs[] = $input;
        if ($this->throw_on_authorize !== null) {
            throw $this->throw_on_authorize;
        }

        return is_array($this->authorize_result) ? $this->authorize_result : ['ok' => false, 'code' => 'missing'];
    }

    public function finalize(string $upload_intent): array {
        $this->calls[] = 'finalize';
        if ($this->throw_on_finalize !== null) {
            throw $this->throw_on_finalize;
        }

        return is_array($this->finalize_result) ? $this->finalize_result : ['ok' => false, 'code' => 'missing'];
    }
}

final class FakeTransferUploader {
    /** @var list<array{signed_url:string,binary:string,storage_path:string}> */
    public $puts = [];
    /** @var string|null */
    public $fail_code = null;
    /** @var string */
    public $fail_message = 'No se pudo subir la imagen.';

    public function put_jpeg(string $signed_url, string $binary, string $storage_path): array {
        $this->puts[] = [
            'signed_url' => $signed_url,
            'binary' => $binary,
            'storage_path' => $storage_path,
        ];

        if (strpos($signed_url, $storage_path) === false) {
            return [
                'ok' => false,
                'code' => 'signed_url_path_invalid',
                'message' => 'La URL no coincide con la ruta.',
            ];
        }

        if ($this->fail_code !== null) {
            return [
                'ok' => false,
                'code' => $this->fail_code,
                'message' => $this->fail_message,
            ];
        }

        return ['ok' => true];
    }
}

/**
 * @return array<string,string>
 */
function aa_xfer_temps(): array {
    $paths = [];
    foreach (['source', 'summary', 'gallery', 'display'] as $name) {
        $path = tempnam(sys_get_temp_dir(), 'aa_xfer_' . $name . '_');
        $payload = $name . '-payload';
        file_put_contents($path, $payload);
        $paths[$name] = $path;
    }

    return $paths;
}

/**
 * @param array<string,string> $paths
 */
function aa_xfer_cleanup_files(array $paths): void {
    foreach ($paths as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * @param array<string,string> $paths
 * @return array<string,array{path:string,width:int,height:int,mime_type:string,byte_size:int}>
 */
function aa_xfer_variant_map(array $paths): array {
    $out = [];
    foreach (['summary', 'gallery', 'display'] as $name) {
        $out[$name] = [
            'path' => $paths[$name],
            'width' => 10,
            'height' => 10,
            'mime_type' => 'image/jpeg',
            'byte_size' => (int) filesize($paths[$name]),
        ];
    }

    return $out;
}

function aa_xfer_signed_url(string $storage_path): string {
    return 'https://example.test/' . SIGNED_URL_SECRET_SENTINEL . '/' . $storage_path . '?token=' . TOKEN_SECRET_SENTINEL;
}

/**
 * @param array<string,string> $status_by_key
 * @return array<string,array<string,string>>
 */
function aa_xfer_objects(string $original, array $status_by_key, bool $shuffle_keys = false): array {
    $paths = [
        'original' => $original,
        'summary' => ExpedienteAdjuntoVariants::derive_path($original, 'summary'),
        'gallery' => ExpedienteAdjuntoVariants::derive_path($original, 'gallery'),
        'display' => ExpedienteAdjuntoVariants::derive_path($original, 'display'),
    ];

    $objects = [];
    foreach (['display', 'original', 'gallery', 'summary'] as $key) {
        if (!isset($status_by_key[$key])) {
            continue;
        }
        $status = $status_by_key[$key];
        $entry = ['status' => $status];
        if ($status === 'pending_upload') {
            $entry['signed_url'] = aa_xfer_signed_url((string) $paths[$key]);
        }
        $objects[$key] = $entry;
    }

    if (!$shuffle_keys) {
        $ordered = [];
        foreach (ExpedienteAdjuntoUploadTransfer::OBJECT_KEYS as $key) {
            if (isset($objects[$key])) {
                $ordered[$key] = $objects[$key];
            }
        }
        return $ordered;
    }

    return $objects;
}

function aa_xfer_op(): string {
    return '22222222-2222-4222-8222-222222222222';
}

function aa_xfer_install(): string {
    return '11111111-1111-4111-8111-111111111111';
}

function aa_xfer_path(?string $install = null, int $client = 7, int $record = 11, ?string $op = null): string {
    return 'installations/' . ($install ?: aa_xfer_install())
        . '/clients/' . $client
        . '/records/' . $record
        . '/' . ($op ?: aa_xfer_op()) . '.jpg';
}

/**
 * @param array<string,mixed> $result
 */
function aa_xfer_public_keys(array $result, bool $ok): bool {
    $keys = array_keys($result);
    sort($keys);
    if ($ok) {
        return $keys === ['finalize', 'ok', 'storage_path'];
    }

    return $keys === ['code', 'message', 'ok'];
}

/**
 * @param mixed $result
 */
function aa_xfer_no_sentinels($result): bool {
    $encoded = json_encode($result);
    if (!is_string($encoded)) {
        return false;
    }

    return strpos($encoded, SIGNED_URL_SECRET_SENTINEL) === false
        && strpos($encoded, UPLOAD_INTENT_SECRET_SENTINEL) === false
        && strpos($encoded, TOKEN_SECRET_SENTINEL) === false;
}

function aa_xfer_base_input(string $source_path): array {
    return [
        'source_path' => $source_path,
        'mime_type' => 'image/jpeg',
        'byte_size' => (int) filesize($source_path),
        'width' => 40,
        'height' => 30,
        'upload_operation_id' => aa_xfer_op(),
        'wp_client_id' => 7,
        'wp_record_id' => 11,
        'used_bytes' => 100,
    ];
}

function aa_xfer_finalize_result(string $path): array {
    return [
        'ok' => true,
        'result' => [
            'storage_path' => $path,
            'upload_operation_id' => aa_xfer_op(),
            'installation_id' => aa_xfer_install(),
            'mime_type' => 'image/jpeg',
            'byte_size' => 13,
            'width' => 40,
            'height' => 30,
        ],
    ];
}

$dummy_gen = new FakeTransferGenerator();
$dummy_back = new FakeTransferBackend();
$dummy_up = new FakeTransferUploader();
$threw = false;
try {
    new ExpedienteAdjuntoUploadTransfer('nope', $dummy_back, $dummy_up);
} catch (TypeError $e) {
    $threw = true;
}
ac_assert('constructor rechaza generator no-objeto', $threw);

$threw = false;
try {
    new ExpedienteAdjuntoUploadTransfer($dummy_gen, null, $dummy_up);
} catch (TypeError $e) {
    $threw = true;
}
ac_assert('constructor rechaza backend no-objeto', $threw);

$threw = false;
try {
    new ExpedienteAdjuntoUploadTransfer($dummy_gen, $dummy_back, []);
} catch (TypeError $e) {
    $threw = true;
}
ac_assert('constructor rechaza uploader no-objeto', $threw);

$files = aa_xfer_temps();
$original = aa_xfer_path();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'token' => TOKEN_SECRET_SENTINEL,
        'objects' => aa_xfer_objects($original, [
            'original' => 'pending_upload',
            'summary' => 'pending_upload',
            'gallery' => 'pending_upload',
            'display' => 'pending_upload',
        ]),
    ],
];
$back->finalize_result = aa_xfer_finalize_result($original);
$up = new FakeTransferUploader();
$xfer = new ExpedienteAdjuntoUploadTransfer($gen, $back, $up);
$ok = $xfer->transfer(aa_xfer_base_input($files['source']));
ac_assert('cuatro pendientes ok', !empty($ok['ok']));
ac_assert('éxito claves exactas', is_array($ok) && aa_xfer_public_keys($ok, true));
ac_assert('éxito sin centinelas', aa_xfer_no_sentinels($ok));
ac_assert('storage_path canónico', ($ok['storage_path'] ?? '') === $original);
$put_order = array_map(static function (array $put) use ($original): string {
    $path = (string) $put['storage_path'];
    if ($path === $original) {
        return 'original';
    }
    foreach (['summary', 'gallery', 'display'] as $name) {
        if ($path === ExpedienteAdjuntoVariants::derive_path($original, $name)) {
            return $name;
        }
    }

    return 'unknown';
}, $up->puts);
ac_assert('cuatro PUT', count($up->puts) === 4);
ac_assert('PUT en UPLOAD_ORDER', $put_order === ['summary', 'gallery', 'display', 'original']);
foreach ($up->puts as $put) {
    $name = 'original';
    if ($put['storage_path'] !== $original) {
        foreach (['summary', 'gallery', 'display'] as $variant) {
            if ($put['storage_path'] === ExpedienteAdjuntoVariants::derive_path($original, $variant)) {
                $name = $variant;
            }
        }
    }
    $expected_bin = file_get_contents($name === 'original' ? $files['source'] : $files[$name]);
    ac_assert('PUT archivo ' . $name, $put['binary'] === $expected_bin);
}
ac_assert('authorize entonces finalize', $back->calls === ['authorize', 'finalize']);
$auth_in = $back->authorize_inputs[0] ?? [];
ac_assert('manifest entero 1', ($auth_in['variants_manifest_version'] ?? null) === 1);
ac_assert(
    'variant_byte_sizes reales',
    ($auth_in['variant_byte_sizes']['summary'] ?? 0) === (int) filesize($files['summary'])
    && ($auth_in['variant_byte_sizes']['gallery'] ?? 0) === (int) filesize($files['gallery'])
    && ($auth_in['variant_byte_sizes']['display'] ?? 0) === (int) filesize($files['display'])
);
ac_assert('limpieza tras éxito', count($gen->deleted) === 1);
ac_assert('fuente intacta tras éxito', is_file($files['source']));
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => aa_xfer_objects($original, [
            'original' => 'pending_upload',
            'summary' => 'pending_upload',
            'gallery' => 'pending_upload',
            'display' => 'pending_upload',
        ], true),
    ],
];
$back->finalize_result = aa_xfer_finalize_result($original);
$up = new FakeTransferUploader();
$ok = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
$put_order = [];
foreach ($up->puts as $put) {
    $path = (string) $put['storage_path'];
    $put_order[] = $path === $original ? 'original' : (
        $path === ExpedienteAdjuntoVariants::derive_path($original, 'summary') ? 'summary' : (
            $path === ExpedienteAdjuntoVariants::derive_path($original, 'gallery') ? 'gallery' : 'display'
        )
    );
}
ac_assert('orden estable con objects desordenado', !empty($ok['ok']) && $put_order === ['summary', 'gallery', 'display', 'original']);
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => aa_xfer_objects($original, [
            'original' => 'pending_upload',
            'summary' => 'already_uploaded',
            'gallery' => 'pending_upload',
            'display' => 'already_uploaded',
        ]),
    ],
];
$back->finalize_result = aa_xfer_finalize_result($original);
$up = new FakeTransferUploader();
$mix = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
$mix_keys = [];
foreach ($up->puts as $put) {
    $mix_keys[] = $put['storage_path'] === $original ? 'original' : (
        $put['storage_path'] === ExpedienteAdjuntoVariants::derive_path($original, 'gallery') ? 'gallery' : 'other'
    );
}
ac_assert('mezcla solo pendientes', !empty($mix['ok']) && $mix_keys === ['gallery', 'original']);
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => aa_xfer_objects($original, [
            'original' => 'already_uploaded',
            'summary' => 'already_uploaded',
            'gallery' => 'already_uploaded',
            'display' => 'already_uploaded',
        ]),
    ],
];
$back->finalize_result = aa_xfer_finalize_result($original);
$up = new FakeTransferUploader();
$already = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('already_uploaded cero PUT', !empty($already['ok']) && $up->puts === []);
ac_assert('already_uploaded sí finalize', $back->calls === ['authorize', 'finalize']);
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => false,
    'code' => 'storage_quota_exceeded',
    'message' => 'No queda espacio de almacenamiento.',
];
$up = new FakeTransferUploader();
$quota = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert(
    'fallo authorize conserva código',
    empty($quota['ok']) && ($quota['code'] ?? '') === 'storage_quota_exceeded'
    && ($quota['message'] ?? '') === 'No queda espacio de almacenamiento.'
);
ac_assert('fallo authorize sin PUT ni finalize', $up->puts === [] && $back->calls === ['authorize']);
ac_assert('fallo claves exactas', aa_xfer_public_keys($quota, false));
ac_assert('limpieza tras fallo authorize', count($gen->deleted) === 1);
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => false,
    'code' => 'invalid_usage_report',
    'error' => 'used_bytes inválido',
];
$up = new FakeTransferUploader();
$usage = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('invalid_usage_report no se convierte', ($usage['code'] ?? '') === 'invalid_usage_report');
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = ['ok' => false];
$up = new FakeTransferUploader();
$malformed = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('authorize malformado → authorize_invalid', ($malformed['code'] ?? '') === 'authorize_invalid');
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'objects' => aa_xfer_objects($original, [
            'original' => 'pending_upload',
            'summary' => 'pending_upload',
            'gallery' => 'pending_upload',
            'display' => 'pending_upload',
        ]),
    ],
];
$up = new FakeTransferUploader();
$no_intent = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('sin upload_intent → authorize_invalid', ($no_intent['code'] ?? '') === 'authorize_invalid');
ac_assert('sin intent cero PUT y cero finalize', $up->puts === [] && $back->calls === ['authorize']);
ac_assert('sin intent sin centinelas', aa_xfer_no_sentinels($no_intent));
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$objects = aa_xfer_objects($original, [
    'original' => 'pending_upload',
    'summary' => 'pending_upload',
    'gallery' => 'pending_upload',
    'display' => 'pending_upload',
]);
$objects['summary']['signed_url'] = aa_xfer_signed_url((string) ExpedienteAdjuntoVariants::derive_path($original, 'gallery'));
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => $objects,
    ],
];
$back->finalize_result = aa_xfer_finalize_result($original);
$up = new FakeTransferUploader();
$cross = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('anticruce falla', empty($cross['ok']) && ($cross['code'] ?? '') === 'signed_url_path_invalid');
$accepted = 0;
foreach ($up->puts as $put) {
    if (strpos($put['signed_url'], $put['storage_path']) !== false) {
        $accepted++;
    }
}
ac_assert('anticruce ningún PUT aceptado', $accepted === 0 && count($up->puts) === 1);
ac_assert('anticruce sin finalize', !in_array('finalize', $back->calls, true));
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$missing = aa_xfer_objects($original, [
    'original' => 'pending_upload',
    'summary' => 'pending_upload',
    'gallery' => 'pending_upload',
    'display' => 'pending_upload',
]);
unset($missing['gallery']);
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => $missing,
    ],
];
$up = new FakeTransferUploader();
$missing_key = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('clave faltante → authorize_invalid', ($missing_key['code'] ?? '') === 'authorize_invalid' && $up->puts === []);
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$unknown = aa_xfer_objects($original, [
    'original' => 'pending_upload',
    'summary' => 'pending_upload',
    'gallery' => 'pending_upload',
    'display' => 'pending_upload',
]);
$unknown['summary']['status'] = 'ready';
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => $unknown,
    ],
];
$up = new FakeTransferUploader();
$unknown_st = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('status desconocido → authorize_invalid', ($unknown_st['code'] ?? '') === 'authorize_invalid' && $up->puts === []);
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$no_url = aa_xfer_objects($original, [
    'original' => 'pending_upload',
    'summary' => 'pending_upload',
    'gallery' => 'pending_upload',
    'display' => 'pending_upload',
]);
unset($no_url['summary']['signed_url']);
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => $no_url,
    ],
];
$up = new FakeTransferUploader();
$pending_no_url = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('pending sin URL → authorize_invalid', ($pending_no_url['code'] ?? '') === 'authorize_invalid' && $up->puts === []);
aa_xfer_cleanup_files($files);

$path_cases = [
    'instalación malformada' => 'installations/not-a-uuid/clients/7/records/11/' . aa_xfer_op() . '.jpg',
    'cliente diferente' => aa_xfer_path(null, 8, 11, null),
    'registro diferente' => aa_xfer_path(null, 7, 12, null),
    'operación diferente' => aa_xfer_path(null, 7, 11, '33333333-3333-4333-8333-333333333333'),
    'path derivado' => (string) ExpedienteAdjuntoVariants::derive_path($original, 'summary'),
];
foreach ($path_cases as $label => $bad_path) {
    $files = aa_xfer_temps();
    $gen = new FakeTransferGenerator();
    $gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
    $back = new FakeTransferBackend();
    $back->authorize_result = [
        'ok' => true,
        'result' => [
            'storage_path' => $bad_path,
            'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
            'objects' => [
                'original' => ['status' => 'already_uploaded'],
                'summary' => ['status' => 'already_uploaded'],
                'gallery' => ['status' => 'already_uploaded'],
                'display' => ['status' => 'already_uploaded'],
            ],
        ],
    ];
    $up = new FakeTransferUploader();
    $got = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
    ac_assert($label . ' → path_mismatch', ($got['code'] ?? '') === 'path_mismatch' && $up->puts === []);
    aa_xfer_cleanup_files($files);
}

$files = aa_xfer_temps();
$upper_op = strtoupper(aa_xfer_op());
$upper_path = 'installations/' . aa_xfer_install() . '/clients/7/records/11/' . $upper_op . '.jpg';
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $upper_path,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => aa_xfer_objects($original, [
            'original' => 'already_uploaded',
            'summary' => 'already_uploaded',
            'gallery' => 'already_uploaded',
            'display' => 'already_uploaded',
        ]),
    ],
];
$back->finalize_result = aa_xfer_finalize_result($original);
$up = new FakeTransferUploader();
$norm = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('UUID operación se normaliza', !empty($norm['ok']) && ($norm['storage_path'] ?? '') === $original);
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => false, 'code' => 'rotate_failed', 'message' => 'No se pudo rotar.'];
$back = new FakeTransferBackend();
$up = new FakeTransferUploader();
$gen_fail = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('generate ok:false → variant_generation_failed', ($gen_fail['code'] ?? '') === 'variant_generation_failed');
ac_assert('generate fallo sin authorize', $back->calls === []);
ac_assert('generate fallo limpia', count($gen->deleted) === 1);
aa_xfer_cleanup_files($files);

$invalid_gen_cases = [
    'falta variante' => static function (array $map): array {
        unset($map['gallery']);
        return $map;
    },
    'path vacío' => static function (array $map): array {
        $map['summary']['path'] = '';
        return $map;
    },
    'byte_size inválido' => static function (array $map): array {
        $map['display']['byte_size'] = 0;
        return $map;
    },
    'archivo ausente' => static function (array $map): array {
        $map['gallery']['path'] = $map['gallery']['path'] . '.missing';
        return $map;
    },
    'tamaño incoherente' => static function (array $map): array {
        $map['summary']['byte_size'] = $map['summary']['byte_size'] + 10;
        return $map;
    },
];
foreach ($invalid_gen_cases as $label => $mutate) {
    $files = aa_xfer_temps();
    $gen = new FakeTransferGenerator();
    $gen->result = ['ok' => true, 'variants' => $mutate(aa_xfer_variant_map($files))];
    $back = new FakeTransferBackend();
    $up = new FakeTransferUploader();
    $got = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
    ac_assert('generate inválido ' . $label, ($got['code'] ?? '') === 'variant_generation_failed' && $back->calls === []);
    ac_assert('generate inválido limpia ' . $label, count($gen->deleted) === 1);
    aa_xfer_cleanup_files($files);
}

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => aa_xfer_objects($original, [
            'original' => 'pending_upload',
            'summary' => 'already_uploaded',
            'gallery' => 'already_uploaded',
            'display' => 'already_uploaded',
        ]),
    ],
];
$back->finalize_result = aa_xfer_finalize_result($original);
$up = new FakeTransferUploader();
$input = aa_xfer_base_input($files['source']);
$input['byte_size'] = $input['byte_size'] + 5;
$read = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer($input);
ac_assert('read_failed tamaño original', ($read['code'] ?? '') === 'read_failed' && $up->puts === []);
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => aa_xfer_objects($original, [
            'original' => 'pending_upload',
            'summary' => 'pending_upload',
            'gallery' => 'pending_upload',
            'display' => 'pending_upload',
        ]),
    ],
];
$up = new FakeTransferUploader();
$up->fail_code = 'upload_http_error';
$up->fail_message = 'No se pudo subir la imagen.';
$put_fail = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('fallo uploader conserva código', ($put_fail['code'] ?? '') === 'upload_http_error');
ac_assert('fallo uploader sin finalize', !in_array('finalize', $back->calls, true));
ac_assert('fallo uploader limpia', count($gen->deleted) === 1);
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => aa_xfer_objects($original, [
            'original' => 'already_uploaded',
            'summary' => 'already_uploaded',
            'gallery' => 'already_uploaded',
            'display' => 'already_uploaded',
        ]),
    ],
];
$back->finalize_result = [
    'ok' => false,
    'code' => 'object_missing',
    'message' => 'Falta un objeto.',
];
$up = new FakeTransferUploader();
$fin_fail = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('fallo finalize conserva código', ($fin_fail['code'] ?? '') === 'object_missing');
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->result = ['ok' => true, 'variants' => aa_xfer_variant_map($files)];
$back = new FakeTransferBackend();
$back->authorize_result = [
    'ok' => true,
    'result' => [
        'storage_path' => $original,
        'upload_intent' => UPLOAD_INTENT_SECRET_SENTINEL,
        'objects' => aa_xfer_objects($original, [
            'original' => 'already_uploaded',
            'summary' => 'already_uploaded',
            'gallery' => 'already_uploaded',
            'display' => 'already_uploaded',
        ]),
    ],
];
$back->finalize_result = ['ok' => true];
$up = new FakeTransferUploader();
$fin_no_result = (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
ac_assert('finalize sin result → finalize_mismatch', ($fin_no_result['code'] ?? '') === 'finalize_mismatch');
aa_xfer_cleanup_files($files);

$files = aa_xfer_temps();
$gen = new FakeTransferGenerator();
$gen->throw = new RuntimeException('generate boom');
$back = new FakeTransferBackend();
$up = new FakeTransferUploader();
$threw = false;
try {
    (new ExpedienteAdjuntoUploadTransfer($gen, $back, $up))->transfer(aa_xfer_base_input($files['source']));
} catch (RuntimeException $e) {
    $threw = $e->getMessage() === 'generate boom';
}
ac_assert('excepción de generate se propaga', $threw);
ac_assert('finally corre si generate lanza', count($gen->deleted) === 1);
ac_assert('fuente intacta si generate lanza', is_file($files['source']));
aa_xfer_cleanup_files($files);

$log_contents = is_file($log_file) ? (string) file_get_contents($log_file) : '';
ac_assert(
    'centinelas ausentes en logs',
    strpos($log_contents, SIGNED_URL_SECRET_SENTINEL) === false
    && strpos($log_contents, UPLOAD_INTENT_SECRET_SENTINEL) === false
    && strpos($log_contents, TOKEN_SECRET_SENTINEL) === false
);
@unlink($log_file);

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
