<?php
/**
 * AC — DeleteExpedienteAdjuntoForExpedienteUseCase (B3b2 / P2).
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

$iid = '11111111-2222-4333-8444-555555555555';
$op_del = '660e8400-e29b-41d4-a716-446655440000';
$op_rem = '770e8400-e29b-41d4-a716-446655440001';

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

final class ExpedienteAdjuntosRepository {
    /** @var array|null */
    public static $adjunto = null;
    /** @var bool|WP_Error|null */
    public static $delete_result = true;
    /** @var array<int,list<array<string,mixed>>>|null */
    public static $remaining = [];
    public static $find_calls = 0;
    public static $delete_calls = 0;
    public static $list_calls = 0;

    public static function find_by_id_for_record(int $attachment_id, int $record_id): ?array {
        self::$find_calls++;
        return self::$adjunto;
    }

    public static function delete_by_exact_identity(array $identity) {
        self::$delete_calls++;
        return self::$delete_result;
    }

    public static function list_by_record_ids_for_records(array $record_ids): ?array {
        self::$list_calls++;
        return self::$remaining;
    }
}

final class FakeDeleteBackend {
    public $calls = [];
    /** @var array<string,mixed> */
    public $response = [
        'ok' => true,
        'result' => ['status' => 'deleted'],
    ];

    public function delete_object(string $storage_path): array {
        $this->calls[] = $storage_path;
        return $this->response;
    }
}

require_once $plugin_root . '/tests/support/aa-test-expediente-aggregate-lock-passthrough.php';
$lock = aa_test_install_passthrough_expediente_lock();
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-adjunto-identity-policy.php';
require_once $plugin_root . '/includes/application/expediente/DeleteExpedienteAdjuntoForExpedienteUseCase.php';

$src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/DeleteExpedienteAdjuntoForExpedienteUseCase.php'
);

ac_assert('NO delega DeleteExpedienteAdjuntoUseCase', strpos($src, 'DeleteExpedienteAdjuntoUseCase') === false);
ac_assert('usa delete_object + find_by_id_for_record + policy', strpos($src, 'delete_object') !== false
    && strpos($src, 'find_by_id_for_record') !== false
    && strpos($src, 'AA_Expediente_Adjunto_Identity_Policy::validate') !== false);
ac_assert('sin código attachments_unavailable', strpos($src, "'attachments_unavailable'") === false);
ac_assert('ignora client_id de input', strpos($src, "input['client_id']") === false);

function aa_related_adjunto(int $id = 20): array {
    global $iid, $op_del;
    $path = ExpedienteAdjuntoVariants::build_client_original_path($iid, 55, 10, $op_del);
    return [
        'id' => $id,
        'record_id' => 10,
        'client_id' => 55,
        'upload_operation_id' => $op_del,
        'storage_path' => $path,
        'mime_type' => 'image/jpeg',
        'byte_size' => 1024,
        'width' => 400,
        'height' => 300,
        'created_at' => '2026-08-20 12:30:00',
    ];
}

function aa_general_adjunto(int $id = 30): array {
    global $iid, $op_del;
    $path = ExpedienteAdjuntoVariants::build_expediente_record_original_path($iid, 7, 14, $op_del);
    return [
        'id' => $id,
        'record_id' => 14,
        'client_id' => null,
        'upload_operation_id' => $op_del,
        'storage_path' => $path,
        'mime_type' => 'image/jpeg',
        'byte_size' => 900,
        'width' => 200,
        'height' => 150,
        'created_at' => '2026-08-21 10:00:00',
    ];
}

function aa_reset(): FakeDeleteBackend {
    global $lock, $op_rem;
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
    ExpedienteAdjuntosRepository::$adjunto = aa_related_adjunto(20);
    ExpedienteAdjuntosRepository::$delete_result = true;
    ExpedienteAdjuntosRepository::$remaining = [
        10 => [
            [
                'id' => 19,
                'record_id' => 10,
                'client_id' => 55,
                'upload_operation_id' => $op_rem,
                'storage_path' => ExpedienteAdjuntoVariants::build_client_original_path(
                    '11111111-2222-4333-8444-555555555555',
                    55,
                    10,
                    $op_rem
                ),
                'mime_type' => 'image/jpeg',
                'byte_size' => 512,
                'width' => 100,
                'height' => 80,
                'created_at' => '2026-08-19 11:00:00',
            ],
        ],
    ];
    ExpedienteAdjuntosRepository::$find_calls = 0;
    ExpedienteAdjuntosRepository::$delete_calls = 0;
    ExpedienteAdjuntosRepository::$list_calls = 0;
    $lock->acquire_calls = [];
    $lock->release_calls = 0;
    return new FakeDeleteBackend();
}

$base = [
    'expediente_id' => '7',
    'record_id' => '10',
    'attachment_id' => '20',
    'client_id' => 999,
    'storage_path' => '/evil',
];

$backend = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
$ok = $uc->execute($base);
ac_assert('relacionado v1 eliminación exitosa', ($ok['success'] ?? false) === true);
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
ac_assert('Storage 1×', count($backend->calls) === 1);
ac_assert('metadata delete 1×', ExpedienteAdjuntosRepository::$delete_calls === 1);
ac_assert(
    'lock scope client',
    ($lock->acquire_calls[0]['scope_kind'] ?? '') === 'client'
    && (int) ($lock->acquire_calls[0]['scope_id'] ?? 0) === 55
);
ac_assert('release finally', $lock->release_calls === 1);

