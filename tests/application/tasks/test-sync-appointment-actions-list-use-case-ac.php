<?php
/**
 * AC MC1 — SyncAppointmentActionsListUseCase.
 *
 * Ejecutar: php tests/application/tasks/test-sync-appointment-actions-list-use-case-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$use_case_file = $plugin_root . '/includes/application/tasks/SyncAppointmentActionsListUseCase.php';
$catalog_file = $plugin_root . '/includes/domain/appointments/class-aa-appointment-actions-catalog.php';
$seeded_repo_file = $plugin_root . '/includes/repositories/SeededTaskRepository.php';

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

$use_case_src = file_get_contents($use_case_file);
ac_assert('Use case file readable', $use_case_src !== false);
ac_assert('Use case defines SyncAppointmentActionsListUseCase', strpos($use_case_src, 'class SyncAppointmentActionsListUseCase') !== false);
ac_assert('Use case reads AA_Appointment_Actions_Catalog', strpos($use_case_src, 'AA_Appointment_Actions_Catalog::list_definition()') !== false);
ac_assert('Use case writes seeded list', strpos($use_case_src, 'upsert_seeded_list') !== false);
ac_assert('Use case does not write seeded tasks', strpos($use_case_src, 'upsert_seeded_task') === false);
ac_assert('Use case has no automatic hook', strpos($use_case_src, 'add_action(') === false);
ac_assert('Use case does not touch AA_Learning_Catalog', strpos($use_case_src, 'AA_Learning_Catalog') === false);
ac_assert('Use case seeds Acciones de citas title', strpos($use_case_src, 'AA_Appointment_Actions_Catalog') !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $schema_file;
    require_once $catalog_file;
    require_once $seeded_repo_file;
    require_once $use_case_file;

    AA_Schema::install();

    global $wpdb;
    $lists_table = $wpdb->prefix . 'aa_task_lists';

    $first_result = (new SyncAppointmentActionsListUseCase())->execute();
    ac_assert('sync returns list_id', (int) ($first_result['list_id'] ?? 0) > 0);
    ac_assert('first sync reports list created or updated', (int) (($first_result['lists_created'] ?? 0) + ($first_result['lists_updated'] ?? 0)) === 1);

    $list = SeededTaskRepository::find_list_by_origin('agenda_app', 'appointment_actions');
    ac_assert('sync creates appointment_actions list', is_array($list) && ($list['title'] ?? '') === 'Acciones de citas');
    ac_assert('seeded list source_category agenda_app', ($list['source_category'] ?? '') === 'agenda_app');
    ac_assert('seeded list managed_by developer', ($list['managed_by'] ?? '') === 'developer');
    ac_assert('seeded list status active', ($list['status'] ?? '') === 'active');

    $list_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$lists_table} WHERE source_category = %s AND origin_key = %s",
            'agenda_app',
            'appointment_actions'
        )
    );
    ac_assert('single row for appointment_actions origin', $list_count === 1);

    $second_result = (new SyncAppointmentActionsListUseCase())->execute();
    $list_after_second = SeededTaskRepository::find_list_by_origin('agenda_app', 'appointment_actions');
    ac_assert('second sync preserves list id', (int) ($list_after_second['id'] ?? 0) === (int) ($list['id'] ?? 0));
    ac_assert('second sync keeps Acciones de citas title', ($list_after_second['title'] ?? '') === 'Acciones de citas');
    ac_assert('second sync keeps status active', ($list_after_second['status'] ?? '') === 'active');
    ac_assert('second sync reports lists_updated', (int) ($second_result['lists_updated'] ?? 0) === 1);
} else {
    echo "\n[SKIP] Integración WP: define AA_WP_ROOT=/ruta/a/wordpress para probar sync real.\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
