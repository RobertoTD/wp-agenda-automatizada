<?php
/**
 * AC Test — CreateFinanceRecordUseCase (Ciclo 3B2a).
 *
 * Ejecutar:
 *   php tests/application/finance/test-create-finance-record-use-case-ac.php
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

if (!function_exists('current_time')) {
    function current_time(string $type = 'mysql'): string {
        return '2026-08-29 18:30:00';
    }
}

require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/repositories/FinanceRecordRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/CreateFinanceRecordUseCase.php';

// Mock de $wpdb para interceptar llamadas
class TestCreateRecordWpdbMock {
    public $prefix = 'wp_';
    public $insert_id = 100;
    public $last_error = '';
    public $inserts = [];
    public $containers = [
        1 => [
            'id' => 1,
            'variant_key' => 'general',
            'title' => 'Caja Principal',
            'details' => null,
            'created_at' => '2026-08-29 10:00:00',
        ],
        2 => [
            'id' => 2,
            'variant_key' => 'other_variant',
            'title' => 'Caja Ajena',
            'details' => null,
            'created_at' => '2026-08-29 11:00:00',
        ],
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
        if (preg_match('/FROM\s+\w+aa_finance_containers\s+WHERE\s+id\s*=\s*(\d+)/i', $query, $m)) {
            $id = (int) $m[1];
            return isset($this->containers[$id]) ? $this->containers[$id] : null;
        }
        return null;
    }

    public function insert(string $table, array $data, array $format = []) {
        $this->inserts[] = ['table' => $table, 'data' => $data, 'format' => $format];
        if ($this->last_error !== '') {
            return false;
        }
        return 1;
    }

    public function query(string $query) {
        if ($this->last_error !== '') {
            return false;
        }
        return 1;
    }

    public function update($table, array $data, array $where, $format = null, $where_format = null) {
        if ($this->last_error !== '') {
            return false;
        }
        return 1;
    }
}

global $wpdb;
$wpdb = new TestCreateRecordWpdbMock();

// Construir registry de dominio aislado
$registry = new AA_Canonical_Registry();
$registry->register_family(new AA_Canonical_Family_Definition('finance', 'Finanzas'));
$registry->freeze();

$use_case = new CreateFinanceRecordUseCase($registry);

echo "=== 1. Creaciones exitosas con amount normalizado ===\n";

// 1.1 Con amount positivo normalizado
$res1 = $use_case->execute([
    'container_id' => 1,
    'title' => 'Ingreso Consulta',
    'details' => "Paciente Juan\nSesión 1",
    'amount' => ' 150.5 ',
]);
ac_assert('Creación exitosa devuelve success: true', $res1['success'] === true);
ac_assert('Devuelve record con id poblado', $res1['data']['record']['id'] === 100);
ac_assert('Enriquece con family_key = finance', $res1['data']['record']['family_key'] === 'finance');
ac_assert('Enriquece con variant_key = general', $res1['data']['record']['variant_key'] === 'general');
ac_assert('Preserva container_id', $res1['data']['record']['container_id'] === 1);
ac_assert('Normaliza amount " 150.5 " a "150.50"', $res1['data']['record']['amount'] === '150.50');
ac_assert('Insertó exactamente el amount normalizado en BD', $wpdb->inserts[0]['data']['amount'] === '150.50');

// 1.2 Con amount null
$wpdb->inserts = [];
$res2 = $use_case->execute([
    'container_id' => 1,
    'title' => 'Nota sin importe',
    'amount' => null,
]);
ac_assert('Creación con amount null exitosa', $res2['success'] === true && $res2['data']['record']['amount'] === null);
ac_assert('Insertó null en BD para amount', $wpdb->inserts[0]['data']['amount'] === null);

// 1.3 Con amount cero normalizado
$wpdb->inserts = [];
$res3 = $use_case->execute([
    'container_id' => 1,
    'title' => 'Ajuste cero',
    'amount' => '-000.00',
]);
ac_assert('Amount "-000.00" normaliza a "0.00"', $res3['success'] === true && $res3['data']['record']['amount'] === '0.00');

// 1.4 Con amount negativo normalizado
$wpdb->inserts = [];
$res4 = $use_case->execute([
    'container_id' => 1,
    'title' => 'Gasto Materiales',
    'amount' => '-0045.8',
]);
ac_assert('Amount "-0045.8" normaliza a "-45.80"', $res4['success'] === true && $res4['data']['record']['amount'] === '-45.80');

echo "\n=== 2. Rechazo contextual anti-leak sin invocar repositorio de registros ===\n";

$wpdb->inserts = [];

// 2.1 Contenedor inexistente
$err_not_found = $use_case->execute([
    'container_id' => 999,
    'title' => 'Válido',
]);
ac_assert('Contenedor inexistente devuelve container_not_found', !$err_not_found['success'] && $err_not_found['error']['code'] === 'container_not_found');

// 2.2 Contenedor de otra variante
$err_wrong_var = $use_case->execute([
    'container_id' => 2, // pertenece a other_variant
    'title' => 'Válido',
]);
ac_assert('Contenedor de otra variante devuelve container_not_found', !$err_wrong_var['success'] && $err_wrong_var['error']['code'] === 'container_not_found');

// 2.3 Container ID inválido
$err_bad_cid = $use_case->execute([
    'container_id' => 'abc',
    'title' => 'Válido',
]);
ac_assert('container_id inválido devuelve invalid_container_id', !$err_bad_cid['success'] && $err_bad_cid['error']['code'] === 'invalid_container_id');

ac_assert('Fallos de contexto no intentaron ningún insert (0 inserts)', count($wpdb->inserts) === 0);

echo "\n=== 3. Rechazo de validaciones de título, details y amount sin insertar ===\n";

$wpdb->inserts = [];

// 3.1 Title ausente
$err_title = $use_case->execute(['container_id' => 1, 'title' => '  ']);
ac_assert('Title vacío devuelve missing_title', !$err_title['success'] && $err_title['error']['code'] === 'missing_title');

// 3.2 Details inválidos
$err_det = $use_case->execute(['container_id' => 1, 'title' => 'Ok', 'details' => 123]);
ac_assert('Details no string devuelve invalid_details', !$err_det['success'] && $err_det['error']['code'] === 'invalid_details');

// 3.3 Amount con 3 decimales
$err_dec = $use_case->execute(['container_id' => 1, 'title' => 'Ok', 'amount' => '1.999']);
ac_assert('Amount con 3 decimales devuelve amount_too_many_decimals', !$err_dec['success'] && $err_dec['error']['code'] === 'amount_too_many_decimals');

// 3.4 Amount con 18 enteros
$err_range = $use_case->execute(['container_id' => 1, 'title' => 'Ok', 'amount' => '100000000000000000.00']);
ac_assert('Amount con 18 enteros devuelve amount_out_of_range', !$err_range['success'] && $err_range['error']['code'] === 'amount_out_of_range');

// 3.5 Amount no string
$err_type = $use_case->execute(['container_id' => 1, 'title' => 'Ok', 'amount' => 10.5]);
ac_assert('Amount float devuelve invalid_amount', !$err_type['success'] && $err_type['error']['code'] === 'invalid_amount');

ac_assert('Validaciones fallidas no llamaron a insert (0 inserts)', count($wpdb->inserts) === 0);

echo "\n=== 4. Fallos de persistencia SQL y carreras ===\n";

$wpdb->last_error = 'Error de FK o desconexión';
$err_db = $use_case->execute(['container_id' => 1, 'title' => 'Fallará']);
ac_assert('Fallo SQL en create se traduce a persistence_failed', !$err_db['success'] && $err_db['error']['code'] === 'persistence_failed');
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
