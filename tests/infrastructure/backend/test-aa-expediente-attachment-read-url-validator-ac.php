<?php
/**
 * AC — AA_Expediente_Attachment_Read_Url_Validator (MC4c / 6A).
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

require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';

$original = 'installations/11111111-2222-4333-8444-555555555555/clients/3/records/10/550e8400-e29b-41d4-a716-446655440000.jpg';
$v = new AA_Expediente_Attachment_Read_Url_Validator();

function aa_signed_url(string $object_path): string {
    return 'https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . $object_path . '?token=eyJhbGciOiJIUzI1NiJ9.payload.sig';
}

$ref = new ReflectionMethod('AA_Expediente_Attachment_Read_Url_Validator', 'validate');
ac_assert('validate exige tres parámetros', $ref->getNumberOfParameters() === 3);

foreach (['summary', 'gallery', 'display'] as $variant) {
    $derived = ExpedienteAdjuntoVariants::derive_path($original, $variant);
    $url = aa_signed_url($derived);
    $ok = $v->validate($url, $original, $variant);
    ac_assert($variant . ' válida', !empty($ok['ok']) && $ok['url'] === $url, json_encode($ok));
    ac_assert($variant . ' path derivado exacto', $derived === $original
        ? false
        : substr($derived, -strlen('_' . $variant . '.jpg')) === '_' . $variant . '.jpg');
}

$original_url = aa_signed_url($original);
ac_assert(
    'URL original rechazada',
    empty($v->validate($original_url, $original, 'summary')['ok'])
);

$summary_url = aa_signed_url(ExpedienteAdjuntoVariants::derive_path($original, 'summary'));
ac_assert(
    'cruce summary como gallery rechazado',
    empty($v->validate($summary_url, $original, 'gallery')['ok'])
);
ac_assert(
    'cruce summary como display rechazado',
    empty($v->validate($summary_url, $original, 'display')['ok'])
);

$cases = [
    ['http://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc', 'http rechazado'],
    ['https://evil.example.com/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc', 'host ajeno rechazado'],
    ['https://proj.supabase.co.evil.com/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc', 'host parecido rechazado'],
    ['https://proj.supabase.co:8443/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc', 'puerto distinto rechazado'],
    ['https://user:pass@proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc', 'userinfo rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc#frag', 'fragment rechazado'],
    ['https://proj.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc', 'path de upload rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/otro-bucket/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc', 'bucket ajeno rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/otra/ruta.jpg?token=abc', 'path discordante rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary'), 'sin query rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc&extra=1', 'query extra rechazada'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?download=1', 'query sin token rechazada'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=', 'token vacío rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/%2e%2e/' . ExpedienteAdjuntoVariants::derive_path($original, 'summary') . '?token=abc', 'traversal codificado rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/installations/../x.jpg?token=abc', 'traversal literal rechazado'],
    ['https://proj.supabase.co/storage/v1/object/sign/expediente-adjuntos/%252e%252e/x.jpg?token=abc', 'doble encoding rechazado'],
];

foreach ($cases as $case) {
    $res = $v->validate($case[0], $original, 'summary');
    ac_assert($case[1], empty($res['ok']), (string) ($res['code'] ?? ''));
}

$src = file_get_contents($plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php');
ac_assert('prefijo de lectura /object/sign/', strpos($src, "'/storage/v1/object/sign/'") !== false);
ac_assert('usa derive_path', strpos($src, 'ExpedienteAdjuntoVariants::derive_path') !== false);
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
