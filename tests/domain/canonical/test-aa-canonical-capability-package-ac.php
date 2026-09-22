<?php
/** AC — Capability Package v0: manifiesto, registry y puente declarativo. */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$root = dirname(__DIR__, 3);
require_once $root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $root . '/includes/domain/canonical/class-aa-canonical-capability-package-definition.php';
require_once $root . '/includes/domain/canonical/class-aa-canonical-capability-package-registry.php';
require_once $root . '/includes/infrastructure/canonical/class-aa-canonical-capability-package-registry-bootstrap.php';
require_once $root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php';
require_once $root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityPresentationDefinition.php';
require_once $root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityPresentationRegistry.php';
require_once $root . '/includes/infrastructure/canonical/class-aa-canonical-capability-presentation-registry-bootstrap.php';

$total = 0;
$passed = 0;
$failed = [];
function package_assert(string $label, bool $ok): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) { $passed++; echo "[ OK ] {$label}\n"; return; }
    $failed[] = $label;
    echo "[FAIL] {$label}\n";
}

$packages = AA_Canonical_Capability_Package_Registry_Bootstrap::build_registry();
$completed = $packages->get('completed');
package_assert('Completed es el primer package v1', $completed->version() === 1 && $completed->label() === 'Completar');
package_assert('Completed declara familia, default y alcance', $completed->family_defaults() === ['action' => true] && $completed->capability()->scope() === AA_Canonical_Capability_Definition::SCOPE_RECORD);
package_assert('Completed declara persistencia y lifecycle conservativo', $completed->persistence_resource_key() === 'canonical_record_completion' && $completed->deactivation_policy() === 'preserve' && $completed->uninstall_policy() === 'preserve' && $completed->purge_policy() === 'explicit');
package_assert('Completed declara lectura, escritura, vistas y presentación v0', $completed->read_contracts() === ['record_read'] && $completed->write_contracts() === ['record_write'] && $completed->write_permission_policy() === 'canonical_record_write' && $completed->has_natural_criterion() && $completed->record_view_keys() === ['completed'] && $completed->presentation_contributions() === ['card_action', 'client_module', 'card_metadata', 'record_views']);
package_assert('Package declara combinaciones vacías explícitamente', $completed->required_capability_keys() === [] && $completed->incompatible_capability_keys() === []);

$product_capabilities = AA_Canonical_Capability_Registry_Bootstrap::build_registry();
$presentations = AA_Canonical_Capability_Presentation_Registry_Bootstrap::build_registry($product_capabilities);
package_assert('Capability registry toma completed del package', $product_capabilities->get('completed')->is_ready());
package_assert('Presentation registry toma label del package', $presentations->get('completed')->label() === $completed->label());

$invalid_version = false;
try {
    new AA_Canonical_Capability_Package_Definition(new AA_Canonical_Capability_Definition('probe', 'record', true), 0, 'Probe', ['action' => false], 'probe_state', 'managed_schema', 'preserve', 'preserve', 'explicit', [], [], 'canonical_record_write', false, [], []);
} catch (InvalidArgumentException $e) {
    $invalid_version = strpos($e->getMessage(), '[invalid_capability_package_version]') !== false;
}
package_assert('Validador rechaza versión no positiva', $invalid_version);

$invalid_view = false;
try {
    new AA_Canonical_Capability_Package_Definition(new AA_Canonical_Capability_Definition('probe', 'record', true), 1, 'Probe', ['action' => false], 'probe_state', 'managed_schema', 'preserve', 'preserve', 'explicit', [], [], 'none', false, ['probe_view'], []);
} catch (InvalidArgumentException $e) {
    $invalid_view = strpos($e->getMessage(), '[invalid_capability_package_views]') !== false;
}
package_assert('Validador exige contribución para declarar vistas', $invalid_view);

$registry = new AA_Canonical_Capability_Package_Registry();
$registry->register($completed)->freeze();
$frozen = false;
try { $registry->register($completed); } catch (LogicException $e) { $frozen = strpos($e->getMessage(), '[capability_package_registry_frozen]') !== false; }
package_assert('Registry sellado rechaza mutación', $frozen);

$defaults_source = (string) file_get_contents($root . '/includes/infrastructure/canonical/class-aa-canonical-capability-defaults-lifecycle.php');
package_assert('Defaults lifecycle deriva packages y no duplica completed', strpos($defaults_source, 'AA_Canonical_Capability_Package_Registry_Bootstrap::bootstrap()') !== false && strpos($defaults_source, "'capability_key' => 'completed'") === false);
$completed_seed = array_values(array_filter(
    AA_Canonical_Capability_Defaults_Lifecycle::declared_seeds(),
    static function (array $seed): bool { return ($seed['capability_key'] ?? '') === 'completed'; }
));
package_assert('Defaults efectivos conservan action/completed activo', $completed_seed === [['family_key' => 'action', 'capability_key' => 'completed', 'is_default' => true]]);

echo "\n{$passed}/{$total} assertions passed.\n";
if ($failed !== []) { exit(1); }
