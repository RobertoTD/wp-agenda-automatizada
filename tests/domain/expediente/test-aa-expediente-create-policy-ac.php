<?php
/**
 * AC — AA_Expediente_Create_Policy.
 *
 * Ejecutar: php tests/domain/expediente/test-aa-expediente-create-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once dirname(__DIR__, 3) . '/includes/domain/expediente/class-aa-expediente-create-policy.php';

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

$policy = new AA_Expediente_Create_Policy();

ac_assert('GENERAL_SLUG es general', AA_Expediente_Create_Policy::GENERAL_SLUG === 'general');
ac_assert('TITLE_MAX_LENGTH es 200', AA_Expediente_Create_Policy::TITLE_MAX_LENGTH === 200);

ac_assert('title trim', $policy->normalize_title('  Contrato  ') === 'Contrato');
ac_assert('title vacío → null', $policy->normalize_title('   ') === null);
ac_assert('title no string → null', $policy->normalize_title(12) === null);

ac_assert('description vacía → null', $policy->normalize_description('   ') === null);
ac_assert('description null → null', $policy->normalize_description(null) === null);
ac_assert('description trim', $policy->normalize_description('  nota  ') === 'nota');

ac_assert('slug omitido → general', $policy->normalize_category_slug(null) === 'general');
ac_assert('slug vacío → general', $policy->normalize_category_slug('  ') === 'general');
ac_assert('slug trim', $policy->normalize_category_slug('  laboral  ') === 'laboral');

$long_title = str_repeat('a', 201);
ac_assert('title 201 supera máximo', $policy->title_exceeds_max($long_title) === true);
ac_assert('title 200 no supera máximo', $policy->title_exceeds_max(str_repeat('a', 200)) === false);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
