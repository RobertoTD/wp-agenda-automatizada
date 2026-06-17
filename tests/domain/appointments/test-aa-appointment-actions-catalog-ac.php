<?php
/**
 * AC MC1 — AA_Appointment_Actions_Catalog.
 *
 * Ejecutar: php tests/domain/appointments/test-aa-appointment-actions-catalog-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$catalog_file = $plugin_root . '/includes/domain/appointments/class-aa-appointment-actions-catalog.php';

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

require_once $catalog_file;

$catalog_src = file_get_contents($catalog_file);
ac_assert('Catalog file readable', $catalog_src !== false);
ac_assert('Catalog defines AA_Appointment_Actions_Catalog', strpos($catalog_src, 'class AA_Appointment_Actions_Catalog') !== false);
ac_assert('Catalog defines SEED_VERSION', strpos($catalog_src, 'SEED_VERSION') !== false);
ac_assert('Catalog SEED_VERSION is 1', AA_Appointment_Actions_Catalog::SEED_VERSION === '1');
ac_assert('Catalog SOURCE_CATEGORY agenda_app', AA_Appointment_Actions_Catalog::SOURCE_CATEGORY === 'agenda_app');
ac_assert('Catalog LIST_ORIGIN_KEY appointment_actions', AA_Appointment_Actions_Catalog::LIST_ORIGIN_KEY === 'appointment_actions');
ac_assert('Catalog LIST_TITLE Acciones de citas', AA_Appointment_Actions_Catalog::LIST_TITLE === 'Acciones de citas');
ac_assert('Catalog TASK_ACTION_KEY appointment.confirm', AA_Appointment_Actions_Catalog::TASK_ACTION_KEY === 'appointment.confirm');
ac_assert('Catalog task_origin_key helper', AA_Appointment_Actions_Catalog::task_origin_key(7) === 'appointment_confirmation:7');

$definition = AA_Appointment_Actions_Catalog::list_definition();
ac_assert('list_definition title', ($definition['title'] ?? '') === 'Acciones de citas');
ac_assert('list_definition source_category', ($definition['source_category'] ?? '') === 'agenda_app');
ac_assert('list_definition origin_key', ($definition['origin_key'] ?? '') === 'appointment_actions');
ac_assert('list_definition managed_by developer', ($definition['managed_by'] ?? '') === 'developer');
ac_assert('list_definition owner_type developer', ($definition['owner_type'] ?? '') === 'developer');
ac_assert('list_definition status active', ($definition['status'] ?? '') === 'active');

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
