<?php
/**
 * AC Test — CanonicalShellManifest (SB1-2B).
 *
 * Ejecutar: php tests/application/canonical/test-canonical-shell-manifest-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalReadIdentity.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalShellManifest.php';

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

$family = new AA_Canonical_Family_Definition('finance', 'Finanzas', 'general');
$variant = new AA_Canonical_Variant_Definition('finance', 'general', 'General');
$identity = new CanonicalReadIdentity('finance', 'general');
$manifest = new CanonicalShellManifest($identity, $family, $variant);

ac_assert('Family label derived from definition', $manifest->family_label() === 'Finanzas');
ac_assert('Variant label derived from definition', $manifest->variant_label() === 'General');
ac_assert('Qualified key from identity', $manifest->qualified_key() === 'finance.general');
ac_assert('Same class for real manifest', $manifest instanceof CanonicalShellManifest);

$ef_family = new AA_Canonical_Family_Definition('shell_preview', 'Demostración del shell', 'demo');
$ef_variant = new AA_Canonical_Variant_Definition('shell_preview', 'demo', 'Vista de prueba');
$ef_identity = new CanonicalReadIdentity('shell_preview', 'demo');
$ef_manifest = new CanonicalShellManifest($ef_identity, $ef_family, $ef_variant);
ac_assert('Ephemeral preview uses same manifest type', $ef_manifest instanceof CanonicalShellManifest);
ac_assert('Ephemeral labels from ephemeral defs', $ef_manifest->family_label() === 'Demostración del shell'
    && $ef_manifest->variant_label() === 'Vista de prueba');

$mismatch = false;
try {
    new CanonicalShellManifest(
        new CanonicalReadIdentity('finance', 'general'),
        $family,
        new AA_Canonical_Variant_Definition('finance', 'other', 'Other')
    );
} catch (InvalidArgumentException $e) {
    $mismatch = strpos($e->getMessage(), '[invalid_manifest]') === 0;
}
ac_assert('Rejects identity/variant mismatch', $mismatch === true);

$wrong_family = false;
try {
    new CanonicalShellManifest(
        new CanonicalReadIdentity('finance', 'general'),
        $family,
        new AA_Canonical_Variant_Definition('tasks', 'general', 'General')
    );
} catch (InvalidArgumentException $e) {
    $wrong_family = strpos($e->getMessage(), '[invalid_manifest]') === 0;
}
ac_assert('Rejects variant from other family', $wrong_family === true);

$src = file_get_contents($plugin_root . '/includes/application/canonical/CanonicalShellManifest.php');
ac_assert('Manifest source has no preview fields', strpos($src, 'preview') === false && strpos($src, 'is_preview') === false);
ac_assert('Manifest source has no URL fields', strpos($src, 'url') === false && strpos($src, 'URL') === false);
ac_assert('Manifest source has no adapter field', stripos($src, 'adapter') === false);
ac_assert('Manifest source has no Finance vocabulary', stripos($src, 'amount') === false && strpos($src, 'AA_FINANCE') === false);
ac_assert('Application manifest does not import Infrastructure', strpos($src, 'infrastructure/') === false);

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
