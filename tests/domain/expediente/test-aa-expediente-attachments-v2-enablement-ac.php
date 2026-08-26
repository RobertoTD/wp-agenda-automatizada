<?php
/**
 * AC — AA_Expediente_Attachments_V2_Enablement (P3).
 *
 * Ejecutar: php tests/domain/expediente/test-aa-expediente-attachments-v2-enablement-ac.php
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
        return;
    }
    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-attachments-v2-enablement.php';

AA_Expediente_Attachments_V2_Enablement::set_for_tests(null);
ac_assert('ausente (sin constante) → false', AA_Expediente_Attachments_V2_Enablement::is_enabled() === false);

AA_Expediente_Attachments_V2_Enablement::set_for_tests(false);
ac_assert('override false → false', AA_Expediente_Attachments_V2_Enablement::is_enabled() === false);

AA_Expediente_Attachments_V2_Enablement::set_for_tests(true);
ac_assert('override true → true', AA_Expediente_Attachments_V2_Enablement::is_enabled() === true);

$src = (string) file_get_contents(
    $plugin_root . '/includes/domain/expediente/class-aa-expediente-attachments-v2-enablement.php'
);
ac_assert('sin $_GET/POST/REQUEST', strpos($src, '$_GET') === false
    && strpos($src, '$_POST') === false
    && strpos($src, '$_REQUEST') === false);
ac_assert('lee solo constante AA_EXPEDIENTE_ATTACHMENTS_V2_ENABLED', strpos($src, 'AA_EXPEDIENTE_ATTACHMENTS_V2_ENABLED') !== false);

AA_Expediente_Attachments_V2_Enablement::set_for_tests(null);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
