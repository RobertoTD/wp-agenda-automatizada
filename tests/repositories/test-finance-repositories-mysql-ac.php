<?php
/**
 * AC Test — Integración MySQL Real de FinanceContainerRepository y FinanceRecordRepository (Ciclo 3A2).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/repositories/test-finance-repositories-mysql-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$c_repo_file = $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
$r_repo_file = $plugin_root . '/includes/repositories/FinanceRecordRepository.php';
$schema_file = $plugin_root . '/includes/infrastructure/wp/FinanceSchema.php';

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

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] Integración MySQL real no ejecutada (AA_WP_ROOT no definido o inaccesible).\n";
    exit(0);
}

require_once $wp_load;
require_once $schema_file;
require_once $c_repo_file;
require_once $r_repo_file;

global $wpdb;

echo "=== Integración MySQL Real con Prefijos Desechables ===\n";
echo "Host: " . DB_HOST . " | Base: " . DB_NAME . "\n";

$original_prefix = $wpdb->prefix;
$temp_prefix_1 = 'tmp_repo1_' . substr(md5(uniqid('r1', true)), 0, 8) . '_';
$temp_prefix_2 = 'tmp_repo2_' . substr(md5(uniqid('r2', true)), 0, 8) . '_';

$cleanup_tables = function(string $p) use ($wpdb) {
    if (strpos($p, 'tmp_repo') !== 0) {
        return; // Seguridad: nunca borrar tablas que no empiecen con tmp_repo
    }
    $r_table = $p . AA_Finance_Schema::TABLE_RECORDS;
    $c_table = $p . AA_Finance_Schema::TABLE_CONTAINERS;
    $wpdb->query("DROP TABLE IF EXISTS `{$r_table}`");
    $wpdb->query("DROP TABLE IF EXISTS `{$c_table}`");
};

try {
    // 1. Preparar instalación en Prefijo 1
    $wpdb->prefix = $temp_prefix_1;
    $cleanup_tables($temp_prefix_1);
    AA_Finance_Schema::install();
    AA_Finance_Schema::verify();
    ac_assert('MySQL real: schema de Finanzas instalado y verificado en prefijo temporal 1', true);

    // 2. Operaciones de Contenedor: Create y Find
    $c1 = FinanceContainerRepository::create('general', 'Gastos Operativos', 'Descripción de gastos');
    ac_assert('MySQL real: contenedor creado con ID > 0', is_array($c1) && $c1['id'] > 0);
    $c1_id = $c1['id'];

    $c1_found = FinanceContainerRepository::find_by_id($c1_id);
    ac_assert('MySQL real: find_by_id recupera contenedor idéntico', $c1_found !== null && $c1_found['title'] === 'Gastos Operativos');
    ac_assert('MySQL real: details preservado fielmente', $c1_found['details'] === 'Descripción de gastos');

    // Inexistente devuelve null
    $c_not_found = FinanceContainerRepository::find_by_id(999999);
    ac_assert('MySQL real: find_by_id inexistente devuelve null', $c_not_found === null);

    // 2.1 Update de contenedor (Ciclo 3E1A)
    $c1_created_at = $c1_found['created_at'];
    $updated_title = FinanceContainerRepository::update($c1_id, 'general', 'Gastos Actualizados', 'Nueva descripción');
    ac_assert('MySQL real: update cambia title efectivamente', $updated_title !== null && $updated_title['title'] === 'Gastos Actualizados');
    ac_assert('MySQL real: update cambia details efectivamente', $updated_title['details'] === 'Nueva descripción');

    $updated_null_details = FinanceContainerRepository::update($c1_id, 'general', 'Gastos Actualizados', null);
    ac_assert('MySQL real: update persiste details null como SQL NULL', $updated_null_details !== null && $updated_null_details['details'] === null);

    $c1_after_null = FinanceContainerRepository::find_by_id($c1_id);
    ac_assert('MySQL real: find_by_id confirma details null persistido', $c1_after_null !== null && $c1_after_null['details'] === null);

    $updated_idempotent = FinanceContainerRepository::update($c1_id, 'general', 'Gastos Actualizados', null);
    ac_assert('MySQL real: update idempotente devuelve fila autoritativa', $updated_idempotent !== null && $updated_idempotent['title'] === 'Gastos Actualizados');

    $wrong_variant_update = FinanceContainerRepository::update($c1_id, 'otra_variante', 'No debe aplicar', 'X');
    ac_assert('MySQL real: update con variante incorrecta devuelve null', $wrong_variant_update === null);
    ac_assert('MySQL real: variante incorrecta no modifica title', FinanceContainerRepository::find_by_id($c1_id)['title'] === 'Gastos Actualizados');

    ac_assert('MySQL real: update preserva created_at', $updated_idempotent['created_at'] === $c1_created_at);

    // 3. Operaciones de Registros: Create con amounts y details extenso
    // Registro 1: amount null
    $r1 = FinanceRecordRepository::create($c1_id, 'Record Sin Monto', 'Detalle sin monto', null);
    ac_assert('MySQL real: registro con amount null creado', is_array($r1) && $r1['amount'] === null);

    // Registro 2: amount "0.00"
    $r2 = FinanceRecordRepository::create($c1_id, 'Record Cero', null, '0.00');
    ac_assert('MySQL real: registro con amount 0.00 creado', is_array($r2) && $r2['amount'] === '0.00');

    // Registro 3: amount positivo "150.50"
    $r3 = FinanceRecordRepository::create($c1_id, 'Ingreso Extra', null, '150.50');
    ac_assert('MySQL real: registro con amount positivo 150.50 creado', is_array($r3) && $r3['amount'] === '150.50');

    // Registro 4: amount negativo "-50.50"
    $r4 = FinanceRecordRepository::create($c1_id, 'Gasto Menor', null, '-50.50');
    ac_assert('MySQL real: registro con amount negativo -50.50 creado', is_array($r4) && $r4['amount'] === '-50.50');

    // Registro 5: details extenso >= 10,000 caracteres
    $large_details = str_repeat('Detalle extenso del registro canónico de Finanzas 12345. ', 200); // ~11,000 chars
    $r5 = FinanceRecordRepository::create($c1_id, 'Registro Texto Largo', $large_details, '100.00');
    ac_assert('MySQL real: registro con details extenso creado', is_array($r5) && strlen($large_details) >= 10000);

    $r5_found = FinanceRecordRepository::find_by_id_and_container($r5['id'], $c1_id);
    ac_assert('MySQL real: find_by_id_and_container recupera details extenso sin truncar', $r5_found !== null && $r5_found['details'] === $large_details);

    // 4. Rechazo de Registro Huérfano (FK activa)
    $caught_orphan_exception = false;
    try {
        FinanceRecordRepository::create(999999, 'Huérfano');
    } catch (\RuntimeException $e) {
        $caught_orphan_exception = true;
    }
    ac_assert('MySQL real: create registro con container_id inexistente falla con RuntimeException (FK)', $caught_orphan_exception);

    // 5. Semántica de Suma (sum_amounts_by_container)
    // Contenedor c1 tiene: null, 0.00, 150.50, -50.50, 100.00 => Total: 200.00
    $sum_c1 = FinanceRecordRepository::sum_amounts_by_container($c1_id);
    ac_assert('MySQL real: sum_amounts_by_container(c1) devuelve "200.00"', $sum_c1 === '200.00');

    // Contenedor c2 vacío => null
    $c2 = FinanceContainerRepository::create('general', 'Contenedor Vacío');
    $c2_id = $c2['id'];
    $sum_c2_empty = FinanceRecordRepository::sum_amounts_by_container($c2_id);
    ac_assert('MySQL real: sum_amounts_by_container para contenedor vacío devuelve null', $sum_c2_empty === null);

    // Contenedor c3 con solo importes null => null
    $c3 = FinanceContainerRepository::create('general', 'Contenedor Nulos');
    $c3_id = $c3['id'];
    FinanceRecordRepository::create($c3_id, 'Nulo 1', null, null);
    FinanceRecordRepository::create($c3_id, 'Nulo 2', null, null);
    $sum_c3_nulls = FinanceRecordRepository::sum_amounts_by_container($c3_id);
    ac_assert('MySQL real: sum_amounts_by_container con solo amounts null devuelve null', $sum_c3_nulls === null);

    // Contenedor c4 con importes que suman cero (+50.00 y -50.00) => "0.00"
    $c4 = FinanceContainerRepository::create('general', 'Contenedor Balance Cero');
    $c4_id = $c4['id'];
    FinanceRecordRepository::create($c4_id, 'Plus', null, '50.00');
    FinanceRecordRepository::create($c4_id, 'Minus', null, '-50.00');
    $sum_c4_zero = FinanceRecordRepository::sum_amounts_by_container($c4_id);
    ac_assert('MySQL real: sum_amounts_by_container con total cero devuelve "0.00"', $sum_c4_zero === '0.00');

    // Contenedor c5 con suma negativa (10.00 y -40.50 => -30.50)
    $c5 = FinanceContainerRepository::create('general', 'Contenedor Balance Negativo');
    $c5_id = $c5['id'];
    FinanceRecordRepository::create($c5_id, 'Ingreso', null, '10.00');
    FinanceRecordRepository::create($c5_id, 'Gasto Mayor', null, '-40.50');
    $sum_c5_neg = FinanceRecordRepository::sum_amounts_by_container($c5_id);
    ac_assert('MySQL real: sum_amounts_by_container con total negativo devuelve "-30.50"', $sum_c5_neg === '-30.50');

    // 5.1 Suma agregada en lote (sum_amounts_by_container_ids) en MySQL real (Ciclo 3B2b)
    $batch_ids = [$c1_id, $c2_id, $c3_id, $c4_id, $c5_id, 999999];
    $batch_map = FinanceRecordRepository::sum_amounts_by_container_ids($batch_ids);
    ac_assert('MySQL real: sum_amounts_by_container_ids devuelve 6 entradas completas', count($batch_map) === 6);
    ac_assert('MySQL real: c1 suma "200.00"', $batch_map[$c1_id] === '200.00');
    ac_assert('MySQL real: c2 vacío es null', $batch_map[$c2_id] === null);
    ac_assert('MySQL real: c3 solo nulls es null', $batch_map[$c3_id] === null);
    ac_assert('MySQL real: c4 total cero es "0.00"', $batch_map[$c4_id] === '0.00');
    ac_assert('MySQL real: c5 total negativo es "-30.50"', $batch_map[$c5_id] === '-30.50');
    ac_assert('MySQL real: ID inexistente es null', $batch_map[999999] === null);
    ac_assert('MySQL real: orden de claves preservado', array_keys($batch_map) === $batch_ids);

    // 6. Paginación canónica fija de 15 y Orden created_at DESC, id DESC
    // Crear contenedor para prueba de paginación
    $c_page = FinanceContainerRepository::create('general', 'Contenedor Paginación');
    $c_page_id = $c_page['id'];
    for ($i = 1; $i <= 20; $i++) {
        FinanceRecordRepository::create($c_page_id, "Registro {$i}", null, "{$i}.00");
    }

    $count_page = FinanceRecordRepository::count_by_container($c_page_id);
    ac_assert('MySQL real: count_by_container devuelve 20', $count_page === 20);

    $page_1 = FinanceRecordRepository::list_by_container($c_page_id, 1);
    ac_assert('MySQL real: página 1 devuelve exactamente 15 registros', count($page_1) === 15);
    ac_assert('MySQL real: orden estable primer elemento es el último insertado (Registro 20)', $page_1[0]['title'] === 'Registro 20');

    $page_2 = FinanceRecordRepository::list_by_container($c_page_id, 2);
    ac_assert('MySQL real: página 2 devuelve exactamente 5 registros restantes', count($page_2) === 5);
    ac_assert('MySQL real: orden estable último elemento es el primero insertado (Registro 1)', $page_2[4]['title'] === 'Registro 1');

    $page_3 = FinanceRecordRepository::list_by_container($c_page_id, 3);
    ac_assert('MySQL real: página 3 fuera de rango devuelve []', $page_3 === []);

    // 7. Eliminación Individual de Registro (acotada por container_id)
    $rec_to_delete = $page_1[0]['id'];
    $del_rec_ok = FinanceRecordRepository::delete($rec_to_delete, $c_page_id);
    ac_assert('MySQL real: delete registro existente devuelve true', $del_rec_ok === true);

    // Intentar borrar con container_id erróneo devuelve false
    $del_rec_wrong_c = FinanceRecordRepository::delete($page_1[1]['id'], $c1_id);
    ac_assert('MySQL real: delete registro con container_id erróneo devuelve false', $del_rec_wrong_c === false);

    // 8. Borrado en Cascada del Contenedor
    $del_c_ok = FinanceContainerRepository::delete($c_page_id);
    ac_assert('MySQL real: delete contenedor devuelve true', $del_c_ok === true);

    $remaining_after_cascade = FinanceRecordRepository::count_by_container($c_page_id);
    ac_assert('MySQL real: registros borrados automáticamente en cascada por la BD (count=0)', $remaining_after_cascade === 0);

    // 9. Aislamiento entre dos Prefijos
    $wpdb->prefix = $temp_prefix_2;
    $cleanup_tables($temp_prefix_2);
    AA_Finance_Schema::install();
    AA_Finance_Schema::verify();

    $c_p2 = FinanceContainerRepository::create('general', 'Contenedor en Prefijo 2');
    $count_p1_containers = FinanceContainerRepository::count_by_variant('general'); // En prefijo 2
    ac_assert('MySQL real: prefijo 2 contiene exactamente 1 contenedor', $count_p1_containers === 1);

    // Volver a prefijo 1
    $wpdb->prefix = $temp_prefix_1;
    $count_p1_containers_actual = FinanceContainerRepository::count_by_variant('general');
    ac_assert('MySQL real: prefijo 1 conserva sus contenedores sin cruzarse con prefijo 2', $count_p1_containers_actual >= 3);

} finally {
    $cleanup_tables($temp_prefix_1);
    $cleanup_tables($temp_prefix_2);
    $wpdb->prefix = $original_prefix;
    echo "Limpieza garantizada: tablas temporales de {$temp_prefix_1} y {$temp_prefix_2} eliminadas.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
