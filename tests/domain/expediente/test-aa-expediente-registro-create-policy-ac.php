<?php
/**
 * AC — AA_Expediente_Registro_Create_Policy.
 *
 * Ejecutar: php tests/domain/expediente/test-aa-expediente-registro-create-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once dirname(__DIR__, 3) . '/includes/domain/expediente/class-aa-expediente-registro-create-policy.php';

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

$policy = new AA_Expediente_Registro_Create_Policy();

ac_assert('TITLE_MAX_LENGTH 200', AA_Expediente_Registro_Create_Policy::TITLE_MAX_LENGTH === 200);
ac_assert('BODY_MAX_LENGTH 10000', AA_Expediente_Registro_Create_Policy::BODY_MAX_LENGTH === 10000);

ac_assert('title trim', $policy->normalize_title('  Nota  ') === 'Nota');
ac_assert('title vacío → null', $policy->normalize_title('   ') === null);
ac_assert('title no string → null', $policy->normalize_title(12) === null);
ac_assert('title null → null', $policy->normalize_title(null) === null);

ac_assert('body trim', $policy->normalize_body("  texto\n ") === "texto");
ac_assert('body vacío → null', $policy->normalize_body('   ') === null);
ac_assert('body no string → null', $policy->normalize_body([]) === null);

ac_assert('title 201 supera', $policy->title_exceeds_max(str_repeat('a', 201)) === true);
ac_assert('title 200 no supera', $policy->title_exceeds_max(str_repeat('a', 200)) === false);
ac_assert('body 10001 supera', $policy->body_exceeds_max(str_repeat('b', 10001)) === true);
ac_assert('body 10000 no supera', $policy->body_exceeds_max(str_repeat('b', 10000)) === false);

$src = file_get_contents(dirname(__DIR__, 3) . '/includes/domain/expediente/class-aa-expediente-registro-create-policy.php');
ac_assert(
    'usa mb_strlen con fallback strlen',
    is_string($src)
    && strpos($src, 'mb_strlen') !== false
    && strpos($src, 'strlen(') !== false
);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
