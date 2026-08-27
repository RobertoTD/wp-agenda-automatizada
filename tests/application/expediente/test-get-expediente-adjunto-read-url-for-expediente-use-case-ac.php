<?php
/**
 * AC — GetExpedienteAdjuntoReadUrlForExpedienteUseCase (B3a / P2).
 *
 * Ejecutar: php tests/application/expediente/test-get-expediente-adjunto-read-url-for-expediente-use-case-ac.php
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
if (!defined('AA_EXPEDIENTE_STORAGE_ORIGIN')) {
    define('AA_EXPEDIENTE_STORAGE_ORIGIN', 'https://proj.supabase.co');
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url) {
        return parse_url($url);
    }
}

$iid = '11111111-2222-4333-8444-555555555555';
$op = '660e8400-e29b-41d4-a716-446655440000';

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
    public static $record = false;
    public static $calls = 0;
    /** @var array{record_id:int,expediente_id:int}|null */
    public static $last_args = null;

    public static function find_by_id_for_expediente(int $record_id, int $expediente_id) {
        self::$calls++;
        self::$last_args = ['record_id' => $record_id, 'expediente_id' => $expediente_id];
        return self::$record;
    }
}

final class ExpedienteAdjuntosRepository {
    /** @var array|null|false */
    public static $adjunto = false;
    public static $calls = 0;
    /** @var array{attachment_id:int,record_id:int}|null */
    public static $last_args = null;

    public static function find_by_id_for_record(int $attachment_id, int $record_id) {
        self::$calls++;
        self::$last_args = ['attachment_id' => $attachment_id, 'record_id' => $record_id];
        return self::$adjunto;
    }
}

final class FakeSignBackend {
    public $calls = [];
    /** @var array<string,mixed>|null */
    public $response = null;

    public function sign_read(string $storage_path, string $variant): array {
        $this->calls[] = compact('storage_path', 'variant');
        if ($this->response !== null) {
            return $this->response;
        }
        $derived = ExpedienteAdjuntoVariants::derive_path($storage_path, $variant);
        $url = 'https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $derived . '?token=eyJx.y.z';
        return [
            'ok' => true,
            'result' => [
                'url' => $url,
                'expires_in' => 600,
                'variant' => $variant,
            ],
        ];
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-adjunto-identity-policy.php';
require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';
require_once $plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlForExpedienteUseCase.php';

$src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlForExpedienteUseCase.php'
);
$list_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/ListExpedienteRegistrosWithPublicAdjuntosUseCase.php'
);

ac_assert('NO delega GetExpedienteAdjuntoReadUrlUseCase', strpos($src, 'GetExpedienteAdjuntoReadUrlUseCase') === false);
ac_assert('usa sign_read + policy + find_by_id_for_record', strpos($src, 'sign_read') !== false
    && strpos($src, 'AA_Expediente_Adjunto_Identity_Policy::validate') !== false
    && strpos($src, 'find_by_id_for_record') !== false);
ac_assert('sin código attachments_unavailable', strpos($src, "'attachments_unavailable'") === false);
ac_assert('usa exists + owner + find_by_id_for_expediente', strpos($src, 'exists_by_id') !== false
    && strpos($src, 'find_owner_context_by_id') !== false
    && strpos($src, 'find_by_id_for_expediente') !== false);
ac_assert('ignora client_id de input', strpos($src, "input['client_id']") === false);
ac_assert('B2b list sin sign-read canónico', strpos($list_src, 'GetExpedienteAdjuntoReadUrlForExpediente') === false);

function aa_related_record(): array {
    return [
        'id' => 10,
        'expediente_id' => 7,
        'client_id' => 55,
        'title' => 'A',
        'body' => 'B',
        'recorded_at' => '2026-08-20 12:00:00',
        'created_at' => '2026-08-20 12:00:00',
        'updated_at' => null,
    ];
}

