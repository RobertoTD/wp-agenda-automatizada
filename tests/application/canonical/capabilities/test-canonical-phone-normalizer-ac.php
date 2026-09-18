<?php
/**
 * AC — normalizador canónico phone (contrato E.164 MVP).
 *
 * Ejecutar:
 *   php tests/application/canonical/capabilities/test-canonical-phone-normalizer-ac.php
 */

$plugin_root = dirname(__DIR__, 4);

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/');
}

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

require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Phone_Normalizer.php';

echo "=== Normalización phone ===\n";

$empty = AA_Canonical_Phone_Normalizer::normalize('');
ac_assert('Vacío → null', !empty($empty['ok']) && $empty['value'] === null);
$spaces = AA_Canonical_Phone_Normalizer::normalize('   ');
ac_assert('Solo espacios → null', !empty($spaces['ok']) && $spaces['value'] === null);

$mx = AA_Canonical_Phone_Normalizer::normalize('+525636299377');
ac_assert('MX válido', !empty($mx['ok']) && $mx['value'] === '+525636299377');

$ar10 = AA_Canonical_Phone_Normalizer::normalize('+541112345678');
ac_assert('AR 10 dígitos', !empty($ar10['ok']) && $ar10['value'] === '+541112345678');
$ar11 = AA_Canonical_Phone_Normalizer::normalize('+5491112345678');
ac_assert('AR 11 con 9', !empty($ar11['ok']) && $ar11['value'] === '+5491112345678');
$ar_sep = AA_Canonical_Phone_Normalizer::normalize('+54 9 11 1234-5678');
ac_assert('AR con separadores', !empty($ar_sep['ok']) && $ar_sep['value'] === '+5491112345678');

$ar_bad_start = AA_Canonical_Phone_Normalizer::normalize('+540111234567');
ac_assert('AR con 0 rechazado', empty($ar_bad_start['ok']));
$ar_no_infer = AA_Canonical_Phone_Normalizer::normalize('+544111234567');
ac_assert('AR 10 empezando en 4 rechazado', empty($ar_no_infer['ok']));

$pe8 = AA_Canonical_Phone_Normalizer::normalize('+5112345678');
ac_assert('PE fijo 8', !empty($pe8['ok']) && $pe8['value'] === '+5112345678');
$pe9 = AA_Canonical_Phone_Normalizer::normalize('+51912345678');
ac_assert('PE móvil 9', !empty($pe9['ok']) && $pe9['value'] === '+51912345678');
$pe9bad = AA_Canonical_Phone_Normalizer::normalize('+51812345678');
ac_assert('PE 9 sin 9 inicial rechazado', empty($pe9bad['ok']));

$ec8 = AA_Canonical_Phone_Normalizer::normalize('+59321234567');
ac_assert('EC fijo 8', !empty($ec8['ok']) && $ec8['value'] === '+59321234567');
$ec9 = AA_Canonical_Phone_Normalizer::normalize('+593991234567');
ac_assert('EC móvil 9', !empty($ec9['ok']) && $ec9['value'] === '+593991234567');

$letters = AA_Canonical_Phone_Normalizer::normalize('+52abc5636299377');
ac_assert('Letras rechazadas', empty($letters['ok']) && ($letters['error']['code'] ?? '') === 'invalid_phone');
$no_plus = AA_Canonical_Phone_Normalizer::normalize('525636299377');
ac_assert('Sin + rechazado', empty($no_plus['ok']));
$ext = AA_Canonical_Phone_Normalizer::normalize('+525636299377x123');
ac_assert('Extensión rechazada', empty($ext['ok']));

$display = AA_Canonical_Phone_Normalizer::format_display('+5491112345678');
ac_assert('Display +CC resto', $display === '+54 91112345678');

$parsed = AA_Canonical_Phone_Normalizer::parse_stored('+5491112345678');
ac_assert(
    'parse_stored AR',
    is_array($parsed) && ($parsed['country'] ?? '') === '54' && ($parsed['national'] ?? '') === '91112345678'
);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
