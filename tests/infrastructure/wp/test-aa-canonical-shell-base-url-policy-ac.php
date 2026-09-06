<?php
/**
 * AC Test — AA_Canonical_Shell_Base_Url_Policy.
 *
 * Ejecutar: php tests/infrastructure/wp/test-aa-canonical-shell-base-url-policy-ac.php
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
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';

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

$url = AA_Canonical_Shell_Base_Url_Policy::build_url('finance');
ac_assert('Build shell URL with finance family only', strpos($url, 'action=aa_iframe_content') !== false
    && strpos($url, 'module=canonical_shell') !== false
    && strpos($url, 'family=finance') !== false
    && strpos($url, 'variant=') === false);

$module_only = AA_Canonical_Shell_Base_Url_Policy::build_module_url();
ac_assert('Build module-only URL without family', strpos($module_only, 'module=canonical_shell') !== false
    && strpos($module_only, 'family=') === false);

ac_assert('Shell URL is allowlisted', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($url) === true);
ac_assert('Module-only URL is allowlisted', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($module_only) === true);

$legacy = AA_Canonical_Shell_Url_Policy::build_url('finance', 'general');
ac_assert('Legacy finance URL still module=canonical', strpos($legacy, 'module=canonical') !== false
    && strpos($legacy, 'canonical_shell') === false);
ac_assert('Legacy finance URL rejected by shell allowlist', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($legacy) === false);

$bad = $url . '&view=detail';
ac_assert('Unknown view=detail rejected', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($bad) === false);

$records = AA_Canonical_Shell_Base_Url_Policy::build_records_url('finance', 5, 2, 3);
ac_assert('Records builder includes transport', strpos($records, 'view=records') !== false
    && strpos($records, 'container_id=5') !== false
    && strpos($records, 'page=2') !== false
    && strpos($records, 'containers_page=3') !== false);
ac_assert('Records URL allowlisted', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($records) === true);

$records_omit = AA_Canonical_Shell_Base_Url_Policy::build_records_url('finance', 5, 1, 1);
ac_assert('Records builder omits page/containers_page when 1', strpos($records_omit, 'page=') === false
    && strpos($records_omit, 'containers_page=') === false);

$records_all = AA_Canonical_Shell_Base_Url_Policy::build_records_url('finance', 5, null, 2, 'all');
ac_assert('Records builder keeps lists_scope=all', strpos($records_all, 'lists_scope=all') !== false
    && strpos($records_all, 'containers_page=2') !== false
    && strpos($records_all, 'family=finance') !== false);
ac_assert('Records with lists_scope allowlisted', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($records_all) === true);

$return_all = AA_Canonical_Shell_Base_Url_Policy::build_containers_return_url('all', 'finance', 2);
ac_assert('Containers return all-scope uses module URL', strpos($return_all, 'module=canonical_shell') !== false
    && strpos($return_all, 'family=') === false
    && strpos($return_all, 'page=2') !== false);

$return_family = AA_Canonical_Shell_Base_Url_Policy::build_containers_return_url(null, 'archive', null);
ac_assert('Containers return family-scope uses family URL', strpos($return_family, 'family=archive') !== false);

ac_assert('lists_scope parser accepts all', AA_Canonical_Shell_Base_Url_Policy::parse_present_lists_scope('all') === 'all');
ac_assert('lists_scope parser rejects other', AA_Canonical_Shell_Base_Url_Policy::parse_present_lists_scope('family') === null);

$preview_records = AA_Canonical_Shell_Base_Url_Policy::build_preview_records_url(9, null, 2);
ac_assert('Preview records builder', strpos($preview_records, 'shell_mode=preview') !== false
    && strpos($preview_records, 'view=records') !== false
    && strpos($preview_records, 'container_id=9') !== false
    && strpos($preview_records, 'containers_page=2') !== false
    && strpos($preview_records, 'family=') === false);
ac_assert('Preview records allowlisted', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($preview_records) === true);

ac_assert('Positive id parser rejects zero', AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id('0') === null);
ac_assert('Positive id parser accepts 3', AA_Canonical_Shell_Base_Url_Policy::parse_present_positive_id('3') === 3);

$threw = false;
try {
    AA_Canonical_Shell_Base_Url_Policy::build_url('Bad Key');
} catch (InvalidArgumentException $e) {
    $threw = true;
}
ac_assert('Invalid family key rejected at build', $threw === true);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
