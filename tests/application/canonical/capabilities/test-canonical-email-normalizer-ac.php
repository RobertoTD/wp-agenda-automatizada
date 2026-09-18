<?php
/**
 * AC — normalizador canónico email (FILTER_VALIDATE_EMAIL + MVP ASCII + mailto).
 *
 * Ejecutar:
 *   php tests/application/canonical/capabilities/test-canonical-email-normalizer-ac.php
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

require_once $plugin_root . '/includes/application/canonical/capabilities/AA_Canonical_Email_Normalizer.php';

echo "=== Normalización email ===\n";

$empty = AA_Canonical_Email_Normalizer::normalize('');
ac_assert('Vacío → null', !empty($empty['ok']) && $empty['value'] === null);
$spaces = AA_Canonical_Email_Normalizer::normalize('   ');
ac_assert('Solo espacios → null', !empty($spaces['ok']) && $spaces['value'] === null);

$norm = AA_Canonical_Email_Normalizer::normalize('  User+Tag@Example.COM  ');
ac_assert(
    'Trim + local intacto + dominio lower',
    !empty($norm['ok']) && $norm['value'] === 'User+Tag@example.com'
);

$ok_plain = AA_Canonical_Email_Normalizer::normalize('a.b@ejemplo.com');
ac_assert('Local con punto', !empty($ok_plain['ok']) && $ok_plain['value'] === 'a.b@ejemplo.com');

foreach (['a?b@example.com', 'a#b@example.com', 'a%b@example.com', 'a&b@example.com', 'a=b@example.com', 'a/b@example.com'] as $special) {
    $r = AA_Canonical_Email_Normalizer::normalize($special);
    ac_assert('Special local ACCEPT: ' . $special, !empty($r['ok']) && $r['value'] === $special);
}

$puny = AA_Canonical_Email_Normalizer::normalize('user@xn--mxico-bsa.com');
ac_assert('Punycode ASCII', !empty($puny['ok']) && $puny['value'] === 'user@xn--mxico-bsa.com');

$no_at = AA_Canonical_Email_Normalizer::normalize('sin-arroba');
ac_assert('Sin @ rechazado', empty($no_at['ok']) && ($no_at['error']['code'] ?? '') === 'invalid_email');
$double = AA_Canonical_Email_Normalizer::normalize('a@b@c.com');
ac_assert('Doble @ rechazado', empty($double['ok']));
$space_in = AA_Canonical_Email_Normalizer::normalize('user@exa mple.com');
ac_assert('Espacio interno rechazado', empty($space_in['ok']));
$no_dot = AA_Canonical_Email_Normalizer::normalize('a@b');
ac_assert('Dominio sin punto rechazado', empty($no_dot['ok']));
$quoted = AA_Canonical_Email_Normalizer::normalize('"a"@example.com');
ac_assert('Local entrecomillado rechazado', empty($quoted['ok']));

$unicode_local = AA_Canonical_Email_Normalizer::normalize('用户@example.com');
ac_assert('Unicode local rechazado', empty($unicode_local['ok']) && ($unicode_local['error']['code'] ?? '') === 'invalid_email');
$unicode_dom = AA_Canonical_Email_Normalizer::normalize('user@dominio.méxico');
ac_assert('Unicode dominio rechazado', empty($unicode_dom['ok']));

$too_long = AA_Canonical_Email_Normalizer::normalize(str_repeat('a', 64) . '@' . str_repeat('b', 190) . '.com');
ac_assert('Longitud total >254 rechazada', empty($too_long['ok']));

$null_t = AA_Canonical_Email_Normalizer::normalize(null);
ac_assert('null → invalid_payload', empty($null_t['ok']) && ($null_t['error']['code'] ?? '') === 'invalid_payload');
$arr_t = AA_Canonical_Email_Normalizer::normalize(['x']);
ac_assert('array → invalid_payload', empty($arr_t['ok']) && ($arr_t['error']['code'] ?? '') === 'invalid_payload');
$int_t = AA_Canonical_Email_Normalizer::normalize(1);
ac_assert('int → invalid_payload', empty($int_t['ok']) && ($int_t['error']['code'] ?? '') === 'invalid_payload');

ac_assert(
    'parse_stored revalida',
    AA_Canonical_Email_Normalizer::parse_stored('User+Tag@example.com') === 'User+Tag@example.com'
);
ac_assert('parse_stored inválido → null', AA_Canonical_Email_Normalizer::parse_stored('bad') === null);

$mailto_cases = [
    'User+Tag@example.com' => 'mailto:User%2BTag@example.com',
    'a?b@example.com' => 'mailto:a%3Fb@example.com',
    'a#b@example.com' => 'mailto:a%23b@example.com',
    'a%b@example.com' => 'mailto:a%25b@example.com',
    'a&b@example.com' => 'mailto:a%26b@example.com',
];
foreach ($mailto_cases as $email => $href) {
    ac_assert('mailto ' . $email, AA_Canonical_Email_Normalizer::mailto_href($email) === $href);
}
ac_assert('mailto inválido → null', AA_Canonical_Email_Normalizer::mailto_href('not-an-email') === null);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
exit($failed === [] ? 0 : 1);
