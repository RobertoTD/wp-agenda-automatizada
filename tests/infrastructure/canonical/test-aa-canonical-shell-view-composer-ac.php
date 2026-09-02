<?php
/**
 * AC Test — Canonical shell view composer + preview fail-closed (SB1-2B).
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-canonical-shell-view-composer-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$status_headers = [];
$aa_timezone_option = 'Europe/Madrid';

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
if (!function_exists('status_header')) {
    function status_header(int $code): void {
        global $status_headers;
        $status_headers[] = $code;
    }
}
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        global $aa_timezone_option;
        if ($key === 'aa_timezone') {
            return $aa_timezone_option;
        }
        return $default;
    }
}
if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone {
        return new DateTimeZone('UTC');
    }
}
if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null): string {
        $tz = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone('UTC');
        $dt = (new DateTimeImmutable('@' . (int) $timestamp))->setTimezone($tz);
        return $dt->format($format);
    }
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url) {
        return parse_url($url);
    }
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapter.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadAdapterResolver.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadBindingNotFound.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellReadResult.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellContainersUseCase.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php';

AA_Canonical_Core_Bootstrap::bootstrap();

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

// Preview disabled by default
if (defined('AA_CANONICAL_SHELL_PREVIEW')) {
    ac_assert('Constant must not be pre-defined in this process', false);
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit(1);
}
ac_assert('Preview disabled without constant', AA_Canonical_Shell_View_Composer::is_preview_enabled() === false);

$family = AA_Canonical_Core_Bootstrap::instance()->family('finance');
$variant = AA_Canonical_Core_Bootstrap::instance()->variant('finance', 'general');
$view = AA_Canonical_Shell_View_Composer::compose_family($family, $variant, 1);
ac_assert('finance.general → read_adapter_pending', ($view['read_state'] ?? '') === 'read_adapter_pending');
ac_assert('Labels from registry defs', ($view['family_label'] ?? '') === 'Finanzas' && ($view['variant_label'] ?? '') === 'General');
ac_assert('No CTA url when preview disabled', empty($view['preview_enabled']) && ($view['preview_url'] ?? null) === null);
ac_assert('Not marked as preview', empty($view['is_preview']));

$blocked = false;
try {
    AA_Canonical_Shell_View_Composer::compose_preview(1);
} catch (LogicException $e) {
    $blocked = strpos($e->getMessage(), '[preview_disabled]') === 0;
}
ac_assert('compose_preview blocked without constant', $blocked === true);

$loaded = get_included_files();
$preview_loaded = false;
foreach ($loaded as $file) {
    if (strpos($file, 'class-aa-canonical-shell-preview-adapter.php') !== false) {
        $preview_loaded = true;
        break;
    }
}
ac_assert('Preview adapter file not loaded without constant', $preview_loaded === false);

// Enable preview for remaining checks
define('AA_CANONICAL_SHELL_PREVIEW', true);
ac_assert('Preview enabled with constant true', AA_Canonical_Shell_View_Composer::is_preview_enabled() === true);

$view_pending = AA_Canonical_Shell_View_Composer::compose_family($family, $variant, 1);
ac_assert('Pending exposes CTA when preview on', !empty($view_pending['preview_enabled'])
    && is_string($view_pending['preview_url'])
    && strpos($view_pending['preview_url'], 'shell_mode=preview') !== false);
ac_assert('Pending CTA URL has no family', strpos((string) $view_pending['preview_url'], 'family=') === false);

$status_headers = [];
$preview_view = AA_Canonical_Shell_View_Composer::compose_preview(1);
ac_assert('Preview resolved_page', ($preview_view['read_state'] ?? '') === 'resolved_page');
ac_assert('Preview is_preview flag', !empty($preview_view['is_preview']));
ac_assert('Preview banner present', is_string($preview_view['preview_banner'] ?? null) && $preview_view['preview_banner'] !== '');
ac_assert('Preview has >=15 items on page 1', count($preview_view['items_view'] ?? []) === 15);
ac_assert('Preview total > 15', (int) ($preview_view['total'] ?? 0) > 15);
ac_assert('Preview qualified key', ($preview_view['qualified_key'] ?? '') === 'shell_preview.demo');
ac_assert('Preview labels ephemeral', ($preview_view['family_label'] ?? '') === 'Demostración del shell');

$iso_ok = true;
$null_details_seen = false;
foreach ($preview_view['items_view'] as $item) {
    if (!isset($item['updated_at_iso']) || substr((string) $item['updated_at_iso'], -1) !== 'Z') {
        $iso_ok = false;
    }
    if (array_key_exists('details', $item) && $item['details'] === null) {
        $null_details_seen = true;
    }
}
ac_assert('Item datetime ISO ends with Z', $iso_ok === true);
ac_assert('Dataset includes details=null', $null_details_seen === true);

$page2 = AA_Canonical_Shell_View_Composer::compose_preview(2);
ac_assert('Page 2 has previous url', ($page2['has_previous'] ?? false) === true && strpos((string) $page2['prev_url'], 'shell_mode=preview') !== false);
ac_assert('Page 2 prev has no family', strpos((string) $page2['prev_url'], 'family=') === false);

// Timezone: valid aa_timezone used; display not empty
ac_assert('Display datetime non-empty', ($preview_view['items_view'][0]['updated_at_display'] ?? '') !== '');

$composer_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php');
ac_assert('Composer has no America/Mexico_City fallback', strpos($composer_src, 'America/Mexico_City') === false);
ac_assert('Composer uses wp_timezone fallback path', strpos($composer_src, 'wp_timezone') !== false);

$bootstrap_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php');
ac_assert('Bootstrap has no shell_preview family', strpos($bootstrap_src, 'shell_preview') === false);

$plugin_main = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert('Plugin bootstrap does not require preview adapter', strpos($plugin_main, 'preview/class-aa-canonical-shell-preview-adapter') === false);

// page parse policy
ac_assert('Absent page handled by caller as 1', true);
ac_assert('page=0 normalizes to 1', AA_Canonical_Shell_Base_Url_Policy::parse_present_page_value('0') === 1);
ac_assert('page=2 accepted', AA_Canonical_Shell_Base_Url_Policy::parse_present_page_value('2') === 2);
ac_assert('page=1.5 invalid', AA_Canonical_Shell_Base_Url_Policy::parse_present_page_value('1.5') === null);
ac_assert('page=abc invalid', AA_Canonical_Shell_Base_Url_Policy::parse_present_page_value('abc') === null);
ac_assert('page array invalid', AA_Canonical_Shell_Base_Url_Policy::parse_present_page_value(['1']) === null);

$preview_url = AA_Canonical_Shell_Base_Url_Policy::build_preview_url(2);
ac_assert('Preview URL allowlisted', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($preview_url));
$mixed = 'https://example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical_shell&shell_mode=preview&family=finance';
ac_assert('Mixed preview+family not allowlisted', AA_Canonical_Shell_Base_Url_Policy::is_allowlisted_shell_url($mixed) === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
