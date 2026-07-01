<?php
/**
 * AC para AA_Google_Calendar_Oauth_Gate_Policy.
 *
 * Ejecutar: php tests/domain/account/test-aa-google-calendar-oauth-gate-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/account/class-aa-google-calendar-oauth-gate-policy.php';

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo "[ OK ] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo "[FAIL] {$label}" . ($detail !== '' ? " - {$detail}" : '') . "\n";
}

ac_assert(
    'sin client_secret requiere consentimiento Freemium',
    AA_Google_Calendar_Oauth_Gate_Policy::requires_freemium_consent_before_oauth(false) === true
);

ac_assert(
    'con client_secret no requiere consentimiento Freemium',
    AA_Google_Calendar_Oauth_Gate_Policy::requires_freemium_consent_before_oauth(true) === false
);

echo "\n{$passed}/{$total} passed.\n";

if ($failed !== []) {
    exit(1);
}

exit(0);
