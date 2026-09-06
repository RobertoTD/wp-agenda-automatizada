<?php
/**
 * AC Test — FinanceContainersAjax (Ciclo 3C1).
 *
 * Ejecutar:
 *   php tests/http/ajax/test-finance-containers-ajax-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$ajax_file = $plugin_root . '/includes/http/ajax/FinanceContainersAjax.php';
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

echo "=== 1. Análisis estático de FinanceContainersAjax y FinanceAjaxSupport ===\n";

ac_assert('Archivo FinanceContainersAjax.php existe y es legible', is_readable($ajax_file));
ac_assert('Archivo FinanceAjaxSupport.php existe y es legible', is_readable($support_file));
$ajax_src = file_get_contents($ajax_file);
$support_src = file_get_contents($support_file);
$boot_src = file_get_contents($bootstrap_file);

ac_assert('Define constante ACTION_LIST', strpos($ajax_src, "ACTION_LIST   = 'aa_list_finance_containers'") !== false);
ac_assert('Define constante ACTION_CREATE', strpos($ajax_src, "ACTION_CREATE = 'aa_create_finance_container'") !== false);
ac_assert('Define constante ACTION_GET', strpos($ajax_src, "ACTION_GET    = 'aa_get_finance_container'") !== false);
ac_assert('Define constante ACTION_DELETE', strpos($ajax_src, "ACTION_DELETE = 'aa_delete_finance_container'") !== false);
ac_assert('Define constante ACTION_UPDATE', strpos($ajax_src, "ACTION_UPDATE = 'aa_update_finance_container'") !== false);
ac_assert('NONCE_ACTION deriva de FinanceAjaxSupport::NONCE_ACTION', strpos($ajax_src, "NONCE_ACTION  = FinanceAjaxSupport::NONCE_ACTION") !== false);
ac_assert('FinanceAjaxSupport define NONCE_ACTION = aa_finance_nonce', strpos($support_src, "NONCE_ACTION = 'aa_finance_nonce'") !== false);
ac_assert('FinanceContainersAjax delega authorize a FinanceAjaxSupport', strpos($ajax_src, 'FinanceAjaxSupport::authorize()') !== false);
ac_assert('FinanceContainersAjax delega resolve_registry a FinanceAjaxSupport', strpos($ajax_src, 'FinanceAjaxSupport::resolve_registry()') !== false);
ac_assert('FinanceContainersAjax delega respond a FinanceAjaxSupport', strpos($ajax_src, 'FinanceAjaxSupport::respond($result)') !== false);

ac_assert('No contiene wp_ajax_nopriv_', strpos($ajax_src, 'wp_ajax_nopriv_') === false && strpos($support_src, 'wp_ajax_nopriv_') === false);
ac_assert('Bootstrap no registra nopriv para finanzas', strpos($boot_src, 'wp_ajax_nopriv_aa_list_finance_containers') === false);
ac_assert('No contiene acceso a $wpdb', strpos($ajax_src, '$wpdb') === false && strpos($support_src, '$wpdb') === false);
ac_assert('No contiene absint()', strpos($ajax_src, 'absint(') === false && strpos($support_src, 'absint(') === false);
ac_assert('No contiene sanitize_text_field ni sanitize_textarea_field', strpos($ajax_src, 'sanitize_text_field(') === false && strpos($ajax_src, 'sanitize_textarea_field(') === false);
ac_assert('No acepta alias variant (solo variant_key)', strpos($ajax_src, "\$_POST['variant']") === false);
ac_assert('No captura Throwable indiscriminado', strpos($ajax_src, 'catch (\Throwable') === false && strpos($support_src, 'catch (\Throwable') === false);

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
require_once $plugin_root . '/includes/domain/canonical/class-aa-canonical-registry.php';
require_once $plugin_root . '/includes/infrastructure/canonical/class-aa-canonical-core-bootstrap.php';
AA_Canonical_Core_Bootstrap::bootstrap();

require_once $plugin_root . '/includes/repositories/FinanceContainerRepository.php';
require_once $plugin_root . '/includes/repositories/FinanceRecordRepository.php';
require_once $plugin_root . '/includes/application/finance/FinanceUseCaseSupport.php';
require_once $plugin_root . '/includes/application/finance/CreateFinanceContainerUseCase.php';
require_once $plugin_root . '/includes/application/finance/GetFinanceContainerUseCase.php';
require_once $plugin_root . '/includes/application/finance/ListFinanceContainersUseCase.php';
require_once $plugin_root . '/includes/application/finance/DeleteFinanceContainerUseCase.php';
require_once $plugin_root . '/includes/application/finance/UpdateFinanceContainerUseCase.php';
require_once $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';
require_once $plugin_root . '/includes/http/ajax/FinanceAjaxSupport.php';

class TestFinanceContainersWpdbMock {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    public $inserts = [];
    public $rows = [];
    public $results = [];
    public $vars = [];
    public $deleted_rows = 1;
    public $query_log = [];
    public $update_result = 1;

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

    public function insert(string $table, array $data, array $format = []) {
        $this->inserts[] = ['table' => $table, 'data' => $data, 'format' => $format];
        if ($this->last_error !== '') {
            return false;
        }
        $this->insert_id = 77;
        return 1;
    }

    public function get_row(string $query, $output = ARRAY_A) {
        $this->query_log[] = $query;
        if ($this->last_error !== '') {
            return null;
        }
        return array_shift($this->rows) ?: null;
    }

    public function get_results(string $query, $output = ARRAY_A) {
        $this->query_log[] = $query;
        if ($this->last_error !== '') {
            return [];
        }
        return array_shift($this->results) ?: [];
    }

    public function get_var(string $query) {
        $this->query_log[] = $query;
        if ($this->last_error !== '') {
            return null;
        }
        return array_shift($this->vars) ?? 0;
    }

    public function delete(string $table, array $where, array $where_format = []) {
        $this->query_log[] = ['delete' => $table, 'where' => $where];
        if ($this->last_error !== '') {
            return false;
        }
        return $this->deleted_rows;
    }

    public function update($table, array $data, array $where, $format = null, $where_format = null) {
        $this->query_log[] = ['update' => $table, 'where' => $where, 'data' => $data];
        if ($this->last_error !== '') {
            return false;
        }
        return $this->update_result;
    }
}

global $wpdb;
$wpdb = new TestFinanceContainersWpdbMock();

require_once $ajax_file;

FinanceContainersAjax::register();

echo "\n=== 3. Verificación de Registro de Hooks ===\n";

ac_assert('Hook wp_ajax_aa_list_finance_containers registrado', isset($GLOBALS['wp_actions']['wp_ajax_aa_list_finance_containers']));
ac_assert('Hook wp_ajax_aa_create_finance_container registrado', isset($GLOBALS['wp_actions']['wp_ajax_aa_create_finance_container']));
ac_assert('Hook wp_ajax_aa_get_finance_container registrado', isset($GLOBALS['wp_actions']['wp_ajax_aa_get_finance_container']));
ac_assert('Hook wp_ajax_aa_delete_finance_container registrado', isset($GLOBALS['wp_actions']['wp_ajax_aa_delete_finance_container']));
ac_assert('Hook wp_ajax_aa_update_finance_container registrado', isset($GLOBALS['wp_actions']['wp_ajax_aa_update_finance_container']));

$nopriv_found = false;
foreach (array_keys($GLOBALS['wp_actions']) as $hook) {
    if (strpos($hook, 'wp_ajax_nopriv_') === 0) {
        $nopriv_found = true;
    }
}
ac_assert('Ausencia total de hooks wp_ajax_nopriv_*', !$nopriv_found);

echo "\n=== 4. Verificación de Autorización y Nonce (Defensa Interna) ===\n";

// 4.1 Usuario no autenticado
$GLOBALS['mock_is_logged_in'] = false;
$_POST = [];
FinanceContainersAjax::handle_list();
ac_assert('Usuario no autenticado devuelve status 401', $GLOBALS['last_json_response']['status_code'] === 401);
ac_assert('Usuario no autenticado devuelve code unauthorized', $GLOBALS['last_json_response']['body']['data']['code'] === 'unauthorized');
$GLOBALS['mock_is_logged_in'] = true;

// 4.2 Multisite usuario no miembro
$GLOBALS['mock_is_multisite'] = true;
$GLOBALS['mock_is_member_of_blog'] = false;
$_POST = [];
FinanceContainersAjax::handle_list();
ac_assert('Multisite no miembro devuelve status 403', $GLOBALS['last_json_response']['status_code'] === 403);
ac_assert('Multisite no miembro devuelve code forbidden', $GLOBALS['last_json_response']['body']['data']['code'] === 'forbidden');
$GLOBALS['mock_is_multisite'] = false;
$GLOBALS['mock_is_member_of_blog'] = true;

// 4.3 Nonce inválido
$GLOBALS['mock_nonce_valid'] = false;
$_POST = [];
FinanceContainersAjax::handle_list();
ac_assert('Nonce inválido devuelve status 403', $GLOBALS['last_json_response']['status_code'] === 403);
ac_assert('Nonce inválido devuelve code bad_nonce', $GLOBALS['last_json_response']['body']['data']['code'] === 'bad_nonce');
$GLOBALS['mock_nonce_valid'] = true;

echo "\n=== 5. Pruebas de aa_create_finance_container ===\n";

// 5.1 Creación exitosa con title, details y variant_key omitido
$_POST = [
    'title'   => 'Gastos Operativos',
    'details' => "Línea 1\nLínea 2 con UTF-8: áéíóú 💼",
];
FinanceContainersAjax::handle_create();
ac_assert('Creación exitosa devuelve status 200', $GLOBALS['last_json_response']['status_code'] === 200);
$container_res = $GLOBALS['last_json_response']['body']['data']['container'] ?? [];
ac_assert('Contenedor creado contiene id=77', ($container_res['id'] ?? 0) === 77);
ac_assert('Contenedor creado asigna variant_key=general', ($container_res['variant_key'] ?? '') === 'general');
ac_assert('Contenedor creado asigna family_key=finance', ($container_res['family_key'] ?? '') === 'finance');
ac_assert('Preserva saltos de línea intactos en details', strpos($container_res['details'] ?? '', "\n") !== false);

// 5.2 Creación con variant_key explícito válido
$_POST = [
    'title'       => 'Caja Chica',
    'variant_key' => 'general',
];
FinanceContainersAjax::handle_create();
ac_assert('Creación con variant_key=general devuelve 200', $GLOBALS['last_json_response']['status_code'] === 200);

// 5.3 Creación con variant_key string vacío -> 400 invalid_variant_key
$_POST = [
    'title'       => 'Caja Chica',
    'variant_key' => '',
];
FinanceContainersAjax::handle_create();
ac_assert('Creación con variant_key vacía devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es invalid_variant_key', $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_variant_key');

// 5.4 Creación con variant_key desconocida -> 404 unknown_variant
$_POST = [
    'title'       => 'Caja Chica',
    'variant_key' => 'variante_inexistente',
];
FinanceContainersAjax::handle_create();
ac_assert('Creación con variant_key desconocida devuelve status 404', $GLOBALS['last_json_response']['status_code'] === 404);
ac_assert('Código de error es unknown_variant', $GLOBALS['last_json_response']['body']['data']['code'] === 'unknown_variant');

// 5.5 Título omitido -> 400 missing_title
$_POST = [
    'details' => 'Sin título',
];
FinanceContainersAjax::handle_create();
ac_assert('Título omitido devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es missing_title', $GLOBALS['last_json_response']['body']['data']['code'] === 'missing_title');

// 5.6 Título demasiado largo (>200 chars) -> 400 title_too_long
$_POST = [
    'title' => str_repeat('A', 201),
];
FinanceContainersAjax::handle_create();
ac_assert('Título >200 chars devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es title_too_long', $GLOBALS['last_json_response']['body']['data']['code'] === 'title_too_long');

// 5.7 Detalles demasiado largos (>65000 bytes) -> 400 details_too_long
$_POST = [
    'title'   => 'Título válido',
    'details' => str_repeat('B', 65001),
];
FinanceContainersAjax::handle_create();
ac_assert('Detalles >65000 bytes devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es details_too_long', $GLOBALS['last_json_response']['body']['data']['code'] === 'details_too_long');

echo "\n=== 6. Pruebas de aa_list_finance_containers ===\n";

// 6.1 Listado vacío (total=0)
$wpdb->vars[] = '0';
$_POST = [];
FinanceContainersAjax::handle_list();
ac_assert('Listado vacío devuelve status 200', $GLOBALS['last_json_response']['status_code'] === 200);
ac_assert('Listado vacío data.items es []', $GLOBALS['last_json_response']['body']['data']['items'] === []);
ac_assert('Listado vacío total=0', $GLOBALS['last_json_response']['body']['data']['total'] === 0);

// 6.2 Listado con elementos y amount_total proyectado
$wpdb->vars[] = '1';
$wpdb->results[] = [
    ['id' => 10, 'variant_key' => 'general', 'title' => 'C10', 'details' => 'D10', 'created_at' => '2026-08-29 12:00:00']
];
$wpdb->results[] = [
    ['container_id' => '10', 'amount_total' => '250.75']
];
$_POST = ['page' => '1'];
FinanceContainersAjax::handle_list();
ac_assert('Listado con items devuelve status 200', $GLOBALS['last_json_response']['status_code'] === 200);
$list_items = $GLOBALS['last_json_response']['body']['data']['items'] ?? [];
ac_assert('Listado contiene 1 item', count($list_items) === 1);
ac_assert('Item proyecta family_key=finance', ($list_items[0]['family_key'] ?? '') === 'finance');
ac_assert('Item proyecta amount_total=250.75 intacto', ($list_items[0]['amount_total'] ?? null) === '250.75');

echo "\n=== 7. Pruebas de aa_get_finance_container ===\n";

// 7.1 Obtención exitosa
$wpdb->rows[] = [
    'id'          => '10',
    'variant_key' => 'general',
    'title'       => 'C10',
    'details'     => 'D10',
    'created_at'  => '2026-08-29 12:00:00',
];
$_POST = ['id' => '10'];
FinanceContainersAjax::handle_get();
ac_assert('Obtención exitosa devuelve status 200', $GLOBALS['last_json_response']['status_code'] === 200);
$get_res = $GLOBALS['last_json_response']['body']['data']['container'] ?? [];
ac_assert('Contenedor obtenido id=10', ($get_res['id'] ?? 0) === 10);
ac_assert('Contenedor obtenido family_key=finance', ($get_res['family_key'] ?? '') === 'finance');

// 7.2 ID inválido (string no numérico o <= 0) -> 400 invalid_id
$_POST = ['id' => 'abc'];
FinanceContainersAjax::handle_get();
ac_assert('ID no numérico devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es invalid_id', $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_id');

// 7.3 Contenedor no encontrado -> 404 not_found
$wpdb->rows = [];
$_POST = ['id' => '999'];
FinanceContainersAjax::handle_get();
ac_assert('Contenedor inexistente devuelve status 404', $GLOBALS['last_json_response']['status_code'] === 404);
ac_assert('Código de error es not_found', $GLOBALS['last_json_response']['body']['data']['code'] === 'not_found');

echo "\n=== 8. Pruebas de aa_delete_finance_container ===\n";

// 8.1 Borrado exitoso
$wpdb->rows[] = [
    'id'          => '10',
    'variant_key' => 'general',
    'title'       => 'C10',
    'details'     => null,
    'created_at'  => '2026-08-29 12:00:00',
];
$wpdb->deleted_rows = 1;
$_POST = ['id' => '10'];
FinanceContainersAjax::handle_delete();
ac_assert('Borrado exitoso devuelve status 200', $GLOBALS['last_json_response']['status_code'] === 200);
ac_assert('Borrado exitoso devuelve deleted=true', ($GLOBALS['last_json_response']['body']['data']['deleted'] ?? false) === true);
ac_assert('Borrado exitoso devuelve id=10', ($GLOBALS['last_json_response']['body']['data']['id'] ?? 0) === 10);

// 8.2 Borrado de contenedor inexistente -> 404 not_found
$wpdb->rows = [];
$_POST = ['id' => '999'];
FinanceContainersAjax::handle_delete();
ac_assert('Borrado de inexistente devuelve status 404', $GLOBALS['last_json_response']['status_code'] === 404);
ac_assert('Código de error es not_found', $GLOBALS['last_json_response']['body']['data']['code'] === 'not_found');

echo "\n=== 9. Pruebas de aa_update_finance_container ===\n";

// 9.1 Actualización exitosa
$wpdb->rows[] = [
    'id'          => '10',
    'variant_key' => 'general',
    'title'       => 'Anterior',
    'details'     => 'Detalle anterior',
    'created_at'  => '2026-08-29 12:00:00',
];
$wpdb->rows[] = [
    'id'          => '10',
    'variant_key' => 'general',
    'title'       => 'Actualizado',
    'details'     => "Línea\nDos",
    'created_at'  => '2026-08-29 12:00:00',
];
$wpdb->update_result = 1;
$_POST = [
    'id'      => '10',
    'title'   => 'Actualizado',
    'details' => "Línea\nDos",
];
FinanceContainersAjax::handle_update();
ac_assert('Update exitoso devuelve status 200', $GLOBALS['last_json_response']['status_code'] === 200);
$update_res = $GLOBALS['last_json_response']['body']['data']['container'] ?? [];
ac_assert('Update devuelve container autoritativo id=10', ($update_res['id'] ?? 0) === 10);
ac_assert('Update devuelve family_key=finance', ($update_res['family_key'] ?? '') === 'finance');
ac_assert('Update preserva saltos de línea en details', strpos($update_res['details'] ?? '', "\n") !== false);
ac_assert('Update no expone amount en respuesta', !array_key_exists('amount', $update_res) && !array_key_exists('amount_total', $update_res));

// 9.2 details vacío preservado hasta Application
$wpdb->rows = [];
$wpdb->rows[] = [
    'id' => '11', 'variant_key' => 'general', 'title' => 'T', 'details' => null, 'created_at' => '2026-08-29 12:00:00',
];
$wpdb->rows[] = [
    'id' => '11', 'variant_key' => 'general', 'title' => 'T', 'details' => null, 'created_at' => '2026-08-29 12:00:00',
];
$wpdb->update_result = 1;
$wpdb->last_error = '';
$_POST = ['id' => '11', 'title' => 'T', 'details' => ''];
FinanceContainersAjax::handle_update();
$empty_details_container = $GLOBALS['last_json_response']['body']['data']['container'] ?? [];
ac_assert('details vacío devuelve 200 con details null', $GLOBALS['last_json_response']['status_code'] === 200 && array_key_exists('details', $empty_details_container) && $empty_details_container['details'] === null);

// 9.3 Clave details ausente
$_POST = ['id' => '11', 'title' => 'T'];
FinanceContainersAjax::handle_update();
ac_assert('Ausencia de clave details devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es missing_details', $GLOBALS['last_json_response']['body']['data']['code'] === 'missing_details');

// 9.4 invalid_id
$_POST = ['id' => 'abc', 'title' => 'T', 'details' => null];
FinanceContainersAjax::handle_update();
ac_assert('ID inválido devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es invalid_id', $GLOBALS['last_json_response']['body']['data']['code'] === 'invalid_id');

// 9.5 missing_title
$_POST = ['id' => '10', 'details' => 'D'];
FinanceContainersAjax::handle_update();
ac_assert('title omitido devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es missing_title', $GLOBALS['last_json_response']['body']['data']['code'] === 'missing_title');

// 9.6 title_too_long
$_POST = ['id' => '10', 'title' => str_repeat('A', 201), 'details' => null];
FinanceContainersAjax::handle_update();
ac_assert('title demasiado largo devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es title_too_long', $GLOBALS['last_json_response']['body']['data']['code'] === 'title_too_long');

// 9.7 details_too_long
$_POST = ['id' => '10', 'title' => 'Título válido', 'details' => str_repeat('B', 65001)];
FinanceContainersAjax::handle_update();
ac_assert('details demasiado largo devuelve status 400', $GLOBALS['last_json_response']['status_code'] === 400);
ac_assert('Código de error es details_too_long', $GLOBALS['last_json_response']['body']['data']['code'] === 'details_too_long');

// 9.8 unknown_variant
$_POST = ['id' => '10', 'title' => 'T', 'details' => null, 'variant_key' => 'variante_inexistente'];
FinanceContainersAjax::handle_update();
ac_assert('variant_key desconocida devuelve status 404', $GLOBALS['last_json_response']['status_code'] === 404);
ac_assert('Código de error es unknown_variant', $GLOBALS['last_json_response']['body']['data']['code'] === 'unknown_variant');

// 9.9 not_found
$wpdb->rows = [];
$_POST = ['id' => '999', 'title' => 'T', 'details' => null];
FinanceContainersAjax::handle_update();
ac_assert('Contenedor inexistente devuelve status 404', $GLOBALS['last_json_response']['status_code'] === 404);
ac_assert('Código de error es not_found', $GLOBALS['last_json_response']['body']['data']['code'] === 'not_found');

// 9.9b Carrera: update afectó 1 fila pero relectura ausente → 404 not_found
$wpdb->rows = [];
$wpdb->rows[] = [
    'id' => '13', 'variant_key' => 'general', 'title' => 'Carrera', 'details' => null, 'created_at' => '2026-08-29 12:00:00',
];
$wpdb->update_result = 1;
$wpdb->last_error = '';
$_POST = ['id' => '13', 'title' => 'Carrera', 'details' => null];
FinanceContainersAjax::handle_update();
ac_assert('Carrera post-update (affected=1) devuelve status 404', $GLOBALS['last_json_response']['status_code'] === 404);
ac_assert('Código de error carrera es not_found', $GLOBALS['last_json_response']['body']['data']['code'] === 'not_found');

// 9.10 persistence_failed
$wpdb->rows[] = [
    'id' => '12', 'variant_key' => 'general', 'title' => 'T', 'details' => null, 'created_at' => '2026-08-29 12:00:00',
];
$wpdb->last_error = 'Database timeout';
$_POST = ['id' => '12', 'title' => 'T', 'details' => null];
FinanceContainersAjax::handle_update();
ac_assert('Error SQL en update devuelve status 500', $GLOBALS['last_json_response']['status_code'] === 500);
ac_assert('Código de error es persistence_failed', $GLOBALS['last_json_response']['body']['data']['code'] === 'persistence_failed');
$wpdb->last_error = '';

echo "\n=== 10. Pruebas de Errores de Persistencia (list) ===\n";

// 9.1 Error de persistencia en listado -> 500 persistence_failed
$wpdb->last_error = 'Database timeout';
$_POST = [];
FinanceContainersAjax::handle_list();
ac_assert('Error SQL devuelve status 500', $GLOBALS['last_json_response']['status_code'] === 500);
ac_assert('Código de error es persistence_failed', $GLOBALS['last_json_response']['body']['data']['code'] === 'persistence_failed');
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
