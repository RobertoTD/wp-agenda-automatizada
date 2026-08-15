<?php
/**
 * Proceso aislado: ejecuta el bloque de constantes del bootstrap real
 * (hasta el update checker) y comprueba AA_EXPEDIENTE_STORAGE_ORIGIN.
 *
 * Uso: php aa-expediente-storage-origin-probe.php default|override|invalid
 */

$mode = isset($argv[1]) ? (string) $argv[1] : 'default';
$plugin_root = dirname(__DIR__, 3);
$main = $plugin_root . '/wp-agenda-automatizada.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

if ($mode === 'override') {
    define('AA_EXPEDIENTE_STORAGE_ORIGIN', 'https://override-valid.example.co');
} elseif ($mode === 'invalid') {
    define('AA_EXPEDIENTE_STORAGE_ORIGIN', 'http://insecure.example');
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return rtrim(str_replace('\\', '/', dirname((string) $file)), '/') . '/';
    }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'https://example.test/wp-content/plugins/wp-agenda-automatizada/';
    }
}
if (!function_exists('get_file_data')) {
    function get_file_data($file, $headers) {
        return ['Version' => '3.2.0'];
    }
}
if (!function_exists('get_site_url')) {
    function get_site_url() {
        return 'https://agenda.example.test';
    }
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        if ($component === -1) {
            return parse_url($url);
        }
        return parse_url($url, $component);
    }
}

$src = file_get_contents($main);
if (!is_string($src) || $src === '') {
    fwrite(STDERR, "bootstrap unreadable\n");
    exit(2);
}

$cut = strpos($src, 'LIBRERÍA PLUGIN UPDATE CHECKER');
if ($cut === false) {
    fwrite(STDERR, "bootstrap constants cutoff missing\n");
    exit(2);
}

eval('?>' . substr($src, 0, $cut));

$payload = [
    'defined' => defined('AA_EXPEDIENTE_STORAGE_ORIGIN'),
    'value' => defined('AA_EXPEDIENTE_STORAGE_ORIGIN')
        ? (string) AA_EXPEDIENTE_STORAGE_ORIGIN
        : '',
    'uploader_loaded' => false,
    'validator_loaded' => false,
    'upload_ok' => false,
    'read_ok' => false,
    'upload_code' => '',
    'read_code' => '',
];

if ($mode === 'default' || $mode === 'invalid') {
    require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
    require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-signed-uploader.php';
    require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';

    $payload['uploader_loaded'] = class_exists('AA_Expediente_Attachment_Signed_Uploader', false);
    $payload['validator_loaded'] = class_exists('AA_Expediente_Attachment_Read_Url_Validator', false);

    $origin = (string) AA_EXPEDIENTE_STORAGE_ORIGIN;
    $path = 'installations/11111111-2222-4333-8444-555555555555/clients/3/records/10/550e8400-e29b-41d4-a716-446655440000.jpg';
    $variant_path = ExpedienteAdjuntoVariants::derive_path($path, 'summary');
    $put_url = rtrim($origin, '/') . '/storage/v1/object/upload/sign/expediente-adjuntos/'
        . $path . '?token=probe-token';
    $read_url = rtrim($origin, '/') . '/storage/v1/object/sign/expediente-adjuntos/'
        . $variant_path . '?token=eyJhbGciOiJIUzI1NiJ9.payload.sig';

    $uploader = new AA_Expediente_Attachment_Signed_Uploader();
    $put = $uploader->validate_signed_upload_url($put_url, $path);
    $payload['upload_ok'] = !empty($put['ok']);
    $payload['upload_code'] = isset($put['code']) ? (string) $put['code'] : '';

    $validator = new AA_Expediente_Attachment_Read_Url_Validator();
    $read = $validator->validate($read_url, $path, 'summary');
    $payload['read_ok'] = !empty($read['ok']);
    $payload['read_code'] = isset($read['code']) ? (string) $read['code'] : '';
}

echo json_encode($payload, JSON_UNESCAPED_SLASHES);
exit(0);
