<?php
/**
 * AC Test — AA_Canonical_Core_Bootstrap.
 *
 * Composición concreta de familias finance/archive y shared instance de infraestructura.
 *
 * Ejecutar: php tests/infrastructure/canonical/test-aa-canonical-core-bootstrap-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';

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

$threw_not_bootstrapped = false;
$msg_not_bootstrapped = '';
try {
    AA_Canonical_Core_Bootstrap::instance();
} catch (\LogicException $e) {
    $threw_not_bootstrapped = true;
    $msg_not_bootstrapped = $e->getMessage();
}
ac_assert('instance() before bootstrap() throws LogicException', $threw_not_bootstrapped);
ac_assert('instance() before bootstrap() message has [not_bootstrapped]', strpos($msg_not_bootstrapped, '[not_bootstrapped]') !== false);

$built_registry = AA_Canonical_Core_Bootstrap::build_registry();
ac_assert('build_registry() returns AA_Canonical_Registry', $built_registry instanceof AA_Canonical_Registry);
ac_assert('build_registry() returns frozen registry', $built_registry->is_frozen() === true);
ac_assert('build_registry() contains finance family', $built_registry->has_family('finance') === true);
ac_assert('build_registry() contains archive family', $built_registry->has_family('archive') === true);

$threw_still_not_bootstrapped = false;
try {
    AA_Canonical_Core_Bootstrap::instance();
} catch (\LogicException $e) {
    $threw_still_not_bootstrapped = true;
}
ac_assert('instance() still throws after build_registry()', $threw_still_not_bootstrapped);

$bootstrapped_registry = AA_Canonical_Core_Bootstrap::bootstrap();
ac_assert('bootstrap() returns AA_Canonical_Registry', $bootstrapped_registry instanceof AA_Canonical_Registry);
ac_assert('bootstrap() is frozen', $bootstrapped_registry->is_frozen() === true);

$second_bootstrap = AA_Canonical_Core_Bootstrap::bootstrap();
ac_assert('Second bootstrap() returns identical instance', $second_bootstrap === $bootstrapped_registry);

$instance = AA_Canonical_Core_Bootstrap::instance();
ac_assert('instance() returns identical instance as bootstrap()', $instance === $bootstrapped_registry);

$family_finance = $instance->family('finance');
ac_assert('Family "finance" exists', $family_finance instanceof AA_Canonical_Family_Definition);
ac_assert('Family "finance" key is "finance"', $family_finance->key() === 'finance');
ac_assert('Family "finance" label is "Finanzas"', $family_finance->label() === 'Finanzas');

$family_archive = $instance->family('archive');
ac_assert('Family "archive" exists', $family_archive instanceof AA_Canonical_Family_Definition);
ac_assert('Family "archive" key is "archive"', $family_archive->key() === 'archive');
ac_assert('Family "archive" label is "Archivo"', $family_archive->label() === 'Archivo');

$families = $instance->families();
ac_assert('Exactly 2 productive families registered', count($families) === 2);
$family_keys = array_map(static function ($f) {
    return $f->key();
}, $families);
sort($family_keys);
ac_assert('Families are archive and finance', $family_keys === ['archive', 'finance']);
ac_assert('Preview family is not in productive catalog', $instance->has_family('shell_preview') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
