<?php
/**
 * AC — GetExpedienteAdjuntoReadUrlUseCase (MC4c).
 *
 * Ejecutar: php tests/application/expediente/test-get-expediente-adjunto-read-url-use-case-ac.php
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
    public static $latest = [];
    public static $calls = [];

    public static function find_latest_by_record_ids(array $record_ids, int $client_id): array {
        self::$calls[] = ['record_ids' => $record_ids, 'client_id' => $client_id];
        return self::$latest;
    }
}

final class FakeSignReadBackend {
    public $calls = [];
    public $response;

    public function sign_read(string $storage_path): array {
        $this->calls[] = $storage_path;
        return $this->response;
    }
}

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoPublicDto.php';
require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';
require_once $plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlUseCase.php';

$src = file_get_contents($plugin_root . '/includes/application/expediente/GetExpedienteAdjuntoReadUrlUseCase.php');
ac_assert('use case existe', is_string($src) && $src !== '');
ac_assert('selección server-side find_latest_by_record_ids', strpos($src, 'find_latest_by_record_ids') !== false);
ac_assert('valida URL antes de responder', strpos($src, 'url_validator->validate') !== false);
ac_assert('no registra la URL', !preg_match('/error_log/', $src));

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

$good_url = 'https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $STORAGE_PATH . '?token=eyJx.y.z';

// Happy path
ExpedienteAdjuntosRepository::$latest = [11 => $adjunto_row];
$backend = new FakeSignReadBackend();
$backend->response = ['ok' => true, 'result' => ['url' => $good_url, 'expires_in' => 600]];
$uc = new GetExpedienteAdjuntoReadUrlUseCase($backend);
$res = $uc->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('happy path ok', !empty($res['ok']), json_encode($res));
ac_assert('devuelve url + expires_in', ($res['url'] ?? '') === $good_url && (int) ($res['expires_in'] ?? 0) === 600);
ac_assert('DTO público exacto', isset($res['adjunto'])
    && $res['adjunto'] === ['id' => 42, 'width' => 1024, 'height' => 768, 'byte_size' => 88000, 'created_at' => '2026-07-31 08:05:00']);
ac_assert('sign_read con storage_path local', $backend->calls === [$STORAGE_PATH]);
ac_assert('sin claves internas en éxito', !isset($res['adjunto']['storage_path'])
    && !isset($res['adjunto']['upload_operation_id']) && !isset($res['adjunto']['mime_type'])
    && !array_key_exists('storage_path', $res));

// Registro ajeno
$backend2 = new FakeSignReadBackend();
$backend2->response = ['ok' => true, 'result' => ['url' => $good_url, 'expires_in' => 600]];
$uc2 = new GetExpedienteAdjuntoReadUrlUseCase($backend2);
$res2 = $uc2->execute(['client_id' => 7, 'record_id' => 999]);
ac_assert('registro ajeno → record_not_found sin sign-read', empty($res2['ok'])
    && ($res2['code'] ?? '') === 'record_not_found' && $backend2->calls === []);

// Cliente inexistente
$res3 = $uc2->execute(['client_id' => 99, 'record_id' => 11]);
ac_assert('cliente inexistente → client_not_found', empty($res3['ok']) && ($res3['code'] ?? '') === 'client_not_found');

// Sin adjunto
ExpedienteAdjuntosRepository::$latest = [];
$res4 = $uc2->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('sin adjunto → no_attachment', empty($res4['ok']) && ($res4['code'] ?? '') === 'no_attachment' && $backend2->calls === []);

// Adjunto local inconsistente (path de otro cliente/registro)
ExpedienteAdjuntosRepository::$latest = [11 => array_merge($adjunto_row, [
    'storage_path' => 'installations/11111111-2222-4333-8444-555555555555/clients/8/records/99/550e8400-e29b-41d4-a716-446655440000.jpg',
])];
$res5 = $uc2->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('adjunto inconsistente → adjunto_inconsistent sin sign-read', empty($res5['ok'])
    && ($res5['code'] ?? '') === 'adjunto_inconsistent' && $backend2->calls === []);

// Backend caído
ExpedienteAdjuntosRepository::$latest = [11 => $adjunto_row];
$backend3 = new FakeSignReadBackend();
$backend3->response = ['ok' => false, 'code' => 'expediente_attachments_unreachable', 'error' => '', 'http_status' => 0];
$uc3 = new GetExpedienteAdjuntoReadUrlUseCase($backend3);
$res6 = $uc3->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('backend caído → código estable', empty($res6['ok']) && ($res6['code'] ?? '') === 'expediente_attachments_unreachable');

// URL maliciosa devuelta → rechazo cerrado
$backend4 = new FakeSignReadBackend();
$backend4->response = ['ok' => true, 'result' => [
    'url' => 'https://evil.example.com/storage/v1/object/sign/expediente-adjuntos/' . $STORAGE_PATH . '?token=abc',
    'expires_in' => 600,
]];
$uc4 = new GetExpedienteAdjuntoReadUrlUseCase($backend4);
$res7 = $uc4->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('URL host ajeno → signed_url_invalid', empty($res7['ok']) && ($res7['code'] ?? '') === 'signed_url_invalid');
ac_assert('URL maliciosa no expuesta en error', strpos(json_encode($res7), 'evil.example.com') === false);

// URL con path de upload (no lectura) → rechazo
$backend5 = new FakeSignReadBackend();
$backend5->response = ['ok' => true, 'result' => [
    'url' => 'https://proj.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/' . $STORAGE_PATH . '?token=abc',
    'expires_in' => 600,
]];
$uc5 = new GetExpedienteAdjuntoReadUrlUseCase($backend5);
$res8 = $uc5->execute(['client_id' => 7, 'record_id' => 11]);
ac_assert('URL de upload → signed_url_invalid', empty($res8['ok']) && ($res8['code'] ?? '') === 'signed_url_invalid');

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
