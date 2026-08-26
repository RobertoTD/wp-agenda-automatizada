<?php
/**
 * AC — Ciclo A: coordinación named lock en upload de adjunto.
 *
 * Ejecutar:
 *   php tests/application/expediente/test-upload-expediente-registro-adjunto-aggregate-lock-ac.php
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

require_once $plugin_root . '/tests/support/aa-test-expediente-aggregate-lock-passthrough.php';

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
                'recorded_at' => '2026-07-30 10:00:00',
                'created_at' => '2026-07-30 10:00:00',
                'updated_at' => null,
            ];
        }
        return null;
    }
}

final class ExpedienteAdjuntosRepository {
    public static $inserts = [];
    public static $sum_bytes = 0;

    public static function sum_byte_size_total(): ?int {
        return self::$sum_bytes;
    }

    public static function insert_finalized(array $row) {
        self::$inserts[] = $row;
        return array_merge($row, [
            'id' => 99,
            'created_at' => '2026-07-30 19:00:00',
        ]);
    }
}

final class ExpedienteAdjuntoJpegValidator {
    public function validate(array $file): array {
        return [
            'ok' => true,
            'tmp_name' => (string) ($file['tmp_name'] ?? ''),
            'mime_type' => 'image/jpeg',
            'byte_size' => 713,
            'width' => 40,
            'height' => 30,
        ];
    }
}

final class FakeTransfer {
    public $calls = 0;
    /** @var callable|null */
    public $during_transfer = null;

    public function transfer(array $input): array {
        $this->calls++;
        if (is_callable($this->during_transfer)) {
            ($this->during_transfer)();
        }
        $op = (string) $input['upload_operation_id'];
        $client = (int) $input['wp_client_id'];
        $record = (int) $input['wp_record_id'];
        $path = "installations/11111111-1111-4111-8111-111111111111/clients/{$client}/records/{$record}/{$op}.jpg";
        return [
            'ok' => true,
            'storage_path' => $path,
            'finalize' => [
                'installation_id' => '11111111-1111-4111-8111-111111111111',
                'upload_operation_id' => $op,
                'storage_path' => $path,
                'mime_type' => 'image/jpeg',
                'byte_size' => 713,
                'width' => 40,
                'height' => 30,
                'wp_client_id' => $client,
                'wp_record_id' => $record,
            ],
        ];
    }
}

final class FakeCleanupClient {
    public $calls = [];
    /** @var array|null */
    public $response = null;

    public function delete_object(string $storage_path): array {
        $this->calls[] = $storage_path;
        return is_array($this->response)
            ? $this->response
            : ['ok' => true, 'result' => ['status' => 'deleted']];
    }
}

class AA_Test_Flip_Assert_Expediente_Lock extends AA_Test_Passthrough_Expediente_Aggregate_Lock {
    public $assert_count = 0;
    public $fail_from = 2;

    public function assert_held($lease) {
        $this->assert_count++;
        if ($this->assert_count >= $this->fail_from) {
            return new WP_Error('coordination_lost', 'lost');
        }
        return true;
    }
}

$base_lock = aa_test_install_passthrough_expediente_lock();
require_once $plugin_root . '/includes/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php';

$src = file_get_contents($plugin_root . '/includes/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php');
$acq = strpos($src, 'lock->acquire');
$xfer = strpos($src, 'transfer->transfer');
$assert_before_insert = strrpos($src, 'assert_held');
$insert = strrpos($src, 'insert_finalized');
ac_assert('lock antes de transfer', $acq !== false && $xfer !== false && $acq < $xfer);
ac_assert('assert_held antes de insert', $assert_before_insert !== false && $insert !== false && $assert_before_insert < $insert);
ac_assert('release en finally', strpos($src, 'lock->release') !== false && strpos($src, 'finally') !== false);
ac_assert('sin $this->backend', strpos($src, '$this->backend') === false);
ac_assert('compensa con delete_object', strpos($src, 'compensate_storage') !== false);

$op = '22222222-2222-4222-8222-222222222222';
$tmp = tempnam(sys_get_temp_dir(), 'aaup');
file_put_contents($tmp, 'jpeg');

$input = [
    'client_id' => 7,
    'record_id' => 11,
    'upload_operation_id' => $op,
    'file' => [
        'tmp_name' => $tmp,
        'name' => 'a.jpg',
        'type' => 'image/jpeg',
        'size' => 713,
        'error' => 0,
    ],
];

