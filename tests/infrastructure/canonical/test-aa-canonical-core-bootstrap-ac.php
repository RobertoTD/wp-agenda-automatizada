<?php
/**
 * AC Test — AA_Canonical_Core_Bootstrap.
 *
 * Composición concreta de finance.general y shared instance de infraestructura.
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

// 1. instance() before bootstrap() throws LogicException [not_bootstrapped]
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

// 2. build_registry() returns a new, complete, frozen registry without publishing it
$built_registry = AA_Canonical_Core_Bootstrap::build_registry();
ac_assert('build_registry() returns AA_Canonical_Registry', $built_registry instanceof AA_Canonical_Registry);
ac_assert('build_registry() returns frozen registry', $built_registry->is_frozen() === true);
ac_assert('build_registry() contains finance family', $built_registry->has_family('finance') === true);
ac_assert('build_registry() contains finance.general variant', $built_registry->has_variant('finance', 'general') === true);
ac_assert('build_registry() contains archive family', $built_registry->has_family('archive') === true);
ac_assert('build_registry() contains archive.general variant', $built_registry->has_variant('archive', 'general') === true);

// instance() still fails because build_registry did not publish
$threw_still_not_bootstrapped = false;
try {
    AA_Canonical_Core_Bootstrap::instance();
} catch (\LogicException $e) {
    $threw_still_not_bootstrapped = true;
}
ac_assert('instance() still throws after build_registry()', $threw_still_not_bootstrapped);

// 3. bootstrap() publishes the shared instance idempotently
$bootstrapped_registry = AA_Canonical_Core_Bootstrap::bootstrap();
ac_assert('bootstrap() returns AA_Canonical_Registry', $bootstrapped_registry instanceof AA_Canonical_Registry);
ac_assert('bootstrap() is frozen', $bootstrapped_registry->is_frozen() === true);

// Idempotency: second call to bootstrap() returns exact same instance
$second_bootstrap = AA_Canonical_Core_Bootstrap::bootstrap();
ac_assert('Second bootstrap() returns identical instance', $second_bootstrap === $bootstrapped_registry);

// 4. instance() after bootstrap() returns the shared published instance
$instance = AA_Canonical_Core_Bootstrap::instance();
ac_assert('instance() returns identical instance as bootstrap()', $instance === $bootstrapped_registry);

// 5. Verify contents of shared registry
$family_finance = $instance->family('finance');
ac_assert('Family "finance" exists', $family_finance instanceof AA_Canonical_Family_Definition);
ac_assert('Family "finance" key is "finance"', $family_finance->key() === 'finance');
ac_assert('Family "finance" label is "Finanzas"', $family_finance->label() === 'Finanzas');
ac_assert('Family "finance" default_variant_key is "general"', $family_finance->default_variant_key() === 'general');

$family_archive = $instance->family('archive');
ac_assert('Family "archive" exists', $family_archive instanceof AA_Canonical_Family_Definition);
ac_assert('Family "archive" key is "archive"', $family_archive->key() === 'archive');
ac_assert('Family "archive" label is "Archivo"', $family_archive->label() === 'Archivo');
ac_assert('Family "archive" default_variant_key is "general"', $family_archive->default_variant_key() === 'general');

$families = $instance->families();
ac_assert('Exactly 2 productive families registered', count($families) === 2);
$family_keys = array_map(static function ($f) {
    return $f->key();
}, $families);
sort($family_keys);
ac_assert('Families are archive and finance', $family_keys === ['archive', 'finance']);
ac_assert('Preview family is not in productive catalog', $instance->has_family('shell_preview') === false);

$variant_general = $instance->variant('finance', 'general');
ac_assert('Variant "general" for "finance" exists', $variant_general instanceof AA_Canonical_Variant_Definition);
ac_assert('Variant family_key is "finance"', $variant_general->family_key() === 'finance');
ac_assert('Variant key is "general"', $variant_general->key() === 'general');
ac_assert('Variant label is "General"', $variant_general->label() === 'General');
ac_assert('Variant qualified_key is "finance.general"', $variant_general->qualified_key() === 'finance.general');

$archive_variant = $instance->variant('archive', 'general');
ac_assert('Variant archive.general exists', $archive_variant->qualified_key() === 'archive.general');
ac_assert('Variant archive.general label is General', $archive_variant->label() === 'General');

$finance_variants = $instance->variants_for('finance');
ac_assert('Only 1 variant registered for finance', count($finance_variants) === 1);
ac_assert('Variant in list is general', $finance_variants[0]->key() === 'general');

$archive_variants = $instance->variants_for('archive');
ac_assert('Only 1 variant registered for archive', count($archive_variants) === 1);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
