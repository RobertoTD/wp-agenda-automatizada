<?php
/**
 * AC — DeleteExpedienteRegistroUseCase (MC5c2).
 *
 * Ejecutar: php tests/application/expediente/test-delete-expediente-registro-use-case-ac.php
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

$PATH_A = 'installations/11111111-2222-4333-8444-555555555555/clients/7/records/11/aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee.jpg';
$PATH_B = 'installations/11111111-2222-4333-8444-555555555555/clients/7/records/11/bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee.jpg';

final class ClientsRepository {
    public static function find_by_id(int $client_id): ?array {
        return $client_id === 7 ? ['id' => 7, 'nombre' => 'A', 'telefono' => '', 'correo' => ''] : null;
    }
}

final class ExpedienteRegistrosRepository {
    public static $records = [];
    public static $delete_should_fail = false;
    public static $deleted = [];

    public static function find_by_id_for_client(int $record_id, int $client_id): ?array {
        $row = self::$records[$record_id] ?? null;
        if (is_array($row) && (int) $row['client_id'] === $client_id) {
            return $row;
        }
        return null;
    }

    public static function delete_by_id_for_client(int $record_id, int $client_id): bool {
        if (self::$delete_should_fail) {
            return false;
        }
        $row = self::find_by_id_for_client($record_id, $client_id);
        if ($row === null) {
            return false;
        }
        self::$deleted[] = $record_id;
        unset(self::$records[$record_id]);
        return true;
    }
}

final class ExpedienteAdjuntosRepository {
    public static $rows_by_record = [];
    public static $delete_should_fail = false;
    public static $delete_calls = 0;

    public static function list_by_record_for_client(int $record_id, int $client_id): array {
        $rows = self::$rows_by_record[$record_id] ?? [];
        return array_values(array_filter($rows, static function ($row) use ($client_id) {
            return (int) $row['client_id'] === $client_id;
        }));
    }

    public static function delete_by_record_for_client(int $record_id, int $client_id): bool {
        self::$delete_calls++;
        if (self::$delete_should_fail) {
            return false;
        }
        // Idempotente: éxito si no quedan filas (incluso si ya estaban vacías).
        $rows = self::$rows_by_record[$record_id] ?? [];
        self::$rows_by_record[$record_id] = array_values(array_filter($rows, static function ($row) use ($client_id) {
            return (int) $row['client_id'] !== $client_id;
        }));
        $remaining = count(self::list_by_record_for_client($record_id, $client_id));
        return $remaining === 0;
    }
}

final class FakeDeleteBackend {
    /** @var list<string> */
    public $calls = [];
    /** @var list<array>|callable|null */
    public $responses = null;

    public function delete_object(string $storage_path): array {
        $this->calls[] = $storage_path;
        if (is_callable($this->responses)) {
            return ($this->responses)($storage_path, count($this->calls));
        }
        if (is_array($this->responses) && isset($this->responses[count($this->calls) - 1])) {
            return $this->responses[count($this->calls) - 1];
        }
        return ['ok' => true, 'result' => ['status' => 'deleted']];
    }
}

require_once $plugin_root . '/includes/application/expediente/DeleteExpedienteRegistroUseCase.php';

$record = [
    'id' => 11,
    'client_id' => 7,
    'title' => 'T',
    'body' => 'B',
    'recorded_at' => '2026-07-31 08:00:00',
    'created_at' => '2026-07-31 08:00:00',
    'updated_at' => null,
];
$adj_a = [
    'id' => 42,
    'record_id' => 11,
    'client_id' => 7,
    'upload_operation_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    'storage_path' => $PATH_A,
    'mime_type' => 'image/jpeg',
    'byte_size' => 88000,
    'width' => 1024,
    'height' => 768,
    'created_at' => '2026-07-31 08:05:00',
];
$adj_b = [
    'id' => 41,
    'record_id' => 11,
    'client_id' => 7,
    'upload_operation_id' => 'bbbbbbbb-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    'storage_path' => $PATH_B,
    'mime_type' => 'image/jpeg',
    'byte_size' => 44000,
    'width' => 800,
    'height' => 600,
    'created_at' => '2026-07-31 08:00:00',
];

function reset_state(array $record, array $adjuntos = []): void {
    ExpedienteRegistrosRepository::$records = [(int) $record['id'] => $record];
    ExpedienteRegistrosRepository::$deleted = [];
    ExpedienteRegistrosRepository::$delete_should_fail = false;
    ExpedienteAdjuntosRepository::$rows_by_record = [(int) $record['id'] => $adjuntos];
    ExpedienteAdjuntosRepository::$delete_should_fail = false;
    ExpedienteAdjuntosRepository::$delete_calls = 0;
}

