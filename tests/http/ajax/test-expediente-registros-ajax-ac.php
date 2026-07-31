<?php
/**
 * AC — ExpedienteRegistrosAjax (MC2 + MC3).
 *
 * Ejecutar: php tests/http/ajax/test-expediente-registros-ajax-ac.php
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

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteRegistrosAjax.php');
$bootstrap = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$index = file_get_contents($plugin_root . '/includes/admin/ui/modules/clients/index.php');

ac_assert('ajax file exists', is_string($ajax_src) && $ajax_src !== '');
ac_assert('ACTION_LIST', strpos($ajax_src, 'aa_list_expediente_registros') !== false);
ac_assert('ACTION_CREATE', strpos($ajax_src, 'aa_create_expediente_registro') !== false);
ac_assert('ACTION_UPDATE', strpos($ajax_src, 'aa_update_expediente_registro') !== false);
ac_assert('ACTION_DELETE (MC5c2)', strpos($ajax_src, 'aa_delete_expediente_registro') !== false);
ac_assert('handle_update', strpos($ajax_src, 'function handle_update') !== false);
ac_assert('handle_delete (MC5c2)', strpos($ajax_src, 'function handle_delete') !== false);
ac_assert('delete no lee storage_path', strpos($ajax_src, "\$_POST['storage_path']") === false
    && strpos($ajax_src, "\$_REQUEST['storage_path']") === false);
ac_assert('NONCE compartido', strpos($ajax_src, 'aa_expediente_registros_nonce') !== false);
ac_assert('manage_options', strpos($ajax_src, "current_user_can('manage_options')") !== false);
ac_assert('valida cliente find_by_id', substr_count($ajax_src, 'ClientsRepository::find_by_id') >= 3);
ac_assert('check_ajax_referer', strpos($ajax_src, 'check_ajax_referer') !== false);
ac_assert('TITLE_MAX 200', strpos($ajax_src, 'TITLE_MAX = 200') !== false);
ac_assert('BODY_MAX 10000', strpos($ajax_src, 'BODY_MAX = 10000') !== false);
ac_assert('current_time para fechas', strpos($ajax_src, "current_time('mysql')") !== false);
ac_assert('update_title_body', strpos($ajax_src, 'update_title_body') !== false);
ac_assert('relee find_by_id_for_client', strpos($ajax_src, 'find_by_id_for_client') !== false);
ac_assert('404 registro no encontrado', strpos($ajax_src, 'Registro no encontrado.') !== false);
ac_assert('acepta record_id', strpos($ajax_src, "\$_REQUEST['record_id']") !== false);
ac_assert('no lee recorded_at del POST', !preg_match('/\$_POST\[[\'"]recorded_at[\'"]\]/', $ajax_src));
ac_assert('no lee blog_id del request', strpos($ajax_src, "\$_REQUEST['blog_id']") === false && strpos($ajax_src, "\$_POST['blog_id']") === false && strpos($ajax_src, '$_GET[\'blog_id\']') === false);
ac_assert('bootstrap register', strpos($bootstrap, 'ExpedienteRegistrosAjax::register()') !== false);
// MC5a: listado enriquecido con adjuntos[] + alias, bulk sin N+1
ac_assert('list usa list_by_record_ids (bulk MC5a)', strpos($ajax_src, 'ExpedienteAdjuntosRepository::list_by_record_ids') !== false);
ac_assert('list serializa DTO público', strpos($ajax_src, 'ExpedienteAdjuntoPublicDto::from') !== false);
ac_assert('list no llama list_by_record_for_client (N+1)', strpos($ajax_src, 'list_by_record_for_client') === false);
ac_assert('list expone adjuntos[]', strpos($ajax_src, "['adjuntos'] = \$dtos") !== false);
ac_assert('alias adjunto = adjuntos[0] | null', strpos($ajax_src, "['adjunto'] = \$dtos[0] ?? null") !== false);
ac_assert('sin consultas dentro del foreach del listado',
    !preg_match('/foreach \(\$records[^}]*ExpedienteAdjuntosRepository::/s', $ajax_src));
ac_assert('index emite listRegistros solo expediente', strpos($index, 'listRegistros') !== false && strpos($index, 'aa_clients_is_expediente') !== false);
ac_assert('index emite updateRegistro', strpos($index, 'updateRegistro') !== false);
ac_assert('index emite deleteRegistro', strpos($index, 'deleteRegistro') !== false);
ac_assert('index carga js solo expediente', strpos($index, 'expediente-registros.js') !== false);
ac_assert('delete usa DeleteExpedienteRegistroUseCase', strpos($ajax_src, 'DeleteExpedienteRegistroUseCase') !== false);
ac_assert('delete responde deleted + record_id', strpos($ajax_src, "'deleted' => true") !== false
    && strpos($ajax_src, "'record_id' => (int) \$result['record_id']") !== false);
ac_assert('delete mapea local_delete_failed / storage_delete_partial',
    strpos($ajax_src, 'local_delete_failed') !== false
    && strpos($ajax_src, 'storage_delete_partial') !== false);
ac_assert('delete mapea record_not_found → 404', preg_match("/case 'record_not_found':/", $ajax_src) === 1);

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/http/ajax/ExpedienteRegistrosAjax.php';

ac_assert('class exists', class_exists('ExpedienteRegistrosAjax'));
ac_assert('register callable', method_exists('ExpedienteRegistrosAjax', 'register'));
ac_assert('handle_list callable', method_exists('ExpedienteRegistrosAjax', 'handle_list'));
ac_assert('handle_create callable', method_exists('ExpedienteRegistrosAjax', 'handle_create'));
ac_assert('handle_update callable', method_exists('ExpedienteRegistrosAjax', 'handle_update'));
ac_assert('handle_delete callable', method_exists('ExpedienteRegistrosAjax', 'handle_delete'));
ac_assert('constants', ExpedienteRegistrosAjax::ACTION_LIST === 'aa_list_expediente_registros'
    && ExpedienteRegistrosAjax::ACTION_CREATE === 'aa_create_expediente_registro'
    && ExpedienteRegistrosAjax::ACTION_UPDATE === 'aa_update_expediente_registro'
    && ExpedienteRegistrosAjax::ACTION_DELETE === 'aa_delete_expediente_registro'
    && ExpedienteRegistrosAjax::NONCE_ACTION === 'aa_expediente_registros_nonce');

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
