<?php
/**
 * AC Test — ListFinanceContainersUseCase (Ciclo 3B1).
 *
 * Ejecutar:
 *   php tests/application/finance/test-list-finance-containers-use-case-ac.php
 */

$plugin_root = dirname(__DIR__, 3);

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

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/ListFinanceContainersUseCase.php';

class TestListContainerWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public $vars = [];
    public $results = [];
    public $queries = [];

    public function prepare(string $query, ...$args): string {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        return $query;
    }

    public function get_var(string $query) {
        $this->queries[] = $query;
        if ($this->last_error !== '') {
            return null;
        }
        return array_shift($this->vars) ?? 0;
    }

    public function get_results(string $query, $output = ARRAY_A) {
        $this->queries[] = $query;
        if ($this->last_error !== '') {
            return [];
        }
        return array_shift($this->results) ?: [];
    }
}

global $wpdb;
$wpdb = new TestListContainerWpdbMock();

$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas', 'general'));
$registry->register_variant(new AA_Canonical_Variant_Definition('finance', 'general', 'General'));
$registry->freeze();

$use_case = new ListFinanceContainersUseCase($registry);

echo "=== 1. Listado vacío (total = 0) ===\n";

$wpdb->vars[] = '0';
$res_empty = $use_case->execute([]);
ac_assert('Listado vacío devuelve success: true', $res_empty['success'] === true);
ac_assert('items es array vacío', $res_empty['data']['items'] === []);
ac_assert('total es 0', $res_empty['data']['total'] === 0);
ac_assert('total_pages es 0', $res_empty['data']['total_pages'] === 0);
ac_assert('page es 1', $res_empty['data']['page'] === 1);
ac_assert('per_page es 15', $res_empty['data']['per_page'] === 15);
ac_assert('has_previous y has_next son false', $res_empty['data']['has_previous'] === false && $res_empty['data']['has_next'] === false);

echo "\n=== 2. Paginación de 15 elementos y navegación ===\n";

// Página 1 de 2 (Total = 20)
$wpdb->vars[] = '20';
$mock_page_1 = [];
for ($i = 20; $i >= 6; $i--) {
    $mock_page_1[] = [
        'id' => (string) $i,
        'variant_key' => 'general',
        'title' => "Contenedor {$i}",
        'details' => null,
        'created_at' => '2026-08-29 12:00:00',
    ];
}
$wpdb->results[] = $mock_page_1;

$res_p1 = $use_case->execute(['page' => 1]);
ac_assert('Página 1 devuelve exactamente 15 items', count($res_p1['data']['items']) === 15);
ac_assert('page es 1 y total_pages es 2', $res_p1['data']['page'] === 1 && $res_p1['data']['total_pages'] === 2);
ac_assert('has_previous es false y has_next es true', $res_p1['data']['has_previous'] === false && $res_p1['data']['has_next'] === true);
ac_assert('Cada item está enriquecido con family_key = finance', $res_p1['data']['items'][0]['family_key'] === 'finance');

// Página 2 de 2 (Total = 20)
$wpdb->vars[] = '20';
$mock_page_2 = [];
for ($i = 5; $i >= 1; $i--) {
    $mock_page_2[] = [
        'id' => (string) $i,
        'variant_key' => 'general',
        'title' => "Contenedor {$i}",
        'details' => null,
        'created_at' => '2026-08-29 11:00:00',
    ];
}
$wpdb->results[] = $mock_page_2;

$res_p2 = $use_case->execute(['page' => 2]);
ac_assert('Página 2 devuelve exactamente 5 items', count($res_p2['data']['items']) === 5);
ac_assert('page es 2 y total_pages es 2', $res_p2['data']['page'] === 2 && $res_p2['data']['total_pages'] === 2);
ac_assert('has_previous es true y has_next es false', $res_p2['data']['has_previous'] === true && $res_p2['data']['has_next'] === false);

echo "\n=== 3. Ajuste de páginas fuera de rango y normalización de página ===\n";

// Página 10 solicitada con total_pages = 2 -> Ajusta a página 2
$wpdb->vars[] = '20';
$wpdb->results[] = $mock_page_2;
$res_out_of_range = $use_case->execute(['page' => 10]);
ac_assert('Página superior al total se ajusta a la última página (page=2)', $res_out_of_range['data']['page'] === 2);

// Normalización de entradas no enteras (decimal, string no numérico, bool) -> Página 1
$wpdb->vars[] = '20';
$wpdb->results[] = $mock_page_1;
$res_bad_page = $use_case->execute(['page' => 'abc']);
ac_assert('Entrada no numérica de página se normaliza a page 1', $res_bad_page['data']['page'] === 1);

echo "\n=== 4. Consistencia eventual ante cambios concurrentes ===\n";

// El conteo dijo 20, pero por una eliminación concurrente el listado devolvió solo 4 items
$wpdb->vars[] = '20';
$wpdb->results[] = array_slice($mock_page_2, 0, 4);
$res_concurrent = $use_case->execute(['page' => 2]);
ac_assert('Cambio concurrente entre count y list retorna envelope limpio sin fallar', $res_concurrent['success'] === true && count($res_concurrent['data']['items']) === 4);

echo "\n=== 5. Rechazo de precondiciones y errores de persistencia ===\n";

$wpdb->queries = [];

$err_bad_var = $use_case->execute(['variant_key' => 'unknown_v']);
ac_assert('Variante desconocida devuelve unknown_variant', !$err_bad_var['success'] && $err_bad_var['error']['code'] === 'unknown_variant');
ac_assert('No llamó al repositorio ante variante desconocida', count($wpdb->queries) === 0);

$wpdb->last_error = 'SQL syntax error';
$err_db = $use_case->execute([]);
ac_assert('Error SQL se traduce a persistence_failed', !$err_db['success'] && $err_db['error']['code'] === 'persistence_failed');
$wpdb->last_error = '';

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
