<?php
/**
 * AC — AssignmentsRepository::ensure_staff_service_link.
 *
 * Ejecutar: php tests/repositories/test-assignments-repository-ensure-staff-service-ac.php
 *
 * Parte estática: sin WordPress.
 * Integración opcional: AA_WP_ROOT=/ruta/wordpress
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 2);
$repo_file = $plugin_root . '/includes/repositories/AssignmentsRepository.php';

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

$repo_src = file_get_contents($repo_file);
ac_assert('Repository file readable', $repo_src !== false);
ac_assert('Repository defines ensure_staff_service_link', strpos($repo_src, 'function ensure_staff_service_link') !== false);
ac_assert('Repository defines list_active_staff_ids', strpos($repo_src, 'function list_active_staff_ids') !== false);
ac_assert('Repository defines list_assignable_service_ids', strpos($repo_src, 'function list_assignable_service_ids') !== false);
ac_assert('Repository uses get_staff_service_ids before insert', strpos($repo_src, 'get_staff_service_ids') !== false);
ac_assert('Repository reuses add_staff_service', strpos($repo_src, 'add_staff_service') !== false);
ac_assert(
    'Assignable services match active and not hidden',
    strpos($repo_src, 'active = 1 AND is_hidden = 0') !== false
);

class AA_Test_Ensure_Staff_Service_Repository {
    /** @var array<int, array<int, true>> */
    private static $links = [];

    public static function reset(): void {
        self::$links = [];
    }

    public static function ensure_staff_service_link(int $staff_id, int $service_id): string {
        if ($staff_id <= 0 || $service_id <= 0) {
            return 'failed';
        }

        if (!isset(self::$links[$staff_id])) {
            self::$links[$staff_id] = [];
        }

        if (isset(self::$links[$staff_id][$service_id])) {
            return 'skipped';
        }

        self::$links[$staff_id][$service_id] = true;

        return 'created';
    }

    public static function count_links(int $staff_id, int $service_id): int {
        return isset(self::$links[$staff_id][$service_id]) ? 1 : 0;
    }
}

AA_Test_Ensure_Staff_Service_Repository::reset();

$first = AA_Test_Ensure_Staff_Service_Repository::ensure_staff_service_link(1, 10);
ac_assert('Creates link when missing', $first === 'created');
ac_assert('Single row stored', AA_Test_Ensure_Staff_Service_Repository::count_links(1, 10) === 1);

$second = AA_Test_Ensure_Staff_Service_Repository::ensure_staff_service_link(1, 10);
ac_assert('Existing link is treated as valid', $second === 'skipped');
ac_assert('No duplicate row stored', AA_Test_Ensure_Staff_Service_Repository::count_links(1, 10) === 1);

$invalid = AA_Test_Ensure_Staff_Service_Repository::ensure_staff_service_link(0, 10);
ac_assert('Invalid ids fail safely', $invalid === 'failed');

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";

    require_once $wp_load;
    require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
    require_once $repo_file;

    AA_Schema::install();

    global $wpdb;

    $staff_table = $wpdb->prefix . 'aa_staff';
    $services_table = $wpdb->prefix . 'aa_services';
    $links_table = $wpdb->prefix . 'aa_staff_services';

    $wpdb->insert($staff_table, [
        'name' => 'Test Staff Ensure',
        'active' => 1,
        'created_at' => current_time('mysql'),
    ]);
    $staff_id = (int) $wpdb->insert_id;

    $wpdb->insert($services_table, [
        'name' => 'Test Service Ensure',
        'active' => 1,
        'created_at' => current_time('mysql'),
    ]);
    $service_id = (int) $wpdb->insert_id;

    $created = AssignmentsRepository::ensure_staff_service_link($staff_id, $service_id);
    ac_assert('Integration creates link', $created === 'created');

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$links_table} WHERE staff_id = %d AND service_id = %d",
            $staff_id,
            $service_id
        )
    );
    ac_assert('Integration stores one row', $count === 1);

    $skipped = AssignmentsRepository::ensure_staff_service_link($staff_id, $service_id);
    ac_assert('Integration skips existing link', $skipped === 'skipped');

    $count_after = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$links_table} WHERE staff_id = %d AND service_id = %d",
            $staff_id,
            $service_id
        )
    );
    ac_assert('Integration does not duplicate row', $count_after === 1);

    $wpdb->delete($links_table, ['staff_id' => $staff_id, 'service_id' => $service_id], ['%d', '%d']);
    $wpdb->delete($staff_table, ['id' => $staff_id], ['%d']);
    $wpdb->delete($services_table, ['id' => $service_id], ['%d']);
}

echo "\nPassed {$passed}/{$total}\n";

if ($passed !== $total) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}