$base_lock->acquire_calls = [];
$base_lock->release_calls = 0;
$base_lock->next_acquire = null;
ExpedienteAdjuntosRepository::$inserts = [];
$transfer = new FakeTransfer();
$held_during_transfer = false;
$transfer->during_transfer = static function () use ($base_lock, &$held_during_transfer) {
    $held_during_transfer = count($base_lock->acquire_calls) === 1 && $base_lock->release_calls === 0;
};
$uc = new UploadExpedienteRegistroAdjuntoUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $base_lock);
$out = $uc->execute($input);
ac_assert('happy ok', !empty($out['ok']));
ac_assert('scope client', ($base_lock->acquire_calls[0]['scope_kind'] ?? '') === 'client');
ac_assert('scope id client_id', (int) ($base_lock->acquire_calls[0]['scope_id'] ?? 0) === 7);
ac_assert('una adquisición', count($base_lock->acquire_calls) === 1);
ac_assert('release success', $base_lock->release_calls === 1);
ac_assert('lock mantenido durante transfer', $held_during_transfer);
ac_assert('insert una vez', count(ExpedienteAdjuntosRepository::$inserts) === 1);

$base_lock->acquire_calls = [];
$base_lock->release_calls = 0;
$base_lock->next_acquire = new WP_Error('resource_busy', 'busy');
ExpedienteAdjuntosRepository::$inserts = [];
$transfer = new FakeTransfer();
$uc = new UploadExpedienteRegistroAdjuntoUseCase(new ExpedienteAdjuntoJpegValidator(), $transfer, $base_lock);
$out = $uc->execute($input);
ac_assert('busy code', ($out['code'] ?? '') === 'resource_busy');
ac_assert('busy cero transfer', $transfer->calls === 0);
ac_assert('busy cero insert', count(ExpedienteAdjuntosRepository::$inserts) === 0);
ac_assert('busy sin release de lease', $base_lock->release_calls === 0);

$base_lock->next_acquire = null;
$flip = new AA_Test_Flip_Assert_Expediente_Lock();
$cleanup = new FakeCleanupClient();
$cleanup->response = ['ok' => true, 'result' => ['status' => 'deleted']];
ExpedienteAdjuntosRepository::$inserts = [];
$uc = new UploadExpedienteRegistroAdjuntoUseCase(
    new ExpedienteAdjuntoJpegValidator(),
    new FakeTransfer(),
    $flip,
    $cleanup
);
$out = $uc->execute($input);
ac_assert('lost → coordination_lost', ($out['code'] ?? '') === 'coordination_lost');
ac_assert('lost cero insert', count(ExpedienteAdjuntosRepository::$inserts) === 0);
ac_assert('compensa deleted', count($cleanup->calls) === 1);
ac_assert('compensa path original', strpos((string) ($cleanup->calls[0] ?? ''), $op) !== false);
ac_assert('release tras lost', $flip->release_calls === 1);

$flip = new AA_Test_Flip_Assert_Expediente_Lock();
$cleanup = new FakeCleanupClient();
$cleanup->response = ['ok' => true, 'result' => ['status' => 'already_absent']];
ExpedienteAdjuntosRepository::$inserts = [];
$uc = new UploadExpedienteRegistroAdjuntoUseCase(
    new ExpedienteAdjuntoJpegValidator(),
    new FakeTransfer(),
    $flip,
    $cleanup
);
$out = $uc->execute($input);
ac_assert('already_absent → coordination_lost', ($out['code'] ?? '') === 'coordination_lost');
ac_assert('already_absent cero insert', count(ExpedienteAdjuntosRepository::$inserts) === 0);
ac_assert('already_absent compensó', count($cleanup->calls) === 1);

$flip = new AA_Test_Flip_Assert_Expediente_Lock();
$cleanup = new FakeCleanupClient();
$cleanup->response = ['ok' => false, 'code' => 'delete_failed'];
ExpedienteAdjuntosRepository::$inserts = [];
$uc = new UploadExpedienteRegistroAdjuntoUseCase(
    new ExpedienteAdjuntoJpegValidator(),
    new FakeTransfer(),
    $flip,
    $cleanup
);
$out = $uc->execute($input);
ac_assert('cleanup fail → storage_cleanup_failed', ($out['code'] ?? '') === 'storage_cleanup_failed');
ac_assert('cleanup fail cero insert', count(ExpedienteAdjuntosRepository::$inserts) === 0);
ac_assert('mensaje sin path', strpos((string) ($out['message'] ?? ''), 'installations/') === false);

@unlink($tmp);

echo "\nResultado: {$passed}/{$total}" . (count($failed) ? (' FAIL: ' . implode(', ', $failed)) : ' OK') . "\n";
exit(count($failed) === 0 ? 0 : 1);
