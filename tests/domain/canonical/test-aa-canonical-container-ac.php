<?php
/**
 * AC Test — AA_Canonical_Container value object.
 *
 * Ejecutar: php tests/domain/canonical/test-aa-canonical-container-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php';

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

$ok = new AA_Canonical_Container(1, 'alpha', 'Titulo', null, '2026-03-01T10:00:00Z');
ac_assert('Accepts valid container with null details', $ok->details() === null);
ac_assert('Serializes updated_at as Z form', $ok->updated_at_canonical() === '2026-03-01T10:00:00Z');
ac_assert('to_canonical_array has stable keys', array_keys($ok->to_canonical_array()) === ['id', 'variant_key', 'title', 'details', 'updated_at']);

$from_dto = new AA_Canonical_Container(
    2,
    'alpha',
    'Otro',
    'Texto',
    new DateTimeImmutable('2026-03-01T12:30:45+00:00')
);
ac_assert('DateTimeImmutable UTC normalizes to Z string', $from_dto->updated_at_canonical() === '2026-03-01T12:30:45Z');

$rejects = [
    'mysql naive' => ['2026-03-01 10:00:00'],
    'plus offset' => ['2026-03-01T10:00:00+00:00'],
    'minus offset' => ['2026-03-01T10:00:00-05:00'],
    'with millis' => ['2026-03-01T10:00:00.123Z'],
    'empty title' => [1, 'alpha', '  ', null, '2026-03-01T10:00:00Z'],
    'bad id' => [0, 'alpha', 'T', null, '2026-03-01T10:00:00Z'],
    'bad variant' => [1, 'Bad!', 'T', null, '2026-03-01T10:00:00Z'],
];

foreach (
    [
        'Rejects MySQL naive' => function () {
            new AA_Canonical_Container(1, 'alpha', 'T', null, '2026-03-01 10:00:00');
        },
        'Rejects +00:00 form' => function () {
            new AA_Canonical_Container(1, 'alpha', 'T', null, '2026-03-01T10:00:00+00:00');
        },
        'Rejects other offset' => function () {
            new AA_Canonical_Container(1, 'alpha', 'T', null, '2026-03-01T10:00:00-05:00');
        },
        'Rejects fractional seconds Z' => function () {
            new AA_Canonical_Container(1, 'alpha', 'T', null, '2026-03-01T10:00:00.123Z');
        },
        'Rejects empty title' => function () {
            new AA_Canonical_Container(1, 'alpha', '  ', null, '2026-03-01T10:00:00Z');
        },
        'Rejects non-positive id' => function () {
            new AA_Canonical_Container(0, 'alpha', 'T', null, '2026-03-01T10:00:00Z');
        },
        'Rejects invalid variant_key' => function () {
            new AA_Canonical_Container(1, 'Bad!', 'T', null, '2026-03-01T10:00:00Z');
        },
    ] as $label => $fn
) {
    $threw = false;
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        $threw = true;
    }
    ac_assert($label, $threw === true);
}

$src = file_get_contents($plugin_root . '/includes/domain/canonical/class-aa-canonical-container.php');
ac_assert('Container source has no Finance vocabulary', stripos($src, 'finance') === false && stripos($src, 'amount') === false);
ac_assert('Container source has no infrastructure import', strpos($src, 'infrastructure/') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}
exit(0);
