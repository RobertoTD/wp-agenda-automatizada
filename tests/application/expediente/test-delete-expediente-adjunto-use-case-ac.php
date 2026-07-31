<?php
/**
 * AC — DeleteExpedienteAdjuntoUseCase (MC5c1).
 *
 * Ejecutar: php tests/application/expediente/test-delete-expediente-adjunto-use-case-ac.php
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

$STORAGE_PATH = 'installations/11111111-2222-4333-8444-555555555555/clients/7/records/11/550e8400-e29b-41d4-a716-446655440000.jpg';

final class ClientsRepository {
    public static function find_by_id(int $client_id): ?array {
        return $client_id === 7 ? ['id' => 7, 'nombre' => 'A', 'telefono' => '', 'correo' => ''] : null;
    }
}

final class ExpedienteRegistrosRepository {
    public static function find_by_id_for_client(int $record_id, int $client_id): ?array {
        if ($record_id === 11 && $client_id === 7) {
            return [
                'id' => 11,
                'client_id' => 7,
                'title' => 'T',
                'body' => 'B',
                'recorded_at' => '2026-07-31 08:00:00',
                'created_at' => '2026-07-31 08:00:00',
                'updated_at' => null,
            ];
        }
        return null;
    }
}

final class ExpedienteAdjuntosRepository {
    public static $by_id = [];
    public static $rows_by_record = [];
    public static $deleted = [];
    public static $delete_should_fail = false;

    public static function find_by_id_for_client(int $attachment_id, int $client_id): ?array {
        $row = self::$by_id[$attachment_id] ?? null;
        if (is_array($row) && (int) $row['client_id'] === $client_id) {
            return $row;
        }
        return null;
    }

    public static function list_by_record_for_client(int $record_id, int $client_id): array {
        $rows = self::$rows_by_record[$record_id] ?? [];
        return array_values(array_filter($rows, static function ($row) use ($client_id) {
            return (int) $row['client_id'] === $client_id;
        }));
    }

    public static function delete_by_id_for_client(int $attachment_id, int $client_id): bool {
        if (self::$delete_should_fail) {
            return false;
        }
        $row = self::find_by_id_for_client($attachment_id, $client_id);
        if ($row === null) {
            return false;
        }
        self::$deleted[] = $attachment_id;
        unset(self::$by_id[$attachment_id]);
        $rid = (int) $row['record_id'];
        self::$rows_by_record[$rid] = array_values(array_filter(
            self::$rows_by_record[$rid] ?? [],
            static function ($r) use ($attachment_id) {
                return (int) $r['id'] !== $attachment_id;
            }
        ));
        return true;
    }
}

final class FakeDeleteBackend {
    public $calls = [];
    public $response;

    public function delete_object(string $storage_path): array {
        $this->calls[] = $storage_path;
        return $this->response;
    }
}

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoPublicDto.php';
require_once $plugin_root . '/includes/application/expediente/DeleteExpedienteAdjuntoUseCase.php';

$adjunto_row = [
    'id' => 42,
    'record_id' => 11,
    'client_id' => 7,
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440000',
    'storage_path' => $STORAGE_PATH,
    'mime_type' => 'image/jpeg',
    'byte_size' => 88000,
    'width' => 1024,
    'height' => 768,
    'created_at' => '2026-07-31 08:05:00',
];
$other = array_merge($adjunto_row, [
    'id' => 41,
    'upload_operation_id' => '550e8400-e29b-41d4-a716-446655440001',
    'storage_path' => str_replace('550e8400-e29b-41d4-a716-446655440000', '550e8400-e29b-41d4-a716-446655440001', $STORAGE_PATH),
    'created_at' => '2026-07-31 08:00:00',
]);

ExpedienteAdjuntosRepository::$by_id = [42 => $adjunto_row, 41 => $other];
ExpedienteAdjuntosRepository::$rows_by_record = [11 => [$adjunto_row, $other]];
ExpedienteAdjuntosRepository::$deleted = [];
ExpedienteAdjuntosRepository::$delete_should_fail = false;

$backend = new FakeDeleteBackend();
$backend->response = ['ok' => true, 'result' => ['status' => 'deleted']];
$uc = new DeleteExpedienteAdjuntoUseCase($backend);

$res = $uc->execute(['client_id' => 7, 'record_id' => 11, 'attachment_id' => 42]);
ac_assert('happy path ok', !empty($res['ok']), json_encode($res));
ac_assert('devuelve deleted_attachment_id', (int) ($res['deleted_attachment_id'] ?? 0) === 42);
ac_assert('devuelve record_id', (int) ($res['record_id'] ?? 0) === 11);
ac_assert('adjuntos restantes id DESC vía list', is_array($res['adjuntos'] ?? null) && count($res['adjuntos']) === 1
    && (int) $res['adjuntos'][0]['id'] === 41);
ac_assert('alias adjunto = restantes[0]', ($res['adjunto']['id'] ?? 0) === 41);
ac_assert('DTO público sin storage_path', !isset($res['adjuntos'][0]['storage_path'])
    && !array_key_exists('storage_path', $res));
ac_assert('backend recibió path local', $backend->calls === [$STORAGE_PATH]);
ac_assert('fila local borrada', ExpedienteAdjuntosRepository::$deleted === [42]);

// already_absent también permite borrar fila
ExpedienteAdjuntosRepository::$by_id = [42 => $adjunto_row];
ExpedienteAdjuntosRepository::$rows_by_record = [11 => [$adjunto_row]];
ExpedienteAdjuntosRepository::$deleted = [];
$backend2 = new FakeDeleteBackend();
$backend2->response = ['ok' => true, 'result' => ['status' => 'already_absent']];
$uc2 = new DeleteExpedienteAdjuntoUseCase($backend2);
$res2 = $uc2->execute(['client_id' => 7, 'record_id' => 11, 'attachment_id' => 42]);
ac_assert('already_absent → ok y fila borrada', !empty($res2['ok']) && ExpedienteAdjuntosRepository::$deleted === [42]);
ac_assert('already_absent → adjuntos vacíos', is_array($res2['adjuntos'] ?? null)
    && count($res2['adjuntos']) === 0
    && array_key_exists('adjunto', $res2)
    && $res2['adjunto'] === null);

// Storage falla → fila conservada
ExpedienteAdjuntosRepository::$by_id = [42 => $adjunto_row];
ExpedienteAdjuntosRepository::$rows_by_record = [11 => [$adjunto_row]];
ExpedienteAdjuntosRepository::$deleted = [];
$backend3 = new FakeDeleteBackend();
$backend3->response = ['ok' => false, 'code' => 'delete_failed', 'error' => '', 'http_status' => 502];
$uc3 = new DeleteExpedienteAdjuntoUseCase($backend3);
$res3 = $uc3->execute(['client_id' => 7, 'record_id' => 11, 'attachment_id' => 42]);
ac_assert('Storage falla → error', empty($res3['ok']) && ($res3['code'] ?? '') === 'delete_failed');
ac_assert('Storage falla → fila conservada', ExpedienteAdjuntosRepository::$deleted === []
    && isset(ExpedienteAdjuntosRepository::$by_id[42]));

// Storage OK, local falla → error; reintento puede completar
ExpedienteAdjuntosRepository::$by_id = [42 => $adjunto_row];
ExpedienteAdjuntosRepository::$rows_by_record = [11 => [$adjunto_row]];
ExpedienteAdjuntosRepository::$deleted = [];
ExpedienteAdjuntosRepository::$delete_should_fail = true;
$backend4 = new FakeDeleteBackend();
$backend4->response = ['ok' => true, 'result' => ['status' => 'deleted']];
$uc4 = new DeleteExpedienteAdjuntoUseCase($backend4);
$res4 = $uc4->execute(['client_id' => 7, 'record_id' => 11, 'attachment_id' => 42]);
ac_assert('fallo local → local_delete_failed', empty($res4['ok']) && ($res4['code'] ?? '') === 'local_delete_failed');
ac_assert('fallo local → fila aún referenciada', isset(ExpedienteAdjuntosRepository::$by_id[42]));

ExpedienteAdjuntosRepository::$delete_should_fail = false;
$backend4->response = ['ok' => true, 'result' => ['status' => 'already_absent']];
$res4b = $uc4->execute(['client_id' => 7, 'record_id' => 11, 'attachment_id' => 42]);
ac_assert('reintento already_absent completa', !empty($res4b['ok']) && ExpedienteAdjuntosRepository::$deleted === [42]);

// Ownership
ExpedienteAdjuntosRepository::$by_id = [42 => $adjunto_row];
ExpedienteAdjuntosRepository::$rows_by_record = [11 => [$adjunto_row]];
ExpedienteAdjuntosRepository::$deleted = [];
$backend5 = new FakeDeleteBackend();
$backend5->response = ['ok' => true, 'result' => ['status' => 'deleted']];
$uc5 = new DeleteExpedienteAdjuntoUseCase($backend5);

$res5 = $uc5->execute(['client_id' => 7, 'record_id' => 11, 'attachment_id' => 999]);
ac_assert('id inexistente → attachment_not_found', empty($res5['ok']) && ($res5['code'] ?? '') === 'attachment_not_found' && $backend5->calls === []);

ExpedienteAdjuntosRepository::$by_id[43] = array_merge($adjunto_row, ['id' => 43, 'record_id' => 99]);
$res6 = $uc5->execute(['client_id' => 7, 'record_id' => 11, 'attachment_id' => 43]);
ac_assert('otro registro → attachment_not_found', empty($res6['ok']) && ($res6['code'] ?? '') === 'attachment_not_found');

ExpedienteAdjuntosRepository::$by_id[44] = array_merge($adjunto_row, ['id' => 44, 'client_id' => 8]);
$res7 = $uc5->execute(['client_id' => 7, 'record_id' => 11, 'attachment_id' => 44]);
ac_assert('otro cliente → attachment_not_found', empty($res7['ok']) && ($res7['code'] ?? '') === 'attachment_not_found');

$res8 = $uc5->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('sin attachment_id → invalid_context', empty($res8['ok']) && ($res8['code'] ?? '') === 'invalid_context');

$src = file_get_contents($plugin_root . '/includes/application/expediente/DeleteExpedienteAdjuntoUseCase.php');
ac_assert('use case no acepta storage_path del input', strpos($src, "\$input['storage_path']") === false
    && strpos($src, '$_POST') === false);

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
