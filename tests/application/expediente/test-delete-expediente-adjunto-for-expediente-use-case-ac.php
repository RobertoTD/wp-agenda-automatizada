<?php
/**
 * AC — DeleteExpedienteAdjuntoForExpedienteUseCase (B3b2).
 *
 * Ejecutar: php tests/application/expediente/test-delete-expediente-adjunto-for-expediente-use-case-ac.php
 *
 * No elimina datos reales: usa stubs.
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

    public static function find_by_id_for_expediente(int $record_id, int $expediente_id) {
        self::$calls++;
        return self::$record;
    }
}

final class FakeDeleteUseCase {
    public $calls = [];
    /** @var list<array<string,mixed>> */
    public $responses = [];
    public $response_index = 0;

    public function execute(array $input): array {
        $this->calls[] = $input;
        if ($this->responses !== []) {
            $idx = min($this->response_index, count($this->responses) - 1);
            $this->response_index++;
            return $this->responses[$idx];
        }
        return [
            'ok' => true,
            'record_id' => (int) ($input['record_id'] ?? 0),
            'deleted_attachment_id' => (int) ($input['attachment_id'] ?? 0),
            'adjuntos' => [
                [
                    'id' => 19,
                    'width' => 100,
                    'height' => 80,
                    'byte_size' => 512,
                    'created_at' => '2026-08-19 11:00:00',
                ],
            ],
            'adjunto' => [
                'id' => 19,
                'width' => 100,
                'height' => 80,
                'byte_size' => 512,
                'created_at' => '2026-08-19 11:00:00',
            ],
        ];
    }
}

final class DeleteExpedienteAdjuntoUseCase {
    /** @var FakeDeleteUseCase|null */
    public static $delegate = null;

    public function execute(array $input): array {
        if (self::$delegate === null) {
            return ['ok' => false, 'code' => 'unused', 'message' => 'unused'];
        }
        return self::$delegate->execute($input);
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/application/expediente/DeleteExpedienteAdjuntoForExpedienteUseCase.php';

$src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/DeleteExpedienteAdjuntoForExpedienteUseCase.php'
);
$legacy_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/DeleteExpedienteAdjuntoUseCase.php'
);

ac_assert('delega DeleteExpedienteAdjuntoUseCase', strpos($src, 'DeleteExpedienteAdjuntoUseCase') !== false);
ac_assert('usa exists + owner + find_by_id_for_expediente', strpos($src, 'exists_by_id') !== false
    && strpos($src, 'find_owner_context_by_id') !== false
    && strpos($src, 'find_by_id_for_expediente') !== false);
ac_assert('ignora client_id de input', strpos($src, "input['client_id']") === false);
ac_assert('legacy delete sin ForExpediente', strpos($legacy_src, 'ForExpediente') === false);
ac_assert('Storage primero documentado en legacy', strpos($legacy_src, 'Storage eliminado') !== false
    || strpos($legacy_src, 'Storage') !== false);

function aa_reset(): FakeDeleteUseCase {
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
    $fake = new FakeDeleteUseCase();
    DeleteExpedienteAdjuntoUseCase::$delegate = $fake;
    return $fake;
}

$base = [
    'expediente_id' => '7',
    'record_id' => '10',
    'attachment_id' => '20',
    'client_id' => 999,
    'storage_path' => '/evil',
];

$fake = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
$ok = $uc->execute($base);
ac_assert('eliminación exitosa', ($ok['success'] ?? false) === true);
ac_assert('record_id', ($ok['data']['record_id'] ?? 0) === 10);
ac_assert('deleted_attachment_id', ($ok['data']['deleted_attachment_id'] ?? 0) === 20);
ac_assert('adjuntos restantes', count($ok['data']['adjuntos'] ?? []) === 1
    && ($ok['data']['adjuntos'][0]['id'] ?? 0) === 19);
ac_assert('adjunto === adjuntos[0]', ($ok['data']['adjunto'] ?? null) === ($ok['data']['adjuntos'][0] ?? null));
ac_assert(
    'DTO público',
    array_keys($ok['data']['adjuntos'][0]) === ['id', 'width', 'height', 'byte_size', 'created_at']
);
$blob = json_encode($ok['data'] ?? []);
ac_assert(
    'sin owners/paths',
    strpos($blob, 'client_id') === false
    && strpos($blob, 'storage_path') === false
    && strpos($blob, 'expediente_id') === false
);
ac_assert(
    'pipeline 1× client padre; POST ignorado',
    count($fake->calls) === 1
    && ($fake->calls[0]['client_id'] ?? 0) === 55
    && ($fake->calls[0]['record_id'] ?? 0) === 10
    && ($fake->calls[0]['attachment_id'] ?? 0) === 20
    && !array_key_exists('storage_path', $fake->calls[0])
);

