<?php
/**
 * AC — AA_Agenda_Privacy_Consent domain constants/validation.
 *
 * Ejecutar: php tests/domain/legal/test-aa-agenda-privacy-consent-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/legal/class-aa-agenda-privacy-consent.php';

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

$expected = 'Manifiesto que he leído el Aviso de Privacidad Integral y consiento el tratamiento de mis datos personales para administrar mi cuenta y agenda, autenticar mi acceso, prestar y proteger el servicio y cumplir las demás finalidades necesarias descritas en el Aviso.';

ac_assert('privacy consent text matches backend AGENDA_PRIVACY_CONSENT_TEXT', AA_Agenda_Privacy_Consent::TEXT === $expected);
ac_assert('consent text is non-empty', AA_Agenda_Privacy_Consent::TEXT !== '');
ac_assert('valid version 2026-08-04.1', AA_Agenda_Privacy_Consent::version_is_valid('2026-08-04.1'));
ac_assert('valid version 2026-08-02.10', AA_Agenda_Privacy_Consent::version_is_valid('2026-08-02.10'));
ac_assert('rejects leading zero revision', !AA_Agenda_Privacy_Consent::version_is_valid('2026-08-02.01'));
ac_assert('rejects missing revision', !AA_Agenda_Privacy_Consent::version_is_valid('2026-08-02'));
ac_assert('rejects invalid calendar date', !AA_Agenda_Privacy_Consent::version_is_valid('2026-02-31.1'));
ac_assert('rejects empty', !AA_Agenda_Privacy_Consent::version_is_valid(''));

echo "\n{$passed}/{$total} passed\n";
exit($failed ? 1 : 0);
