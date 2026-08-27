<?php
/**
 * AC — DeleteExpedienteRegistroForExpedienteUseCase (P2).
 *
 * Ejecutar: php tests/application/expediente/test-delete-expediente-registro-for-expediente-use-case-ac.php
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
$op = '660e8400-e29b-41d4-a716-446655440000';

final class ExpedientesRepository {
    /** @var array<int,bool|null> */
    public static $exists = [];
    /** @var array<int,mixed> */
    public static $owners = [];
    public static $exists_calls = 0;
    public static $owner_calls = 0;

    public static function reset(): void {
        self::$exists = [];
        self::$owners = [];
        self::$exists_calls = 0;
        self::$owner_calls = 0;
    }

    public static function exists_by_id(int $id) {
        self::$exists_calls++;
        if (!array_key_exists($id, self::$exists)) {
            return false;
        }
        return self::$exists[$id];
    }

    public static function find_owner_context_by_id(int $id): ?array {
        self::$owner_calls++;
        if (!array_key_exists($id, self::$exists) || self::$exists[$id] !== true) {
            return null;
        }
        return [
            'id' => $id,
            'client_id' => array_key_exists($id, self::$owners) ? self::$owners[$id] : null,
        ];
    }
}

final class ExpedienteRegistrosRepository {
    public static $find = [];
    public static $find_calls = 0;
    public static $delete_calls = 0;
    /** @var list<array{record_id:int,expediente_id:int}> */
    public static $deletes = [];
    /** @var bool|null */
    public static $delete_result = true;

    public static function reset(): void {
        self::$find = [];
        self::$find_calls = 0;
        self::$delete_calls = 0;
        self::$deletes = [];
        self::$delete_result = true;
    }

    public static function find_by_id_for_expediente(int $record_id, int $expediente_id) {
        self::$find_calls++;
        $key = $record_id . ':' . $expediente_id;
        if (!array_key_exists($key, self::$find)) {
            return false;
        }
        return self::$find[$key];
    }

    public static function delete_by_id_for_expediente(int $record_id, int $expediente_id): ?bool {
        self::$delete_calls++;
        self::$deletes[] = compact('record_id', 'expediente_id');
        return self::$delete_result;
    }
}

final class ExpedienteAdjuntosRepository {
    /** @var array<int,list<array<string,mixed>>>|null */
    public static $bulk = [];
    public static $bulk_calls = 0;
    public static $delete_calls = 0;
    /** @var bool|WP_Error|null */
    public static $delete_result = true;
    public static $has_calls = 0;
    /** @var bool|null */
    public static $has_result = false;

    public static function reset(): void {
        self::$bulk = [];
        self::$bulk_calls = 0;
        self::$delete_calls = 0;
        self::$delete_result = true;
        self::$has_calls = 0;
        self::$has_result = false;
    }

    public static function list_by_record_ids_for_records(array $record_ids): ?array {
        self::$bulk_calls++;
        return self::$bulk;
    }

    public static function delete_by_exact_identity(array $identity) {
        self::$delete_calls++;
        return self::$delete_result;
    }

