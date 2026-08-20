<?php
/**
 * AC — GetExpedienteAdjuntoReadUrlForExpedienteUseCase (B3a).
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

final class FakeSignReadUseCase {
    public $calls = [];
    /** @var array<string,mixed> */
    public $response = [
        'ok' => true,
        'url' => 'https://proj.supabase.co/storage/v1/object/sign/x.jpg?token=abc',
        'expires_in' => 600,
        'variant' => 'summary',
    ];

    public function execute(array $input): array {
        $this->calls[] = $input;
        $resp = $this->response;
        if (!empty($resp['ok']) && isset($input['variant'])) {
            $resp['variant'] = $input['variant'];
        }
        return $resp;
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';

// Stub GetExpedienteAdjuntoReadUrlUseCase before requiring the for-expediente UC.
final class GetExpedienteAdjuntoReadUrlUseCase {
    /** @var FakeSignReadUseCase|null */
    public static $delegate = null;

    public function execute(array $input): array {
        if (self::$delegate === null) {
            return ['ok' => false, 'code' => 'unused', 'message' => 'unused'];
        }
        return self::$delegate->execute($input);
    }
}

require_once $plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlForExpedienteUseCase.php';

$src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlForExpedienteUseCase.php'
);
$legacy_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlUseCase.php'
);
$list_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/ListExpedienteRegistrosWithPublicAdjuntosUseCase.php'
);

ac_assert('delega GetExpedienteAdjuntoReadUrlUseCase', strpos($src, 'GetExpedienteAdjuntoReadUrlUseCase') !== false);
ac_assert('usa exists + owner + find_by_id_for_expediente', strpos($src, 'exists_by_id') !== false
    && strpos($src, 'find_owner_context_by_id') !== false
    && strpos($src, 'find_by_id_for_expediente') !== false);
ac_assert('ignora client_id de input', strpos($src, "input['client_id']") === false);
ac_assert('legacy UC sin cambios de action canónica', strpos($legacy_src, 'ForExpediente') === false);
ac_assert('B2b list sin sign-read canónico', strpos($list_src, 'GetExpedienteAdjuntoReadUrlForExpediente') === false);

function aa_reset(): FakeSignReadUseCase {
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
    $fake = new FakeSignReadUseCase();
    GetExpedienteAdjuntoReadUrlUseCase::$delegate = $fake;
    return $fake;
}

$input_base = [
    'expediente_id' => '7',
    'record_id' => '10',
    'attachment_id' => '301',
    'variant' => 'summary',
    'client_id' => 999,
    'storage_path' => '/evil',
];

foreach (['summary', 'gallery', 'display'] as $variant) {
    $fake = aa_reset();
    $uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
    $res = $uc->execute(array_merge($input_base, ['variant' => $variant]));
    ac_assert("firma válida {$variant}", ($res['success'] ?? false) === true
        && ($res['data']['variant'] ?? '') === $variant
        && ($res['data']['url'] ?? '') !== ''
        && ($res['data']['expires_in'] ?? 0) === 600);
    ac_assert(
        "{$variant}: client_id del padre; POST ignorado",
        count($fake->calls) === 1
        && ($fake->calls[0]['client_id'] ?? 0) === 55
        && !array_key_exists('storage_path', $fake->calls[0])
        && ($fake->calls[0]['record_id'] ?? 0) === 10
        && ($fake->calls[0]['attachment_id'] ?? 0) === 301
    );
}

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
$ok = $uc->execute($input_base);
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

// --- Fallos previos a firma ---

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
ExpedienteRegistrosRepository::$record = false;
$res = $uc->execute($input_base);
ac_assert('registro ajeno → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('registro ajeno sin firma', $fake->calls === []);

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
ExpedienteRegistrosRepository::$record = null;
$res = $uc->execute($input_base);
ac_assert('SQL registro → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('SQL registro sin firma', $fake->calls === []);

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
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
$res = $uc->execute($input_base);
ac_assert('client_id mismatch → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('client_id mismatch sin firma', $fake->calls === []);

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
ExpedientesRepository::$exists_result = false;
$res = $uc->execute($input_base);
ac_assert('expediente inexistente → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin owner/registro/firma', ExpedientesRepository::$owner_calls === 0
    && ExpedienteRegistrosRepository::$calls === 0
    && $fake->calls === []);

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
ExpedientesRepository::$exists_result = null;
$res = $uc->execute($input_base);
ac_assert('exists SQL → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('exists SQL sin firma', $fake->calls === []);

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
ExpedientesRepository::$owner = null;
$res = $uc->execute($input_base);
ac_assert('owner null → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('owner null sin firma', $fake->calls === []);

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
$res = $uc->execute($input_base);
ac_assert('padre general → attachments_unavailable', ($res['error']['code'] ?? '') === 'attachments_unavailable');
ac_assert('general sin registro/firma', ExpedienteRegistrosRepository::$calls === 0 && $fake->calls === []);

foreach (['01', '0', '-1', '1.0', '1e2', '', ['7'], (object) ['id' => 7]] as $bad) {
    $fake = aa_reset();
    $uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
    $res = $uc->execute(array_merge($input_base, ['expediente_id' => $bad]));
    ac_assert('ID inválido → invalid_id', ($res['error']['code'] ?? '') === 'invalid_id');
    ac_assert('ID inválido sin firma', $fake->calls === []);
}

foreach ([null, '', 'original', 'thumb', ['summary'], 1] as $badVar) {
    $fake = aa_reset();
    $uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
    $payload = $input_base;
    if ($badVar === null) {
        unset($payload['variant']);
    } else {
        $payload['variant'] = $badVar;
    }
    $res = $uc->execute($payload);
    ac_assert('variante inválida → variant_invalid', ($res['error']['code'] ?? '') === 'variant_invalid');
    ac_assert('variante inválida sin firma', $fake->calls === []);
}

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
$fake->response = ['ok' => false, 'code' => 'object_missing', 'message' => 'No se pudo obtener la imagen.'];
$res = $uc->execute($input_base);
ac_assert('propaga object_missing', ($res['error']['code'] ?? '') === 'object_missing');
ac_assert('object_missing tras una firma', count($fake->calls) === 1);

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
$fake->response = ['ok' => false, 'code' => 'sign_failed', 'message' => 'No se pudo obtener la imagen.'];
$res = $uc->execute($input_base);
ac_assert('propaga sign_failed', ($res['error']['code'] ?? '') === 'sign_failed');

$fake = aa_reset();
$uc = new GetExpedienteAdjuntoReadUrlForExpedienteUseCase(new GetExpedienteAdjuntoReadUrlUseCase());
$fake->response = ['ok' => false, 'code' => 'attachment_not_found', 'message' => 'Imagen no encontrada.'];
$res = $uc->execute($input_base);
ac_assert('propaga attachment_not_found (otro record/client)', ($res['error']['code'] ?? '') === 'attachment_not_found');

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
