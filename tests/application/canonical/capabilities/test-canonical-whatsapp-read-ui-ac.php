<?php
/**
 * AC — UI/preservación whatsapp (orden, enlace wa.me, fail-soft).
 *
 * Ejecutar:
 *   php tests/application/canonical/capabilities/test-canonical-whatsapp-read-ui-ac.php
 *   AA_WP_ROOT=... php tests/application/canonical/capabilities/test-canonical-whatsapp-read-ui-ac.php
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

echo "=== 1. Estructura UI ===\n";
$card = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php'
);
ac_assert('Card usa WhatsApp presenter', strpos($card, 'AA_Canonical_Whatsapp_Shell_Presenter') !== false);
$wa_markup = strpos($card, 'aa-shell-record-whatsapp__icon');
$phone_markup = strpos($card, 'sr-only">Teléfono:');
$details_markup = strpos($card, 'aa-shell-record-details');
ac_assert(
    'WhatsApp markup antes de phone y details',
    $wa_markup !== false && $phone_markup !== false && $details_markup !== false
    && $wa_markup < $phone_markup && $phone_markup < $details_markup
);
ac_assert('Enlace wa.me en card', strpos($card, 'wa.me') !== false || strpos($card, 'href') !== false);
ac_assert('target blank + noopener', strpos($card, 'target="_blank"') !== false && strpos($card, 'noopener noreferrer') !== false);

$index = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
);
$title_pos = strpos($index, 'aa-shell-record-title');
$wa_field_pos = strpos($index, 'aa-shell-record-whatsapp-field');
$phone_field_pos = strpos($index, 'aa-shell-record-phone-field');
$details_field_pos = strpos($index, 'aa-shell-record-details');
ac_assert(
    'Modal: whatsapp antes de phone antes de details',
    $title_pos !== false && $wa_field_pos !== false && $phone_field_pos !== false && $details_field_pos !== false
    && $title_pos < $wa_field_pos && $wa_field_pos < $phone_field_pos && $phone_field_pos < $details_field_pos
);
ac_assert('Label WhatsApp en repertorio', strpos($index, "'WhatsApp'") !== false || strpos($index, 'WhatsApp') !== false);
ac_assert('Sort whatsapp antes de phone', strpos($index, "\$ka === 'whatsapp' && \$kb === 'phone'") !== false);
ac_assert('JS whatsapp enqueued', strpos($index, 'canonical-shell-whatsapp-field.js') !== false);

$js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-whatsapp-field.js'
);
ac_assert('JS omit en read_failed', strpos($js, "STATUS_READ_FAILED") !== false && strpos($js, "sendMode = 'omit'") !== false);
ac_assert('JS clear envía vacío nacional', strpos($js, "formData.append('whatsapp', '')") !== false);
ac_assert('JS no envía solo código país', strpos($js, "formData.append('whatsapp', '+' + countrySelect.value)") === false);
ac_assert('JS maneja códigos phone del normalizador', strpos($js, 'invalid_phone') !== false);

$presenter = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-whatsapp-shell-presenter.php'
);
ac_assert('Presenter construye https://wa.me/', strpos($presenter, "'https://wa.me/'") !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
$has_wp = ($wp_load !== '' && is_readable($wp_load));

if ($has_wp) {
    require_once $wp_load;
} elseif (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}

require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Phone_Normalizer.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-whatsapp-shell-presenter.php';

$card_view = AA_Canonical_Whatsapp_Shell_Presenter::card_view([
    'whatsapp' => [
        'status' => CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE,
        'value' => '+525555555555',
    ],
]);
ac_assert(
    'card_view display + href',
    is_array($card_view)
    && ($card_view['kind'] ?? '') === 'value'
    && ($card_view['display'] ?? '') === '+52 5555555555'
    && ($card_view['href'] ?? '') === 'https://wa.me/525555555555'
);
ac_assert(
    'wa_me_url helper',
    AA_Canonical_Whatsapp_Shell_Presenter::wa_me_url('+525555555555') === 'https://wa.me/525555555555'
);

$bad_edit = AA_Canonical_Whatsapp_Shell_Presenter::edit_payload_fragment([
    'whatsapp' => [
        'status' => CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE,
        'value' => '+999111111111',
    ],
]);
ac_assert(
    'No interpretable → read_failed en edit',
    is_array($bad_edit) && ($bad_edit['status'] ?? '') === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED
);

$err_card = AA_Canonical_Whatsapp_Shell_Presenter::card_view([
    'whatsapp' => ['status' => CanonicalCapabilityRecordReadState::STATUS_READ_FAILED],
]);
ac_assert('read_failed card error', is_array($err_card) && ($err_card['kind'] ?? '') === 'error');

if (!$has_wp) {
    echo "[INFO / SKIP] MySQL contributor no ejecutado (AA_WP_ROOT ausente).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/repositories/CanonicalCapabilityConfigRepository.php';
require_once $plugin_root . '/includes/repositories/CanonicalRecordWhatsappRepository.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/whatsapp/CanonicalWhatsappRecordsPageContributor.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_war_' . substr(md5((string) microtime(true)), 0, 8) . '_';
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

echo "\n=== 2. MySQL contributor fail-soft ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();
    AA_Canonical_Schema::install();
    AA_Canonical_Core_Bootstrap::bootstrap();
    $family_registry = AA_Canonical_Core_Bootstrap::build_registry();
    (new AA_Canonical_Family_Provisioner($wpdb))->ensure_declared_families($family_registry);

    $cap_reg = (new AA_Canonical_Capability_Registry())
        ->register(new AA_Canonical_Capability_Definition('whatsapp', AA_Canonical_Capability_Definition::SCOPE_RECORD, true))
        ->freeze();
    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $whatsapp_repo = new CanonicalRecordWhatsappRepository($wpdb);
    $contributor = new CanonicalWhatsappRecordsPageContributor($config, $whatsapp_repo, $cap_reg);

    $family_id = (int) $config->resolve_family_id('contact');
    $now = gmdate('Y-m-d H:i:s');
    $wpdb->insert(AA_Canonical_Schema::containers_table_name(), [
        'public_id' => '11111111-1111-4111-8111-111111111111',
        'family_id' => $family_id,
        'title' => 'Lista',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', '%s', '%s', '%s']);
    $container_id = (int) $wpdb->insert_id;
    $config->upsert_container_capability($container_id, 'whatsapp', true);
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '22222222-2222-4222-8222-222222222222',
        'container_id' => $container_id,
        'title' => 'R1',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', '%s', '%s', '%s']);
    $record_id = (int) $wpdb->insert_id;
    $whatsapp_repo->upsert($record_id, '+525555555555', $now);

    $ok = $contributor->contribute_for_records_page('contact', $container_id, [$record_id]);
    ac_assert('offered true', $ok->offered() === true);
    $state = $ok->state_for($record_id);
    ac_assert(
        'known_value',
        $state !== null && $state->status() === CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE
        && $state->value() === '+525555555555'
    );

    $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', AA_Canonical_Schema::record_whatsapp_table_name()) . '`');
    $failed_contrib = $contributor->contribute_for_records_page('contact', $container_id, [$record_id]);
    ac_assert('fail-soft offered', $failed_contrib->offered() === true);
    $failed_state = $failed_contrib->state_for($record_id);
    ac_assert(
        'fail-soft read_failed',
        $failed_state !== null
        && $failed_state->status() === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED
    );
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
