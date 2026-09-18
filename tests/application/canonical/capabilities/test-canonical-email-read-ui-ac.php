<?php
/**
 * AC — UI/preservación email (orden phone→email→details, mailto, fail-soft).
 *
 * Ejecutar:
 *   php tests/application/canonical/capabilities/test-canonical-email-read-ui-ac.php
 *   AA_WP_ROOT=... php tests/application/canonical/capabilities/test-canonical-email-read-ui-ac.php
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
ac_assert('Card usa Email presenter', strpos($card, 'AA_Canonical_Email_Shell_Presenter') !== false);
$phone_markup = strpos($card, 'sr-only">Teléfono:');
$email_markup = strpos($card, 'Enviar correo:');
$details_markup = strpos($card, 'aa-shell-record-details');
ac_assert(
    'Email markup entre phone y details',
    $phone_markup !== false && $email_markup !== false && $details_markup !== false
    && $phone_markup < $email_markup && $email_markup < $details_markup
);
ac_assert('Card usa esc_attr para href mailto', strpos($card, "esc_attr((string) \$email_card['href'])") !== false);

$index = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php'
);
$title_pos = strpos($index, 'aa-shell-record-title');
$phone_field_pos = strpos($index, 'aa-shell-record-phone-field');
$email_field_pos = strpos($index, 'aa-shell-record-email-field');
$details_field_pos = strpos($index, 'aa-shell-record-details');
ac_assert(
    'Modal: phone antes de email antes de details',
    $title_pos !== false && $phone_field_pos !== false && $email_field_pos !== false && $details_field_pos !== false
    && $title_pos < $phone_field_pos && $phone_field_pos < $email_field_pos && $email_field_pos < $details_field_pos
);
ac_assert('Label Email en repertorio', strpos($index, "'Email'") !== false || strpos($index, 'Email') !== false);
ac_assert('Bloque contacto whatsapp→phone→email', strpos($index, "\$block_order = ['whatsapp', 'phone', 'email']") !== false);
ac_assert('JS email enqueued', strpos($index, 'canonical-shell-email-field.js') !== false);
ac_assert('lists_scope=all create_family_key primero', strpos($index, 'lists_scope=all es solo retorno') !== false);

$js = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-email-field.js'
);
ac_assert('JS omit en read_failed', strpos($js, 'STATUS_READ_FAILED') !== false && strpos($js, "sendMode = 'omit'") !== false);
ac_assert('JS clear/set envía email trim', strpos($js, "formData.append('email', String(input.value || '').trim())") !== false);
ac_assert('JS maneja invalid_email', strpos($js, 'invalid_email') !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
$has_wp = ($wp_load !== '' && is_readable($wp_load));

if ($has_wp) {
    require_once $wp_load;
} elseif (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}

require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Email_Normalizer.php';
require_once $plugin_root . '/includes/admin/ui/modules/canonical_shell/presenters/class-aa-canonical-email-shell-presenter.php';

$card_view = AA_Canonical_Email_Shell_Presenter::card_view([
    'email' => [
        'status' => CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE,
        'value' => 'User+Tag@example.com',
    ],
]);
ac_assert(
    'card_view display + mailto',
    is_array($card_view)
    && ($card_view['kind'] ?? '') === 'value'
    && ($card_view['display'] ?? '') === 'User+Tag@example.com'
    && ($card_view['href'] ?? '') === 'mailto:User%2BTag@example.com'
);

$mailto_cases = [
    'a?b@example.com' => 'mailto:a%3Fb@example.com',
    'a#b@example.com' => 'mailto:a%23b@example.com',
    'a%b@example.com' => 'mailto:a%25b@example.com',
    'a&b@example.com' => 'mailto:a%26b@example.com',
];
foreach ($mailto_cases as $email => $href) {
    $cv = AA_Canonical_Email_Shell_Presenter::card_view([
        'email' => [
            'status' => CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE,
            'value' => $email,
        ],
    ]);
    ac_assert(
        'presenter mailto ' . $email,
        is_array($cv) && ($cv['href'] ?? '') === $href
    );
}

$bad_edit = AA_Canonical_Email_Shell_Presenter::edit_payload_fragment([
    'email' => [
        'status' => CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE,
        'value' => 'not-an-email',
    ],
]);
ac_assert(
    'No interpretable → read_failed en edit',
    is_array($bad_edit) && ($bad_edit['status'] ?? '') === CanonicalCapabilityRecordReadState::STATUS_READ_FAILED
);

$err_card = AA_Canonical_Email_Shell_Presenter::card_view([
    'email' => ['status' => CanonicalCapabilityRecordReadState::STATUS_READ_FAILED],
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
require_once $plugin_root . '/includes/repositories/CanonicalRecordEmailRepository.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/email/CanonicalEmailRecordsPageContributor.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-family-provisioner.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$temp_prefix = 'tmp_emr_' . substr(md5((string) microtime(true)), 0, 8) . '_';
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
        ->register(new AA_Canonical_Capability_Definition('email', AA_Canonical_Capability_Definition::SCOPE_RECORD, true))
        ->freeze();
    $config = new CanonicalCapabilityConfigRepository($wpdb);
    $email_repo = new CanonicalRecordEmailRepository($wpdb);
    $contributor = new CanonicalEmailRecordsPageContributor($config, $email_repo, $cap_reg);

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
    $config->upsert_container_capability($container_id, 'email', true);
    $wpdb->insert(AA_Canonical_Schema::records_table_name(), [
        'public_id' => '22222222-2222-4222-8222-222222222222',
        'container_id' => $container_id,
        'title' => 'R1',
        'details' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], ['%s', '%d', '%s', '%s', '%s', '%s']);
    $record_id = (int) $wpdb->insert_id;
    $email_repo->upsert($record_id, 'User+Tag@example.com', $now);

    $ok = $contributor->contribute_for_records_page('contact', $container_id, [$record_id]);
    ac_assert('offered true', $ok->offered() === true);
    $state = $ok->state_for($record_id);
    ac_assert(
        'known_value',
        $state !== null && $state->status() === CanonicalCapabilityRecordReadState::STATUS_KNOWN_VALUE
        && $state->value() === 'User+Tag@example.com'
    );

    $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', AA_Canonical_Schema::record_email_table_name()) . '`');
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
