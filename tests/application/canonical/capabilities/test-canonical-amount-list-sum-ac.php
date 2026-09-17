<?php
/**
 * AC — total de lista amount (proyección agregada del contenedor).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-amount-list-sum-ac.php
 */

$plugin_root = dirname(__DIR__, 4);

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

echo "=== 1. Contratos estáticos / formateo sin float ===\n";

$sum_src = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Amount_List_Sum.php'
);
$repo_src = (string) file_get_contents(
    $plugin_root . '/includes/repositories/CanonicalRecordAmountRepository.php'
);
$contrib_src = (string) file_get_contents(
    $plugin_root . '/includes/application/canonical/capabilities/amount/CanonicalAmountRecordsPageContributor.php'
);
$presenter_src = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-amount-shell-presenter.php'
);
$index_src = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
);

ac_assert('List Sum helper existe', strpos($sum_src, 'class AA_Canonical_Amount_List_Sum') !== false);
ac_assert(
    'List Sum sin cast float',
    strpos($sum_src, '(float)') === false && strpos($sum_src, 'floatval') === false
);
ac_assert('Repo suma por container_id', strpos($repo_src, 'sum_amounts_for_container') !== false
    && strpos($repo_src, 'SUM(a.amount)') !== false);
ac_assert('Contributor expone list_sum', strpos($contrib_src, 'list_sum') !== false
    && strpos($contrib_src, 'sum_amounts_for_container') !== false);
ac_assert('Presenter list_details_view', strpos($presenter_src, 'list_details_view') !== false);
ac_assert('UI panel Detalles renderiza total', strpos($index_src, 'aa-shell-list-amount-sum') !== false
    && strpos($index_src, 'Total no disponible') !== false
    && strpos($index_src, 'list_details_view') !== false);