// --- Sin adjuntos ---
reset_state($record, []);
$backend = new FakeDeleteBackend();
$uc = new DeleteExpedienteRegistroUseCase($backend);
$res = $uc->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('sin adjuntos → ok', !empty($res['ok']) && !empty($res['deleted']));
ac_assert('sin adjuntos → record_id', (int) ($res['record_id'] ?? 0) === 11);
ac_assert('sin adjuntos → DTO cerrado', array_keys($res) === ['ok', 'deleted', 'record_id']
    || (isset($res['ok'], $res['deleted'], $res['record_id'])
        && !isset($res['storage_path']) && !isset($res['adjuntos']) && !isset($res['bucket'])));
ac_assert('sin adjuntos → sin llamadas Storage', $backend->calls === []);
ac_assert('sin adjuntos → registro borrado', ExpedienteRegistrosRepository::$deleted === [11]
    && ExpedienteRegistrosRepository::find_by_id_for_client(11, 7) === null);
ac_assert('sin adjuntos → delete_by_record idempotente llamado', ExpedienteAdjuntosRepository::$delete_calls === 1);

// --- Todos deleted/already_absent ---
reset_state($record, [$adj_a, $adj_b]);
$backend2 = new FakeDeleteBackend();
$backend2->responses = [
    ['ok' => true, 'result' => ['status' => 'deleted']],
    ['ok' => true, 'result' => ['status' => 'already_absent']],
];
$uc2 = new DeleteExpedienteRegistroUseCase($backend2);
$res2 = $uc2->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('deleted+already_absent → ok', !empty($res2['ok']) && ($res2['deleted'] ?? false) === true);
ac_assert('Storage secuencial ambos paths', $backend2->calls === [$PATH_A, $PATH_B]);
ac_assert('filas locales y registro eliminados',
    ExpedienteAdjuntosRepository::list_by_record_for_client(11, 7) === []
    && ExpedienteRegistrosRepository::find_by_id_for_client(11, 7) === null);

// --- Fallo remoto intermedio: conserva todo local ---
reset_state($record, [$adj_a, $adj_b]);
$backend3 = new FakeDeleteBackend();
$backend3->responses = [
    ['ok' => true, 'result' => ['status' => 'deleted']],
    ['ok' => false, 'code' => 'delete_failed', 'error' => 'x', 'http_status' => 502],
];
$uc3 = new DeleteExpedienteRegistroUseCase($backend3);
$res3 = $uc3->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('fallo Storage intermedio → error', empty($res3['ok'])
    && ($res3['code'] ?? '') === 'delete_failed');
ac_assert('fallo Storage → no borra filas locales',
    count(ExpedienteAdjuntosRepository::list_by_record_for_client(11, 7)) === 2
    && ExpedienteAdjuntosRepository::$delete_calls === 0);
ac_assert('fallo Storage → registro conservado',
    ExpedienteRegistrosRepository::find_by_id_for_client(11, 7) !== null
    && ExpedienteRegistrosRepository::$deleted === []);

// Ambiguo (status raro) → fail closed, sin local delete
reset_state($record, [$adj_a]);
$backendAmb = new FakeDeleteBackend();
$backendAmb->responses = [['ok' => true, 'result' => ['status' => 'pending']]];
$ucAmb = new DeleteExpedienteRegistroUseCase($backendAmb);
$resAmb = $ucAmb->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('status ambiguo → storage_delete_partial', empty($resAmb['ok'])
    && ($resAmb['code'] ?? '') === 'storage_delete_partial');
ac_assert('status ambiguo → filas locales intactas',
    count(ExpedienteAdjuntosRepository::list_by_record_for_client(11, 7)) === 1
    && ExpedienteAdjuntosRepository::$delete_calls === 0);

// --- Reintento después de objetos ya ausentes ---
reset_state($record, [$adj_a, $adj_b]);
$backend4 = new FakeDeleteBackend();
$backend4->responses = [
    ['ok' => true, 'result' => ['status' => 'already_absent']],
    ['ok' => true, 'result' => ['status' => 'already_absent']],
];
$uc4 = new DeleteExpedienteRegistroUseCase($backend4);
$res4 = $uc4->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('reintento already_absent completa', !empty($res4['ok'])
    && ExpedienteRegistrosRepository::$deleted === [11]);

// --- Fallo al eliminar filas de adjuntos ---
reset_state($record, [$adj_a]);
ExpedienteAdjuntosRepository::$delete_should_fail = true;
$backend5 = new FakeDeleteBackend();
$uc5 = new DeleteExpedienteRegistroUseCase($backend5);
$res5 = $uc5->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('fallo filas adjuntos → local_delete_failed', empty($res5['ok'])
    && ($res5['code'] ?? '') === 'local_delete_failed');
