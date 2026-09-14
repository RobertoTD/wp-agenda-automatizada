<?php
/**
 * AC Test — CanonicalDeleteRecordImageAjax (IMG-5 inc. 5).
 *
 * Ejecutar: php tests/http/ajax/test-canonical-delete-record-image-ajax-ac.php
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

$ajax_file = $plugin_root . '/includes/http/ajax/CanonicalDeleteRecordImageAjax.php';
$ajax_src = (string) file_get_contents($ajax_file);
$boot_src = (string) file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$shell_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
$card_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php');
$js_src = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js');
$uc_src = (string) file_get_contents($plugin_root . '/includes/application/canonical/images/RetireCanonicalRecordImageUseCase.php');

ac_assert('Ajax file readable', $ajax_src !== '');
ac_assert('Action constante', strpos($ajax_src, "ACTION = 'aa_delete_canonical_record_image'") !== false);
ac_assert('Nonce específico', strpos($ajax_src, "NONCE_ACTION = 'aa_delete_canonical_record_image'") !== false);
ac_assert('Solo wp_ajax_', strpos($ajax_src, "add_action('wp_ajax_'") !== false);
ac_assert('Sin nopriv', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('Bootstrap registra', strpos($boot_src, 'CanonicalDeleteRecordImageAjax::register()') !== false);
ac_assert('authorize_identity', strpos($ajax_src, 'CanonicalShellWriteAjaxSupport::authorize_identity') !== false);
ac_assert('RetireCanonicalRecordImageUseCase', strpos($ajax_src, 'new RetireCanonicalRecordImageUseCase()') !== false);
ac_assert('Sin mandate_id en JSON', strpos($ajax_src, "'mandate_id'") === false
    && strpos($ajax_src, '"mandate_id"') === false);
ac_assert('Sin is_ready gate', strpos($ajax_src, 'images.is_ready') !== false
    || (strpos($ajax_src, "is_ready") === false || strpos($ajax_src, 'Sin gate images.is_ready') !== false));
ac_assert('UseCase no consulta is_ready', strpos($uc_src, '->is_ready') === false
    && strpos($uc_src, "['is_ready']") === false
    && strpos($uc_src, 'capability_ready') === false);
ac_assert('Parsea image_id', strpos($ajax_src, 'image_id') !== false
    && strpos($ajax_src, "'invalid_image_id'") !== false);
ac_assert('Códigos de estado', strpos($ajax_src, "'image_not_found'") !== false
    && strpos($ajax_src, "'incomplete'") !== false
    && strpos($ajax_src, "'uncertain'") !== false
    && strpos($ajax_src, "'cancel_rejected'") !== false
    && strpos($ajax_src, "'conflict'") !== false);
ac_assert('Boot nonces deleteImage', strpos($shell_src, 'deleteImageAction') !== false
    && strpos($shell_src, 'deleteImageNonce') !== false
    && strpos($shell_src, 'CanonicalDeleteRecordImageAjax') !== false);
ac_assert('Card Eliminar imagen', strpos($card_src, 'aa-shell-delete-image-btn') !== false
    && strpos($card_src, 'data-aa-image') !== false);
ac_assert('Modal imagen + Continuar recovery', strpos($shell_src, 'aa-shell-delete-image-modal') !== false
    && strpos($shell_src, 'aa-shell-resume-image-delete-btn') !== false);
ac_assert('JS one-click / incomplete / Cerrar', strpos($js_src, 'submitDeleteImage') !== false
    && strpos($js_src, "code === 'incomplete'") !== false
    && strpos($js_src, 'closeDeleteImageModal') !== false
    && strpos($js_src, 'bindImageDeleteOnce') !== false
    && strpos($js_src, 'e.repeat') !== false);

echo "\n";
if (count($failed) === 0) {
    echo "Passed {$passed}/{$total}\n";
    exit(0);
}
echo 'Failed ' . count($failed) . "/{$total}\n";
exit(1);
