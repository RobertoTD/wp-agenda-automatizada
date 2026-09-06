<?php
/**
 * AC Test — FinanceUseCaseSupport (Ciclo 3B1).
 *
 * Ejecutar:
 *   php tests/application/finance/test-finance-use-case-support-ac.php
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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';

echo "=== 1. Pruebas de validación y medición UTF-8 ===\n";

ac_assert('ASCII estándar es UTF-8 válido', FinanceUseCaseSupport::is_valid_utf8('Presupuesto 2026'));
ac_assert('Acentos y ñ son UTF-8 válidos', FinanceUseCaseSupport::is_valid_utf8('Operación de Nómina y Año'));
ac_assert('Caracteres multibyte internacionales son UTF-8 válidos', FinanceUseCaseSupport::is_valid_utf8('Финансы & 财务'));
ac_assert('Emojis 4-bytes son UTF-8 válidos', FinanceUseCaseSupport::is_valid_utf8('Presupuesto 💰🚀✨'));
ac_assert('Secuencia de bytes truncada/inválida es rechazada', !FinanceUseCaseSupport::is_valid_utf8("Presupuesto \xC3\x28"));
ac_assert('Byte 0xFF es rechazado como UTF-8 inválido', !FinanceUseCaseSupport::is_valid_utf8("Inválido \xFF"));

$emoji_text = '💰🚀';
ac_assert('utf8_length mide 2 caracteres para 2 emojis', FinanceUseCaseSupport::utf8_length($emoji_text) === 2);
ac_assert('strlen mide 8 bytes para 2 emojis (4 bytes c/u)', strlen($emoji_text) === 8);

echo "\n=== 2. Pruebas de normalize_title() ===\n";

$t_null = FinanceUseCaseSupport::normalize_title(null);
ac_assert('title null devuelve missing_title', !$t_null['ok'] && $t_null['error']['code'] === 'missing_title');

$t_int = FinanceUseCaseSupport::normalize_title(12345);
ac_assert('title entero devuelve invalid_title', !$t_int['ok'] && $t_int['error']['code'] === 'invalid_title');

$t_array = FinanceUseCaseSupport::normalize_title(['title' => 'Test']);
ac_assert('title array devuelve invalid_title', !$t_array['ok'] && $t_array['error']['code'] === 'invalid_title');

$t_bool = FinanceUseCaseSupport::normalize_title(true);
ac_assert('title booleano devuelve invalid_title', !$t_bool['ok'] && $t_bool['error']['code'] === 'invalid_title');

$t_bad_utf8 = FinanceUseCaseSupport::normalize_title("Título \xC3\x28");
ac_assert('title con UTF-8 inválido devuelve invalid_title', !$t_bad_utf8['ok'] && $t_bad_utf8['error']['code'] === 'invalid_title');

$t_whitespace = FinanceUseCaseSupport::normalize_title("   \r\n\t   ");
ac_assert('title compuesto solo de espacios devuelve missing_title', !$t_whitespace['ok'] && $t_whitespace['error']['code'] === 'missing_title');

$t_norm = FinanceUseCaseSupport::normalize_title("  Presupuesto \r\n de \t Oficina  ");
ac_assert('title normaliza saltos de línea a espacios y hace trim', $t_norm['ok'] && $t_norm['value'] === 'Presupuesto de Oficina');

// Exactamente 200 caracteres UTF-8
$title_200 = str_repeat('ñ', 198) . '💰🚀'; // 198 chars + 2 emoji chars = 200 chars
$t_200 = FinanceUseCaseSupport::normalize_title($title_200);
ac_assert('title de exactamente 200 caracteres UTF-8 es válido', $t_200['ok'] && FinanceUseCaseSupport::utf8_length($t_200['value']) === 200);

// 201 caracteres UTF-8
$title_201 = str_repeat('ñ', 199) . '💰🚀'; // 199 chars + 2 emoji chars = 201 chars
$t_201 = FinanceUseCaseSupport::normalize_title($title_201);
ac_assert('title de 201 caracteres UTF-8 devuelve title_too_long', !$t_201['ok'] && $t_201['error']['code'] === 'title_too_long');

echo "\n=== 3. Pruebas de normalize_details() ===\n";

$d_null = FinanceUseCaseSupport::normalize_details(null);
ac_assert('details null devuelve value null', $d_null['ok'] && $d_null['value'] === null);

$d_int = FinanceUseCaseSupport::normalize_details(9876);
ac_assert('details entero devuelve invalid_details', !$d_int['ok'] && $d_int['error']['code'] === 'invalid_details');

$d_array = FinanceUseCaseSupport::normalize_details(['desc']);
ac_assert('details array devuelve invalid_details', !$d_array['ok'] && $d_array['error']['code'] === 'invalid_details');

$d_bad_utf8 = FinanceUseCaseSupport::normalize_details("Detalles \xFF");
ac_assert('details con UTF-8 inválido devuelve invalid_details', !$d_bad_utf8['ok'] && $d_bad_utf8['error']['code'] === 'invalid_details');

$d_empty_spaces = FinanceUseCaseSupport::normalize_details("   \r\n\t   ");
ac_assert('details de espacios en blanco se normaliza a null', $d_empty_spaces['ok'] && $d_empty_spaces['value'] === null);

$d_multiline = FinanceUseCaseSupport::normalize_details("Línea 1\nLínea 2\r\nLínea 3");
ac_assert('details preserva saltos de línea', $d_multiline['ok'] && $d_multiline['value'] === "Línea 1\nLínea 2\r\nLínea 3");

// Límite físico de bytes (65,000 bytes)
$details_65k = str_repeat('A', 65000);
$d_65k = FinanceUseCaseSupport::normalize_details($details_65k);
ac_assert('details de exactamente 65,000 bytes es válido', $d_65k['ok'] && strlen($d_65k['value']) === 65000);

$details_65001 = str_repeat('A', 65001);
$d_65001 = FinanceUseCaseSupport::normalize_details($details_65001);
ac_assert('details de 65,001 bytes devuelve details_too_long', !$d_65001['ok'] && $d_65001['error']['code'] === 'details_too_long');

echo "\n=== 4. Pruebas de normalize_id() ===\n";

ac_assert('normalize_id con int 1', FinanceUseCaseSupport::normalize_id(1) === 1);
ac_assert('normalize_id con int 42', FinanceUseCaseSupport::normalize_id(42) === 42);
ac_assert('normalize_id con string "100"', FinanceUseCaseSupport::normalize_id('100') === 100);
ac_assert('normalize_id con int 0 devuelve null', FinanceUseCaseSupport::normalize_id(0) === null);
ac_assert('normalize_id con int -5 devuelve null', FinanceUseCaseSupport::normalize_id(-5) === null);
ac_assert('normalize_id con string "0" devuelve null', FinanceUseCaseSupport::normalize_id('0') === null);
ac_assert('normalize_id con string "1.5" devuelve null', FinanceUseCaseSupport::normalize_id('1.5') === null);
ac_assert('normalize_id con string "abc" devuelve null', FinanceUseCaseSupport::normalize_id('abc') === null);
ac_assert('normalize_id con array devuelve null', FinanceUseCaseSupport::normalize_id([1]) === null);
ac_assert('normalize_id con null devuelve null', FinanceUseCaseSupport::normalize_id(null) === null);

echo "\n=== 5. Pruebas de normalize_page() ===\n";

ac_assert('normalize_page con null devuelve 1', FinanceUseCaseSupport::normalize_page(null) === 1);
ac_assert('normalize_page con cadena vacía devuelve 1', FinanceUseCaseSupport::normalize_page('') === 1);
ac_assert('normalize_page con int 1 devuelve 1', FinanceUseCaseSupport::normalize_page(1) === 1);
ac_assert('normalize_page con int 5 devuelve 5', FinanceUseCaseSupport::normalize_page(5) === 5);
ac_assert('normalize_page con string "3" devuelve 3', FinanceUseCaseSupport::normalize_page('3') === 3);
ac_assert('normalize_page con int 0 devuelve 1', FinanceUseCaseSupport::normalize_page(0) === 1);
ac_assert('normalize_page con int -2 devuelve 1', FinanceUseCaseSupport::normalize_page(-2) === 1);
ac_assert('normalize_page con string "-5" devuelve 1', FinanceUseCaseSupport::normalize_page('-5') === 1);
ac_assert('normalize_page con string "1.5" devuelve 1', FinanceUseCaseSupport::normalize_page('1.5') === 1);
ac_assert('normalize_page con string "texto" devuelve 1', FinanceUseCaseSupport::normalize_page('texto') === 1);
ac_assert('normalize_page con booleano true devuelve 1', FinanceUseCaseSupport::normalize_page(true) === 1);
ac_assert('normalize_page con array devuelve 1', FinanceUseCaseSupport::normalize_page(['page' => 2]) === 1);

echo "\n=== 6. Pruebas de resolve_variant() ===\n";

// Registry no sellado
$unfrozen_reg = new AA_Canonical_Registry();
$r_unfrozen = FinanceUseCaseSupport::resolve_variant($unfrozen_reg, []);
ac_assert('resolve_variant con registry no sellado devuelve canonical_unavailable', !$r_unfrozen['ok'] && $r_unfrozen['error']['code'] === 'canonical_unavailable');

// Registry sellado sin finance
$empty_reg = new AA_Canonical_Registry();
$empty_reg->register_family(new AA_Canonical_Family_Definition('other', 'Other'));
$empty_reg->freeze();
$r_no_fin = FinanceUseCaseSupport::resolve_variant($empty_reg, []);
ac_assert('resolve_variant sin familia finance devuelve canonical_unavailable', !$r_no_fin['ok'] && $r_no_fin['error']['code'] === 'canonical_unavailable');

// Registry sellado con finance.general
$valid_reg = new AA_Canonical_Registry();
$valid_reg->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$valid_reg->freeze();

$r_default = FinanceUseCaseSupport::resolve_variant($valid_reg, []);
ac_assert('resolve_variant sin variant_key resuelve la variante predeterminada general', $r_default['ok'] && $r_default['variant_key'] === 'general');

$r_null_var = FinanceUseCaseSupport::resolve_variant($valid_reg, ['variant_key' => null]);
ac_assert('resolve_variant con variant_key null resuelve general', $r_null_var['ok'] && $r_null_var['variant_key'] === 'general');

$r_explicit = FinanceUseCaseSupport::resolve_variant($valid_reg, ['variant_key' => 'general']);
ac_assert('resolve_variant con variant_key explícita general devuelve general', $r_explicit['ok'] && $r_explicit['variant_key'] === 'general');

$r_empty_str = FinanceUseCaseSupport::resolve_variant($valid_reg, ['variant_key' => '']);
ac_assert('resolve_variant con variant_key cadena vacía devuelve invalid_variant_key', !$r_empty_str['ok'] && $r_empty_str['error']['code'] === 'invalid_variant_key');

$r_non_str = FinanceUseCaseSupport::resolve_variant($valid_reg, ['variant_key' => ['general']]);
ac_assert('resolve_variant con variant_key array devuelve invalid_variant_key', !$r_non_str['ok'] && $r_non_str['error']['code'] === 'invalid_variant_key');

$r_bad_format = FinanceUseCaseSupport::resolve_variant($valid_reg, ['variant_key' => 'finance.general']);
ac_assert('resolve_variant con formato no clave canónica devuelve invalid_variant_key', !$r_bad_format['ok'] && $r_bad_format['error']['code'] === 'invalid_variant_key');

$r_unknown = FinanceUseCaseSupport::resolve_variant($valid_reg, ['variant_key' => 'taxes']);
ac_assert('resolve_variant con variante sintácticamente válida pero no permitida localmente devuelve unknown_variant', !$r_unknown['ok'] && $r_unknown['error']['code'] === 'unknown_variant');

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