ac_assert('Sin identificador legado amount_total', strpos($index_src, 'amount_total') === false
    && strpos($contrib_src, 'amount_total') === false
    && strpos($presenter_src, 'amount_total') === false
    && strpos($repo_src, 'amount_total') === false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL/formateo runtime no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Amount_List_Sum.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Amount_Normalizer.php';

echo "\n=== 1b. Formateo sin float ===\n";
ac_assert('Canon 0', AA_Canonical_Amount_List_Sum::canonicalize_aggregate(null) === '0.00');
ac_assert('Canon 12.5', AA_Canonical_Amount_List_Sum::canonicalize_aggregate('12.5') === '12.50');
ac_assert('Canon negativo', AA_Canonical_Amount_List_Sum::canonicalize_aggregate('-3.1') === '-3.10');
try {
    AA_Canonical_Amount_List_Sum::canonicalize_aggregate('1.234');
    ac_assert('Escala inesperada se rechaza', false);
} catch (\InvalidArgumentException $e) {
    ac_assert('Escala inesperada se rechaza', true);
}
ac_assert(
    'Display miles',
    AA_Canonical_Amount_List_Sum::format_display('5454.00') === '5,454.00'
);
ac_assert(
    'Display negativo',
    AA_Canonical_Amount_List_Sum::format_display('-1234567.89') === '-1,234,567.89'
);

$big = str_repeat('9', 17) . '.00';
$sum_expected = '199999999999999998.00';
ac_assert(
    'Suma overflow rango individual se conserva',
    AA_Canonical_Amount_List_Sum::canonicalize_aggregate($sum_expected) === $sum_expected
);
$norm_big_sum = AA_Canonical_Amount_Normalizer::normalize($sum_expected);
ac_assert(
    'Normalizador individual rechaza esa suma',
    empty($norm_big_sum['ok']) && ($norm_big_sum['error']['code'] ?? '') === 'amount_out_of_range'
);
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-page-contributor-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-read-binding-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPagination.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsPage.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadGateway.php';
require_once $plugin_root . '/includes/application/canonical/ReadCanonicalShellRecordsUseCase.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellRecordsReadResult.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordAmountRepository.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-amount-shell-presenter.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordsPageContribution.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordPageContributor.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordPageContributorRegistry.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityShellRecordsEnricher.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/amount/CanonicalAmountRecordsPageContributor.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_als_' . substr(md5((string) microtime(true)), 0, 8) . '_';
$prior_db_version = (string) get_option('aa_db_version', '0');

$cleanup = static function () use ($wpdb, $temp_prefix): void {
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $like = $wpdb->esc_like($temp_prefix) . '%';
    $rows = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    if (is_array($rows)) {
        foreach ($rows as $t) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
        }
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
};

echo "\n=== 2. MySQL: agregado de contenedor ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    AA_Canonical_Schema::install();
    AA_Canonical_Core_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);
    AA_Canonical_Capability_Registry_Bootstrap::reset_for_tests();
    AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
    AA_Canonical_Capability_Page_Contributor_Bootstrap::reset_for_tests();

    $families = AA_Canonical_Schema::families_table_name();
    $wpdb->update($families, ['is_enabled' => 1], ['family_key' => 'finance']);

    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $amount_repo = new CanonicalRecordAmountRepository($wpdb);
    $finance_id = (int) $config->resolve_family_id('finance');
    $now = gmdate('Y-m-d H:i:s');

    $wpdb->insert(AA_Canonical_Schema::containers_table_name(), [
        'public_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'family_id' => $finance_id,
        'title' => 'Lista suma',
        'details' => 'desc',
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', '%s', '%s', '%s']);
    $cid = (int) $wpdb->insert_id;
    $config->upsert_container_capability($cid, 'amount', true);

    $record_ids = [];
    $amounts = [
        '10.50',
        '-3.25',
        '0.00',
        null, // absent
        '100.00',
    ];
    foreach ($amounts as $i => $amt) {
        $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
            'public_id' => sprintf('bbbbbbbb-bbbb-4bbb-8bbb-%012d', $i + 1),
            'container_id' => $cid,
            'title' => 'R' . ($i + 1),
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%d', '%s', null, '%s', '%s']);
        $rid = (int) $wpdb->insert_id;
        $record_ids[] = $rid;
        if ($amt !== null) {
            $amount_repo->upsert($rid, $amt, $now);
        }
    }

    // 10.50 - 3.25 + 0.00 + 100.00 = 107.25 (absent ignored)
    $sum = $amount_repo->sum_amounts_for_container($cid);
    ac_assert('Suma positivos/negativos/cero/ausente', $sum === '107.25');

    $contributor = new CanonicalAmountRecordsPageContributor(
        $config,
        $amount_repo,
        AA_Canonical_Capability_Registry_Bootstrap::bootstrap()
    );
    $page_ids = array_slice($record_ids, 0, 2);
    $contrib = $contributor->contribute_for_records_page('finance', $cid, $page_ids);
    $summary = $contrib->list_summary();
    ac_assert('list_sum en summary', !empty($summary['offered'])
        && ($summary['list_sum']['status'] ?? '') === 'known_value'
        && ($summary['list_sum']['value'] ?? '') === '107.25');
    ac_assert(
        'list_sum ignora recorte de página',
        ($summary['list_sum']['value'] ?? '') === '107.25'
        && count($contrib->records()) === 2
    );

    $view = AA_Canonical_Shell_View_Composer::compose_family_records(
        $family_registry->family('finance'),
        $cid,
        1,
        1,
        null
    );
    ac_assert(
        'Composer propaga list_sum',
        ($view['capability_contributions']['amount']['list_sum']['value'] ?? '') === '107.25'
    );
    $details = AA_Canonical_Amount_Shell_Presenter::list_details_view($view['capability_contributions']);
    ac_assert(
        'Presenter display',
        is_array($details)
        && ($details['kind'] ?? '') === 'value'
        && ($details['display'] ?? '') === '107.25'
        && ($details['is_negative'] ?? true) === false
    );

    // Lista sin importes
    $wpdb->insert(AA_Canonical_Schema::containers_table_name(), [
        'public_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        'family_id' => $finance_id,
        'title' => 'Sin importes',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', null, '%s', '%s']);
    $cid_empty = (int) $wpdb->insert_id;
    $config->upsert_container_capability($cid_empty, 'amount', true);
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
        'container_id' => $cid_empty,
        'title' => 'Solo título',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', null, '%s', '%s']);
    ac_assert('Sin importes → 0.00', $amount_repo->sum_amounts_for_container($cid_empty) === '0.00');

    // Capability inactiva
    $config->upsert_container_capability($cid, 'amount', false);
    $inactive = $contributor->contribute_for_records_page('finance', $cid, $page_ids);
    ac_assert('Inactiva → not offered', $inactive->offered() === false);
    ac_assert('Inactiva sin list_sum', !isset($inactive->list_summary()['list_sum']));
    ac_assert(
        'Presenter inactiva → null',
        AA_Canonical_Amount_Shell_Presenter::list_details_view([
            'amount' => $inactive->list_summary(),
        ]) === null
    );
    // valores conservados
    ac_assert('Valores conservados al desactivar', $amount_repo->find_amount($record_ids[0]) === '10.50');
    $config->upsert_container_capability($cid, 'amount', true);

    // Overflow individual via repo SUM
    $wpdb->insert(AA_Canonical_Schema::containers_table_name(), [
        'public_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
        'family_id' => $finance_id,
        'title' => 'Overflow',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', null, '%s', '%s']);
    $cid_big = (int) $wpdb->insert_id;
    $config->upsert_container_capability($cid_big, 'amount', true);
    foreach ([1, 2] as $n) {
        $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
            'public_id' => sprintf('ffffffff-ffff-4fff-8fff-%012d', $n),
            'container_id' => $cid_big,
            'title' => 'Big' . $n,
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%d', '%s', null, '%s', '%s']);
        $amount_repo->upsert((int) $wpdb->insert_id, $big, $now);
    }
    $big_sum = $amount_repo->sum_amounts_for_container($cid_big);
    ac_assert('Repo SUM overflow individual', $big_sum === $sum_expected);
    $big_display = AA_Canonical_Amount_List_Sum::format_display($big_sum);
    ac_assert('Display overflow conserva dígitos', strpos($big_display, ',') !== false
        && str_replace(',', '', $big_display) === $sum_expected);

    // Fallo de agregado
    $amt_table = AA_Canonical_Schema::record_amount_table_name();
    $wpdb->query('RENAME TABLE `' . str_replace('`', '``', $amt_table) . '` TO `' . str_replace('`', '``', $amt_table) . '_bak`');
    $failed_contrib = $contributor->contribute_for_records_page('finance', $cid, $page_ids);
    ac_assert(
        'Fallo agregado → list_sum read_failed',
        $failed_contrib->offered()
        && ($failed_contrib->list_summary()['list_sum']['status'] ?? '') === 'read_failed'
    );
    $err_view = AA_Canonical_Amount_Shell_Presenter::list_details_view([
        'amount' => $failed_contrib->list_summary(),
    ]);
    ac_assert('Presenter error total', is_array($err_view) && ($err_view['kind'] ?? '') === 'error');
    $wpdb->query('RENAME TABLE `' . str_replace('`', '``', $amt_table) . '_bak` TO `' . str_replace('`', '``', $amt_table) . '`');

    // Paginación: 16 registros amount=1.00 → total 16.00; page 1 solo 15 items
    $wpdb->insert(AA_Canonical_Schema::containers_table_name(), [
        'public_id' => '12121212-1212-4121-8121-121212121212',
        'family_id' => $finance_id,
        'title' => 'Paginada',
        'details' => 'x',
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', '%s', '%s', '%s']);
    $cid_page = (int) $wpdb->insert_id;
    $config->upsert_container_capability($cid_page, 'amount', true);
    for ($i = 0; $i < 16; $i++) {
        $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
            'public_id' => sprintf('13131313-1313-4131-8131-%012d', $i + 1),
            'container_id' => $cid_page,
            'title' => 'P' . ($i + 1),
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%d', '%s', null, '%s', '%s']);
        $amount_repo->upsert((int) $wpdb->insert_id, '1.00', $now);
    }
    $view_p1 = AA_Canonical_Shell_View_Composer::compose_family_records(
        $family_registry->family('finance'),
        $cid_page,
        1,
        1,
        null
    );
    ac_assert('Página 1 tiene 15 items', count($view_p1['items_view']) === 15);
    ac_assert(
        'list_sum es 16.00 no 15.00',
        ($view_p1['capability_contributions']['amount']['list_sum']['value'] ?? '') === '16.00'
    );

    // Markup panel
    $parent_details = 'desc';
    $parent_iso = '2024-01-01T00:00:00Z';
    $parent_display = '1 ene';
    $parent_has_details_text = true;
    $parent_has_updated = true;
    $amount_list_details = AA_Canonical_Amount_Shell_Presenter::list_details_view(
        $view_p1['capability_contributions']
    );
    ob_start();
    ?>
    <div id="aa-shell-list-details">
        <?php if (is_array($amount_list_details) && ($amount_list_details['kind'] ?? '') === 'value') : ?>
            <?php
            $list_sum_classes = 'aa-shell-list-amount-sum text-base font-semibold m-0';
            $list_sum_classes .= !empty($amount_list_details['is_negative']) ? ' text-red-800' : ' text-gray-900';
            ?>
            <p class="<?php echo esc_attr($list_sum_classes); ?>">
                <span class="sr-only">Total: </span>
                <span aria-hidden="true">Total: $</span><?php echo esc_html((string) $amount_list_details['display']); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
    $html_total = (string) ob_get_clean();
    ac_assert('Markup Total: $16.00', strpos($html_total, 'Total: $') !== false
        && strpos($html_total, '16.00') !== false
        && strpos($html_total, 'aa-shell-list-amount-sum') !== false);

    $neg_details = AA_Canonical_Amount_Shell_Presenter::list_details_view([
        'amount' => [
            'offered' => true,
            'list_sum' => ['status' => 'known_value', 'value' => '-25.50'],
        ],
    ]);
    ac_assert(
        'Negativo tipografía',
        is_array($neg_details)
        && ($neg_details['is_negative'] ?? false) === true
        && ($neg_details['display'] ?? '') === '-25.50'
    );
} catch (\Throwable $e) {
    ac_assert('Excepción inesperada: ' . $e->getMessage(), false);
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
    update_option('aa_db_version', $prior_db_version);
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
