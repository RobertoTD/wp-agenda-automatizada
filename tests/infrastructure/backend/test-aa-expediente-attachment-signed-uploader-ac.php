<?php
/**
 * AC — AA_Expediente_Attachment_Signed_Uploader (MC4a2).
 *
 * Ejecutar: php tests/infrastructure/backend/test-aa-expediente-attachment-signed-uploader-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

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

$GLOBALS['aa_test_safe_remote_calls'] = [];
$GLOBALS['aa_test_safe_remote_response'] = [
    'response' => ['code' => 200],
    'body' => '',
];

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        if ($component === -1) {
            return parse_url($url);
        }
        return parse_url($url, $component);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function get_error_message() {
            return 'err';
        }
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (!function_exists('wp_safe_remote_request')) {
    function wp_safe_remote_request($url, $args = []) {
        $GLOBALS['aa_test_safe_remote_calls'][] = [
            'url' => $url,
            'args' => $args,
        ];
        return $GLOBALS['aa_test_safe_remote_response'];
    }
}

require_once $plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-signed-uploader.php';

$uploader = new AA_Expediente_Attachment_Signed_Uploader();
$storage_path = 'installations/11111111-2222-4333-8444-555555555555/clients/3/records/10/550e8400-e29b-41d4-a716-446655440000.jpg';

// Sin origen
if (defined('AA_EXPEDIENTE_STORAGE_ORIGIN')) {
    // no-op for linters
}
// Definir origen de prueba
if (!defined('AA_EXPEDIENTE_STORAGE_ORIGIN')) {
    define('AA_EXPEDIENTE_STORAGE_ORIGIN', 'https://abcdefgh.supabase.co');
}

$ok_url = 'https://abcdefgh.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/'
    . $storage_path . '?token=secret-token-value';

$v = $uploader->validate_signed_upload_url($ok_url, $storage_path);
ac_assert('URL canónica válida', $v['ok'] === true);

$bad_host = $uploader->validate_signed_upload_url(
    'https://evil.example/storage/v1/object/upload/sign/expediente-adjuntos/' . $storage_path,
    $storage_path
);
ac_assert('host mismatch', $bad_host['ok'] === false && $bad_host['code'] === 'signed_url_host_mismatch');

$bad_path = $uploader->validate_signed_upload_url(
    'https://abcdefgh.supabase.co/storage/v1/object/upload/sign/other-bucket/' . $storage_path,
    $storage_path
);
ac_assert('bucket path inválido', $bad_path['ok'] === false);

$dotdot = $uploader->validate_signed_upload_url(
    'https://abcdefgh.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/../' . $storage_path,
    $storage_path
);
ac_assert('rechaza traversal', $dotdot['ok'] === false);

$encoded_slash = $uploader->validate_signed_upload_url(
    'https://abcdefgh.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/%2e%2e/' . rawurlencode($storage_path),
    $storage_path
);
ac_assert('rechaza %2e traversal', $encoded_slash['ok'] === false);

$userinfo = $uploader->validate_signed_upload_url(
    'https://user:pass@abcdefgh.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/' . $storage_path,
    $storage_path
);
ac_assert('rechaza userinfo', $userinfo['ok'] === false);

$fragment = $uploader->validate_signed_upload_url($ok_url . '#x', $storage_path);
ac_assert('rechaza fragment', $fragment['ok'] === false);

$http = $uploader->validate_signed_upload_url(
    'http://abcdefgh.supabase.co/storage/v1/object/upload/sign/expediente-adjuntos/' . $storage_path,
    $storage_path
);
ac_assert('exige https', $http['ok'] === false);

$GLOBALS['aa_test_safe_remote_calls'] = [];
$put = $uploader->put_jpeg($ok_url, str_repeat('a', 100), $storage_path);
ac_assert('PUT OK', $put['ok'] === true);
ac_assert('PUT method', ($GLOBALS['aa_test_safe_remote_calls'][0]['args']['method'] ?? '') === 'PUT');
ac_assert('PUT content-type', ($GLOBALS['aa_test_safe_remote_calls'][0]['args']['headers']['Content-Type'] ?? '') === 'image/jpeg');
ac_assert('PUT x-upsert false', ($GLOBALS['aa_test_safe_remote_calls'][0]['args']['headers']['x-upsert'] ?? '') === 'false');
ac_assert('PUT sin Cache-Control', !isset($GLOBALS['aa_test_safe_remote_calls'][0]['args']['headers']['Cache-Control']));
ac_assert('PUT redirection 0', (int) ($GLOBALS['aa_test_safe_remote_calls'][0]['args']['redirection'] ?? -1) === 0);
ac_assert('PUT reject_unsafe_urls', ($GLOBALS['aa_test_safe_remote_calls'][0]['args']['reject_unsafe_urls'] ?? false) === true);
ac_assert('PUT sslverify', ($GLOBALS['aa_test_safe_remote_calls'][0]['args']['sslverify'] ?? false) === true);

$too_big = $uploader->put_jpeg($ok_url, str_repeat('a', 1048577), $storage_path);
ac_assert('rechaza body > 1MiB', $too_big['ok'] === false && $too_big['code'] === 'invalid_body_size');
ac_assert('no HTTP si body grande', count($GLOBALS['aa_test_safe_remote_calls']) === 1);

$src = file_get_contents($plugin_root . '/includes/infrastructure/backend/class-aa-expediente-attachment-signed-uploader.php');
ac_assert('código usa wp_safe_remote_request', strpos($src, 'wp_safe_remote_request') !== false);
ac_assert('código no error_log signed_url', !preg_match('/error_log\([^\)]*signed/i', $src));

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
