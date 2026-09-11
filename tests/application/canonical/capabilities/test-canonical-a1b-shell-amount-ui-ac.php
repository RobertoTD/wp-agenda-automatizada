<?php
/**
 * AC A1b bloque 2 — shell genérico + módulo amount (SSR/presenters + contratos JS).
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/application/canonical/capabilities/test-canonical-a1b-shell-amount-ui-ac.php
 *   scripts/safe-node-test.sh tests/js/canonical-shell-amount-field.test.js
 *   scripts/safe-node-test.sh tests/js/canonical-shell-record-form.test.js
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

echo "=== 1. Contratos UI estáticos ===\n";

$index = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
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
$composer = (string) file_get_contents(
    $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php'
);

ac_assert('Modal monta capability fields tras details', strpos($index, 'aa-shell-record-capability-fields') !== false);
ac_assert('Campo amount SSR condicionado a offered', strpos($index, "capability_contributions['amount']") !== false || strpos($index, 'amount_offered') !== false);
ac_assert('Boot SSR capabilityContributions', strpos($index, 'capabilityContributions') !== false);
ac_assert('Carga amount-field.js antes del form', strpos($index, 'canonical-shell-amount-field.js') !== false
    && strpos($index, 'canonical-shell-amount-field.js') < strpos($index, 'canonical-shell-record-form.js'));
ac_assert('Form clear→apply en openModal', strpos($form_js, 'clearCapabilityModules()') !== false
    && strpos($form_js, 'applyCapabilityModules') !== false);
ac_assert('Form collectCapabilityModules en submit', strpos($form_js, 'collectCapabilityModules(body)') !== false);
ac_assert('Form handleCapabilityError', strpos($form_js, 'handleCapabilityError') !== false);
ac_assert('Amount read_failed omite envío', strpos($amount_js, "sendMode = 'omit'") !== false
    && strpos($amount_js, 'STATUS_READ_FAILED') !== false);
ac_assert('Amount vacío se envía (clear A1a)', strpos($amount_js, "formData.append('amount', input.value)") !== false);
ac_assert('Card importe en panel compacto', strpos($card, 'aa-shell-record-amount') !== false);
ac_assert('Card error distinguible', strpos($card, 'aa-shell-record-amount-error') !== false);
ac_assert('build_records_view_data usa enrich', strpos($composer, 'enrich_records_with_capabilities') !== false);
ac_assert('Preview salta enrich', preg_match('/if\s*\(\s*!\s*\$is_preview\b/', $composer) === 1);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL/SSR real no ejecutado (AA_WP_ROOT ausente).\n";
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
$temp_prefix = 'tmp_a1b2_' . substr(md5((string) microtime(true)), 0, 8) . '_';
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

echo "\n=== 2. build_records_view_data enrichment ===\n";
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
        'public_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
        'family_id' => $finance_id,
        'title' => 'Lista UI',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', null, '%s', '%s']);
    $cid = (int) $wpdb->insert_id;

    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
        'container_id' => $cid,
        'title' => 'Con importe',
        'details' => 'detalle',
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', '%s', '%s', '%s']);
    $rid = (int) $wpdb->insert_id;
    $config->upsert_container_capability($cid, 'amount', true);
    $amount_repo->upsert($rid, '99.01', $now);

    $view = AA_Canonical_Shell_View_Composer::compose_family_records(
        $family_registry->family('finance'),
        $cid,
        1,
        1,
        null
    );
    ac_assert('View records tiene capability_contributions', isset($view['capability_contributions']['amount']['offered'])
        && $view['capability_contributions']['amount']['offered'] === true);
    ac_assert(
        'Item enriquecido known_value',
        isset($view['items_view'][0]['capabilities']['amount']['status'])
        && $view['items_view'][0]['capabilities']['amount']['status'] === 'known_value'
        && $view['items_view'][0]['capabilities']['amount']['value'] === '99.01'
    );

    // Lista sin amount
    $wpdb->insert(AA_Canonical_Schema::containers_table_name(), [
        'public_id' => '11111111-1111-4111-8111-111111111111',
        'family_id' => $finance_id,
        'title' => 'Sin amount',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', null, '%s', '%s']);
    $cid2 = (int) $wpdb->insert_id;
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '22222222-2222-4222-8222-222222222222',
        'container_id' => $cid2,
        'title' => 'Base',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', null, '%s', '%s']);

    $view2 = AA_Canonical_Shell_View_Composer::compose_family_records(
        $family_registry->family('finance'),
        $cid2,
        1,
        1,
        null
    );
    ac_assert(
        'Lista sin activación → amount not offered',
        isset($view2['capability_contributions']['amount'])
        && $view2['capability_contributions']['amount']['offered'] === false
    );
    ac_assert(
        'Item sin clave capabilities amount',
        !isset($view2['items_view'][0]['capabilities']['amount'])
    );

    // Render parcial compacto
    $item = $view['items_view'][0];
    $card_title = (string) $item['title'];
    $card_details = $item['details'] ?? null;
    $card_iso = (string) ($item['updated_at_iso'] ?? '');
    $card_display = (string) ($item['updated_at_display'] ?? '');
    $card_record_id = (int) $item['id'];
    $card_capabilities = $item['capabilities'] ?? null;
    $show_edit_record = true;
    $shell_record_presentation = 'compact';
    ob_start();
    require $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php';
    $html_compact = (string) ob_get_clean();
    ac_assert('Compact muestra 99.01', strpos($html_compact, '99.01') !== false);
    ac_assert('Compact data-aa-record incluye capabilities', strpos($html_compact, 'known_value') !== false);

    $shell_record_presentation = 'card';
    ob_start();
    require $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php';
    $html_card = (string) ob_get_clean();
    ac_assert('Card mode muestra importe', strpos($html_card, '99.01') !== false);

    $card_capabilities = [
        'amount' => ['status' => CanonicalCapabilityRecordReadState::STATUS_KNOWN_ABSENT],
    ];
    ob_start();
    require $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php';
    $html_absent = (string) ob_get_clean();
    ac_assert('known_absent sin nodo amount', strpos($html_absent, 'aa-shell-record-amount') === false);

    $card_capabilities = [
        'amount' => ['status' => CanonicalCapabilityRecordReadState::STATUS_READ_FAILED],
    ];
    ob_start();
    require $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php';
    $html_fail = (string) ob_get_clean();
    ac_assert('read_failed muestra error', strpos($html_fail, 'aa-shell-record-amount-error') !== false);
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