function aa_related_adjunto(): array {
    global $iid, $op;
    $path = ExpedienteAdjuntoVariants::build_client_original_path($iid, 55, 10, $op);
    return [
        'id' => 301,
        'record_id' => 10,
        'client_id' => 55,
        'upload_operation_id' => $op,
        'storage_path' => $path,
        'mime_type' => 'image/jpeg',
        'byte_size' => 1024,
        'width' => 400,
        'height' => 300,
        'created_at' => '2026-08-20 12:30:00',
    ];
}

function aa_general_record(): array {
    return [
        'id' => 14,
        'expediente_id' => 7,
        'client_id' => null,
        'title' => 'G',
        'body' => 'B',
        'recorded_at' => '2026-08-20 12:00:00',
        'created_at' => '2026-08-20 12:00:00',
        'updated_at' => null,
    ];
}

function aa_general_adjunto(): array {
    global $iid, $op;
    $path = ExpedienteAdjuntoVariants::build_expediente_record_original_path($iid, 7, 14, $op);
    return [
        'id' => 401,
        'record_id' => 14,
        'client_id' => null,
        'upload_operation_id' => $op,
        'storage_path' => $path,
        'mime_type' => 'image/jpeg',
        'byte_size' => 900,
        'width' => 200,
        'height' => 150,
        'created_at' => '2026-08-21 10:00:00',
    ];
}

function aa_reset_related(): FakeSignBackend {
    ExpedientesRepository::$exists_result = true;
    ExpedientesRepository::$exists_calls = 0;
    ExpedientesRepository::$owner = ['id' => 7, 'client_id' => 55];
    ExpedientesRepository::$owner_calls = 0;
    ExpedienteRegistrosRepository::$record = aa_related_record();
    ExpedienteRegistrosRepository::$calls = 0;
    ExpedienteRegistrosRepository::$last_args = null;
    ExpedienteAdjuntosRepository::$adjunto = aa_related_adjunto();
    ExpedienteAdjuntosRepository::$calls = 0;
    ExpedienteAdjuntosRepository::$last_args = null;
    return new FakeSignBackend();
}

$input_related = [
    'expediente_id' => '7',
    'record_id' => '10',
    'attachment_id' => '301',
    'variant' => 'summary',
    'client_id' => 999,
    'storage_path' => '/evil',
];

foreach (['summary', 'gallery', 'display'] as $variant) {
    $backend = aa_reset_related();
    $uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
    $res = $uc->execute(array_merge($input_related, ['variant' => $variant]));
    ac_assert("relacionado v1 firma {$variant}", ($res['success'] ?? false) === true
        && ($res['data']['variant'] ?? '') === $variant
        && ($res['data']['url'] ?? '') !== ''
        && ($res['data']['expires_in'] ?? 0) === 600);
    ac_assert(
        "{$variant}: sign_read con path canónico",
        count($backend->calls) === 1
        && ($backend->calls[0]['variant'] ?? '') === $variant
        && strpos((string) ($backend->calls[0]['storage_path'] ?? ''), '/clients/55/') !== false
    );
    ac_assert("{$variant}: find_by_id_for_record", (ExpedienteAdjuntosRepository::$last_args['attachment_id'] ?? 0) === 301
        && (ExpedienteAdjuntosRepository::$last_args['record_id'] ?? 0) === 10);
}

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
$ok = $uc->execute($input_related);
$blob = json_encode($ok['data'] ?? []);
ac_assert(
    'respuesta sin owners/paths',
    strpos($blob, 'client_id') === false
    && strpos($blob, 'storage_path') === false
    && strpos($blob, 'expediente_id') === false
    && array_keys($ok['data'] ?? []) === ['url', 'expires_in', 'variant']
);
ac_assert(
    'lookup registro con ids canónicos',
    (ExpedienteRegistrosRepository::$last_args['record_id'] ?? 0) === 10
    && (ExpedienteRegistrosRepository::$last_args['expediente_id'] ?? 0) === 7
);

// --- General v2 success ---