    public static function has_any_by_record_id(int $record_id): ?bool {
        self::$has_calls++;
        return self::$has_result;
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
require_once $plugin_root . '/includes/application/expediente/DeleteExpedienteRegistroForExpedienteUseCase.php';

$src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/DeleteExpedienteRegistroForExpedienteUseCase.php'
);
ac_assert('NO delega DeleteExpedienteRegistroUseCase', strpos($src, 'DeleteExpedienteRegistroUseCase') === false);
ac_assert('usa list_by_record_ids_for_records', strpos($src, 'list_by_record_ids_for_records') !== false);
ac_assert('usa policy preflight', strpos($src, 'AA_Expediente_Adjunto_Identity_Policy::validate') !== false);
ac_assert('usa delete_by_exact_identity + has_any', strpos($src, 'delete_by_exact_identity') !== false
    && strpos($src, 'has_any_by_record_id') !== false);
ac_assert('usa delete_by_id_for_expediente', strpos($src, 'delete_by_id_for_expediente') !== false);
ac_assert('no $_POST', strpos($src, '$_POST') === false);

$backend = new FakeDeleteBackend();
$uc = new DeleteExpedienteRegistroForExpedienteUseCase($backend, $lock);

function seed_client_record(int $client_id = 42): void {
    ExpedientesRepository::reset();
    ExpedienteRegistrosRepository::reset();
    ExpedienteAdjuntosRepository::reset();
    ExpedientesRepository::$exists = [5 => true];
    ExpedientesRepository::$owners = [5 => $client_id];
    ExpedienteRegistrosRepository::$find['14:5'] = [
        'id' => 14,
        'expediente_id' => 5,
        'client_id' => $client_id,
        'title' => 'T',
        'body' => 'B',
        'recorded_at' => 'x',
        'created_at' => 'x',
        'updated_at' => null,
    ];
}

function seed_general_record(): void {
    ExpedientesRepository::reset();
    ExpedienteRegistrosRepository::reset();
    ExpedienteAdjuntosRepository::reset();
    ExpedientesRepository::$exists = [9 => true];
    ExpedientesRepository::$owners = [9 => null];
    ExpedienteRegistrosRepository::$find['21:9'] = [
        'id' => 21,
        'expediente_id' => 9,
        'client_id' => null,
        'title' => 'G',
        'body' => 'B',
        'recorded_at' => 'x',
        'created_at' => 'x',
        'updated_at' => null,
    ];
}

function aa_v1_adjunto(int $client_id, int $record_id): array {
    global $iid, $op;
    return [
        'id' => 88,
        'record_id' => $record_id,
        'client_id' => $client_id,
        'upload_operation_id' => $op,
        'storage_path' => ExpedienteAdjuntoVariants::build_client_original_path($iid, $client_id, $record_id, $op),
        'mime_type' => 'image/jpeg',
        'byte_size' => 900,
        'width' => 100,
        'height' => 80,
        'created_at' => '2026-08-01 00:00:00',
    ];
}

function aa_v2_adjunto(int $expediente_id, int $record_id): array {
    global $iid, $op;
    return [
        'id' => 99,
        'record_id' => $record_id,
        'client_id' => null,
        'upload_operation_id' => $op,
        'storage_path' => ExpedienteAdjuntoVariants::build_expediente_record_original_path(
            $iid,
            $expediente_id,
            $record_id,
            $op
        ),
        'mime_type' => 'image/jpeg',
        'byte_size' => 900,
        'width' => 100,
        'height' => 80,
        'created_at' => '2026-08-01 00:00:00',
    ];
}

// IDs inválidos
ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedienteAdjuntosRepository::reset();
$bad = $uc->execute(['expediente_id' => '01', 'record_id' => 14]);
ac_assert('ID inválido → invalid_id', ($bad['error']['code'] ?? '') === 'invalid_id');
ac_assert('ID inválido sin exists', ExpedientesRepository::$exists_calls === 0);
ac_assert('ID inválido sin Storage', $backend->calls === []);

// Expediente inexistente
ExpedientesRepository::reset();
ExpedientesRepository::$exists = [5 => false];
$nf = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('expediente inexistente', ($nf['error']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin Storage', $backend->calls === []);

ExpedientesRepository::reset();
ExpedientesRepository::$exists = [5 => null];
$lf = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('exists null → lookup_failed', ($lf['error']['code'] ?? '') === 'lookup_failed');

// Registro inexistente
seed_client_record();
ExpedienteRegistrosRepository::$find['14:5'] = false;
$nr = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('registro inexistente', ($nr['error']['code'] ?? '') === 'not_found');

// Owner malformado ''
seed_client_record();
ExpedientesRepository::$owners = [5 => ''];
$emptyOwner = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('padre client_id=\'\' → not_found', ($emptyOwner['error']['code'] ?? '') === 'not_found');
ac_assert('malformado sin delete SQL', ExpedienteRegistrosRepository::$delete_calls === 0);

// Mismatch
seed_client_record(42);
ExpedienteRegistrosRepository::$find['14:5']['client_id'] = 99;
$mm = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('owner mismatch → not_found', ($mm['error']['code'] ?? '') === 'not_found');

// Relacionado v1 OK
seed_client_record(42);
ExpedienteAdjuntosRepository::$bulk = [14 => [aa_v1_adjunto(42, 14)]];
ExpedienteAdjuntosRepository::$has_result = false;
$backend->calls = [];
$ok = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'client_id' => 999]);
ac_assert('relacionado v1 OK', !empty($ok['success']));
ac_assert('relacionado Storage 1×', count($backend->calls) === 1);
ac_assert('relacionado metadata delete 1×', ExpedienteAdjuntosRepository::$delete_calls === 1);
ac_assert('relacionado has_any 1×', ExpedienteAdjuntosRepository::$has_calls === 1);
ac_assert(
    'relacionado delete registro',
    ExpedienteRegistrosRepository::$delete_calls === 1
    && (ExpedienteRegistrosRepository::$deletes[0]['expediente_id'] ?? 0) === 5
);
ac_assert(
    'DTO mínimo',
    ($ok['data']['deleted'] ?? false) === true
    && ($ok['data']['record_id'] ?? 0) === 14
    && !array_key_exists('client_id', $ok['data'] ?? [])
);

// Strings wpdb
seed_client_record(42);
ExpedienteRegistrosRepository::$find['14:5']['client_id'] = '42';
ExpedienteRegistrosRepository::$find['14:5']['expediente_id'] = '5';
ExpedienteAdjuntosRepository::$bulk = [];
ExpedienteAdjuntosRepository::$has_result = false;
$backend->calls = [];
$sok = $uc->execute(['expediente_id' => '5', 'record_id' => '14']);
ac_assert('strings MySQL OK', !empty($sok['success']));

// General sin adjuntos
seed_general_record();
ExpedienteAdjuntosRepository::$bulk = [21 => []];
ExpedienteAdjuntosRepository::$has_result = false;
$backend->calls = [];
$gok = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('general sin adjuntos OK', !empty($gok['success']));
ac_assert('general sin Storage', $backend->calls === []);
ac_assert('general delete canónico', ExpedienteRegistrosRepository::$delete_calls === 1);

// General con v2 OK
seed_general_record();
ExpedienteAdjuntosRepository::$bulk = [21 => [aa_v2_adjunto(9, 21)]];
ExpedienteAdjuntosRepository::$has_result = false;
$backend->calls = [];
$gv2 = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('general v2 OK', !empty($gv2['success']));
ac_assert('general v2 Storage', count($backend->calls) === 1);
ac_assert('general v2 metadata delete', ExpedienteAdjuntosRepository::$delete_calls === 1);

// Preflight inconsistente: v1 path en general
seed_general_record();
$bad_adj = aa_v1_adjunto(55, 21);
ExpedienteAdjuntosRepository::$bulk = [21 => [$bad_adj]];
$backend->calls = [];
$gin = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('general v1 path → adjunto_inconsistent', ($gin['error']['code'] ?? '') === 'adjunto_inconsistent');
ac_assert('preflight inconsistente cero Storage', $backend->calls === []);
ac_assert('preflight inconsistente sin delete', ExpedienteRegistrosRepository::$delete_calls === 0);
ac_assert('preflight inconsistente sin metadata delete', ExpedienteAdjuntosRepository::$delete_calls === 0);

// bulk SQL fail
seed_general_record();
ExpedienteAdjuntosRepository::$bulk = null;
$glf = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('bulk null → lookup_failed', ($glf['error']['code'] ?? '') === 'lookup_failed');
ac_assert('bulk fail sin delete', ExpedienteRegistrosRepository::$delete_calls === 0);

// Storage error
seed_client_record(42);
ExpedienteAdjuntosRepository::$bulk = [14 => [aa_v1_adjunto(42, 14)]];
$backend->calls = [];
$backend->response = ['ok' => false, 'code' => 'storage_delete_partial'];
$st = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('Storage code preservado', ($st['error']['code'] ?? '') === 'storage_delete_partial');
ac_assert(
    'Storage sin paths en mensaje',
    strpos((string) ($st['error']['message'] ?? ''), 'storage_path') === false
    && strpos((string) ($st['error']['message'] ?? ''), '/clients/') === false
);
ac_assert('Storage fail sin delete registro', ExpedienteRegistrosRepository::$delete_calls === 0);
$backend->response = ['ok' => true, 'result' => ['status' => 'deleted']];

// has_any SQL fail
seed_general_record();
ExpedienteAdjuntosRepository::$bulk = [21 => []];
ExpedienteAdjuntosRepository::$has_result = null;
$gsql = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('has_any null → lookup_failed', ($gsql['error']['code'] ?? '') === 'lookup_failed');

// 0 filas delete
seed_general_record();
ExpedienteAdjuntosRepository::$bulk = [21 => []];
ExpedienteAdjuntosRepository::$has_result = false;
ExpedienteRegistrosRepository::$delete_result = false;
$g0 = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('0 filas → not_found', ($g0['error']['code'] ?? '') === 'not_found');

// SQL error delete
seed_general_record();
ExpedienteAdjuntosRepository::$bulk = [21 => []];
ExpedienteAdjuntosRepository::$has_result = false;
ExpedienteRegistrosRepository::$delete_result = null;
$gsql2 = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('SQL error → local_delete_failed', ($gsql2['error']['code'] ?? '') === 'local_delete_failed');

// Segundo delete (registro ya ausente)
seed_client_record();
ExpedienteRegistrosRepository::$find['14:5'] = false;
$second = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('segundo delete → not_found', ($second['error']['code'] ?? '') === 'not_found');

echo "\nResultado: {$passed}/{$total}\n";
if ($failed) {
    echo 'Fallos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
