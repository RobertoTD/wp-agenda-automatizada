<?php
/**
 * AC Test — AA_Canonical_Shell_Url_Policy.
 *
 * Ejecutar: php tests/infrastructure/wp/test-aa-canonical-shell-url-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args): string {
        if (count($args) === 2 && is_array($args[0])) {
            $query = http_build_query($args[0]);
            $url = $args[1];
        } elseif (count($args) === 3) {
            $query = http_build_query([$args[0] => $args[1]]);
            $url = $args[2];
        } else {
            return '';
        }
        $sep = strpos($url, '?') === false ? '?' : '&';
        return $url . $sep . $query;
    }
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url) {
        return parse_url($url);
    }
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-url-policy.php';

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

// 1. Build URL with family and variant
$url1 = AA_Canonical_Shell_Url_Policy::build_url('finance', 'general');
ac_assert('Build URL with family and variant', strpos($url1, 'action=aa_iframe_content') !== false
    && strpos($url1, 'module=canonical') !== false
    && strpos($url1, 'family=finance') !== false
    && strpos($url1, 'variant=general') !== false);

// 2. Build URL with family only (no variant)
$url2 = AA_Canonical_Shell_Url_Policy::build_url('finance');
ac_assert('Build URL without variant', strpos($url2, 'family=finance') !== false && strpos($url2, 'variant=') === false);

// 3. Allowlist validation of canonical URLs
ac_assert('Valid full canonical URL is allowlisted', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($url1) === true);
ac_assert('Valid family-only canonical URL is allowlisted', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($url2) === true);

// 4. Rejection of disallowed query params (e.g. view, client_id, nonces, arbitrary queries)
$bad_url_view = $url1 . '&view=detail';
ac_assert('Canonical URL with view is rejected', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($bad_url_view) === false);

$bad_url_client = $url1 . '&client_id=5';
ac_assert('Canonical URL with client_id is rejected', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($bad_url_client) === false);

$bad_url_nonce = $url1 . '&_wpnonce=123456';
ac_assert('Canonical URL with _wpnonce is rejected', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($bad_url_nonce) === false);

$bad_url_gate = $url1 . '&aa_gate=1';
ac_assert('Canonical URL with aa_gate is rejected', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($bad_url_gate) === false);

// 5. Rejection of invalid family / variant keys
$bad_url_key = 'https://example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical&family=Invalid-Key';
ac_assert('Canonical URL with invalid family key is rejected', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($bad_url_key) === false);

$bad_url_var = 'https://example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical&family=finance&variant=Invalid.Var';
ac_assert('Canonical URL with invalid variant key is rejected', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($bad_url_var) === false);

// 6. Rejection of external host or wrong path
$bad_url_host = 'https://malicious.com/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical&family=finance';
ac_assert('External host is rejected', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($bad_url_host) === false);

$bad_url_path = 'https://example.com/other-path.php?action=aa_iframe_content&module=canonical&family=finance';
ac_assert('Wrong path is rejected', AA_Canonical_Shell_Url_Policy::is_allowlisted_canonical_url($bad_url_path) === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
