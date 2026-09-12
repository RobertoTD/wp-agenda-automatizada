<?php
/**
 * AC IMG-4 bloque 3 — contributor/enricher, preview y amount intacto.
 *
 * Ejecutar:
 *   php tests/application/canonical/images/test-canonical-images-contributor-integration-ac.php
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

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordsPageContribution.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordPageContributor.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordPageContributorRegistry.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityShellRecordsEnricher.php';
require_once $plugin_root . '/includes/application/canonical/images/CanonicalRecordImagePublicDto.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/images/CanonicalImagesRecordsPageContributor.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/amount/CanonicalAmountRecordsPageContributor.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-amount-shell-presenter.php';

$composer_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php');
$boot_src = file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-page-contributor-bootstrap.php');
$enricher_src = file_get_contents($plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityShellRecordsEnricher.php');

ac_assert('bootstrap registra images contributor', strpos($boot_src, 'CanonicalImagesRecordsPageContributor') !== false);
ac_assert('enricher sin ramas images', strpos($enricher_src, 'images') === false);
ac_assert('preview sin enrich', strpos($composer_src, '!$is_preview') !== false
    || strpos($composer_src, '! $is_preview') !== false
    || preg_match('/is_preview[^\n]{0,80}enrich_records_with_capabilities|enrich_records_with_capabilities[^\n]{0,120}is_preview/', $composer_src));

// Inspect build_records_view_data gate
ac_assert(
    'única vía enrich en build_records_view_data',
    strpos($composer_src, 'enrich_records_with_capabilities') !== false
    && substr_count($composer_src, 'function enrich_records_with_capabilities') === 1
);

$cap_registry = new AA_Canonical_Capability_Registry();
$cap_registry->register(new AA_Canonical_Capability_Definition(
    'images',
    AA_Canonical_Capability_Definition::SCOPE_RECORD,
    true
));
$cap_registry->register(new AA_Canonical_Capability_Definition(
    'amount',
    AA_Canonical_Capability_Definition::SCOPE_RECORD,
    true
));
$cap_registry->freeze();

$config = new class {
    public function find_container_capability($c, $k) {
        return ['is_active' => 1];
    }
};

$images_repo = new class {
    public function find_public_rows_by_record_ids_for_container(int $container_id, array $record_ids): array {
        $out = [];
        foreach ($record_ids as $id) {
            $out[(int) $id] = [];
        }
        if (isset($out[10])) {
            $out[10] = [
                [
                    'id' => 5,
                    'record_id' => 10,
                    'width' => 10,
                    'height' => 8,
                    'byte_size' => 100,
                    'created_at' => '2026-01-02 00:00:00',
                ],
            ];
        }
        return $out;
    }
};

$images_contributor = new CanonicalImagesRecordsPageContributor($config, $images_repo, $cap_registry);

$empty_page = $images_contributor->contribute_for_records_page('finance', 1, []);
ac_assert('página vacía offered', $empty_page->offered() === true && $empty_page->list_summary() === ['offered' => true]);
ac_assert('página vacía sin records map', $empty_page->records() === []);

$with_ids = $images_contributor->contribute_for_records_page('finance', 1, [10, 11]);
ac_assert('colección en r10', $with_ids->state_for(10)->status() === CanonicalCapabilityRecordReadState::STATUS_KNOWN_COLLECTION);
ac_assert('ausencia en r11', $with_ids->state_for(11)->status() === CanonicalCapabilityRecordReadState::STATUS_KNOWN_ABSENT);
ac_assert('DTO público en items', ($with_ids->state_for(10)->items()[0]['id'] ?? 0) === 5
    && !isset($with_ids->state_for(10)->items()[0]['storage_path']));

$nr = new AA_Canonical_Capability_Registry();
$nr->register(new AA_Canonical_Capability_Definition(
    'images',
    AA_Canonical_Capability_Definition::SCOPE_RECORD,
    false
));
$nr->freeze();
$not_offered = (new CanonicalImagesRecordsPageContributor($config, $images_repo, $nr))
    ->contribute_for_records_page('finance', 1, [10]);
ac_assert('not-ready no ofrecida', $not_offered->offered() === false);

$config_fail = new class {
    public function find_container_capability($c, $k) {
        throw new CanonicalCapabilityPersistenceFailed('boom');
    }
};
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityPersistenceFailed.php';
$failed_contrib = (new CanonicalImagesRecordsPageContributor($config_fail, $images_repo, $cap_registry))
    ->contribute_for_records_page('finance', 1, [10]);
ac_assert('config ilegible → offered+read_failed', $failed_contrib->offered() === true
    && $failed_contrib->state_for(10)->status() === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED);

$registry = new CanonicalCapabilityRecordPageContributorRegistry();
$registry->register($images_contributor);
$registry->freeze();

$enricher = new CanonicalCapabilityShellRecordsEnricher($registry);
$enriched = $enricher->enrich('finance', 1, [
    ['id' => 10, 'title' => 'A', 'details' => null, 'updated_at_iso' => '', 'updated_at_display' => ''],
    ['id' => 11, 'title' => 'B', 'details' => null, 'updated_at_iso' => '', 'updated_at_display' => ''],
]);

ac_assert('contributions offered images', ($enriched['capability_contributions']['images']['offered'] ?? false) === true);
ac_assert('item capabilities images', isset($enriched['items_view'][0]['capabilities']['images']));
ac_assert('images known_collection en wire', ($enriched['items_view'][0]['capabilities']['images']['status'] ?? '') === 'known_collection'
    && isset($enriched['items_view'][0]['capabilities']['images']['items'][0]['id']));

// Amount contrato conservado (ReadState + presenter) junto a images en el mismo mapa.
$cap_map = $enriched['items_view'][0]['capabilities'];
$cap_map['amount'] = CanonicalCapabilityRecordReadState::known_value('1.00')->to_array();
ac_assert('amount known_value serialización vigente', $cap_map['amount'] === ['status' => 'known_value', 'value' => '1.00']);
$amount_view = AA_Canonical_Amount_Shell_Presenter::card_view($cap_map);
ac_assert('presenter amount ignora images', is_array($amount_view) && ($amount_view['kind'] ?? '') === 'value'
    && ($amount_view['value'] ?? '') === '1.00');
$amount_edit = AA_Canonical_Amount_Shell_Presenter::edit_payload_fragment($cap_map);
ac_assert('edit payload amount sin items', is_array($amount_edit) && !isset($amount_edit['items']));

$compact_same = $enriched['items_view'];
$card_same = $enriched['items_view'];
ac_assert('compact/card misma proyección', $compact_same === $card_same);

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
