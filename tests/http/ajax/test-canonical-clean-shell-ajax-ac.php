<?php
/** Execute: php tests/http/ajax/test-canonical-clean-shell-ajax-ac.php */
$root = dirname(__DIR__, 3);
$src = (string) file_get_contents($root . '/includes/http/ajax/CanonicalCleanShellAjax.php');
$boot = (string) file_get_contents($root . '/wp-agenda-automatizada.php');
$post = $root . '/includes/http/admin/CanonicalFreeShellPost.php';
function clean_ajax_assert(string $label, bool $ok): void { if (!$ok) { fwrite(STDERR, "[FAIL] {$label}\n"); exit(1); } echo "[ OK ] {$label}\n"; }
clean_ajax_assert('Registers only authenticated AJAX actions', strpos($src, "add_action('wp_ajax_aa_canonical_clean_") !== false && strpos($src, 'nopriv') === false);
clean_ajax_assert('Requires administrator capability and nonce', strpos($src, "current_user_can('manage_options')") !== false && strpos($src, 'wp_verify_nonce') !== false);
clean_ajax_assert('Uses only the horizontal core', strpos($src, 'CanonicalCoreUseCase') !== false && strpos($src, 'family') === false && strpos($src, 'capability') === false);
clean_ajax_assert('Exposes explicit mutations and delete preview', strpos($src, "'list_delete_preview'") !== false && strpos($src, 'record_count(') !== false);
clean_ajax_assert('Bootstrap registers clean transport', strpos($boot, 'CanonicalCleanShellAjax::register()') !== false);
clean_ajax_assert('C1 form-post bridge is removed', !file_exists($post));
echo "--- Resumen: 6/6 ---\n";
