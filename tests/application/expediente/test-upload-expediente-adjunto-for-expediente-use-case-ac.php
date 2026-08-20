<?php
/**
 * AC — UploadExpedienteAdjuntoForExpedienteUseCase (B3b1).
 *
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

final class ExpedientesRepository {
    /** @var bool|null */
    public static $exists_result = true;
    public static $exists_calls = 0;
    /** @var array{id:int,client_id:?int}|null */
    public static $owner = ['id' => 7, 'client_id' => 55];
    public static $owner_calls = 0;

    public static function exists_by_id(int $id) {
        self::$exists_calls++;
        return self::$exists_result;
    }

    public static function find_owner_context_by_id(int $id): ?array {
        self::$owner_calls++;
        return self::$owner;
    }
}

final class ExpedienteRegistrosRepository {
    /** @var array|false|null */
    public static $record = [
        'id' => 10,
        'expediente_id' => 7,
        'client_id' => 55,
        'title' => 'A',
        'body' => 'B',
        'recorded_at' => '2026-08-20 12:00:00',
        'created_at' => '2026-08-20 12:00:00',
        'updated_at' => null,
    ];
    public static $calls = 0;
    /** @var array{record_id:int,expediente_id:int}|null */
    public static $last_args = null;

    public static function find_by_id_for_expediente(int $record_id, int $expediente_id) {
        self::$calls++;
        self::$last_args = ['record_id' => $record_id, 'expediente_id' => $expediente_id];
        return self::$record;
    }
}

final class FakeUploadUseCase {
    public $calls = [];
    /** @var array<string,mixed> */
    public $response = [
        'ok' => true,
        'attachment' => [
            'id' => 301,
            'record_id' => 10,
            'client_id' => 55,
            'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
            'storage_path' => 'installations/x/clients/55/records/10/550e8400-e29b-41d4-a716-446655440000.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 1024,
            'width' => 800,
            'height' => 600,
            'created_at' => '2026-08-20 13:00:00',
        ],
    ];

    public function execute(array $input): array {
        $this->calls[] = $input;
        return $this->response;
    }
}

final class UploadExpedienteRegistroAdjuntoUseCase {
    /** @var FakeUploadUseCase|null */
    public static $delegate = null;

    public function execute(array $input): array {
        if (self::$delegate === null) {
            return ['ok' => false, 'code' => 'unused', 'message' => 'unused'];
        }
        return self::$delegate->execute($input);
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoPublicDto.php';
require_once $plugin_root . '/includes/application/expediente/UploadExpedienteAdjuntoForExpedienteUseCase.php';

$src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/UploadExpedienteAdjuntoForExpedienteUseCase.php'
);
$legacy_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php'
);
$sign_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlForExpedienteUseCase.php'
);

ac_assert('delega UploadExpedienteRegistroAdjuntoUseCase', strpos($src, 'UploadExpedienteRegistroAdjuntoUseCase') !== false);
ac_assert('usa exists + owner + find_by_id_for_expediente', strpos($src, 'exists_by_id') !== false
    && strpos($src, 'find_owner_context_by_id') !== false
    && strpos($src, 'find_by_id_for_expediente') !== false);
ac_assert('DTO público', strpos($src, 'ExpedienteAdjuntoPublicDto::from') !== false);
ac_assert('ignora client_id de input', strpos($src, "input['client_id']") === false);
ac_assert('legacy upload sin ForExpediente', strpos($legacy_src, 'ForExpediente') === false);
ac_assert('B3a sign sin attach canónico', strpos($sign_src, 'UploadExpedienteAdjuntoForExpediente') === false);

function aa_reset(): FakeUploadUseCase {
    ExpedientesRepository::$exists_result = true;
    ExpedientesRepository::$exists_calls = 0;
    ExpedientesRepository::$owner = ['id' => 7, 'client_id' => 55];
    ExpedientesRepository::$owner_calls = 0;
    ExpedienteRegistrosRepository::$record = [
        'id' => 10,
        'expediente_id' => 7,
        'client_id' => 55,
        'title' => 'A',
        'body' => 'B',
        'recorded_at' => '2026-08-20 12:00:00',
        'created_at' => '2026-08-20 12:00:00',
        'updated_at' => null,
    ];
    ExpedienteRegistrosRepository::$calls = 0;
    ExpedienteRegistrosRepository::$last_args = null;
    $fake = new FakeUploadUseCase();
    UploadExpedienteRegistroAdjuntoUseCase::$delegate = $fake;
    return $fake;
}

$op = '550e8400-e29b-41d4-a716-446655440000';
$file = [
    'name' => 'adjunto.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => '/tmp/phpXXXX',
    'error' => 0,
    'size' => 1024,
];
$base = [
    'expediente_id' => '7',
    'record_id' => '10',
    'upload_operation_id' => $op,
    'file' => $file,
    'client_id' => 999,
    'storage_path' => '/evil',
];

