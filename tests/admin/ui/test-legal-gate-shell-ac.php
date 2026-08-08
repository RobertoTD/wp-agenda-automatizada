<?php
/**
 * AC — Legal gate shell branch + blocking UI (source contracts).
 *
 * Ejecutar: php tests/admin/ui/test-legal-gate-shell-ac.php
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
    } else {
        $failed[] = $label;
        echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
    }
}

$router = (string) file_get_contents($plugin_root . '/includes/admin/ui/index.php');
$gate = (string) file_get_contents($plugin_root . '/includes/admin/ui/legal-gate/index.php');
$js = (string) file_get_contents($plugin_root . '/includes/admin/ui/legal-gate/legal-gate.js');
$iframe = (string) file_get_contents($plugin_root . '/includes/admin/iframe-test.php');
$agenda = (string) file_get_contents($plugin_root . '/includes/routes/agenda-app.php');
$ajax = (string) file_get_contents($plugin_root . '/includes/http/ajax/LegalGateAjax.php');

ac_assert('router loads ResolveShellAccessUseCase', strpos($router, 'ResolveShellAccessUseCase') !== false);
ac_assert('router branches on ACCESS_LEGAL_GATE', strpos($router, 'ACCESS_LEGAL_GATE') !== false);
ac_assert('router loads legal-gate when gated', strpos($router, "legal-gate/index.php") !== false);
ac_assert('router keeps manage_options for shell', strpos($router, "current_user_can('manage_options')") !== false);
ac_assert('router still loads layout for free/full', strpos($router, 'shared/layout.php') !== false);
ac_assert(
    'gate branch happens before layout',
    strpos($router, "legal-gate/index.php") < strpos($router, 'shared/layout.php')
);
ac_assert('router does not unlock only on ready string', strpos($router, "\$aa_legal_status !== 'ready'") === false);
ac_assert(
    'router expediente URL gate before layout',
    strpos($router, "\$view_raw === 'expediente'") !== false
    && strpos($router, "\$view_raw === 'expediente'") < strpos($router, 'shared/layout.php')
);
ac_assert(
    'router expediente gate uses ACCESS_FULL',
    strpos($router, 'AA_Shell_Access::ACCESS_FULL') !== false
);

ac_assert('ajax status uses ResolveShellAccessUseCase', strpos($ajax, 'ResolveShellAccessUseCase') !== false);
ac_assert('ajax exposes access in payload', strpos($ajax, "'access'") !== false || strpos($ajax, '"access"') !== false);

ac_assert('iframe no longer hard-dies on manage_options before UI', strpos($iframe, "wp_die('Acceso denegado'") === false);
ac_assert('agenda-app no longer hard-dies on manage_options before redirect', strpos($agenda, "wp_die('Acceso denegado'") === false);

ac_assert('gate links to https://deoia.com/terminos/', strpos($gate, 'https://deoia.com/terminos/') !== false || strpos($gate, 'AA_Agenda_Terms_Consent::HUMAN_URL') !== false);
ac_assert('gate has terms checkbox', strpos($gate, 'aa-legal-gate-consent') !== false);
ac_assert('gate has privacy checkbox', strpos($gate, 'aa-legal-gate-privacy-consent') !== false);
ac_assert('gate dual status branch', strpos($gate, 'needs_privacy_and_terms') !== false);
ac_assert('gate privacy consent text from domain', strpos($gate, 'AA_Agenda_Privacy_Consent::TEXT') !== false);
ac_assert('accept button starts disabled', strpos($gate, 'id="aa-legal-gate-accept" disabled') !== false);
ac_assert('gate exposes AA_LEGAL_GATE_DATA', strpos($gate, 'AA_LEGAL_GATE_DATA') !== false);
ac_assert('gate exposes acceptDualAction', strpos($gate, 'acceptDualAction') !== false);
ac_assert('gate exposes canAcceptDual', strpos($gate, 'canAcceptDual') !== false);
ac_assert('gate exposes privacyVersion', strpos($gate, 'privacyVersion') !== false);
ac_assert('gate data has no account_id', strpos($gate, 'account_id') === false);
ac_assert('gate data has no installation_id', strpos($gate, 'installation_id') === false);
ac_assert('gate data has no subscription_request_id', strpos($gate, 'subscription_request_id') === false);
ac_assert('gate data has no client_secret', strpos($gate, 'client_secret') === false);
ac_assert('gate data has no HMAC secret fields', stripos($gate, 'hmac') === false);
ac_assert('gate data has no wp_user_id', strpos($gate, 'wp_user_id') === false);
ac_assert('gate is not a modal', stripos($gate, 'modal') === false);
ac_assert('gate dies after render', strpos($gate, 'die();') !== false);
ac_assert('privacy_required copy present', strpos($gate, 'privacy_required') !== false);
ac_assert('provisioning_request_missing copy present', strpos($gate, 'provisioning_request_missing') !== false);
ac_assert('non-admin note without accept', strpos($gate, 'Un administrador de la instalación') !== false);

ac_assert('js dual mode flag', strpos($js, 'cfg.canAcceptDual') !== false);
ac_assert('js requires both checkboxes for dual', strpos($js, 'privacyConsent.checked') !== false && strpos($js, 'termsConsent.checked') !== false);
ac_assert('js sends terms_consent', strpos($js, "terms_consent: '1'") !== false);
ac_assert('js sends privacy_consent', strpos($js, "privacy_consent: '1'") !== false);
ac_assert('js sends terms_document_version from cfg', strpos($js, 'terms_document_version: cfg.termsVersion') !== false);
ac_assert('js sends privacy_document_version from cfg', strpos($js, 'privacy_document_version: cfg.privacyVersion') !== false);
ac_assert('js dual uses acceptDualAction', strpos($js, 'cfg.acceptDualAction') !== false);
ac_assert('js does not send wp_user_id', strpos($js, 'wp_user_id') === false);
ac_assert('js does not send account_id', strpos($js, 'account_id') === false);
ac_assert('js does not send installation_id', strpos($js, 'installation_id') === false);
ac_assert('js guards double submit', strpos($js, 'submitting') !== false);
ac_assert('js reloads on success', strpos($js, 'window.location.reload') !== false);
ac_assert('js handles privacy outdated', strpos($js, 'privacy_notice_version_outdated') !== false);
ac_assert('js handles terms outdated', strpos($js, 'terms_document_version_outdated') !== false);
ac_assert('js retry checks access free/full', strpos($js, "access === 'free'") !== false && strpos($js, "access === 'full'") !== false);

echo "\n{$passed}/{$total} passed\n";
exit($failed ? 1 : 0);
