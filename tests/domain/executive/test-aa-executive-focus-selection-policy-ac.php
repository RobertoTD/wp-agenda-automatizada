<?php
/**
 * AC MC5 / MC5.1 — AA_Executive_Focus_Selection_Policy.
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
ac_assert(
    '[A] con current A devuelve A',
    AA_Executive_Focus_Selection_Policy::select_random_focus([12], 12) === 12
);
ac_assert(
    '[A, B] con current A siempre devuelve B',
    AA_Executive_Focus_Selection_Policy::select_random_focus([3, 7], 3) === 7
);

$excludes_current = AA_Executive_Focus_Selection_Policy::select_random_focus(
    [3, 7, 9],
    3,
    static function (int $count): int {
        return 1;
    }
);
ac_assert('[A, B, C] con current A y randomizer excluye A', $excludes_current === 9);

$any_with_null_current = AA_Executive_Focus_Selection_Policy::select_random_focus(
    [3, 7, 9],
    null,
    static function (int $count): int {
        return 0;
    }
);
ac_assert('[A, B, C] con current null puede devolver cualquiera', $any_with_null_current === 3);

$current_not_eligible = AA_Executive_Focus_Selection_Policy::select_random_focus(
    [3, 7, 9],
    99,
    static function (int $count): int {
        return 2;
    }
);
ac_assert('[A, B, C] con current no elegible puede devolver cualquiera', $current_not_eligible === 9);

$out_of_range = AA_Executive_Focus_Selection_Policy::select_random_focus(
    [4, 6],
    4,
    static function (): int {
        return 99;
    }
);
ac_assert('índice fuera de rango se sanea al último del pool sin current', $out_of_range === 6);

$negative_index = AA_Executive_Focus_Selection_Policy::select_random_focus(
    [4, 6],
    4,
    static function (): int {
        return -5;
    }
);
ac_assert('índice negativo se sanea al primero del pool sin current', $negative_index === 6);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
