<?php
/**
 * AC — Cierre de proyección al desactivar una capability.
 *
 * Ejecutar: php tests/application/canonical/capabilities/test-canonical-capability-activation-lifecycle-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 4);

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/application/canonical/CanonicalRecordsQuerySpec.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordsViewProvider.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordsViewRegistry.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordsPageContribution.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordPageContributor.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/completed/CanonicalCompletedCriterion.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/completed/CanonicalCompletedRecordsViewProvider.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/completed/CanonicalCompletedRecordsPageContributor.php';

$total = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok): void {
    global $total, $passed, $failed;
    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . "\n";
        return;
    }
    $failed[] = $label;
    echo '[FAIL] ' . $label . "\n";
}

final class ActivationLifecycleConfigStub {
    public $active = true;
    public function find_container_capability(int $container_id, string $capability_key): ?array {
        return $container_id === 41 && $capability_key === 'completed'
            ? ['is_active' => $this->active ? 1 : 0]
            : null;
    }
}

final class ActivationLifecycleCompletionStub {
    public function states_for_record_ids(array $record_ids): array {
        return in_array(7, $record_ids, true) ? [7 => true] : [];
    }
}

$capabilities = (new AA_Canonical_Capability_Registry())
    ->register(new AA_Canonical_Capability_Definition('completed', AA_Canonical_Capability_Definition::SCOPE_RECORD, true))
    ->freeze();
$config = new ActivationLifecycleConfigStub();
$provider = new CanonicalCompletedRecordsViewProvider($config, $capabilities);
$views = new CanonicalCapabilityRecordsViewRegistry();
$views->register($provider);
$contributor = new CanonicalCompletedRecordsPageContributor($config, new ActivationLifecycleCompletionStub(), $capabilities);

// Activa: la capability posee filtro, vista, navegación y representación.
$active_resolution = $views->resolve_query('action', 41, ['completed' => 'completed']);
$active_contribution = $contributor->contribute_for_records_page('action', 41, [7]);
ac_assert('Activa: filtro base de pendientes', !$views->resolve_query('action', 41)['spec']->criteria()['completed']->completed());
ac_assert('Activa: vista completadas resoluble', $active_resolution['spec']->criteria()['completed']->completed() && ($active_resolution['current']['label'] ?? '') === 'Completadas');
ac_assert('Activa: contribución y estado de tarjeta ofrecidos', $active_contribution->offered() && $active_contribution->state_for(7) !== null);

// Inactiva: no queda ninguna proyección, pero la vista sigue reconocida para que el router pueda volver a base.
$config->active = false;
$inactive_resolution = $views->resolve_query('action', 41, ['completed' => 'completed']);
$inactive_contribution = $contributor->contribute_for_records_page('action', 41, [7]);
ac_assert('Inactiva: lectura base sin filtro de capability', $views->resolve_query('action', 41)['spec']->criteria() === []);
ac_assert('Inactiva: sin vista, etiqueta ni navegación ofrecidas', $inactive_resolution['available'] === [] && $inactive_resolution['current'] === null);
ac_assert('Inactiva: URL de vista reconocida no produce filtro y permite retorno a base', $inactive_resolution['redirect'] && $inactive_resolution['selections'] === [] && $inactive_resolution['spec']->criteria() === []);
ac_assert('Inactiva: sin contribución ni acción de tarjeta', !$inactive_contribution->offered() && $inactive_contribution->records() === []);

// Reactivar recupera la misma proyección; la prueba no borra ni fabrica estado persistido.
$config->active = true;
$reactivated_resolution = $views->resolve_query('action', 41, ['completed' => 'completed']);
$reactivated_contribution = $contributor->contribute_for_records_page('action', 41, [7]);
ac_assert('Reactivada: recupera vista y filtro', $reactivated_resolution['spec']->criteria()['completed']->completed() && $reactivated_resolution['selections'] === ['completed' => 'completed']);
ac_assert('Reactivada: recupera el estado previamente legible', $reactivated_contribution->offered() && $reactivated_contribution->state_for(7) !== null);

// El router y el compositor son neutrales: no codifican la capability ni su familia.
$router_source = (string) file_get_contents($plugin_root . '/includes/admin/ui/index.php');
$composer_source = (string) file_get_contents($plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-shell-view-composer.php');
ac_assert('Router: normaliza transporte e inactividad a la URL canónica', strpos($router_source, "\$resolution['redirect'] || \$shell_records_view === null") !== false && strpos($router_source, 'wp_safe_redirect') !== false);
ac_assert('Router y compositor no nombran completed ni bifurcan por familia', strpos($router_source, "'completed'") === false && strpos($composer_source, "'completed'") === false && strpos($router_source, 'family_key() ===') === false && strpos($composer_source, 'family_key() ===') === false);

echo "\n{$passed}/{$total} assertions passed.\n";
if ($failed !== []) {
    exit(1);
}
