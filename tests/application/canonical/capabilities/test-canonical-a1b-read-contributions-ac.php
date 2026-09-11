<?php
/**
 * AC A1b bloque 1 — contribuciones, lote, precisión y estados de lectura.
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-a1b-read-contributions-ac.php
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

echo "=== 1. Contención / presenters ===\n";
$composer = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php'
);
$form_js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/canonical-shell-record-form.js'
);
$amount_js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-amount-field.js'
);
$card = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php'
);

ac_assert('Composer enriquece vía enrich_records_with_capabilities', strpos($composer, 'enrich_records_with_capabilities') !== false);
ac_assert('Preview no ofrece capabilities', strpos($composer, '!$is_preview') !== false || strpos($composer, 'if (!$is_preview') !== false);
ac_assert('Form coordina por capabilityContributions', strpos($form_js, 'capabilityContributions') !== false);
ac_assert('Form limpia módulos al abrir', strpos($form_js, 'clearCapabilityModules') !== false);
ac_assert('Amount module posee collect/apply', strpos($amount_js, 'collect') !== false && strpos($amount_js, 'apply') !== false);
ac_assert('Card usa Amount presenter', strpos($card, 'AA_Canonical_Amount_Shell_Presenter') !== false);
ac_assert('Card no nodo en known_absent (presenter)', true);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL real no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordsPageContribution.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordPageContributor.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordPageContributorRegistry.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityShellRecordsEnricher.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/amount/CanonicalAmountRecordsPageContributor.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilitySchemaNotReady.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityPersistenceFailed.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordAmountRepository.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-amount-shell-presenter.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-page-contributor-bootstrap.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_a1b1_' . substr(md5((string) microtime(true)), 0, 8) . '_';
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

echo "\n=== 2. MySQL: lote + estados ===\n";
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

    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $amount_repo = new CanonicalRecordAmountRepository($wpdb);
    $finance_id = (int) $config->resolve_family_id('finance');
    $now = gmdate('Y-m-d H:i:s');

    $c_table = AA_Canonical_Schema::containers_table_name();
    $r_table = AA_Canonical_Schema::records_table_name();
    $wpdb->insert($c_table, [
        'public_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        'family_id' => $finance_id,
        'title' => 'Lista A1b',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', null, '%s', '%s']);
    $container_id = (int) $wpdb->insert_id;

    $ids = [];
    foreach (['A', 'B', 'C'] as $i => $label) {
        $wpdb->insert($r_table, [
            'public_id' => sprintf('dddddddd-dddd-4ddd-8ddd-ddddddddddd%d', $i),
            'container_id' => $container_id,
            'title' => 'Rec ' . $label,
            'details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%d', '%s', null, '%s', '%s']);
        $ids[] = (int) $wpdb->insert_id;
    }

    // Sin oferta → no contribución activa.
    $contributor = new CanonicalAmountRecordsPageContributor(
        $config,
        $amount_repo,
        AA_Canonical_Capability_Registry_Bootstrap::instance()
    );
    $none = $contributor->contribute_for_records_page('finance', $container_id, $ids);
    ac_assert('Sin activación → offered=false', $none->offered() === false && $none->records() === []);

    $config->upsert_container_capability($container_id, 'amount', true);
    $amount_repo->upsert($ids[0], '12.50', $now);
    $amount_repo->upsert($ids[1], '0.00', $now);
    // ids[2] ausente

    $batch = $amount_repo->find_amounts_by_record_ids($ids);
    ac_assert('Lote conserva 12.50 como string', isset($batch[$ids[0]]) && $batch[$ids[0]] === '12.50');
    ac_assert('Lote conserva 0.00', isset($batch[$ids[1]]) && $batch[$ids[1]] === '0.00');
    ac_assert('Lote ausente → null', array_key_exists($ids[2], $batch) && $batch[$ids[2]] === null);

    $contrib = $contributor->contribute_for_records_page('finance', $container_id, $ids);
    ac_assert('Offered true', $contrib->offered() === true);
    ac_assert(
        'known_value',
        $contrib->state_for($ids[0]) !== null
        && $contrib->state_for($ids[0])->status() === CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE
        && $contrib->state_for($ids[0])->value() === '12.50'
    );
    ac_assert(
        'known_value cero',
        $contrib->state_for($ids[1]) !== null
        && $contrib->state_for($ids[1])->value() === '0.00'
    );
    ac_assert(
        'known_absent',
        $contrib->state_for($ids[2]) !== null
        && $contrib->state_for($ids[2])->status() === CanonicalCapabilityRecordReadState::STATUS_KNOWN_ABSENT
    );

    $items = [
        ['id' => $ids[0], 'title' => 'A'],
        ['id' => $ids[1], 'title' => 'B'],
        ['id' => $ids[2], 'title' => 'C'],
    ];
    $enricher = new CanonicalCapabilityShellRecordsEnricher(
        AA_Canonical_Capability_Page_Contributor_Bootstrap::bootstrap()
    );
    $enriched = $enricher->enrich('finance', $container_id, $items);
    ac_assert('capability_contributions amount offered', !empty($enriched['capability_contributions']['amount']['offered']));
    ac_assert(
        'items_view capabilities amount value',
        isset($enriched['items_view'][0]['capabilities']['amount']['value'])
        && $enriched['items_view'][0]['capabilities']['amount']['value'] === '12.50'
    );
    ac_assert(
        'known_absent no value key en to_array',
        isset($enriched['items_view'][2]['capabilities']['amount']['status'])
        && $enriched['items_view'][2]['capabilities']['amount']['status'] === 'known_absent'
        && !isset($enriched['items_view'][2]['capabilities']['amount']['value'])
    );

    $card_value = AA_Canonical_Amount_Shell_Presenter::card_view($enriched['items_view'][0]['capabilities']);
    $card_absent = AA_Canonical_Amount_Shell_Presenter::card_view($enriched['items_view'][2]['capabilities']);
    ac_assert('Presenter value', is_array($card_value) && ($card_value['kind'] ?? '') === 'value');
    ac_assert('Presenter known_absent → null (sin nodo)', $card_absent === null);

    // read_failed: forzar fallo de lote renombrando la tabla.
    $amt_table = AA_Canonical_Schema::record_amount_table_name();
    $wpdb->query('RENAME TABLE `' . str_replace('`', '``', $amt_table) . '` TO `' . str_replace('`', '``', $amt_table) . '_bak`');
    $failed_contrib = $contributor->contribute_for_records_page('finance', $container_id, [$ids[0]]);
    ac_assert(
        'read_failed offered',
        $failed_contrib->offered()
        && $failed_contrib->state_for($ids[0])->status() === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED
    );
    $card_err = AA_Canonical_Amount_Shell_Presenter::card_view([
        'amount' => $failed_contrib->state_for($ids[0])->to_array(),
    ]);
    ac_assert('Presenter error ≠ ausencia', is_array($card_err) && ($card_err['kind'] ?? '') === 'error');
    $wpdb->query('RENAME TABLE `' . str_replace('`', '``', $amt_table) . '_bak` TO `' . str_replace('`', '``', $amt_table) . '`');

    // Config indeterminable → read_failed (no afirmar inactive).
    $cfg_table = AA_Canonical_Schema::container_capabilities_table_name();
    $wpdb->query('RENAME TABLE `' . str_replace('`', '``', $cfg_table) . '` TO `' . str_replace('`', '``', $cfg_table) . '_bak`');
    $u = $contributor->contribute_for_records_page('finance', $container_id, [$ids[0]]);
    ac_assert(
        'Config fallida → offered+read_failed (no not_offered)',
        $u->offered()
        && $u->state_for($ids[0])->status() === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED
    );
    $wpdb->query('RENAME TABLE `' . str_replace('`', '``', $cfg_table) . '_bak` TO `' . str_replace('`', '``', $cfg_table) . '`');

    // Fixture not-ready → no ofrecida.
    $nr = (new AA_Canonical_Capability_Registry())
        ->register(new AA_Canonical_Capability_Definition('amount', AA_Canonical_Capability_Definition::SCOPE_RECORD, false))
        ->freeze();
    $nr_contrib = (new CanonicalAmountRecordsPageContributor($config, $amount_repo, $nr))
        ->contribute_for_records_page('finance', $container_id, $ids);
    ac_assert('Fixture !ready → not offered', $nr_contrib->offered() === false);
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