$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
$ok = $uc->execute($base);
ac_assert('attach exitoso', ($ok['success'] ?? false) === true);
ac_assert(
    'record_id + DTO público',
    ($ok['data']['record_id'] ?? 0) === 10
    && array_keys($ok['data']['adjunto'] ?? []) === ['id', 'width', 'height', 'byte_size', 'created_at']
    && ($ok['data']['adjunto']['id'] ?? 0) === 301
);
$blob = json_encode($ok['data'] ?? []);
ac_assert(
    'sin owners/paths/operation_id',
    strpos($blob, 'client_id') === false
    && strpos($blob, 'storage_path') === false
    && strpos($blob, 'upload_operation_id') === false
    && strpos($blob, 'expediente_id') === false
);
ac_assert(
    'pipeline 1× con client del padre; POST ignorado',
    count($fake->calls) === 1
    && ($fake->calls[0]['client_id'] ?? 0) === 55
    && ($fake->calls[0]['record_id'] ?? 0) === 10
    && ($fake->calls[0]['upload_operation_id'] ?? '') === $op
    && ($fake->calls[0]['file'] ?? null) === $file
    && !array_key_exists('storage_path', $fake->calls[0])
);

// Retry idempotente (mismo op → misma fila)
$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
$r1 = $uc->execute($base);
$r2 = $uc->execute($base);
ac_assert('retry mismo op → dos llamadas pipeline', count($fake->calls) === 2);
ac_assert(
    'retry idempotente mismo adjunto',
    ($r1['data']['adjunto']['id'] ?? 0) === ($r2['data']['adjunto']['id'] ?? -1)
    && ($r1['success'] ?? false) && ($r2['success'] ?? false)
);

// Pertenencia
$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
ExpedientesRepository::$exists_result = false;
$res = $uc->execute($base);
ac_assert('expediente inexistente → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
ExpedientesRepository::$exists_result = null;
$res = $uc->execute($base);
ac_assert('exists SQL → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('exists SQL sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
ExpedientesRepository::$owner = null;
$res = $uc->execute($base);
ac_assert('owner null → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('owner null sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
$res = $uc->execute($base);
ac_assert('general → attachments_unavailable', ($res['error']['code'] ?? '') === 'attachments_unavailable');
ac_assert('general sin registro/pipeline', ExpedienteRegistrosRepository::$calls === 0 && $fake->calls === []);

$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
ExpedienteRegistrosRepository::$record = false;
$res = $uc->execute($base);
ac_assert('registro ajeno → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('registro ajeno sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
ExpedienteRegistrosRepository::$record = null;
$res = $uc->execute($base);
ac_assert('registro SQL → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('registro SQL sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
ExpedienteRegistrosRepository::$record = [
    'id' => 10,
    'expediente_id' => 7,
    'client_id' => 99,
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => '2026-08-20 12:00:00',
    'created_at' => '2026-08-20 12:00:00',
    'updated_at' => null,
];
$res = $uc->execute($base);
ac_assert('owner mismatch → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('mismatch sin pipeline', $fake->calls === []);

foreach (['01', '0', '-1', '1.0', '1e2', '', ['7'], (object) ['id' => 7]] as $bad) {
    $fake = aa_reset();
    $uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
    $res = $uc->execute(array_merge($base, ['expediente_id' => $bad]));
    ac_assert('ID inválido → invalid_id', ($res['error']['code'] ?? '') === 'invalid_id');
    ac_assert('ID inválido sin pipeline', $fake->calls === []);
}

// Propagación códigos legacy
foreach ([
    'invalid_operation_id' => 'Identificador de operación no válido.',
    'invalid_mime' => 'Solo se admiten imágenes JPEG.',
    'invalid_size' => 'El archivo supera el tamaño permitido o está vacío.',
    'invalid_dimensions' => 'Las dimensiones de la imagen no son válidas.',
    'invalid_jpeg' => 'La imagen JPEG no es válida o está truncada.',
    'upload_error' => 'No se pudo recibir el archivo.',
    'storage_quota_exceeded' => 'No queda espacio.',
    'variant_generation_failed' => 'No se pudo generar las variantes.',
    'expediente_attachments_unreachable' => 'No se pudo subir la imagen.',
    'persist_failed' => 'No se pudo guardar el adjunto.',
    'adjunto_meta_conflict' => 'El adjunto existente no coincide.',
] as $code => $message) {
    $fake = aa_reset();
    $uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
    $fake->response = ['ok' => false, 'code' => $code, 'message' => $message];
    $res = $uc->execute($base);
    ac_assert("propaga {$code}", ($res['error']['code'] ?? '') === $code);
    ac_assert("{$code} tras 1 pipeline", count($fake->calls) === 1);
}

// Op no escalar normalizado a ''
$fake = aa_reset();
$uc = new UploadExpedienteAdjuntoForExpedienteUseCase(new UploadExpedienteRegistroAdjuntoUseCase());
$fake->response = ['ok' => false, 'code' => 'invalid_operation_id', 'message' => 'Identificador de operación no válido.'];
$res = $uc->execute(array_merge($base, ['upload_operation_id' => ['x']]));
ac_assert('op array → pipeline con string vacío', count($fake->calls) === 1
    && ($fake->calls[0]['upload_operation_id'] ?? null) === '');
ac_assert('op array propaga invalid_operation_id', ($res['error']['code'] ?? '') === 'invalid_operation_id');

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
