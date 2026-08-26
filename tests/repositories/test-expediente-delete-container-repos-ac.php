<?php
/**
 * AC — Repositories Ciclo B (delete contenedor): contratos SQL focalizados.
 *
 * Ejecutar: php tests/repositories/test-expediente-delete-container-repos-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
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

$exp = file_get_contents($plugin_root . '/includes/repositories/ExpedientesRepository.php');
$reg = file_get_contents($plugin_root . '/includes/repositories/ExpedienteRegistrosRepository.php');
$adj = file_get_contents($plugin_root . '/includes/repositories/ExpedienteAdjuntosRepository.php');

ac_assert('find_delete_context_by_id', strpos($exp, 'function find_delete_context_by_id') !== false);
ac_assert('find_delete_context FOR UPDATE', strpos($exp, 'FOR UPDATE') !== false
    && strpos($exp, 'find_delete_context_by_id_for_update') !== false);
ac_assert('delete_by_expected_identity', strpos($exp, 'function delete_by_expected_identity') !== false);
ac_assert('delete padre client_id IS NULL rama', strpos($exp, 'client_id IS NULL') !== false);
ac_assert('delete padre no toca clientes', strpos($exp, 'aa_clientes') === false);
ac_assert('delete padre no DELETE categories', !preg_match('/DELETE FROM.*aa_expediente_categories/s', $exp));
ac_assert('WP_Error en delete context', strpos($exp, 'new WP_Error') !== false);

ac_assert('list_identity_page keyset', strpos($reg, 'list_identity_page_by_expediente_id') !== false
    && strpos($reg, 'id > %d') !== false
    && strpos($reg, 'ORDER BY id ASC') !== false);
ac_assert('list identity sin LIST_LIMIT techo', preg_match(
    '/function list_identity_page_by_expediente_id[\s\S]*?min\(\$limit,\s*self::LIST_LIMIT\)/',
    $reg
) !== 1);
ac_assert('list_identity_for_update', strpos($reg, 'list_identity_for_update_by_expediente_id') !== false
    && strpos($reg, 'FOR UPDATE') !== false);
ac_assert('delete_all_by_expediente_id', strpos($reg, 'delete_all_by_expediente_id') !== false
    && strpos($reg, 'WHERE expediente_id = %d') !== false);

ac_assert('JOIN adjuntos→registros', strpos($adj, 'list_joined_page_by_expediente_id') !== false
    && strpos($adj, 'INNER JOIN') !== false
    && strpos($adj, 'r.expediente_id = %d') !== false);
ac_assert('has_any_joined', strpos($adj, 'has_any_joined_by_expediente_id') !== false);
ac_assert('delete_by_exact_identity', strpos($adj, 'delete_by_exact_identity') !== false
    && strpos($adj, 'upload_operation_id') !== false
    && strpos($adj, 'storage_path') !== false);

echo "\nResultado: {$passed}/{$total}" . (count($failed) ? ' FAIL' : ' OK') . "\n";
exit(count($failed) === 0 ? 0 : 1);
