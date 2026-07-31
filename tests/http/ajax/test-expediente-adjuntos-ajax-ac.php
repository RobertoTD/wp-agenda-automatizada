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
ac_assert('handle_attach callable', method_exists('ExpedienteAdjuntosAjax', 'handle_attach'));

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
