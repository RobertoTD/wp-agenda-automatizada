<?php
/**
 * AC — Registry mínimo de presentación de capabilities (CP-1).
 *
 * Ejecutar: php tests/application/canonical/capabilities/test-canonical-capability-presentation-registry-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 4);
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-capability-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityPresentationDefinition.php';
require_once $plugin_root . '/includes/application/canonical/capabilities/presentation/CanonicalCapabilityPresentationRegistry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-capability-presentation-registry-bootstrap.php';

$total = 0;
$passed = 0;
$failed = [];

function presentation_registry_assert(string $label, bool $ok): void {
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

$blank_label_rejected = false;
try {
    new CanonicalCapabilityPresentationDefinition('amount', '  ');
} catch (InvalidArgumentException $e) {
    $blank_label_rejected = strpos($e->getMessage(), '[invalid_capability_presentation_label]') !== false;
}
presentation_registry_assert('Un label vacío es inválido', $blank_label_rejected);

$registry = new CanonicalCapabilityPresentationRegistry();
$registry->register(new CanonicalCapabilityPresentationDefinition('amount', 'Importe'));
$duplicate_rejected = false;
try {
    $registry->register(new CanonicalCapabilityPresentationDefinition('amount', 'Otro importe'));
} catch (InvalidArgumentException $e) {
    $duplicate_rejected = strpos($e->getMessage(), '[duplicate_capability_presentation]') !== false;
}
presentation_registry_assert('No acepta metadata duplicada', $duplicate_rejected);
$registry->freeze();
presentation_registry_assert('Registry sellado resuelve el label', $registry->get('amount')->label() === 'Importe');
$frozen_rejected = false;
try {
    $registry->register(new CanonicalCapabilityPresentationDefinition('email', 'Email'));
} catch (LogicException $e) {
    $frozen_rejected = strpos($e->getMessage(), '[capability_presentation_registry_frozen]') !== false;
}
presentation_registry_assert('Registry sellado rechaza registros nuevos', $frozen_rejected);

$capabilities = AA_Canonical_Capability_Registry_Bootstrap::build_registry();
$presentations = AA_Canonical_Capability_Presentation_Registry_Bootstrap::build_registry($capabilities);
$expected_labels = [
    'amount' => 'Importe',
    'phone' => 'Teléfono',
    'whatsapp' => 'WhatsApp',
    'email' => 'Email',
    'images' => 'Imágenes',
    'completed' => 'Completar',
];
foreach ($expected_labels as $key => $label) {
    presentation_registry_assert('Metadata de ' . $key . ' conserva su label', $presentations->get($key)->label() === $label);
}
foreach ($capabilities->all() as $capability) {
    if ($capability->is_ready()) {
        presentation_registry_assert('Toda capability ready tiene metadata: ' . $capability->key(), $presentations->has($capability->key()));
    }
}

$shell_source = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_shell/index.php');
presentation_registry_assert('El selector consulta el registry de presentación', strpos($shell_source, 'capability_presentation_registry_for_shell->get($cap_key)->label()') !== false);
presentation_registry_assert('El selector ya no contiene la cascada de labels por capability', strpos($shell_source, "if (\$cap_key === 'amount')") === false);

echo "\n{$passed}/{$total} assertions passed.\n";
if ($failed !== []) {
    exit(1);
}
