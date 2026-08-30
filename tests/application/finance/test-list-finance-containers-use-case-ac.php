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
    public $aggregated_queries_count = 0;

    public function prepare(string $query, ...$args): string {
        $flat_args = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                foreach ($arg as $sub) {
                    $flat_args[] = $sub;
                }
            } else {
                $flat_args[] = $arg;
            }
        }
        foreach ($flat_args as $val) {
            $rep = is_numeric($val) ? $val : "'" . addslashes((string) $val) . "'";
            $query = preg_replace('/%[sdf]/', (string) $rep, $query, 1);
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
        if (stripos($query, 'GROUP BY container_id') !== false) {
            $this->aggregated_queries_count++;
        }
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
$wpdb->aggregated_queries_count = 0;
$res_empty = $use_case->execute([]);
ac_assert('Listado vacío devuelve success: true', $res_empty['success'] === true);
ac_assert('items es array vacío', $res_empty['data']['items'] === []);
ac_assert('total es 0', $res_empty['data']['total'] === 0);
ac_assert('total_pages es 0', $res_empty['data']['total_pages'] === 0);
ac_assert('page es 1', $res_empty['data']['page'] === 1);
ac_assert('per_page es 15', $res_empty['data']['per_page'] === 15);
ac_assert('has_previous y has_next son false', $res_empty['data']['has_previous'] === false && $res_empty['data']['has_next'] === false);
ac_assert('Total cero no ejecuta consulta agregada de sumas (0 queries)', $wpdb->aggregated_queries_count === 0);

echo "\n=== 2. Paginación de 15 elementos y navegación con amount_total ===\n";

// Página 1 de 2 (Total = 20)
$wpdb->vars[] = '20';
$mock_page_1 = [];
$mock_sum_results_p1 = [];
for ($i = 20; $i >= 6; $i--) {
    $mock_page_1[] = [
        'id' => $i,
        'variant_key' => 'general',
        'title' => "Contenedor {$i}",
        'details' => null,
        'created_at' => '2026-08-29 12:00:00',
    ];
}
// Sumas para página 1: ID 20 tiene "100.00", ID 19 tiene "0.00", ID 18 tiene "-25.50", demás sin fila (null)
$mock_sum_results_p1 = [
    ['container_id' => '20', 'amount_total' => '100.00'],
    ['container_id' => '19', 'amount_total' => '0.00'],
    ['container_id' => '18', 'amount_total' => '-25.50'],
];

$wpdb->results[] = $mock_page_1;
$wpdb->results[] = $mock_sum_results_p1;
$wpdb->aggregated_queries_count = 0;

$res_p1 = $use_case->execute(['page' => 1]);
ac_assert('Página 1 devuelve exactamente 15 items', count($res_p1['data']['items']) === 15);
ac_assert('page es 1 y total_pages es 2', $res_p1['data']['page'] === 1 && $res_p1['data']['total_pages'] === 2);
ac_assert('has_previous es false y has_next es true', $res_p1['data']['has_previous'] === false && $res_p1['data']['has_next'] === true);
ac_assert('Cada item está enriquecido con family_key = finance', $res_p1['data']['items'][0]['family_key'] === 'finance');
ac_assert('Exactamente 1 consulta agregada ejecutada para 15 contenedores (ausencia de N+1)', $wpdb->aggregated_queries_count === 1);
ac_assert('amount_total positivo "100.00" proyectado en item 20', $res_p1['data']['items'][0]['amount_total'] === '100.00');
ac_assert('amount_total cero "0.00" proyectado en item 19', $res_p1['data']['items'][1]['amount_total'] === '0.00');
ac_assert('amount_total negativo "-25.50" proyectado en item 18', $res_p1['data']['items'][2]['amount_total'] === '-25.50');
ac_assert('amount_total null proyectado en item sin registros', $res_p1['data']['items'][3]['amount_total'] === null);

// Página 2 de 2 (Total = 20) -> 5 items
$wpdb->vars[] = '20';
$mock_page_2 = [];
for ($i = 5; $i >= 1; $i--) {
    $mock_page_2[] = [
        'id' => $i,
        'variant_key' => 'general',
        'title' => "Contenedor {$i}",
        'details' => null,
        'created_at' => '2026-08-29 11:00:00',
    ];
}
$mock_sum_results_p2 = [
    ['container_id' => '5', 'amount_total' => '50.00'],
];
$wpdb->results[] = $mock_page_2;
$wpdb->results[] = $mock_sum_results_p2;
$wpdb->aggregated_queries_count = 0;

$res_p2 = $use_case->execute(['page' => 2]);
ac_assert('Página 2 devuelve exactamente 5 items', count($res_p2['data']['items']) === 5);
ac_assert('page es 2 y total_pages es 2', $res_p2['data']['page'] === 2 && $res_p2['data']['total_pages'] === 2);
ac_assert('has_previous es true y has_next es false', $res_p2['data']['has_previous'] === true && $res_p2['data']['has_next'] === false);
ac_assert('Exactamente 1 consulta agregada ejecutada para 5 contenedores', $wpdb->aggregated_queries_count === 1);
ac_assert('amount_total "50.00" proyectado en item 5', $res_p2['data']['items'][0]['amount_total'] === '50.00');

// Caso de 1 solo contenedor en listado
$wpdb->vars[] = '1';
$wpdb->results[] = [
    ['id' => 100, 'variant_key' => 'general', 'title' => 'Único', 'details' => null, 'created_at' => '2026-08-29 10:00:00']
];
$wpdb->results[] = [
    ['container_id' => '100', 'amount_total' => '75.00']
];
$wpdb->aggregated_queries_count = 0;
$res_single = $use_case->execute(['page' => 1]);
ac_assert('Página con 1 contenedor ejecuta exactamente 1 consulta agregada', $wpdb->aggregated_queries_count === 1 && count($res_single['data']['items']) === 1);
ac_assert('amount_total "75.00" proyectado en contenedor único', $res_single['data']['items'][0]['amount_total'] === '75.00');

echo "\n=== 3. Ajuste de páginas fuera de rango y normalización de página ===\n";

// Página 10 solicitada con total_pages = 2 -> Ajusta a página 2
$wpdb->vars[] = '20';
$wpdb->results[] = $mock_page_2;
$wpdb->results[] = $mock_sum_results_p2;
$res_out_of_range = $use_case->execute(['page' => 10]);
ac_assert('Página superior al total se ajusta a la última página (page=2)', $res_out_of_range['data']['page'] === 2);

// Normalización de entradas no enteras (decimal, string no numérico, bool) -> Página 1
$wpdb->vars[] = '20';
$wpdb->results[] = $mock_page_1;
$wpdb->results[] = $mock_sum_results_p1;
$res_bad_page = $use_case->execute(['page' => 'abc']);
ac_assert('Entrada no numérica de página se normaliza a page 1', $res_bad_page['data']['page'] === 1);

echo "\n=== 4. Consistencia eventual ante cambios concurrentes ===\n";

// El conteo dijo 20, pero por una eliminación concurrente el listado devolvió solo 4 items
$wpdb->vars[] = '20';
$wpdb->results[] = array_slice($mock_page_2, 0, 4);
$wpdb->results[] = $mock_sum_results_p2;
$res_concurrent = $use_case->execute(['page' => 2]);
ac_assert('Cambio concurrente entre count y list retorna envelope limpio sin fallar', $res_concurrent['success'] === true && count($res_concurrent['data']['items']) === 4);

// El conteo dijo 5, pero la lista quedó vacía por eliminaciones concurrentes
$wpdb->vars[] = '5';
$wpdb->results[] = [];
$wpdb->aggregated_queries_count = 0;
$res_concurrent_empty = $use_case->execute(['page' => 1]);
ac_assert('Lista vacía por cambio concurrente devuelve items vacíos', $res_concurrent_empty['success'] === true && $res_concurrent_empty['data']['items'] === []);
ac_assert('Lista vacía por cambio concurrente no ejecuta consulta agregada', $wpdb->aggregated_queries_count === 0);

echo "\n=== 5. Rechazo de precondiciones y errores de persistencia ===\n";

$wpdb->queries = [];

// 5.1 Variante desconocida
$err_bad_var = $use_case->execute(['variant_key' => 'unknown_v']);
ac_assert('Variante desconocida devuelve unknown_variant', !$err_bad_var['success'] && $err_bad_var['error']['code'] === 'unknown_variant');
ac_assert('No llamó al repositorio ante variante desconocida', count($wpdb->queries) === 0);

// 5.2 Error SQL en count_by_variant
$wpdb->last_error = 'SQL syntax error';
$err_db = $use_case->execute([]);
ac_assert('Error SQL en conteo se traduce a persistence_failed', !$err_db['success'] && $err_db['error']['code'] === 'persistence_failed');
$wpdb->last_error = '';

// 5.3 Error SQL en consulta agregada de sumas
$wpdb->vars[] = '1';
$wpdb->results[] = [
    ['id' => 10, 'variant_key' => 'general', 'title' => 'C10', 'details' => null, 'created_at' => '2026-08-29 10:00:00']
];
$wpdb->last_error = 'Timeout in sum query';
$err_db_sum = $use_case->execute([]);
ac_assert('Error SQL en sum_amounts_by_container_ids se traduce a persistence_failed', !$err_db_sum['success'] && $err_db_sum['error']['code'] === 'persistence_failed');
$wpdb->last_error = '';

// 5.4 Fila de contenedor sin ID válido o fallo en persistencia de totales (fail-closed)
// Sobrescribimos temporalmente el mock para simular una consulta agregada que lanza error
$wpdb->vars[] = '1';
$wpdb->results[] = [
    ['id' => '1', 'variant_key' => 'general', 'title' => 'C1', 'details' => null, 'created_at' => '2026-08-29 10:00:00']
];
$wpdb->last_error = 'Aggregate query failed';
$err_bad_row_id = $use_case->execute([]);
ac_assert('Fallo en consulta agregada devuelve persistence_failed', !$err_bad_row_id['success'] && $err_bad_row_id['error']['code'] === 'persistence_failed');
ac_assert('Fallo en consulta agregada preserva código persistence_failed', $err_bad_row_id['error']['code'] === 'persistence_failed');
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
