<?php
/**
 * AC — AA_Expediente_Id_Policy (normalización canónica compartida).
 *
 * Ejecutar: php tests/domain/expediente/test-aa-expediente-id-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once dirname(__DIR__, 3) . '/includes/domain/expediente/class-aa-expediente-id-policy.php';

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

$src = file_get_contents(dirname(__DIR__, 3) . '/includes/domain/expediente/class-aa-expediente-id-policy.php');
ac_assert('policy pura sin WP/SQL', is_string($src)
    && strpos($src, '$wpdb') === false
    && strpos($src, 'get_option') === false
    && strpos($src, 'current_time') === false);

ac_assert('int positivo', AA_Expediente_Id_Policy::normalize(7) === 7);
ac_assert('string decimal canónico', AA_Expediente_Id_Policy::normalize('7') === 7);
ac_assert('string largo canónico', AA_Expediente_Id_Policy::normalize('123456789') === 123456789);

$invalids = [
    'null' => null,
    'vacío' => '',
    'cero string' => '0',
    'cero int' => 0,
    'negativo string' => '-1',
    'negativo int' => -1,
    'leading zero' => '01',
    'decimal' => '1.5',
    'plus' => '+7',
    'texto' => 'abc',
    'array' => ['7'],
    'object' => (object) ['id' => 7],
    'float' => 1.5,
    'bool' => true,
];

foreach ($invalids as $label => $value) {
    ac_assert(
        'rechaza ' . $label,
        AA_Expediente_Id_Policy::normalize($value) === null
    );
}

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
