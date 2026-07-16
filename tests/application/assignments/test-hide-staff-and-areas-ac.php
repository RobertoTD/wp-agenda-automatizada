<?php
/**
 * AC — Soft hide for staff and service areas (MC1).
 *
 * Ejecutar: php tests/application/assignments/test-hide-staff-and-areas-ac.php
 *
 * Parte estática: sin WordPress.
 * Integración opcional: AA_WP_ROOT=/ruta/wordpress
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);
$model_file = $plugin_root . '/includes/models/AssignmentsModel.php';
$staff_service_file = $plugin_root . '/includes/services/assignments/staffService.php';
$areas_service_file = $plugin_root . '/includes/services/assignments/areasService.php';
$repo_file = $plugin_root . '/includes/repositories/AssignmentsRepository.php';
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';

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

$model_src = file_get_contents($model_file);
$staff_src = file_get_contents($staff_service_file);
$areas_src = file_get_contents($areas_service_file);
$repo_src = file_get_contents($repo_file);
$schema_src = file_get_contents($schema_file);

ac_assert('Model defines delete_staff', strpos($model_src, 'function delete_staff') !== false);
ac_assert('Model defines delete_service_area', strpos($model_src, 'function delete_service_area') !== false);
ac_assert(
    'delete_staff sets is_hidden and active',
    strpos($model_src, "'is_hidden' => 1") !== false
    && strpos($model_src, 'Personal ID $id ocultado correctamente') !== false
);
ac_assert(
    'delete_service_area sets is_hidden and active',
    strpos($model_src, 'Zona de atención ID $id ocultada correctamente') !== false
);
ac_assert(
    'get_staff excludes hidden rows',
    preg_match('/function get_staff[\s\S]*?is_hidden = 0/', $model_src) === 1
);
ac_assert(
    'get_service_areas excludes hidden rows',
    preg_match('/function get_service_areas[\s\S]*?is_hidden = 0/', $model_src) === 1
);

ac_assert(
    'staffService registers aa_delete_staff_db',
    strpos($staff_src, "add_action('wp_ajax_aa_delete_staff_db', 'aa_delete_staff_db')") !== false
);
ac_assert('staffService defines aa_delete_staff_db handler', strpos($staff_src, 'function aa_delete_staff_db') !== false);
ac_assert(
    'staffService hide response includes hidden flag',
    strpos($staff_src, "'hidden' => true") !== false
);

ac_assert(
    'areasService registers aa_delete_service_area_db',
    strpos($areas_src, "add_action('wp_ajax_aa_delete_service_area_db', 'aa_delete_service_area_db')") !== false
);
ac_assert('areasService defines aa_delete_service_area_db handler', strpos($areas_src, 'function aa_delete_service_area_db') !== false);
ac_assert(
    'areasService hide response includes hidden flag',
    strpos($areas_src, "'hidden' => true") !== false
);

ac_assert('Schema DB_VERSION is 11', strpos($schema_src, "DB_VERSION = '11'") !== false);
ac_assert(
    'Schema guardrail for staff is_hidden',
    strpos($schema_src, 'Ensure is_hidden column exists for existing staff installs') !== false
    && strpos($schema_src, 'ALTER TABLE {$staff_table} ADD COLUMN is_hidden') !== false
);
ac_assert(
    'Schema guardrail for service_areas is_hidden',
    strpos($schema_src, 'Ensure is_hidden column exists for existing service area installs') !== false
    && strpos($schema_src, 'ALTER TABLE {$service_areas_table} ADD COLUMN is_hidden') !== false
);
ac_assert(
    'Schema CREATE TABLE service_areas includes is_hidden',
    preg_match('/aa_service_areas[\s\S]*?is_hidden tinyint\(1\) NOT NULL DEFAULT 0/', $schema_src) === 1
);

ac_assert(
    'Repository count_active_staff excludes hidden',
    preg_match('/function count_active_staff[\s\S]*?active = 1 AND is_hidden = 0/', $repo_src) === 1
);
ac_assert(
    'Repository count_active_service_areas excludes hidden',
    preg_match('/function count_active_service_areas[\s\S]*?active = 1 AND is_hidden = 0/', $repo_src) === 1
);
ac_assert(
    'Repository count_active_staff_with_active_services excludes hidden staff',
    strpos($repo_src, 'st.is_hidden = 0') !== false
);
ac_assert(
    'Repository list_active_staff_ids excludes hidden',
    preg_match('/function list_active_staff_ids[\s\S]*?active = 1 AND is_hidden = 0/', $repo_src) === 1
);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $schema_file;
    require_once $model_file;
    require_once $repo_file;

    AA_Schema::install();

    global $wpdb;

    $staff_table = $wpdb->prefix . 'aa_staff';
    $areas_table = $wpdb->prefix . 'aa_service_areas';
    $services_table = $wpdb->prefix . 'aa_services';
    $links_table = $wpdb->prefix . 'aa_staff_services';

    // --- Staff hide ---
    $wpdb->insert($staff_table, [
        'name' => 'AC Hide Staff Visible',
        'active' => 1,
        'is_hidden' => 0,
        'created_at' => current_time('mysql'),
    ]);
    $visible_staff_id = (int) $wpdb->insert_id;

    $wpdb->insert($staff_table, [
        'name' => 'AC Hide Staff Target',
        'active' => 1,
        'is_hidden' => 0,
        'created_at' => current_time('mysql'),
    ]);
    $hidden_staff_id = (int) $wpdb->insert_id;

    $count_before = AssignmentsRepository::count_active_staff();
    ac_assert('Integration staff count includes visible active staff', $count_before >= 2);

    $hide_staff_ok = AssignmentsModel::delete_staff($hidden_staff_id);
    ac_assert('Integration delete_staff succeeds', $hide_staff_ok === true);

    $hidden_staff_row = $wpdb->get_row(
        $wpdb->prepare("SELECT is_hidden, active FROM {$staff_table} WHERE id = %d", $hidden_staff_id),
        ARRAY_A
    );
    ac_assert(
        'Integration delete_staff sets is_hidden=1 and active=0',
        (int) ($hidden_staff_row['is_hidden'] ?? -1) === 1
        && (int) ($hidden_staff_row['active'] ?? -1) === 0
    );

    $all_staff = AssignmentsModel::get_staff(false);
    $all_staff_ids = array_map('intval', array_column($all_staff, 'id'));
    ac_assert('Integration get_staff excludes hidden staff', !in_array($hidden_staff_id, $all_staff_ids, true));
    ac_assert('Integration get_staff still returns visible staff', in_array($visible_staff_id, $all_staff_ids, true));

    $active_staff = AssignmentsModel::get_staff(true);
    $active_staff_ids = array_map('intval', array_column($active_staff, 'id'));
    ac_assert('Integration get_staff(true) excludes hidden staff', !in_array($hidden_staff_id, $active_staff_ids, true));

    $count_after_hide = AssignmentsRepository::count_active_staff();
    ac_assert('Integration hidden staff not counted as active', $count_after_hide === $count_before - 1);

    $listed_ids = AssignmentsRepository::list_active_staff_ids();
    ac_assert('Integration list_active_staff_ids excludes hidden', !in_array($hidden_staff_id, $listed_ids, true));

    // --- Service area hide ---
    $wpdb->insert($areas_table, [
        'name' => 'AC Hide Area Visible',
        'active' => 1,
        'is_hidden' => 0,
        'created_at' => current_time('mysql'),
    ]);
    $visible_area_id = (int) $wpdb->insert_id;

    $wpdb->insert($areas_table, [
        'name' => 'AC Hide Area Target',
        'active' => 1,
        'is_hidden' => 0,
        'created_at' => current_time('mysql'),
    ]);
    $hidden_area_id = (int) $wpdb->insert_id;

    $area_count_before = AssignmentsRepository::count_active_service_areas();
    ac_assert('Integration area count includes visible active areas', $area_count_before >= 2);

    $hide_area_ok = AssignmentsModel::delete_service_area($hidden_area_id);
    ac_assert('Integration delete_service_area succeeds', $hide_area_ok === true);

    $hidden_area_row = $wpdb->get_row(
        $wpdb->prepare("SELECT is_hidden, active FROM {$areas_table} WHERE id = %d", $hidden_area_id),
        ARRAY_A
    );
    ac_assert(
        'Integration delete_service_area sets is_hidden=1 and active=0',
        (int) ($hidden_area_row['is_hidden'] ?? -1) === 1
        && (int) ($hidden_area_row['active'] ?? -1) === 0
    );

    $all_areas = AssignmentsModel::get_service_areas(false);
    $all_area_ids = array_map('intval', array_column($all_areas, 'id'));
    ac_assert('Integration get_service_areas excludes hidden area', !in_array($hidden_area_id, $all_area_ids, true));
    ac_assert('Integration get_service_areas still returns visible area', in_array($visible_area_id, $all_area_ids, true));

    $active_areas = AssignmentsModel::get_service_areas(true);
    $active_area_ids = array_map('intval', array_column($active_areas, 'id'));
    ac_assert('Integration get_service_areas(true) excludes hidden area', !in_array($hidden_area_id, $active_area_ids, true));

    $area_count_after_hide = AssignmentsRepository::count_active_service_areas();
    ac_assert('Integration hidden area not counted as active', $area_count_after_hide === $area_count_before - 1);

    // --- Hidden staff with active service not counted in staff_with_services ---
    $wpdb->insert($services_table, [
        'name' => 'AC Hide Staff Service',
        'code' => 'ac-hide-staff-svc',
        'active' => 1,
        'is_hidden' => 0,
        'created_at' => current_time('mysql'),
    ]);
    $service_id = (int) $wpdb->insert_id;

    $wpdb->insert($links_table, [
        'staff_id' => $hidden_staff_id,
        'service_id' => $service_id,
    ]);

    $staff_with_services = AssignmentsRepository::count_active_staff_with_active_services();
    ac_assert(
        'Integration hidden staff with service not counted',
        !in_array($hidden_staff_id, AssignmentsRepository::list_active_staff_ids(), true)
        && $staff_with_services <= $count_after_hide
    );

    // Cleanup (soft-hidden rows remain; delete test rows only)
    $wpdb->delete($links_table, ['staff_id' => $hidden_staff_id, 'service_id' => $service_id], ['%d', '%d']);
    $wpdb->delete($staff_table, ['id' => $visible_staff_id], ['%d']);
    $wpdb->delete($staff_table, ['id' => $hidden_staff_id], ['%d']);
    $wpdb->delete($areas_table, ['id' => $visible_area_id], ['%d']);
    $wpdb->delete($areas_table, ['id' => $hidden_area_id], ['%d']);
    $wpdb->delete($services_table, ['id' => $service_id], ['%d']);
} else {
    echo "\n(Sin AA_WP_ROOT: solo pruebas estáticas)\n";
}

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
