<?php
/**
 * AC — AA_Agenda_Terms_Consent domain constants/validation.
 *
 * Ejecutar: php tests/domain/legal/test-aa-agenda-terms-consent-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/legal/class-aa-agenda-terms-consent.php';

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
    'human URL is deoia terminos',
    AA_Agenda_Terms_Consent::HUMAN_URL === 'https://deoia.com/terminos/'
);
ac_assert(
    'consent text is non-empty',
    AA_Agenda_Terms_Consent::TEXT !== ''
);
ac_assert('valid version 2026-08-03.1', AA_Agenda_Terms_Consent::version_is_valid('2026-08-03.1'));
ac_assert('valid version 2026-08-02.10', AA_Agenda_Terms_Consent::version_is_valid('2026-08-02.10'));
ac_assert('rejects leading zero revision', !AA_Agenda_Terms_Consent::version_is_valid('2026-08-02.01'));
ac_assert('rejects missing revision', !AA_Agenda_Terms_Consent::version_is_valid('2026-08-02'));
ac_assert('rejects invalid calendar date', !AA_Agenda_Terms_Consent::version_is_valid('2026-02-31.1'));
ac_assert('rejects empty', !AA_Agenda_Terms_Consent::version_is_valid(''));

echo "\n{$passed}/{$total} passed\n";
exit($failed ? 1 : 0);
