<?php
/** Execute: php tests/admin/ui/test-canonical-clean-shell-read-route-ac.php */
define('ABSPATH', __DIR__ . '/');
$plugin_root = dirname(__DIR__, 3);
require_once $plugin_root . '/includes/admin/ui/modules/canonical_clean_shell/class-aa-canonical-clean-shell-read-route.php';

function clean_route_assert(string $label, bool $condition): void {
    if (!$condition) { fwrite(STDERR, "[FAIL] {$label}\n"); exit(1); }
    echo "[ OK ] {$label}\n";
}

$root = AA_Canonical_Clean_Shell_Read_Route::from_query([]);
clean_route_assert('Root is the default route', $root === ['kind' => 'root', 'page' => 1, 'container_id' => 0]);

$records = AA_Canonical_Clean_Shell_Read_Route::from_query(['view' => 'records', 'container_id' => '17', 'page' => '2']);
clean_route_assert('Records route accepts positive decimal identifiers', $records === ['kind' => 'records', 'page' => 2, 'container_id' => 17]);

$invalid_records = AA_Canonical_Clean_Shell_Read_Route::from_query(['view' => 'records', 'container_id' => '0']);
clean_route_assert('Records route rejects missing or non-positive list identifiers', $invalid_records['kind'] === 'invalid_records');

$invalid_page = AA_Canonical_Clean_Shell_Read_Route::from_query(['page' => 'bad']);
clean_route_assert('Invalid presentation page normalizes to first page', $invalid_page['page'] === 1);

$unknown_view = AA_Canonical_Clean_Shell_Read_Route::from_query(['view' => 'anything', 'container_id' => '99']);
clean_route_assert('Unknown view stays on the only root surface', $unknown_view === ['kind' => 'root', 'page' => 1, 'container_id' => 0]);

$shell = (string) file_get_contents($plugin_root . '/includes/admin/ui/modules/canonical_free_shell.php');
clean_route_assert('Read shell uses paginated core reads', strpos($shell, 'lists_page(') !== false && strpos($shell, 'records_page(') !== false);
clean_route_assert('Read shell has no visible mutation transport', strpos($shell, 'aa_canonical_free_save_') === false && strpos($shell, 'aa_canonical_free_delete_') === false);
clean_route_assert('Read shell does not select a Family', strpos($shell, "['family']") === false && strpos($shell, 'family=') === false);

echo "--- Resumen: 8/8 ---\n";
