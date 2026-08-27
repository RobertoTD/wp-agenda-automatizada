<?php
/**
 * AC — Legacy bridged → canónico v2 permanente (P3).
 *
 * Ejecutar: php tests/application/expediente/test-upload-expediente-registro-adjunto-bridge-v2-ac.php
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

final class ClientsRepository {
    public static function find_by_id(int $client_id): ?array {
        return $client_id === 55 ? ['id' => 55, 'nombre' => 'A', 'telefono' => '', 'correo' => ''] : null;
    }
}

final class ExpedientesRepository {
    public static function find_owner_context_by_id(int $id): ?array {
        return ['id' => $id, 'client_id' => 55];
    }
}

final class ExpedienteRegistrosRepository {
    public static $record = [
        'id' => 10,
        'client_id' => 55,
        'expediente_id' => 7,
        'title' => 'T',
        'body' => 'B',
        'recorded_at' => '2026-08-20 12:00:00',
        'created_at' => '2026-08-20 12:00:00',
        'updated_at' => null,
    ];

    public static function find_by_id_for_client(int $record_id, int $client_id): ?array {
        if ($record_id === 10 && $client_id === 55) {
            return self::$record;
        }
        return null;
    }
}

final class ExpedienteAdjuntosRepository {
    public static $by_op = null;

    public static function find_by_upload_operation_id(string $op): ?array {
        return self::$by_op;
    }
}

final class FakeCanonical {
    public $calls = [];
    public $response = [
        'success' => true,
        'data' => [
            'record_id' => 10,
            'adjunto' => [
                'id' => 88,
                'width' => 40,
                'height' => 30,
                'byte_size' => 713,
                'created_at' => '2026-08-26 12:00:00',
            ],
        ],
    ];

    public function execute(array $input): array {
        $this->calls[] = $input;
        return $this->response;
    }
}

final class FakeTransferNever {
    public $calls = 0;
    public function transfer(array $input): array {
        $this->calls++;
        return ['ok' => false, 'code' => 'should_not_run', 'message' => 'x'];
    }
}

require_once $plugin_root . '/tests/support/aa-test-expediente-aggregate-lock-passthrough.php';
$lock = aa_test_install_passthrough_expediente_lock();
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoJpegValidator.php';
require_once $plugin_root . '/includes/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php';

$legacy_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php'
);
ac_assert('legacy sin Enablement', strpos($legacy_src, 'Attachments_V2_Enablement') === false);
ac_assert('legacy sin constante V2', strpos($legacy_src, 'AA_EXPEDIENTE_ATTACHMENTS_V2_ENABLED') === false);

$op = '550e8400-e29b-41d4-a716-446655440000';
$input = [
    'client_id' => 55,
    'record_id' => 10,
    'upload_operation_id' => $op,
    'file' => [
        'tmp_name' => '/tmp/x',
        'name' => 'a.jpg',
        'type' => 'image/jpeg',
        'size' => 10,
        'error' => 0,
    ],
];

$canonical = new FakeCanonical();
ExpedienteAdjuntosRepository::$by_op = [
    'id' => 88,
    'record_id' => 10,
    'client_id' => 55,
    'upload_operation_id' => $op,
    'storage_path' => 'installations/x/expedientes/7/records/10/' . $op . '.jpg',
    'mime_type' => 'image/jpeg',
    'byte_size' => 713,
    'width' => 40,
    'height' => 30,
    'created_at' => '2026-08-26 12:00:00',
];
$transfer = new FakeTransferNever();
$uc = new UploadExpedienteRegistroAdjuntoUseCase(
    new ExpedienteAdjuntoJpegValidator(),
    $transfer,
    $lock,
    null,
    $canonical
);
$out = $uc->execute($input);
ac_assert('bridged → ok v2', !empty($out['ok']));
ac_assert('delegó canónico 1×', count($canonical->calls) === 1);
ac_assert('canónico recibe expediente_id', (int) ($canonical->calls[0]['expediente_id'] ?? 0) === 7);
ac_assert('sin transfer local', $transfer->calls === 0);
ac_assert('sin locks locales', $lock->acquire_calls === []);

// Orphan siempre v1
ExpedienteRegistrosRepository::$record['expediente_id'] = null;
$canonical = new FakeCanonical();
$uc = new UploadExpedienteRegistroAdjuntoUseCase(
    new ExpedienteAdjuntoJpegValidator(static function () {
        return false;
    }),
    new FakeTransferNever(),
    $lock,
    null,
    $canonical
);
$out = $uc->execute($input);
ac_assert('orphan no delega', $canonical->calls === []);
ac_assert('orphan entra validate (fallo jpeg esperado)', ($out['code'] ?? '') !== 'should_not_run');

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