ac_assert('fallo filas adjuntos → Storage sí llamado', $backend5->calls === [$PATH_A]);
ac_assert('fallo filas adjuntos → registro conservado',
    ExpedienteRegistrosRepository::find_by_id_for_client(11, 7) !== null);

// --- Fallo al eliminar registro después de eliminar adjuntos + reintento con 0 filas ---
reset_state($record, [$adj_a, $adj_b]);
ExpedienteRegistrosRepository::$delete_should_fail = true;
$backend6 = new FakeDeleteBackend();
$uc6 = new DeleteExpedienteRegistroUseCase($backend6);
$res6 = $uc6->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('fallo registro tras adjuntos → local_delete_failed', empty($res6['ok'])
    && ($res6['code'] ?? '') === 'local_delete_failed');
ac_assert('fallo registro → adjuntos ya ausentes',
    ExpedienteAdjuntosRepository::list_by_record_for_client(11, 7) === []);
ac_assert('fallo registro → registro aún presente',
    ExpedienteRegistrosRepository::find_by_id_for_client(11, 7) !== null);

ExpedienteRegistrosRepository::$delete_should_fail = false;
$backend6b = new FakeDeleteBackend();
$uc6b = new DeleteExpedienteRegistroUseCase($backend6b);
$res6b = $uc6b->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('reintento con 0 adjuntos completa', !empty($res6b['ok']) && ($res6b['deleted'] ?? false) === true);
ac_assert('reintento 0 adjuntos → sin Storage', $backend6b->calls === []);
ac_assert('reintento 0 adjuntos → registro borrado',
    ExpedienteRegistrosRepository::find_by_id_for_client(11, 7) === null);

// --- Ownership / inexistente ---
reset_state($record, [$adj_a]);
$backend7 = new FakeDeleteBackend();
$uc7 = new DeleteExpedienteRegistroUseCase($backend7);
$res7 = $uc7->execute(['client_id' => 7, 'record_id' => 999]);
ac_assert('registro inexistente → record_not_found', empty($res7['ok'])
    && ($res7['code'] ?? '') === 'record_not_found' && $backend7->calls === []);

$res8 = $uc7->execute(['client_id' => 8, 'record_id' => 11]);
ac_assert('cliente ajeno/inexistente → client_not_found', empty($res8['ok'])
    && ($res8['code'] ?? '') === 'client_not_found');

ExpedienteRegistrosRepository::$records[11] = array_merge($record, ['client_id' => 9]);
$res9 = $uc7->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('registro de otro cliente → record_not_found', empty($res9['ok'])
    && ($res9['code'] ?? '') === 'record_not_found');

$res10 = $uc7->execute(['client_id' => 0, 'record_id' => 11]);
ac_assert('contexto inválido → invalid_context', empty($res10['ok'])
    && ($res10['code'] ?? '') === 'invalid_context');

// Path inconsistente
reset_state($record, [array_merge($adj_a, [
    'storage_path' => 'installations/x/clients/99/records/11/bad.jpg',
])]);
$backend8 = new FakeDeleteBackend();
$uc8 = new DeleteExpedienteRegistroUseCase($backend8);
$res11 = $uc8->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('path inconsistente → adjunto_inconsistent', empty($res11['ok'])
    && ($res11['code'] ?? '') === 'adjunto_inconsistent' && $backend8->calls === []);
ac_assert('path inconsistente → sin delete local',
    ExpedienteAdjuntosRepository::$delete_calls === 0
    && ExpedienteRegistrosRepository::find_by_id_for_client(11, 7) !== null);

$src = file_get_contents($plugin_root . '/includes/application/expediente/DeleteExpedienteRegistroUseCase.php');
ac_assert('use case no acepta storage_path del input', strpos($src, "\$input['storage_path']") === false
    && strpos($src, '$_POST') === false);
ac_assert('use case reutiliza delete_object', strpos($src, 'delete_object(') !== false);
ac_assert('orden Storage antes de filas locales',
    strpos($src, 'delete_object') < strpos($src, 'delete_by_record_for_client')
    && strpos($src, 'delete_by_record_for_client') < strpos($src, 'delete_by_id_for_client'));

// DTO éxito sin info interna
reset_state($record, []);
$ucDto = new DeleteExpedienteRegistroUseCase(new FakeDeleteBackend());
$resDto = $ucDto->execute(['client_id' => 7, 'record_id' => 11]);
$encoded = json_encode($resDto);
ac_assert('DTO éxito sin paths/bucket/credenciales',
    !empty($resDto['ok'])
    && strpos($encoded, 'storage_path') === false
    && strpos($encoded, 'bucket') === false
    && strpos($encoded, 'installations/') === false
    && strpos($encoded, 'credential') === false);

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
