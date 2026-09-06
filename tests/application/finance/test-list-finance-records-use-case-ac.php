<?php
/**
 * AC Test — ListFinanceRecordsUseCase (Ciclo 3B2a).
 *
 * Ejecutar:
 *   php tests/application/finance/test-list-finance-records-use-case-ac.php
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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/repositories/FinanceRecordRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/ListFinanceRecordsUseCase.php';

// Mock de $wpdb
class TestListRecordsWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public $count_queries = 0;
    public $sum_queries = 0;
    public $list_queries = 0;

    public $containers = [
        1 => [
            'id' => 1,
            'variant_key' => 'general',
            'title' => 'Caja Con Registros',
            'details' => 'Detalle de caja',
            'created_at' => '2026-08-29 10:00:00',
        ],
        2 => [
            'id' => 2,
            'variant_key' => 'general',
            'title' => 'Caja Vacía',
            'details' => null,
            'created_at' => '2026-08-29 10:05:00',
        ],
        3 => [
            'id' => 3,
            'variant_key' => 'other_variant',
            'title' => 'Caja Ajena',
            'details' => null,
            'created_at' => '2026-08-29 10:10:00',
        ],
    ];

    public $mock_counts = [
        1 => 20, // 20 registros
        2 => 0,  // 0 registros
    ];

    public $mock_sums = [
        1 => ['count_with_amount' => 15, 'total_amount' => '350.50'],
        2 => ['count_with_amount' => 0, 'total_amount' => null],
    ];

    public function prepare(string $query, ...$args) {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[dfs]/', (string) $val, $query, 1);
        }
        return $query;
    }

    public function get_row(string $query, string $output = ARRAY_A) {
        if ($this->last_error !== '') {
            return null;
        }
        if (stripos($query, 'aa_finance_containers') !== false && preg_match('/WHERE\s+id\s*=\s*(\d+)/i', $query, $m)) {
            $id = (int) $m[1];
            return isset($this->containers[$id]) ? $this->containers[$id] : null;
        }
        if (stripos($query, 'aa_finance_records') !== false && preg_match('/WHERE\s+container_id\s*=\s*(\d+)/i', $query, $m)) {
            $this->sum_queries++;
            $cid = (int) $m[1];
            return isset($this->mock_sums[$cid]) ? $this->mock_sums[$cid] : null;
        }
        return null;
    }

    public function get_var(string $query) {
        if ($this->last_error !== '') {
            return null;
        }
        if (stripos($query, 'aa_finance_records') !== false && preg_match('/WHERE\s+container_id\s*=\s*(\d+)/i', $query, $m)) {
            $this->count_queries++;
            $cid = (int) $m[1];
            return isset($this->mock_counts[$cid]) ? $this->mock_counts[$cid] : 0;
        }
        return 0;
    }

    public function get_results(string $query, string $output = ARRAY_A) {
        if ($this->last_error !== '') {
            return [];
        }
        if (stripos($query, 'aa_finance_records') !== false && preg_match('/WHERE\s+container_id\s*=\s*(\d+)/i', $query, $m)) {
            $this->list_queries++;
            $cid = (int) $m[1];
            $limit = 15;
            $offset = 0;
            if (preg_match('/LIMIT\s+(\d+)\s+OFFSET\s+(\d+)/i', $query, $lm)) {
                $limit = (int) $lm[1];
                $offset = (int) $lm[2];
            }

            $results = [];
            $total = isset($this->mock_counts[$cid]) ? $this->mock_counts[$cid] : 0;
            for ($i = $offset + 1; $i <= min($offset + $limit, $total); $i++) {
                $results[] = [
                    'id' => $i,
                    'container_id' => $cid,
                    'title' => "Registro {$i}",
                    'details' => null,
                    'amount' => '10.00',
                    'created_at' => '2026-08-29 12:00:00',
                ];
            }
            return $results;
        }
        return [];
    }
}

global $wpdb;
$wpdb = new TestListRecordsWpdbMock();

// Construir registry de dominio aislado
$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$registry->freeze();

$use_case = new ListFinanceRecordsUseCase($registry);

echo "=== 1. Listado vacío y evitación de consultas innecesarias ===\n";

$wpdb->count_queries = 0;
$wpdb->sum_queries = 0;
$wpdb->list_queries = 0;

$res_empty = $use_case->execute(['container_id' => 2]);
ac_assert('Listado vacío devuelve success: true', $res_empty['success'] === true);
ac_assert('items es array vacío', $res_empty['data']['items'] === []);
ac_assert('total es 0 y total_pages es 0', $res_empty['data']['total'] === 0 && $res_empty['data']['total_pages'] === 0);
ac_assert('page es 1 y per_page es 15', $res_empty['data']['page'] === 1 && $res_empty['data']['per_page'] === 15);
ac_assert('has_previous y has_next son false', !$res_empty['data']['has_previous'] && !$res_empty['data']['has_next']);
ac_assert('amount_total es null', $res_empty['data']['amount_total'] === null);
ac_assert('Incluye container DTO con family_key', $res_empty['data']['container']['family_key'] === 'finance' && $res_empty['data']['container']['id'] === 2);

ac_assert('Ejecutó 1 consulta de count', $wpdb->count_queries === 1);
ac_assert('NO ejecutó consulta de sum ante total 0', $wpdb->sum_queries === 0);
ac_assert('NO ejecutó consulta de list ante total 0', $wpdb->list_queries === 0);

echo "\n=== 2. Listado con registros, paginación y suma ===\n";

$wpdb->count_queries = 0;
$wpdb->sum_queries = 0;
$wpdb->list_queries = 0;

// 2.1 Página 1 (de 2 páginas: 20 registros)
$res_p1 = $use_case->execute(['container_id' => 1, 'page' => 1]);
ac_assert('Página 1 devuelve 15 items', count($res_p1['data']['items']) === 15);
ac_assert('total es 20 y total_pages es 2', $res_p1['data']['total'] === 20 && $res_p1['data']['total_pages'] === 2);
ac_assert('page es 1', $res_p1['data']['page'] === 1);
ac_assert('has_previous es false y has_next es true', !$res_p1['data']['has_previous'] && $res_p1['data']['has_next']);
ac_assert('amount_total es "350.50"', $res_p1['data']['amount_total'] === '350.50');
ac_assert('Items enriquecidos con family_key y variant_key', $res_p1['data']['items'][0]['family_key'] === 'finance' && $res_p1['data']['items'][0]['variant_key'] === 'general');

// 2.2 Página 2
$res_p2 = $use_case->execute(['container_id' => 1, 'page' => 2]);
ac_assert('Página 2 devuelve 5 items restantes', count($res_p2['data']['items']) === 5);
ac_assert('page es 2', $res_p2['data']['page'] === 2);
ac_assert('has_previous es true y has_next es false', $res_p2['data']['has_previous'] && !$res_p2['data']['has_next']);

// 2.3 Página superior al total (ej. page 99) ajusta a la última página (page 2)
$res_p_high = $use_case->execute(['container_id' => 1, 'page' => 99]);
ac_assert('Página 99 se ajusta a total_pages (2)', $res_p_high['data']['page'] === 2);

echo "\n=== 3. Variaciones de suma monetaria ===\n";

// 3.1 Suma con resultado cero
$wpdb->mock_sums[1] = ['count_with_amount' => 5, 'total_amount' => '0.00'];
$res_sum_zero = $use_case->execute(['container_id' => 1]);
ac_assert('Suma cero devuelve "0.00"', $res_sum_zero['data']['amount_total'] === '0.00');

// 3.2 Suma negativa
$wpdb->mock_sums[1] = ['count_with_amount' => 3, 'total_amount' => '-75.25'];
$res_sum_neg = $use_case->execute(['container_id' => 1]);
ac_assert('Suma negativa devuelve "-75.25"', $res_sum_neg['data']['amount_total'] === '-75.25');

// 3.3 Todos los registros con amount null
$wpdb->mock_sums[1] = ['count_with_amount' => 0, 'total_amount' => null];
$res_sum_null = $use_case->execute(['container_id' => 1]);
ac_assert('Contenedor sin importes sumables devuelve amount_total null', $res_sum_null['data']['amount_total'] === null);

echo "\n=== 4. Rechazos contextuales (anti-leak) y errores ===\n";

// 4.1 Contenedor inexistente
$err_no_c = $use_case->execute(['container_id' => 999]);
ac_assert('Contenedor inexistente devuelve container_not_found', !$err_no_c['success'] && $err_no_c['error']['code'] === 'container_not_found');

// 4.2 Contenedor de otra variante
$err_wrong_var = $use_case->execute(['container_id' => 3]);
ac_assert('Contenedor de otra variante devuelve container_not_found', !$err_wrong_var['success'] && $err_wrong_var['error']['code'] === 'container_not_found');

// 4.3 Fallo SQL se traduce a persistence_failed
$wpdb->last_error = 'Error de conexión';
$err_db = $use_case->execute(['container_id' => 1]);
ac_assert('Fallo SQL se traduce a persistence_failed', !$err_db['success'] && $err_db['error']['code'] === 'persistence_failed');
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
