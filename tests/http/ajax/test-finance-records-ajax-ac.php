<?php
/**
 * AC Test — FinanceRecordsAjax (Ciclo 3C2).
 *
 * Ejecutar:
 *   php tests/http/ajax/test-finance-records-ajax-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$ajax_file = $plugin_root . '/includes/http/ajax/FinanceRecordsAjax.php';
$support_file = $plugin_root . '/includes/http/ajax/FinanceAjaxSupport.php';
$bootstrap_file = $plugin_root . '/wp-agenda-automatizada.php';

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

echo "=== 1. Análisis estático de FinanceRecordsAjax y FinanceAjaxSupport ===\n";

ac_assert('Archivo FinanceRecordsAjax.php existe y es legible', is_readable($ajax_file));
ac_assert('Archivo FinanceAjaxSupport.php existe y es legible', is_readable($support_file));
$ajax_src = file_get_contents($ajax_file);
$support_src = file_get_contents($support_file);
$boot_src = file_get_contents($bootstrap_file);

ac_assert('Define constante ACTION_LIST = aa_list_finance_records', strpos($ajax_src, "ACTION_LIST   = 'aa_list_finance_records'") !== false);
ac_assert('Define constante ACTION_CREATE = aa_create_finance_record', strpos($ajax_src, "ACTION_CREATE = 'aa_create_finance_record'") !== false);
ac_assert('Define constante ACTION_GET = aa_get_finance_record', strpos($ajax_src, "ACTION_GET    = 'aa_get_finance_record'") !== false);
ac_assert('Define constante ACTION_DELETE = aa_delete_finance_record', strpos($ajax_src, "ACTION_DELETE = 'aa_delete_finance_record'") !== false);
ac_assert('NONCE_ACTION deriva de FinanceAjaxSupport::NONCE_ACTION', strpos($ajax_src, "NONCE_ACTION  = FinanceAjaxSupport::NONCE_ACTION") !== false);
ac_assert('FinanceAjaxSupport define NONCE_ACTION = aa_finance_nonce', strpos($support_src, "NONCE_ACTION = 'aa_finance_nonce'") !== false);

ac_assert('Delega authorize a FinanceAjaxSupport', strpos($ajax_src, 'FinanceAjaxSupport::authorize()') !== false);
ac_assert('Delega resolve_registry a FinanceAjaxSupport', strpos($ajax_src, 'FinanceAjaxSupport::resolve_registry()') !== false);
ac_assert('Delega respond a FinanceAjaxSupport', strpos($ajax_src, 'FinanceAjaxSupport::respond($result)') !== false);

ac_assert('No contiene wp_ajax_nopriv_', strpos($ajax_src, 'wp_ajax_nopriv_') === false);
ac_assert('Bootstrap registra FinanceRecordsAjax', strpos($boot_src, 'FinanceRecordsAjax::register()') !== false);
ac_assert('Bootstrap no registra nopriv para registros de finanzas', strpos($boot_src, 'wp_ajax_nopriv_aa_list_finance_records') === false);
ac_assert('No contiene acceso a $wpdb', strpos($ajax_src, '$wpdb') === false);
ac_assert('No contiene (float), floatval(), number_format()', strpos($ajax_src, '(float)') === false && strpos($ajax_src, 'floatval(') === false && strpos($ajax_src, 'number_format(') === false);
ac_assert('No contiene (int), absint()', strpos($ajax_src, '(int)') === false && strpos($ajax_src, 'absint(') === false);
ac_assert('No contiene sanitize_text_field ni sanitize_textarea_field', strpos($ajax_src, 'sanitize_text_field(') === false && strpos($ajax_src, 'sanitize_textarea_field(') === false);
ac_assert('No acepta alias variant (solo variant_key)', strpos($ajax_src, "\$_POST['variant']") === false);
ac_assert('No acepta alias id en lugar de record_id', strpos($ajax_src, "\$_POST['id']") === false);
ac_assert('No captura Throwable indiscriminado', strpos($ajax_src, 'catch (\Throwable') === false && strpos($ajax_src, 'catch (Throwable') === false);

echo "\n=== 2. Configuración de Dobles de Prueba ===\n";

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$GLOBALS['wp_actions'] = [];
$GLOBALS['last_json_response'] = null;
$GLOBALS['mock_is_logged_in'] = true;
$GLOBALS['mock_is_multisite'] = false;
$GLOBALS['mock_is_member_of_blog'] = true;
$GLOBALS['mock_can_manage_options'] = false;
$GLOBALS['mock_nonce_valid'] = true;

if (!function_exists('add_action')) {
    function add_action(string $hook, $callback): void {
        $GLOBALS['wp_actions'][$hook] = $callback;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return (bool) $GLOBALS['mock_is_logged_in'];
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        return (bool) $GLOBALS['mock_is_multisite'];
    }
}

if (!function_exists('is_user_member_of_blog')) {
    function is_user_member_of_blog(): bool {
        return (bool) $GLOBALS['mock_is_member_of_blog'];
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $cap): bool {
        if ($cap === 'manage_options') {
            return (bool) $GLOBALS['mock_can_manage_options'];
        }
        return false;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer(string $action = '1', $query_arg = false, bool $die = true): bool {
        return ($action === 'aa_finance_nonce' && !empty($GLOBALS['mock_nonce_valid']));
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type = 'mysql'): string {
        return '2026-08-29 18:00:00';
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, ?int $status_code = null): void {
        $GLOBALS['last_json_response'] = [
            'type'        => 'success',
            'status_code' => $status_code ?? 200,
            'body'        => [
                'success' => true,
                'data'    => $data,
            ],
        ];
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, ?int $status_code = null): void {
        $GLOBALS['last_json_response'] = [
            'type'        => 'error',
            'status_code' => $status_code ?? 400,
            'body'        => [
                'success' => false,
                'data'    => $data,
            ],
        ];
    }
}

// Cargar dependencias
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-key.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-family-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-variant-definition.php';
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
AA_Canonical_Core_Bootstrap::bootstrap();

require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';
require_once $plugin_root . '/includes/http/ajax/FinanceAjaxSupport.php';
require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/repositories/FinanceRecordRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/CreateFinanceRecordUseCase.php';
require_once $plugin_root . '/includes/application/finance/GetFinanceRecordUseCase.php';
require_once $plugin_root . '/includes/application/finance/ListFinanceRecordsUseCase.php';
require_once $plugin_root . '/includes/application/finance/DeleteFinanceRecordUseCase.php';
require_once $ajax_file;

class TestFinanceRecordsWpdbMock {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    public $queries = [];
    public $rows_affected = 0;

    public $container_rows = [];
    public $record_rows = [];

    public function insert(string $table, array $data, ?array $format = null) {
        $this->queries[] = ['insert', $table, $data];
        if (strpos($table, 'aa_finance_records') !== false) {
            $this->insert_id++;
            $row = array_merge([
                'id' => (string) $this->insert_id,
                'created_at' => current_time('mysql'),
            ], $data);
            $this->record_rows[$this->insert_id] = $row;
            return 1;
        }
        return false;
    }

    public function get_row(string $query, $output = ARRAY_A) {
        $this->queries[] = ['get_row', $query];

        // Container lookup
        if (strpos($query, 'aa_finance_containers') !== false) {
            if (preg_match('/WHERE id = (\d+)/', $query, $m)) {
                $cid = (int) $m[1];
                if (isset($this->container_rows[$cid])) {
                    return $this->container_rows[$cid];
                }
            }
            return null;
        }

        // Record lookup (id + container_id)
        if (strpos($query, 'aa_finance_records') !== false) {
            if (preg_match('/WHERE id = (\d+) AND container_id = (\d+)/', $query, $m)) {
                $rid = (int) $m[1];
                $cid = (int) $m[2];
                if (isset($this->record_rows[$rid]) && (int)$this->record_rows[$rid]['container_id'] === $cid) {
                    return $this->record_rows[$rid];
                }
                return null;
            }
            if (stripos($query, 'count_with_amount') !== false) {
                if (preg_match('/WHERE container_id = (\d+)/i', $query, $m)) {
                    $cid = (int) $m[1];
                    $sum = 0.0;
                    $counted = 0;
                    foreach ($this->record_rows as $r) {
                        if ((int)$r['container_id'] === $cid && $r['amount'] !== null) {
                            $sum += (float) $r['amount'];
                            $counted++;
                        }
                    }
                    return [
                        'count_with_amount' => $counted > 0 ? (string) $counted : '0',
                        'total_amount' => $counted > 0 ? number_format($sum, 2, '.', '') : null,
                    ];
                }
            }
            return null;
        }

        return null;
    }

    public function get_var(string $query) {
        $this->queries[] = ['get_var', $query];
        if (strpos($query, 'aa_finance_records') !== false && preg_match('/COUNT\(\*\) FROM .+ WHERE container_id = (\d+)/', $query, $m)) {
            $cid = (int) $m[1];
            $count = 0;
            foreach ($this->record_rows as $r) {
                if ((int)$r['container_id'] === $cid) {
                    $count++;
                }
            }
            return $count;
        }
        return 0;
    }

    public function get_results(string $query, $output = ARRAY_A): array {
        $this->queries[] = ['get_results', $query];
        if (strpos($query, 'aa_finance_records') !== false && preg_match('/WHERE container_id = (\d+)/', $query, $m)) {
            $cid = (int) $m[1];
            $results = [];
            foreach ($this->record_rows as $r) {
                if ((int)$r['container_id'] === $cid) {
                    $results[] = $r;
                }
            }
            return array_reverse($results);
        }
        return [];
    }

    public function delete(string $table, array $where, ?array $where_format = null) {
        $this->queries[] = ['delete', $table, $where];
        if (strpos($table, 'aa_finance_records') !== false) {
            $rid = $where['id'] ?? null;
            $cid = $where['container_id'] ?? null;
            if (isset($this->record_rows[$rid]) && (int)$this->record_rows[$rid]['container_id'] === (int)$cid) {
                unset($this->record_rows[$rid]);
                $this->rows_affected = 1;
                return 1;
            }
            $this->rows_affected = 0;
            return 0;
        }
        return false;
    }

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
            $rep = is_null($val) ? 'NULL' : (is_numeric($val) ? $val : "'" . addslashes((string) $val) . "'");
            $query = preg_replace('/%[sdf]/', (string) $rep, $query, 1);
        }
        return $query;
    }
}

global $wpdb;
$wpdb_mock = new TestFinanceRecordsWpdbMock();
$wpdb = $wpdb_mock;

// Fixture: Contenedor válido
$wpdb_mock->container_rows[10] = [
    'id' => '10',
    'variant_key' => 'general',
    'title' => 'Contenedor General',
    'details' => 'Detalles del contenedor',
    'created_at' => '2026-08-29 12:00:00',
];
// Fixture: Contenedor de otra variante
$wpdb_mock->container_rows[20] = [
    'id' => '20',
    'variant_key' => 'general',
    'title' => 'Contenedor Dos',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$wpdb_mock->container_rows[25] = [
    'id' => '25',
    'variant_key' => 'custom_other',
    'title' => 'Contenedor Otro',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];

echo "\n=== 3. Registro de Hooks ===\n";
FinanceRecordsAjax::register();

ac_assert('Hook wp_ajax_aa_list_finance_records registrado', isset($GLOBALS['wp_actions']['wp_ajax_aa_list_finance_records']));
ac_assert('Hook wp_ajax_aa_create_finance_record registrado', isset($GLOBALS['wp_actions']['wp_ajax_aa_create_finance_record']));
ac_assert('Hook wp_ajax_aa_get_finance_record registrado', isset($GLOBALS['wp_actions']['wp_ajax_aa_get_finance_record']));
ac_assert('Hook wp_ajax_aa_delete_finance_record registrado', isset($GLOBALS['wp_actions']['wp_ajax_aa_delete_finance_record']));
ac_assert('No se registraron hooks nopriv', !isset($GLOBALS['wp_actions']['wp_ajax_nopriv_aa_list_finance_records']) && !isset($GLOBALS['wp_actions']['wp_ajax_nopriv_aa_create_finance_record']));

echo "\n=== 4. Seguridad, Autorización y Nonce ===\n";

// No autenticado
$GLOBALS['mock_is_logged_in'] = false;
$GLOBALS['last_json_response'] = null;
$_POST = ['container_id' => '10', '_wpnonce' => 'aa_finance_nonce'];
FinanceRecordsAjax::handle_list();
ac_assert('No autenticado devuelve status 401', $GLOBALS['last_json_response']['status_code'] === 401);
ac_assert('No autenticado devuelve code unauthorized', $GLOBALS['last_json_response']['body']['data']['code'] === 'unauthorized');
$GLOBALS['mock_is_logged_in'] = true;

// Multisite no miembro
$GLOBALS['mock_is_multisite'] = true;
$GLOBALS['mock_is_member_of_blog'] = false;
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_list();
ac_assert('Multisite no miembro devuelve status 403', $GLOBALS['last_json_response']['status_code'] === 403);
ac_assert('Multisite no miembro devuelve code forbidden', $GLOBALS['last_json_response']['body']['data']['code'] === 'forbidden');
$GLOBALS['mock_is_multisite'] = false;
$GLOBALS['mock_is_member_of_blog'] = true;

// Nonce inválido
$GLOBALS['mock_nonce_valid'] = false;
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_list();
ac_assert('Nonce inválido devuelve status 403', $GLOBALS['last_json_response']['status_code'] === 403);
ac_assert('Nonce inválido devuelve code bad_nonce', $GLOBALS['last_json_response']['body']['data']['code'] === 'bad_nonce');
$GLOBALS['mock_nonce_valid'] = true;

echo "\n=== 5. Operación: Crear Registro (aa_create_finance_record) ===\n";

// Creación exitosa básica (amount con decimal)
$_POST = [
    'container_id' => '10',
    'title'        => 'Compra de equipo',
    'details'      => "Factura #123\nSegunda línea UTF-8 🚀",
    'amount'       => '150.85',
    'variant_key'  => 'general',
    '_wpnonce'     => 'aa_finance_nonce',
];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('Creación exitosa devuelve status 200', $GLOBALS['last_json_response']['status_code'] === 200);
ac_assert('Creación exitosa devuelve structure con record', isset($GLOBALS['last_json_response']['body']['data']['record']));
ac_assert('Importe preservado exactamente como string decimal', $GLOBALS['last_json_response']['body']['data']['record']['amount'] === '150.85');
ac_assert('Saltos de línea en details preservados', strpos($GLOBALS['last_json_response']['body']['data']['record']['details'], "\n") !== false);
ac_assert('Emoji UTF-8 en details preservado', strpos($GLOBALS['last_json_response']['body']['data']['record']['details'], '🚀') !== false);
$created_record_id = $GLOBALS['last_json_response']['body']['data']['record']['id'];

// Creación con amount omitido
$_POST = [
    'container_id' => '10',
    'title'        => 'Nota sin importe',
    '_wpnonce'     => 'aa_finance_nonce',
];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('Creación con amount omitido devuelve 200', $GLOBALS['last_json_response']['status_code'] === 200);
ac_assert('amount omitido resulta en amount null', $GLOBALS['last_json_response']['body']['data']['record']['amount'] === null);

// Creación con amount = null
$_POST = [
    'container_id' => '10',
    'title'        => 'Nota con null explícito',
    'amount'       => null,
    '_wpnonce'     => 'aa_finance_nonce',
];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('amount = null resulta en amount null', $GLOBALS['last_json_response']['body']['data']['record']['amount'] === null);

// Creación con amounts especiales: "0", "0.00", "-0.00", "-25.50"
$_POST = ['container_id' => '10', 'title' => 'Cero', 'amount' => '0', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('amount "0" se normaliza a "0.00"', $GLOBALS['last_json_response']['body']['data']['record']['amount'] === '0.00');

$_POST = ['container_id' => '10', 'title' => 'Cero negativo', 'amount' => '-0.00', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('amount "-0.00" se normaliza a "0.00"', $GLOBALS['last_json_response']['body']['data']['record']['amount'] === '0.00');

$_POST = ['container_id' => '10', 'title' => 'Negativo', 'amount' => '-25.50', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('amount "-25.50" preserva signo', $GLOBALS['last_json_response']['body']['data']['record']['amount'] === '-25.50');

// Rango decimal: 17 dígitos enteros permitido, 18 dígitos produce amount_out_of_range
$amount_17_digits = '99999999999999999.99'; // 17 nueves
$_POST = ['container_id' => '10', 'title' => 'Max 17 digits', 'amount' => $amount_17_digits, '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('17 dígitos enteros aceptado dentro del rango', $GLOBALS['last_json_response']['status_code'] === 200 && $GLOBALS['last_json_response']['body']['data']['record']['amount'] === $amount_17_digits);

$amount_18_digits = '999999999999999999.99'; // 18 nueves
$_POST = ['container_id' => '10', 'title' => 'Over 17 digits', 'amount' => $amount_18_digits, '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('18 dígitos enteros produce status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('18 dígitos enteros produce code amount_out_of_range', $GLOBALS['last_json_response']['body']['data']['code'] === 'amount_out_of_range');

// Tipos no válidos y rechazo de amount por Application
$_POST = ['container_id' => '10', 'title' => 'Float', 'amount' => 12.34, '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('amount float es rechazado con 400 invalid_amount', $GLOBALS['last_json_response']['status_code'] === 400 && $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_amount');

$_POST = ['container_id' => '10', 'title' => '3 decimales', 'amount' => '10.555', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('amount con 3 decimales produce 400 amount_too_many_decimals', $GLOBALS['last_json_response']['status_code'] === 400 && $GLOBALS['last_json_response']['body']['data']['code'] === 'amount_too_many_decimals');

$_POST = ['container_id' => '10', 'title' => 'Coma decimal', 'amount' => '10,50', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('amount con coma produce 400 invalid_amount', $GLOBALS['last_json_response']['status_code'] === 400 && $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_amount');

$_POST = ['container_id' => '10', 'title' => 'Notación científica', 'amount' => '1e2', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('amount notación científica produce 400 invalid_amount', $GLOBALS['last_json_response']['status_code'] === 400 && $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_amount');

// Errores de title y details
$_POST = ['container_id' => '10', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('title omitido produce 400 missing_title', $GLOBALS['last_json_response']['status_code'] === 400 && $GLOBALS['last_json_response']['body']['data']['code'] === 'missing_title');

// Variant key semantics
$_POST = ['container_id' => '10', 'title' => 'Test', 'variant_key' => '', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('variant_key string vacío produce 400 invalid_variant_key', $GLOBALS['last_json_response']['status_code'] === 400 && $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_variant_key');

$_POST = ['container_id' => '10', 'title' => 'Test', 'variant_key' => null, '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('variant_key = null resuelve default general exitosamente', $GLOBALS['last_json_response']['status_code'] === 200 && $GLOBALS['last_json_response']['body']['data']['record']['variant_key'] === 'general');

$_POST = ['container_id' => '10', 'title' => 'Test', 'variant_key' => 'unknown_var', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('variant_key desconocido produce 404 unknown_variant', $GLOBALS['last_json_response']['status_code'] === 404 && $GLOBALS['last_json_response']['body']['data']['code'] === 'unknown_variant');

echo "\n=== 6. Contexto Padre y Anti-Leak ===\n";

// Contenedor inexistente
$_POST = ['container_id' => '999', 'title' => 'Test', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('Contenedor inexistente produce 404 container_not_found', $GLOBALS['last_json_response']['status_code'] === 404 && $GLOBALS['last_json_response']['body']['data']['code'] === 'container_not_found');

// Contenedor de otra variante
$_POST = ['container_id' => '25', 'title' => 'Test', 'variant_key' => 'general', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('Contenedor en otra variante produce 404 container_not_found', $GLOBALS['last_json_response']['status_code'] === 404 && $GLOBALS['last_json_response']['body']['data']['code'] === 'container_not_found');

// container_id inválido
$_POST = ['container_id' => 'abc', 'title' => 'Test', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_create();
ac_assert('container_id no numérico produce 400 invalid_container_id', $GLOBALS['last_json_response']['status_code'] === 400 && $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_container_id');

echo "\n=== 7. Operación: Obtener Registro (aa_get_finance_record) ===\n";

// Obtención exitosa con record_id
$_POST = [
    'container_id' => '10',
    'record_id'    => (string) $created_record_id,
    '_wpnonce'     => 'aa_finance_nonce',
];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_get();
ac_assert('Obtener registro existente devuelve 200', $GLOBALS['last_json_response']['status_code'] === 200);
ac_assert('Obtener registro devuelve id correcto', $GLOBALS['last_json_response']['body']['data']['record']['id'] === $created_record_id);

// record_id omitido
$_POST = ['container_id' => '10', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_get();
ac_assert('record_id omitido produce 400 invalid_record_id', $GLOBALS['last_json_response']['status_code'] === 400 && $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_record_id');

// No acepta alias id
$_POST = ['container_id' => '10', 'id' => (string) $created_record_id, '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_get();
ac_assert('Uso de alias id no es reconocido y produce 400 invalid_record_id', $GLOBALS['last_json_response']['status_code'] === 400 && $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_record_id');

// Registro de otro contenedor
$_POST = ['container_id' => '20', 'record_id' => (string) $created_record_id, 'variant_key' => 'general', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_get();
ac_assert('Registro en otro contenedor produce 404 record_not_found', $GLOBALS['last_json_response']['status_code'] === 404 && $GLOBALS['last_json_response']['body']['data']['code'] === 'record_not_found');

echo "\n=== 8. Operación: Listar Registros (aa_list_finance_records) ===\n";

// Listado de contenedor 10 con registros
$_POST = ['container_id' => '10', 'page' => '1', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_list();
ac_assert('Listado devuelve 200', $GLOBALS['last_json_response']['status_code'] === 200);
$list_data = $GLOBALS['last_json_response']['body']['data'];
ac_assert('Listado contiene envelope container', isset($list_data['container']) && $list_data['container']['id'] === 10);
ac_assert('Listado contiene items array', is_array($list_data['items']) && count($list_data['items']) > 0);
ac_assert('Listado contiene metadatos de paginación', $list_data['page'] === 1 && $list_data['per_page'] === 15);
ac_assert('Listado contiene amount_total proyectado para todo el contenedor', array_key_exists('amount_total', $list_data) && $list_data['amount_total'] !== null);

// Listado de contenedor vacío
$wpdb_mock->container_rows[30] = [
    'id' => '30',
    'variant_key' => 'general',
    'title' => 'Contenedor Vacío',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$_POST = ['container_id' => '30', '_wpnonce' => 'aa_finance_nonce'];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_list();
ac_assert('Listado de contenedor vacío devuelve items vacíos y amount_total null', $GLOBALS['last_json_response']['body']['data']['items'] === [] && $GLOBALS['last_json_response']['body']['data']['amount_total'] === null);

echo "\n=== 9. Operación: Eliminar Registro (aa_delete_finance_record) ===\n";

// Eliminar registro existente
$_POST = [
    'container_id' => '10',
    'record_id'    => (string) $created_record_id,
    '_wpnonce'     => 'aa_finance_nonce',
];
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_delete();
ac_assert('Eliminación devuelve status 200', $GLOBALS['last_json_response']['status_code'] === 200);
ac_assert('Eliminación devuelve envelope deleted true con id y container_id', $GLOBALS['last_json_response']['body']['data']['deleted'] === true && $GLOBALS['last_json_response']['body']['data']['id'] === $created_record_id && $GLOBALS['last_json_response']['body']['data']['container_id'] === 10);

// Eliminar de nuevo (ya no existe)
$GLOBALS['last_json_response'] = null;
FinanceRecordsAjax::handle_delete();
ac_assert('Segunda eliminación devuelve 404 record_not_found', $GLOBALS['last_json_response']['status_code'] === 404 && $GLOBALS['last_json_response']['body']['data']['code'] === 'record_not_found');

echo "\n=== 10. Fallbacks Fail-Closed de FinanceAjaxSupport ===\n";

// Simular código de error Application desconocido
$mock_unknown_result = [
    'success' => false,
    'error' => [
        'code' => 'some_unregistered_code',
        'message' => 'Error no estándar.',
    ],
];
$GLOBALS['last_json_response'] = null;
FinanceAjaxSupport::respond($mock_unknown_result);
ac_assert('Código de error Application desconocido produce HTTP 500 fail-closed', $GLOBALS['last_json_response']['status_code'] === 500);
ac_assert('Conserva código y mensaje en el envelope de error 500', $GLOBALS['last_json_response']['body']['data']['code'] === 'some_unregistered_code');

echo "\n=========================================\n";
echo "Total assertions: $total\n";
echo "Passed: $passed\n";
echo "Failed: " . count($failed) . "\n";
echo "=========================================\n";

if (count($failed) > 0) {
    echo "Fallas detectadas:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}

exit(0);
