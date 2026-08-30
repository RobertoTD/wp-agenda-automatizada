<?php
/**
 * AC Test — Normalización y validación decimal de amount en Finanzas (Ciclo 3B2a).
 *
 * Ejecutar:
 *   php tests/application/finance/test-finance-amount-normalization-ac.php
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

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';

echo "=== 1. Valores vacíos, nulos o con espacios ===\n";

$res_null = FinanceUseCaseSupport::normalize_amount(null);
ac_assert('null devuelve ok(null)', $res_null['ok'] === true && $res_null['value'] === null);

$res_empty = FinanceUseCaseSupport::normalize_amount('');
ac_assert('Cadena vacía devuelve ok(null)', $res_empty['ok'] === true && $res_empty['value'] === null);

$res_spaces = FinanceUseCaseSupport::normalize_amount('    ');
ac_assert('Espacios en blanco devuelve ok(null)', $res_spaces['ok'] === true && $res_spaces['value'] === null);

echo "\n=== 2. Ceros y normalización canónica de 2 decimales ===\n";

$res_zero1 = FinanceUseCaseSupport::normalize_amount('0');
ac_assert('"0" normaliza a "0.00"', $res_zero1['ok'] === true && $res_zero1['value'] === '0.00');

$res_zero2 = FinanceUseCaseSupport::normalize_amount('0.0');
ac_assert('"0.0" normaliza a "0.00"', $res_zero2['ok'] === true && $res_zero2['value'] === '0.00');

$res_zero3 = FinanceUseCaseSupport::normalize_amount('0.00');
ac_assert('"0.00" normaliza a "0.00"', $res_zero3['ok'] === true && $res_zero3['value'] === '0.00');

$res_zero_padded = FinanceUseCaseSupport::normalize_amount('0000');
ac_assert('"0000" normaliza a "0.00"', $res_zero_padded['ok'] === true && $res_zero_padded['value'] === '0.00');

$res_neg_zero1 = FinanceUseCaseSupport::normalize_amount('-0');
ac_assert('"-0" normaliza a "0.00" (sin signo negativo)', $res_neg_zero1['ok'] === true && $res_neg_zero1['value'] === '0.00');

$res_neg_zero2 = FinanceUseCaseSupport::normalize_amount('-0.0');
ac_assert('"-0.0" normaliza a "0.00"', $res_neg_zero2['ok'] === true && $res_neg_zero2['value'] === '0.00');

$res_neg_zero3 = FinanceUseCaseSupport::normalize_amount('-0.00');
ac_assert('"-0.00" normaliza a "0.00"', $res_neg_zero3['ok'] === true && $res_neg_zero3['value'] === '0.00');

$res_neg_zero_padded = FinanceUseCaseSupport::normalize_amount('-000.00');
ac_assert('"-000.00" normaliza a "0.00"', $res_neg_zero_padded['ok'] === true && $res_neg_zero_padded['value'] === '0.00');

echo "\n=== 3. Cifras positivas, negativas y ceros iniciales ===\n";

$res_one = FinanceUseCaseSupport::normalize_amount('1');
ac_assert('"1" normaliza a "1.00"', $res_one['ok'] === true && $res_one['value'] === '1.00');

$res_one_dec = FinanceUseCaseSupport::normalize_amount('1.8');
ac_assert('"1.8" normaliza a "1.80"', $res_one_dec['ok'] === true && $res_one_dec['value'] === '1.80');

$res_exact = FinanceUseCaseSupport::normalize_amount('1.85');
ac_assert('"1.85" se conserva como "1.85"', $res_exact['ok'] === true && $res_exact['value'] === '1.85');

$res_neg = FinanceUseCaseSupport::normalize_amount('-250.75');
ac_assert('"-250.75" se conserva como "-250.75"', $res_neg['ok'] === true && $res_neg['value'] === '-250.75');

$res_lead_zeros = FinanceUseCaseSupport::normalize_amount('0001.50');
ac_assert('"0001.50" normaliza a "1.50"', $res_lead_zeros['ok'] === true && $res_lead_zeros['value'] === '1.50');

$res_lead_zeros_neg = FinanceUseCaseSupport::normalize_amount('-0050.20');
ac_assert('"-0050.20" normaliza a "-50.20"', $res_lead_zeros_neg['ok'] === true && $res_lead_zeros_neg['value'] === '-50.20');

$res_lead_zeros_long = FinanceUseCaseSupport::normalize_amount('0000000000000000000000000000000001.00');
ac_assert('Ceros iniciales largos pero <= 60 bytes normaliza a "1.00"', $res_lead_zeros_long['ok'] === true && $res_lead_zeros_long['value'] === '1.00');

echo "\n=== 4. Rangos máximos y mínimos (decimal(19,2) = 17 enteros y 2 decimales) ===\n";

$max_amount = '99999999999999999.99';
$res_max = FinanceUseCaseSupport::normalize_amount($max_amount);
ac_assert('Máximo permitido (17 dígitos enteros) es válido', $res_max['ok'] === true && $res_max['value'] === $max_amount);

$min_amount = '-99999999999999999.99';
$res_min = FinanceUseCaseSupport::normalize_amount($min_amount);
ac_assert('Mínimo permitido (-17 dígitos enteros) es válido', $res_min['ok'] === true && $res_min['value'] === $min_amount);

$overflow_18_digits = '100000000000000000.00';
$res_overflow = FinanceUseCaseSupport::normalize_amount($overflow_18_digits);
ac_assert('18 dígitos enteros devuelve amount_out_of_range', !$res_overflow['ok'] && $res_overflow['error']['code'] === 'amount_out_of_range');

$overflow_neg_18_digits = '-100000000000000000.00';
$res_overflow_neg = FinanceUseCaseSupport::normalize_amount($overflow_neg_18_digits);
ac_assert('-18 dígitos enteros devuelve amount_out_of_range', !$res_overflow_neg['ok'] && $res_overflow_neg['error']['code'] === 'amount_out_of_range');

echo "\n=== 5. Rechazos sintácticos y formales (invalid_amount, amount_too_many_decimals) ===\n";

$res_dot_lead = FinanceUseCaseSupport::normalize_amount('.50');
ac_assert('".50" sin parte entera devuelve invalid_amount', !$res_dot_lead['ok'] && $res_dot_lead['error']['code'] === 'invalid_amount');

$res_dot_trail = FinanceUseCaseSupport::normalize_amount('1.');
ac_assert('"1." sin decimales devuelve invalid_amount', !$res_dot_trail['ok'] && $res_dot_trail['error']['code'] === 'invalid_amount');

$res_plus = FinanceUseCaseSupport::normalize_amount('+10');
ac_assert('"+10" con signo más devuelve invalid_amount', !$res_plus['ok'] && $res_plus['error']['code'] === 'invalid_amount');

$res_plus_dec = FinanceUseCaseSupport::normalize_amount('+10.00');
ac_assert('"+10.00" con signo más devuelve invalid_amount', !$res_plus_dec['ok'] && $res_plus_dec['error']['code'] === 'invalid_amount');

$res_comma = FinanceUseCaseSupport::normalize_amount('1,85');
ac_assert('"1,85" con coma devuelve invalid_amount', !$res_comma['ok'] && $res_comma['error']['code'] === 'invalid_amount');

$res_thousand_sep = FinanceUseCaseSupport::normalize_amount('1,000.00');
ac_assert('"1,000.00" con separador de miles devuelve invalid_amount', !$res_thousand_sep['ok'] && $res_thousand_sep['error']['code'] === 'invalid_amount');

$res_sci1 = FinanceUseCaseSupport::normalize_amount('1e3');
ac_assert('"1e3" en notación científica devuelve invalid_amount', !$res_sci1['ok'] && $res_sci1['error']['code'] === 'invalid_amount');

$res_sci2 = FinanceUseCaseSupport::normalize_amount('1E+3');
ac_assert('"1E+3" en notación científica devuelve invalid_amount', !$res_sci2['ok'] && $res_sci2['error']['code'] === 'invalid_amount');

$res_nan = FinanceUseCaseSupport::normalize_amount('NaN');
ac_assert('"NaN" devuelve invalid_amount', !$res_nan['ok'] && $res_nan['error']['code'] === 'invalid_amount');

$res_inf = FinanceUseCaseSupport::normalize_amount('INF');
ac_assert('"INF" devuelve invalid_amount', !$res_inf['ok'] && $res_inf['error']['code'] === 'invalid_amount');

$res_three_decimals = FinanceUseCaseSupport::normalize_amount('1.999');
ac_assert('"1.999" (3 decimales) devuelve amount_too_many_decimals', !$res_three_decimals['ok'] && $res_three_decimals['error']['code'] === 'amount_too_many_decimals');

$res_over_60_bytes = FinanceUseCaseSupport::normalize_amount(str_repeat('0', 61) . '1.00');
ac_assert('String mayor de 60 bytes tras trim devuelve invalid_amount', !$res_over_60_bytes['ok'] && $res_over_60_bytes['error']['code'] === 'invalid_amount');

echo "\n=== 6. Rechazo estricto de tipos PHP no string ===\n";

$res_int = FinanceUseCaseSupport::normalize_amount(10);
ac_assert('Entero PHP 10 devuelve invalid_amount', !$res_int['ok'] && $res_int['error']['code'] === 'invalid_amount');

$res_float = FinanceUseCaseSupport::normalize_amount(1.85);
ac_assert('Float PHP 1.85 devuelve invalid_amount', !$res_float['ok'] && $res_float['error']['code'] === 'invalid_amount');

$res_bool = FinanceUseCaseSupport::normalize_amount(true);
ac_assert('Boolean PHP true devuelve invalid_amount', !$res_bool['ok'] && $res_bool['error']['code'] === 'invalid_amount');

$res_array = FinanceUseCaseSupport::normalize_amount(['10.00']);
ac_assert('Array PHP devuelve invalid_amount', !$res_array['ok'] && $res_array['error']['code'] === 'invalid_amount');

$res_obj = FinanceUseCaseSupport::normalize_amount((object) ['amount' => '10.00']);
ac_assert('Object PHP devuelve invalid_amount', !$res_obj['ok'] && $res_obj['error']['code'] === 'invalid_amount');

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
