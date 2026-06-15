<?php
/**
 * AC MC5 — AA_Executive_Focus_Selection_Policy.
 *
 * Ejecutar: php tests/domain/executive/test-aa-executive-focus-selection-policy-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../../../includes/domain/executive/class-aa-executive-focus-selection-policy.php';

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

ac_assert('sin elegibles devuelve null', AA_Executive_Focus_Selection_Policy::select_random_focus([]) === null);
ac_assert('una lista devuelve esa', AA_Executive_Focus_Selection_Policy::select_random_focus([12]) === 12);

$selected = AA_Executive_Focus_Selection_Policy::select_random_focus(
    [3, 7, 9],
    static function (int $count): int {
        return 1;
    }
);
ac_assert('randomizer inyectado elige índice 1', $selected === 7);

$current_can_repeat = AA_Executive_Focus_Selection_Policy::select_random_focus(
    [5, 8],
    static function (): int {
        return 0;
    }
);
ac_assert('puede devolver lista actual', $current_can_repeat === 5);

$out_of_range = AA_Executive_Focus_Selection_Policy::select_random_focus(
    [4, 6],
    static function (): int {
        return 99;
    }
);
ac_assert('índice fuera de rango se sanea al último', $out_of_range === 6);

$negative_index = AA_Executive_Focus_Selection_Policy::select_random_focus(
    [4, 6],
    static function (): int {
        return -5;
    }
);
ac_assert('índice negativo se sanea al primero', $negative_index === 4);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
