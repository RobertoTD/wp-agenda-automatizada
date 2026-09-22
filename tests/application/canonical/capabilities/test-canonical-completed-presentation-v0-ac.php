<?php
/**
 * AC — completed como consumidor del Capability Presentation Contract v0.
 *
 * Ejecutar: php tests/application/canonical/capabilities/test-canonical-completed-presentation-v0-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 4);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityCardAction.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityCardActionProvider.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityCardActionRegistry.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityCardMetadata.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityCardMetadataProvider.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityCardMetadataRegistry.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityClientModule.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityClientModuleRegistry.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/completed/CanonicalCompletedCardActionProvider.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/CanonicalCapabilityRecordReadState.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/completed/CanonicalCompletedCardMetadataProvider.php';

$total = 0;
$passed = 0;
$failed = [];

function completed_presentation_assert(string $label, bool $ok): void {
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

$provider = new CanonicalCompletedCardActionProvider();
$pending_actions = $provider->actions_for_record(19, ['completed' => ['status' => 'known_value', 'value' => '0']]);
$done_actions = $provider->actions_for_record(19, ['completed' => ['status' => 'known_value', 'value' => '1']]);
$absent_actions = $provider->actions_for_record(19, []);
$invalid_actions = $provider->actions_for_record(19, ['completed' => ['status' => 'known_value', 'value' => 'other']]);
$pending = $pending_actions[0]->to_array();
$done = $done_actions[0]->to_array();

completed_presentation_assert('Estado pendiente ofrece una acción', count($pending_actions) === 1);
completed_presentation_assert('Pendiente conserva copy y payload de completar', $pending['label'] === 'Completar' && $pending['payload'] === ['record_id' => 19, 'completed' => 1]);
completed_presentation_assert('Estado completado conserva copy y payload de retorno', $done['label'] === 'Marcar como pendiente' && $done['payload'] === ['record_id' => 19, 'completed' => 0]);
completed_presentation_assert('Estado ausente no ofrece acción', $absent_actions === []);
completed_presentation_assert('Estado inválido no ofrece acción', $invalid_actions === []);

$metadata_provider = new CanonicalCompletedCardMetadataProvider();
$completed_metadata = $metadata_provider->metadata_for_record(19, [
    'completed' => CanonicalCapabilityRecordReadState::known_fields([
        'completed' => '1',
        'completed_at' => '2026-09-21 16:12:40',
    ])->to_array(),
]);
$pending_metadata = $metadata_provider->metadata_for_record(19, [
    'completed' => CanonicalCapabilityRecordReadState::known_fields(['completed' => '0'])->to_array(),
]);
$metadata = $completed_metadata[0]->to_array();
completed_presentation_assert('Estado completado aporta metadata tipada', count($completed_metadata) === 1 && $metadata['label'] === 'Completada el' && $metadata['datetime_utc'] === '2026-09-21T16:12:40Z');
completed_presentation_assert('Estado pendiente no aporta metadata', $pending_metadata === []);

$actions_registry = new CanonicalCapabilityCardActionRegistry();
$actions_registry->register($provider)->freeze();
completed_presentation_assert('Registry de acciones ofrece provider sellado', count($actions_registry->all()) === 1);
$duplicate_rejected = false;
try {
    $actions_registry->register($provider);
} catch (LogicException $e) {
    $duplicate_rejected = strpos($e->getMessage(), '[capability_card_action_registry_frozen]') !== false;
}
completed_presentation_assert('Registry sellado rechaza nuevas acciones', $duplicate_rejected);

$metadata_registry = new CanonicalCapabilityCardMetadataRegistry();
$metadata_registry->register($metadata_provider)->freeze();
completed_presentation_assert('Registry sellado ofrece provider de metadata', count($metadata_registry->all()) === 1);

$module = new CanonicalCapabilityClientModule('completed', 'asset.js', 'ajax_action', 'nonce_action');
$modules_registry = new CanonicalCapabilityClientModuleRegistry();
$modules_registry->register($module)->freeze();
completed_presentation_assert('Módulo se ofrece solo con capability activa', count($modules_registry->offered(['completed' => ['offered' => true]])) === 1);
completed_presentation_assert('Capability inactiva no carga módulo', $modules_registry->offered(['completed' => ['offered' => false]]) === []);

$card_source = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/partials/record-card.php');
$shell_source = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
$script_source = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-completed-action.js');
$endpoint_source = (string) file_get_contents($plugin_root . '/includes/http/ajax/CanonicalSetRecordCompletionAjax.php');
completed_presentation_assert('Card usa slots genéricos y no conoce completed', strpos($card_source, 'completed') === false && strpos($card_source, 'record-card-capability-actions.php') !== false && strpos($card_source, 'record-card-capability-metadata.php') !== false);
completed_presentation_assert('Shell itera módulos y no conoce configuración completed', strpos($shell_source, 'completed_capability_active') === false && strpos($shell_source, 'AA_CANONICAL_SHELL_COMPLETED') === false && strpos($shell_source, 'capability_client_modules') !== false);
completed_presentation_assert('Módulo de completed consume namespace genérico y payload', strpos($script_source, 'AA_CANONICAL_SHELL_CAPABILITY_ACTION_MODULES') !== false && strpos($script_source, 'data-aa-capability-action-payload') !== false);
completed_presentation_assert('Módulo de completed transporta el contexto compuesto', strpos($script_source, 'config.returnContext') !== false && strpos($script_source, "capability_views[' + owner + ']") !== false);
completed_presentation_assert('Endpoint completed valida y reconstruye el retorno compuesto', strpos($endpoint_source, 'parse_mutation_return_context') !== false && strpos($endpoint_source, '$return_ctx[\'capability_views\']') !== false);

echo "\n{$passed}/{$total} assertions passed.\n";
if ($failed !== []) {
    exit(1);
}
