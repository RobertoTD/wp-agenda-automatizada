<?php
/**
 * AC — DeleteExpedienteRegistroForExpedienteUseCase.
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

    public static function delete_by_id_for_client(...$args) {
        throw new RuntimeException('delete legacy repo no debe llamarse desde wrapper');
    }
}

final class ExpedienteAdjuntosRepository {
    public static $has_calls = 0;
    /** @var bool|null */
    public static $has_result = false;

    public static function reset(): void {
        self::$has_calls = 0;
        self::$has_result = false;
    }

    public static function has_any_by_record_id(int $record_id): ?bool {
        self::$has_calls++;
        return self::$has_result;
    }

    public static function list_by_record_for_client(...$args) {
        throw new RuntimeException('list adjuntos no debe llamarse en wrapper');
    }
}

final class FakeLegacyDelete {
    public static $calls = 0;
    /** @var array<string,mixed>|null */
    public static $last_input = null;
    /** @var array<string,mixed> */
    public static $result = ['ok' => true, 'deleted' => true, 'record_id' => 14];

    public static function reset(): void {
        self::$calls = 0;
        self::$last_input = null;
        self::$result = ['ok' => true, 'deleted' => true, 'record_id' => 14];
    }

    public function execute(array $input): array {
        self::$calls++;
        self::$last_input = $input;
        return self::$result;
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/application/expediente/DeleteExpedienteRegistroForExpedienteUseCase.php';

$src = (string) file_get_contents($plugin_root . '/includes/application/expediente/DeleteExpedienteRegistroForExpedienteUseCase.php');
ac_assert('delega DeleteExpedienteRegistroUseCase', strpos($src, 'DeleteExpedienteRegistroUseCase') !== false);
ac_assert('usa has_any_by_record_id', strpos($src, 'has_any_by_record_id') !== false);
ac_assert('usa delete_by_id_for_expediente', strpos($src, 'delete_by_id_for_expediente') !== false);
ac_assert('normaliza record_not_found', strpos($src, "record_not_found") !== false);
ac_assert('no $_POST', strpos($src, '$_POST') === false);

$legacy = new FakeLegacyDelete();
$uc = new DeleteExpedienteRegistroForExpedienteUseCase($legacy);

function seed_client_record($client_id = 42): void {
    ExpedientesRepository::reset();
    ExpedienteRegistrosRepository::reset();
    ExpedienteAdjuntosRepository::reset();
    FakeLegacyDelete::reset();
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
    FakeLegacyDelete::reset();
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

// IDs inválidos
ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
FakeLegacyDelete::reset();
$bad = $uc->execute(['expediente_id' => '01', 'record_id' => 14]);
ac_assert('ID inválido → invalid_id', ($bad['error']['code'] ?? '') === 'invalid_id');
ac_assert('ID inválido sin exists', ExpedientesRepository::$exists_calls === 0);
ac_assert('ID inválido sin legacy', FakeLegacyDelete::$calls === 0);

// Expediente inexistente
ExpedientesRepository::reset();
ExpedientesRepository::$exists = [5 => false];
$nf = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('expediente inexistente', ($nf['error']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin legacy', FakeLegacyDelete::$calls === 0);

ExpedientesRepository::reset();
ExpedientesRepository::$exists = [5 => null];
$lf = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('exists null → lookup_failed', ($lf['error']['code'] ?? '') === 'lookup_failed');

// Registro inexistente / otro expediente
seed_client_record();
ExpedienteRegistrosRepository::$find['14:5'] = false;
$nr = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('registro inexistente', ($nr['error']['code'] ?? '') === 'not_found');

// Owner malformado ''
seed_client_record();
ExpedientesRepository::$owners = [5 => ''];
$emptyOwner = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('padre client_id=\'\' → not_found', ($emptyOwner['error']['code'] ?? '') === 'not_found');
ac_assert('malformado sin legacy', FakeLegacyDelete::$calls === 0);
ac_assert('malformado sin delete SQL', ExpedienteRegistrosRepository::$delete_calls === 0);

// Mismatch
seed_client_record(42);
ExpedienteRegistrosRepository::$find['14:5']['client_id'] = 99;
$mm = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('owner mismatch → not_found', ($mm['error']['code'] ?? '') === 'not_found');
ac_assert('mismatch sin legacy', FakeLegacyDelete::$calls === 0);

// Cliente OK ints
seed_client_record(42);
$ok = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'client_id' => 999]);
ac_assert('cliente OK', !empty($ok['success']));
ac_assert('una delegación', FakeLegacyDelete::$calls === 1);
ac_assert(
    'owner derivado servidor',
    (FakeLegacyDelete::$last_input['client_id'] ?? 0) === 42
    && (FakeLegacyDelete::$last_input['record_id'] ?? 0) === 14
);
ac_assert('wrapper no lista adjuntos', ExpedienteAdjuntosRepository::$has_calls === 0);
ac_assert(
    'DTO mínimo',
    ($ok['data']['deleted'] ?? false) === true
    && ($ok['data']['record_id'] ?? 0) === 14
    && !array_key_exists('client_id', $ok['data'] ?? [])
);

// Strings wpdb
seed_client_record('42');
ExpedienteRegistrosRepository::$find['14:5']['client_id'] = '42';
ExpedienteRegistrosRepository::$find['14:5']['expediente_id'] = '5';
$sok = $uc->execute(['expediente_id' => '5', 'record_id' => '14']);
ac_assert('strings MySQL OK', !empty($sok['success']));
ac_assert('legacy client_id int', (FakeLegacyDelete::$last_input['client_id'] ?? 0) === 42);

// record_not_found → not_found
seed_client_record();
FakeLegacyDelete::$result = ['ok' => false, 'code' => 'record_not_found', 'message' => 'Registro no encontrado.'];
$mapped = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('record_not_found → not_found', ($mapped['error']['code'] ?? '') === 'not_found');

// Storage error preservado
seed_client_record();
FakeLegacyDelete::$result = [
    'ok' => false,
    'code' => 'storage_delete_partial',
    'message' => 'No se pudo eliminar el registro.',
];
$st = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('Storage code preservado', ($st['error']['code'] ?? '') === 'storage_delete_partial');
ac_assert(
    'Storage sin paths en mensaje',
    strpos((string) ($st['error']['message'] ?? ''), 'storage_path') === false
    && strpos((string) ($st['error']['message'] ?? ''), '/clients/') === false
);

// General sin adjuntos
seed_general_record();
ExpedienteAdjuntosRepository::$has_result = false;
$gok = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('general OK', !empty($gok['success']));
ac_assert('general no delega legacy', FakeLegacyDelete::$calls === 0);
ac_assert('general has_any una vez', ExpedienteAdjuntosRepository::$has_calls === 1);
ac_assert(
    'general delete canónico',
    ExpedienteRegistrosRepository::$delete_calls === 1
    && (ExpedienteRegistrosRepository::$deletes[0]['expediente_id'] ?? 0) === 9
);

// General con adjuntos
seed_general_record();
ExpedienteAdjuntosRepository::$has_result = true;
$gin = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('general + adjuntos → adjunto_inconsistent', ($gin['error']['code'] ?? '') === 'adjunto_inconsistent');
ac_assert('general inconsistente sin delete', ExpedienteRegistrosRepository::$delete_calls === 0);
ac_assert('general inconsistente sin legacy', FakeLegacyDelete::$calls === 0);

// has_any SQL fail
seed_general_record();
ExpedienteAdjuntosRepository::$has_result = null;
$glf = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('has_any null → lookup_failed', ($glf['error']['code'] ?? '') === 'lookup_failed');
ac_assert('has_any fail sin delete', ExpedienteRegistrosRepository::$delete_calls === 0);

// 0 filas delete
seed_general_record();
ExpedienteAdjuntosRepository::$has_result = false;
ExpedienteRegistrosRepository::$delete_result = false;
$g0 = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('0 filas → not_found', ($g0['error']['code'] ?? '') === 'not_found');

// SQL error delete
seed_general_record();
ExpedienteAdjuntosRepository::$has_result = false;
ExpedienteRegistrosRepository::$delete_result = null;
$gsql = $uc->execute(['expediente_id' => 9, 'record_id' => 21]);
ac_assert('SQL error → local_delete_failed', ($gsql['error']['code'] ?? '') === 'local_delete_failed');

// Segundo delete (registro ya ausente en find)
seed_client_record();
ExpedienteRegistrosRepository::$find['14:5'] = false;
$second = $uc->execute(['expediente_id' => 5, 'record_id' => 14]);
ac_assert('segundo delete → not_found', ($second['error']['code'] ?? '') === 'not_found');
ac_assert('segundo sin legacy', FakeLegacyDelete::$calls === 0);

echo "\nResultado: {$passed}/{$total}\n";
if ($failed) {
    echo 'Fallos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
