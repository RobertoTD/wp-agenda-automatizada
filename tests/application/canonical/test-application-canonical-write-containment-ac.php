<?php
/**
 * AC Test — Application canonical write containment (SB1-5A1).
 *
 * Verifica que includes/application/canonical no contiene vocabulario Finance ni SQL.
 *
 * Ejecutar: php tests/application/canonical/test-application-canonical-write-containment-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$canonical_dir = $plugin_root . '/includes/application/canonical';
$forbidden = [
    'finance',
    'amount',
    'amount_total',
    'AA_Finance',
    'aa_finance_',
    '$wpdb',
];

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

$violations = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($canonical_dir));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    // Las capabilities y solutions son verticales: sus términos de dominio son
    // legítimos. Esta guardia protege exclusivamente el núcleo horizontal.
    if (strpos($path, DIRECTORY_SEPARATOR . 'capabilities' . DIRECTORY_SEPARATOR) !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR) !== false
        || strpos($path, DIRECTORY_SEPARATOR . 'solutions' . DIRECTORY_SEPARATOR) !== false
    ) {
        continue;
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        $violations[] = $path . ' (unreadable)';
        continue;
    }
    foreach ($forbidden as $term) {
        if (stripos($contents, $term) !== false) {
            $violations[] = $path . ' contains ' . $term;
        }
    }
}

ac_assert('Núcleo horizontal sin vocabulario vertical prohibido', $violations === [], $violations === [] ? '' : implode('; ', $violations));

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