// General v2 success
$backend = aa_reset();
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
ExpedienteRegistrosRepository::$record = [
    'id' => 14,
    'expediente_id' => 7,
    'client_id' => null,
    'title' => 'G',
    'body' => 'B',
    'recorded_at' => '2026-08-20 12:00:00',
    'created_at' => '2026-08-20 12:00:00',
    'updated_at' => null,
];
ExpedienteAdjuntosRepository::$adjunto = aa_general_adjunto(30);
ExpedienteAdjuntosRepository::$remaining = [14 => []];
$lock->acquire_calls = [];
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
$gok = $uc->execute([
    'expediente_id' => '7',
    'record_id' => '14',
    'attachment_id' => '30',
]);
ac_assert('general v2 eliminación exitosa', ($gok['success'] ?? false) === true);
ac_assert(
    'general lock scope expediente',
    ($lock->acquire_calls[0]['scope_kind'] ?? '') === 'expediente'
    && (int) ($lock->acquire_calls[0]['scope_id'] ?? 0) === 7
);
ac_assert('general v2 Storage', count($backend->calls) === 1);

// Colección vacía tras delete
$backend = aa_reset();
ExpedienteAdjuntosRepository::$remaining = [10 => []];
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
$empty = $uc->execute($base);
ac_assert('colección vacía → adjunto null', ($empty['data']['adjuntos'] ?? null) === []
    && array_key_exists('adjunto', $empty['data'])
    && $empty['data']['adjunto'] === null);

// already_absent
$backend = aa_reset();
$backend->response = ['ok' => true, 'result' => ['status' => 'already_absent']];
ExpedienteAdjuntosRepository::$remaining = [10 => []];
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
$res = $uc->execute($base);
ac_assert('already_absent → success', ($res['success'] ?? false) === true);
ac_assert('already_absent metadata delete', ExpedienteAdjuntosRepository::$delete_calls === 1);

// Pertenencia
$backend = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
ExpedientesRepository::$exists_result = false;
$res = $uc->execute($base);
ac_assert('expediente inexistente → not_found', ($res['error']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin Storage', $backend->calls === []);

$backend = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
ExpedientesRepository::$exists_result = null;
$res = $uc->execute($base);
ac_assert('exists SQL → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');

$backend = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
ExpedientesRepository::$owner = null;
$res = $uc->execute($base);
ac_assert('owner null → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');

$backend = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
ExpedienteRegistrosRepository::$record = false;
$res = $uc->execute($base);
ac_assert('registro ajeno → not_found', ($res['error']['code'] ?? '') === 'not_found');

$backend = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
ExpedienteRegistrosRepository::$record = null;
$res = $uc->execute($base);
ac_assert('registro SQL → lookup_failed', ($res['error']['code'] ?? '') === 'lookup_failed');

$backend = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
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
ac_assert('owner mismatch → adjunto_inconsistent', ($res['error']['code'] ?? '') === 'adjunto_inconsistent');

$backend = aa_reset();
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
ExpedienteAdjuntosRepository::$adjunto = null;
$res = $uc->execute($base);
ac_assert('adjunto ausente → not_found', ($res['error']['code'] ?? '') === 'not_found');

// Inconsistencia: cero Storage
$backend = aa_reset();
$bad = aa_related_adjunto(20);
$bad['storage_path'] = '/invalid';
ExpedienteAdjuntosRepository::$adjunto = $bad;
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
$res = $uc->execute($base);
ac_assert('inconsistente → adjunto_inconsistent', ($res['error']['code'] ?? '') === 'adjunto_inconsistent');
ac_assert('inconsistente cero Storage', $backend->calls === []);
ac_assert('inconsistente cero metadata delete', ExpedienteAdjuntosRepository::$delete_calls === 0);

// Storage fail keeps metadata
$backend = aa_reset();
$backend->response = ['ok' => false, 'code' => 'storage_delete_failed'];
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
$res = $uc->execute($base);
ac_assert('storage fail → storage_delete_failed', ($res['error']['code'] ?? '') === 'storage_delete_failed');
ac_assert('storage fail sin metadata delete', ExpedienteAdjuntosRepository::$delete_calls === 0);

// local_delete_failed
$backend = aa_reset();
ExpedienteAdjuntosRepository::$delete_result = new WP_Error('db', 'fail');
$uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
$res = $uc->execute($base);
ac_assert('local_delete_failed', ($res['error']['code'] ?? '') === 'local_delete_failed');
ac_assert('local_delete tras Storage', count($backend->calls) === 1);

foreach (['01', '0', '-1', '1.0', '1e2', '', ['7'], (object) ['id' => 7]] as $badId) {
    $backend = aa_reset();
    $uc = new DeleteExpedienteAdjuntoForExpedienteUseCase($backend, $lock);
    $res = $uc->execute(array_merge($base, ['expediente_id' => $badId]));
    ac_assert('ID inválido → invalid_id', ($res['error']['code'] ?? '') === 'invalid_id');
    ac_assert('ID inválido sin Storage', $backend->calls === []);
}

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