$backend = aa_reset_related();
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
ExpedienteRegistrosRepository::$record = aa_general_record();
ExpedienteAdjuntosRepository::$adjunto = aa_general_adjunto();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
$general = $uc->execute([
    'expediente_id' => '7',
    'record_id' => '14',
    'attachment_id' => '401',
    'variant' => 'summary',
]);
ac_assert('general v2 → success', ($general['success'] ?? false) === true);
ac_assert('general v2 sign_read', count($backend->calls) === 1
    && strpos((string) ($backend->calls[0]['storage_path'] ?? ''), '/expedientes/7/') !== false);
ac_assert('general sin attachments_unavailable', ($general['error']['code'] ?? '') !== 'attachments_unavailable');

// --- Fallos previos a firma ---

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
ExpedienteRegistrosRepository::$record = false;
$res = $uc->execute($input_related);
ac_assert('registro ajeno → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('registro ajeno sin firma', $backend->calls === []);

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
ExpedienteRegistrosRepository::$record = null;
$res = $uc->execute($input_related);
ac_assert('SQL registro → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('SQL registro sin firma', $backend->calls === []);

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
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
$res = $uc->execute($input_related);
ac_assert('client_id mismatch → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('client_id mismatch sin firma', $backend->calls === []);

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
ExpedientesRepository::$exists_result = false;
$res = $uc->execute($input_related);
ac_assert('expediente inexistente → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin owner/registro/firma', ExpedientesRepository::$owner_calls === 0
    && ExpedienteRegistrosRepository::$calls === 0
    && $backend->calls === []);

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
ExpedientesRepository::$exists_result = null;
$res = $uc->execute($input_related);
ac_assert('exists SQL → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('exists SQL sin firma', $backend->calls === []);

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
ExpedientesRepository::$owner = null;
$res = $uc->execute($input_related);
ac_assert('owner null → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('owner null sin firma', $backend->calls === []);

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
ExpedienteAdjuntosRepository::$adjunto = null;
$res = $uc->execute($input_related);
ac_assert('adjunto ausente → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('adjunto ausente sin firma', $backend->calls === []);

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
$bad = aa_related_adjunto();
$bad['storage_path'] = '/invalid';
ExpedienteAdjuntosRepository::$adjunto = $bad;
$res = $uc->execute($input_related);
ac_assert('adjunto inconsistente → adjunto_inconsistent', ($res['error']['code'] ?? '') === 'adjunto_inconsistent');
ac_assert('inconsistente sin firma', $backend->calls === []);

foreach (['01', '0', '-1', '1.0', '1e2', '', ['7'], (object) ['id' => 7]] as $badId) {
    $backend = aa_reset_related();
    $uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
    $res = $uc->execute(array_merge($input_related, ['expediente_id' => $badId]));
    ac_assert('ID inválido → invalid_id', ($res['error']['code'] ?? '') === 'invalid_id');
    ac_assert('ID inválido sin firma', $backend->calls === []);
}

foreach ([null, '', 'original', 'thumb', ['summary'], 1] as $badVar) {
    $backend = aa_reset_related();
    $uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
    $payload = $input_related;
    if ($badVar === null) {
        unset($payload['variant']);
    } else {
        $payload['variant'] = $badVar;
    }
    $res = $uc->execute($payload);
    ac_assert('variante inválida → variant_invalid', ($res['error']['code'] ?? '') === 'variant_invalid');
    ac_assert('variante inválida sin firma', $backend->calls === []);
}

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
$backend->response = ['ok' => false, 'code' => 'object_missing', 'message' => 'No se pudo obtener la imagen.'];
$res = $uc->execute($input_related);
ac_assert('propaga object_missing', ($res['error']['code'] ?? '') === 'object_missing');
ac_assert('object_missing tras una firma', count($backend->calls) === 1);

$backend = aa_reset_related();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase($backend);
$backend->response = ['ok' => false, 'code' => 'sign_failed', 'message' => 'No se pudo obtener la imagen.'];
$res = $uc->execute($input_related);
ac_assert('propaga sign_failed', ($res['error']['code'] ?? '') === 'sign_failed');

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
