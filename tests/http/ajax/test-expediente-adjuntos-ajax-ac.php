<?php
/**
 * AC — ExpedienteAdjuntosAjax (MC4b).
 *
 * Ejecutar: php tests/http/ajax/test-expediente-adjuntos-ajax-ac.php
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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteAdjuntosAjax.php');
$registros_src = file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteRegistrosAjax.php');
$bootstrap = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$index = file_get_contents($plugin_root . '/includes/admin/ui/modules/clients/index.php');
$js = file_get_contents($plugin_root . '/includes/admin/ui/modules/clients/expediente-registros.js');

ac_assert('ajax file exists', is_string($ajax_src) && $ajax_src !== '');
ac_assert('ACTION_ATTACH', strpos($ajax_src, 'aa_attach_expediente_registro') !== false);
ac_assert('manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('nonce compartido con registros', strpos($ajax_src, 'ExpedienteRegistrosAjax::NONCE_ACTION') !== false);
ac_assert('lee $_FILES[file]', strpos($ajax_src, "\$_FILES['file']") !== false);
ac_assert('lee upload_operation_id', strpos($ajax_src, 'upload_operation_id') !== false);
ac_assert('lee client_id y record_id', strpos($ajax_src, 'client_id') !== false && strpos($ajax_src, 'record_id') !== false);
ac_assert('no acepta signed_url del browser', strpos($ajax_src, "\$_POST['signed_url']") === false
    && strpos($ajax_src, "\$_REQUEST['signed_url']") === false);
ac_assert('no acepta upload_intent del browser', strpos($ajax_src, "\$_POST['upload_intent']") === false);
ac_assert('no acepta storage_path del browser', strpos($ajax_src, "\$_POST['storage_path']") === false);
ac_assert('no acepta token del browser', strpos($ajax_src, "\$_POST['token']") === false);
ac_assert('usa UploadExpedienteRegistroAdjuntoUseCase', strpos($ajax_src, 'UploadExpedienteRegistroAdjuntoUseCase') !== false);
ac_assert('create/update intactos', strpos($registros_src, 'aa_create_expediente_registro') !== false
    && strpos($registros_src, 'aa_update_expediente_registro') !== false
    && strpos($registros_src, 'aa_attach_expediente_registro') === false);
ac_assert('bootstrap register', strpos($bootstrap, 'ExpedienteAdjuntosAjax::register()') !== false);
ac_assert('index emite attachRegistro', strpos($index, 'attachRegistro') !== false);

// ── MC4c: contratos públicos con DTO seguro + sign-read ──
ac_assert('ACTION_SIGN_READ', strpos($ajax_src, 'aa_sign_expediente_adjunto_read') !== false);
ac_assert('ACTION_DELETE (MC5c1)', strpos($ajax_src, 'aa_delete_expediente_adjunto') !== false);
ac_assert('handle_sign_read', strpos($ajax_src, 'function handle_sign_read') !== false);
ac_assert('handle_delete', strpos($ajax_src, 'function handle_delete') !== false);
ac_assert('delete usa DeleteExpedienteAdjuntoUseCase', strpos($ajax_src, 'DeleteExpedienteAdjuntoUseCase') !== false);
ac_assert('delete lee attachment_id no storage_path', strpos($ajax_src, "absint(\$_POST['attachment_id'])") !== false
    && strpos($ajax_src, "\$_POST['storage_path']") === false);
ac_assert('delete responde adjuntos + deleted_attachment_id', strpos($ajax_src, "'deleted_attachment_id'") !== false
    && strpos($ajax_src, "'adjuntos' => \$result['adjuntos']") !== false);
ac_assert('storage_delete_failed / local_delete_failed mapeados',
    strpos($ajax_src, 'storage_delete_failed') !== false
    && strpos($ajax_src, 'local_delete_failed') !== false);

// ── MC5d2: consumo de almacenamiento (solo lectura) ──
ac_assert('ACTION_STORAGE_USAGE (MC5d2)', strpos($ajax_src, 'aa_get_expediente_storage_usage') !== false);
ac_assert('handle_storage_usage', strpos($ajax_src, 'function handle_storage_usage') !== false);
ac_assert('usage usa GetExpedienteStorageUsageUseCase', strpos($ajax_src, 'GetExpedienteStorageUsageUseCase') !== false);
$usage_handler = '';
if (preg_match('/function handle_storage_usage\(\): void \{.*?\n    \}/s', $ajax_src, $uh)) {
    $usage_handler = $uh[0];
}
ac_assert('usage exige authorize()', $usage_handler !== ''
    && strpos($usage_handler, 'self::authorize()') !== false);
ac_assert('adjuntos authorize incluye manage_options + shell full',
    strpos($ajax_src, 'function authorize') !== false
    && strpos($ajax_src, "current_user_can('manage_options')") !== false
    && strpos($ajax_src, 'require_expediente_shell_access') !== false);
ac_assert('usage no acepta scope del navegador', $usage_handler !== ''
    && strpos($usage_handler, '$_POST') === false
    && strpos($usage_handler, '$_REQUEST') === false
    && strpos($usage_handler, '$_GET') === false
    && strpos($usage_handler, 'installation_id') === false
    && strpos($usage_handler, 'client_id') === false);
ac_assert('usage contrato limitado a used_bytes', $usage_handler !== ''
    && strpos($usage_handler, "'used_bytes'") !== false
    && strpos($usage_handler, 'storage_path') === false
    && strpos($usage_handler, 'bucket') === false
    && strpos($usage_handler, 'adjuntos') === false
    && strpos($usage_handler, 'limit_bytes') === false
    && strpos($usage_handler, 'available_bytes') === false);
ac_assert('usage responde solo la clave used_bytes',
    preg_match('/wp_send_json_success\(\[\s*\'used_bytes\' => \(int\) \$result\[\'used_bytes\'\],\s*\]\);/s', $ajax_src) === 1);
ac_assert('index emite deleteAdjunto', strpos($index, 'deleteAdjunto') !== false);
ac_assert('js usa deleteAdjunto', strpos($js, 'deleteAdjunto') !== false);
ac_assert('attach responde DTO público', strpos($ajax_src, "'adjunto' => ExpedienteAdjuntoPublicDto::from(") !== false);
ac_assert('attach ya no expone attachment crudo', strpos($ajax_src, "'attachment' => \$result") === false);
ac_assert('sign-read usa GetExpedienteAdjuntoReadUrlUseCase', strpos($ajax_src, 'GetExpedienteAdjuntoReadUrlUseCase') !== false);
ac_assert('sign-read acepta attachment_id saneado (MC5a)',
    strpos($ajax_src, "absint(\$_POST['attachment_id'])") !== false);
ac_assert('sign-read exige attachment_id (MC5b, fallback retirado)',
    strpos($ajax_src, '$client_id < 1 || $record_id < 1 || $attachment_id < 1') !== false);
ac_assert('no_attachment retirado con el fallback', strpos($ajax_src, 'no_attachment') === false);
ac_assert('sign-read nunca acepta storage_path ni metadatos', strpos($ajax_src, "\$_POST['storage_path']") === false
    && strpos($ajax_src, "\$_POST['adjunto_id']") === false
    && strpos($ajax_src, "\$_POST['mime_type']") === false);
ac_assert('sign-read lee variant con wp_unslash', strpos($ajax_src, "wp_unslash(\$_POST['variant'])") !== false);
ac_assert('sign-read no sanitiza variant', !preg_match("/sanitize_text_field\\([^\\n]*variant/", $ajax_src));
$sign_handler = '';
if (preg_match('/function handle_sign_read\(\): void \{.*?\n    \}/s', $ajax_src, $sm)) {
    $sign_handler = $sm[0];
}
ac_assert(
    'sign-read éxito sin adjunto DTO',
    $sign_handler !== ''
    && strpos($sign_handler, "'variant' => \$result['variant']") !== false
    && strpos($sign_handler, "'adjunto'") === false
);
ac_assert("variant_invalid → 400", preg_match("/case 'variant_invalid':\\s*return 400;/", $ajax_src) === 1);
ac_assert('attachment_not_found → 404', preg_match("/case 'attachment_not_found':/", $ajax_src) === 1);
ac_assert(
    'variant_generation_failed → 500',
    preg_match(
        "/case 'local_delete_failed':\\s*case 'storage_usage_unavailable':\\s*case 'variant_generation_failed':\\s*return 500;/",
        $ajax_src
    ) === 1
);
ac_assert('index emite signAdjuntoRead', strpos($index, 'signAdjuntoRead') !== false);
ac_assert('js usa signAdjuntoRead', strpos($js, 'signAdjuntoRead') !== false);

$dto_src = file_get_contents($plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoPublicDto.php');
ac_assert('DTO expone exactamente 5 claves', substr_count($dto_src, "'id' =>") === 1
    && strpos($dto_src, "'width' =>") !== false
    && strpos($dto_src, "'height' =>") !== false
    && strpos($dto_src, "'byte_size' =>") !== false
    && strpos($dto_src, "'created_at' =>") !== false
    && strpos($dto_src, "'storage_path'") === false
    && strpos($dto_src, "'upload_operation_id'") === false
    && strpos($dto_src, "'mime_type'") === false
    && strpos($dto_src, "'installation_id'") === false);
ac_assert('js usa attachRegistro', strpos($js, 'attachRegistro') !== false);
ac_assert('js partial message', strpos($js, 'Registro guardado. No se pudo subir la imagen.') !== false);
ac_assert('js Reintentar imagen', strpos($js, 'Reintentar imagen') !== false);
ac_assert('js HEIC copy MX', strpos($js, 'Guarda o exporta la foto como JPG e inténtalo de nuevo.') !== false);
ac_assert('js sin capture', !preg_match('/capture\s*=/', $js));
ac_assert('js no segundo create en retry', strpos($js, 'partial_attachment_failed') !== false
    && strpos($js, 'runAttachRetry') !== false);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/http/ajax/ExpedienteAdjuntosAjax.php';
ac_assert('class exists', class_exists('ExpedienteAdjuntosAjax'));
ac_assert('ACTION constant', ExpedienteAdjuntosAjax::ACTION_ATTACH === 'aa_attach_expediente_registro');
ac_assert('SIGN_READ constant', ExpedienteAdjuntosAjax::ACTION_SIGN_READ === 'aa_sign_expediente_adjunto_read');
ac_assert('DELETE constant', ExpedienteAdjuntosAjax::ACTION_DELETE === 'aa_delete_expediente_adjunto');
ac_assert('STORAGE_USAGE constant', ExpedienteAdjuntosAjax::ACTION_STORAGE_USAGE === 'aa_get_expediente_storage_usage');
ac_assert('handle_attach callable', method_exists('ExpedienteAdjuntosAjax', 'handle_attach'));
ac_assert('handle_sign_read callable', method_exists('ExpedienteAdjuntosAjax', 'handle_sign_read'));
ac_assert('handle_delete callable', method_exists('ExpedienteAdjuntosAjax', 'handle_delete'));
ac_assert('handle_storage_usage callable', method_exists('ExpedienteAdjuntosAjax', 'handle_storage_usage'));

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
