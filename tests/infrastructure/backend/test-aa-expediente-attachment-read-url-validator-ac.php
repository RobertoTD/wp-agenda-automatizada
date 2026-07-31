<?php
/**
 * AC — AA_Expediente_Attachment_Read_Url_Validator (MC4c).
 *
 * Ejecutar: php tests/infrastructure/backend/test-aa-expediente-attachment-read-url-validator-ac.php
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

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';

$storage_path = 'installations/11111111-2222-4333-8444-555555555555/clients/3/records/10/550e8400-e29b-41d4-a716-446655440000.jpg';
$good_url = 'https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $storage_path . '?token=eyJhbGciOiJIUzI1NiJ9.payload.sig';

$v = new AA_Expediente_Attachment_Read_Url_Validator();

$ok = $v->validate($good_url, $storage_path);
ac_assert('URL de lectura válida pasa', !empty($ok['ok']) && $ok['url'] === $good_url, json_encode($ok));

$cases = [
    ['http://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $storage_path . '?token=abc', 'http rechazado'],
    ['https://evil.example.com/storage/v1/object/sign/expediente-adjuntos/' . $storage_path . '?token=abc', 'host ajeno rechazado'],
    ['https://proj.supabase.co:8443/storage/v1/object/sign/expediente-adjuntos/' . $storage_path . '?token=abc', 'puerto distinto rechazado'],
    ['https://user:pass@proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $storage_path . '?token=abc', 'userinfo rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $storage_path . '?token=abc#frag', 'fragment rechazado'],
    ['https://proj.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/' . $storage_path . '?token=abc', 'path de upload rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/otro-bucket/' . $storage_path . '?token=abc', 'bucket ajeno rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/otra/ruta.jpg?token=abc', 'path discordante rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $storage_path, 'sin query rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $storage_path . '?token=abc&extra=1', 'query extra rechazada'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $storage_path . '?download=1', 'query sin token rechazada'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $storage_path . '?token=', 'token vacío rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/%2e%2e/' . $storage_path . '?token=abc', 'traversal codificado rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/installations/../x.jpg?token=abc', 'traversal literal rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/%252e%252e/x.jpg?token=abc', 'doble encoding rechazado'],
];

foreach ($cases as $case) {
    $res = $v->validate($case[0], $storage_path);
    ac_assert($case[1], empty($res['ok']), (string) ($res['code'] ?? ''));
}

$bad_storage = $v->validate($good_url, '/leading/slash.jpg');
ac_assert('storage_path con slash inicial rechazado', empty($bad_storage['ok']));

$src = file_get_contents($plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php');
ac_assert('prefijo de lectura /object/sign/', strpos($src, "'/storage/v1/object/sign/'") !== false);
ac_assert('sin wp_remote (no sigue redirects: nunca hace GET)', strpos($src, 'wp_remote') === false);
ac_assert('no registra URLs', strpos($src, 'error_log') === false);

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