$fake = aa_reset();
$fake->responses = [[
    'ok' => true,
    'record_id' => 10,
    'deleted_attachment_id' => 20,
    'adjuntos' => [],
    'adjunto' => null,
]];
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
$empty = $uc->execute($base);
ac_assert('colección vacía → adjunto null', ($empty['data']['adjuntos'] ?? null) === []
    && array_key_exists('adjunto', $empty['data'])
    && $empty['data']['adjunto'] === null);

// Pertenencia
$fake = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
ExpedientesRepository::$exists_result = false;
$res = $uc->execute($base);
ac_assert('expediente inexistente → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
ExpedientesRepository::$exists_result = null;
$res = $uc->execute($base);
ac_assert('exists SQL → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('exists SQL sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
ExpedientesRepository::$owner = null;
$res = $uc->execute($base);
ac_assert('owner null → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('owner null sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
$res = $uc->execute($base);
ac_assert('general → attachments_unavailable', ($res['error']['code'] ?? '') === 'attachments_unavailable');
ac_assert('general sin registro/pipeline', ExpedienteRegistrosRepository::$calls === 0 && $fake->calls === []);

$fake = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
ExpedienteRegistrosRepository::$record = false;
$res = $uc->execute($base);
ac_assert('registro ajeno → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('registro ajeno sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
ExpedienteRegistrosRepository::$record = null;
$res = $uc->execute($base);
ac_assert('registro SQL → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');
ac_assert('registro SQL sin pipeline', $fake->calls === []);

$fake = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
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
    $uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
    $res = $uc->execute(array_merge($base, ['expediente_id' => $bad]));
    ac_assert('ID inválido → invalid_id', ($res['error']['code'] ?? '') === 'invalid_id');
    ac_assert('ID inválido sin pipeline', $fake->calls === []);
}

// Propagación legacy
foreach ([
    'attachment_not_found' => 'Imagen no encontrada.',
    'adjunto_inconsistent' => 'El adjunto local es inconsistente.',
    'expediente_attachments_unreachable' => 'No se pudo eliminar la imagen.',
    'storage_delete_failed' => 'No se pudo eliminar la imagen.',
    'local_delete_failed' => 'No se pudo eliminar la imagen.',
    'client_not_found' => 'Cliente no encontrado.',
    'record_not_found' => 'Registro no encontrado.',
] as $code => $message) {
    $fake = aa_reset();
    $uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
    $fake->responses = [['ok' => false, 'code' => $code, 'message' => $message]];
    $res = $uc->execute($base);
    ac_assert("propaga {$code}", ($res['error']['code'] ?? '') === $code);
    ac_assert("{$code} tras 1 pipeline", count($fake->calls) === 1);
}

// already_absent path (legacy returns ok)
$fake = aa_reset();
$fake->responses = [[
    'ok' => true,
    'record_id' => 10,
    'deleted_attachment_id' => 20,
    'adjuntos' => [],
    'adjunto' => null,
]];
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
$res = $uc->execute($base);
ac_assert('already_absent + DELETE OK → success', ($res['success'] ?? false) === true);

// local_delete_failed then retry success
$fake = aa_reset();
$fake->responses = [
    ['ok' => false, 'code' => 'local_delete_failed', 'message' => 'No se pudo eliminar la imagen.'],
    [
        'ok' => true,
        'record_id' => 10,
        'deleted_attachment_id' => 20,
        'adjuntos' => [],
        'adjunto' => null,
    ],
];
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
$r1 = $uc->execute($base);
$r2 = $uc->execute($base);
ac_assert('local_delete_failed primero', ($r1['error']['code'] ?? '') === 'local_delete_failed');
ac_assert('retry completa', ($r2['success'] ?? false) === true);
ac_assert('retry 2× pipeline', count($fake->calls) === 2);

// Segunda eliminación tras éxito → attachment_not_found
$fake = aa_reset();
$fake->responses = [
    [
        'ok' => true,
        'record_id' => 10,
        'deleted_attachment_id' => 20,
        'adjuntos' => [],
        'adjunto' => null,
    ],
    ['ok' => false, 'code' => 'attachment_not_found', 'message' => 'Imagen no encontrada.'],
];
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase(new DeleteExpedienteAdjuntoUseCase());
$s1 = $uc->execute($base);
$s2 = $uc->execute($base);
ac_assert('primera OK', ($s1['success'] ?? false) === true);
ac_assert('segunda → attachment_not_found (no éxito)', ($s2['error']['code'] ?? '') === 'attachment_not_found');

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
