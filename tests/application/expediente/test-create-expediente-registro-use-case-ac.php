<?php
/**
 * AC — CreateExpedienteRegistroUseCase (escritura interna por expediente_id).
 *
 * Ejecutar: php tests/application/expediente/test-create-expediente-registro-use-case-ac.php
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

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        public function __construct($code = '', $message = '') {
            $this->code = $code;
            $this->message = $message;
        }
        public function get_error_message() {
            return $this->message;
        }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

$GLOBALS['aa_test_current_time_calls'] = 0;
$GLOBALS['aa_test_current_time_value'] = '2026-08-20 12:00:00';
if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        $GLOBALS['aa_test_current_time_calls']++;
        return $GLOBALS['aa_test_current_time_value'];
    }
}

final class ExpedientesRepository {
    /** @var array<int,bool|null> */
    public static $exists = [];
    /** @var array<int,?int> */
    public static $owners = [];
    /** @var list<int> */
    public static $lookups = [];

    public static function exists_by_id(int $id) {
        self::$lookups[] = $id;
        if (!array_key_exists($id, self::$exists)) {
            return false;
        }
        return self::$exists[$id];
    }

    public static function find_owner_context_by_id(int $id): ?array {
        if (!array_key_exists($id, self::$exists) || self::$exists[$id] !== true) {
            return null;
        }
        $client = array_key_exists($id, self::$owners) ? self::$owners[$id] : null;

        return [
            'id' => $id,
            'client_id' => $client,
        ];
    }
}

final class ExpedienteRegistrosRepository {
    /** @var list<array<string,mixed>> */
    public static $inserts = [];
    /** @var list<string> */
    public static $insert_methods = [];
    /** @var array<string,mixed>|WP_Error|null */
    public static $next_result = null;

    public static function insert_for_expediente(array $data) {
        self::$insert_methods[] = 'insert_for_expediente';
        self::$inserts[] = $data;
        if (self::$next_result instanceof WP_Error) {
            return self::$next_result;
        }
        if (is_array(self::$next_result)) {
            return self::$next_result;
        }
        return [
            'id' => 55,
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'recorded_at' => (string) ($data['recorded_at'] ?? ''),
            'created_at' => (string) ($data['created_at'] ?? ''),
            'updated_at' => null,
        ];
    }

    public static function insert_for_client_expediente(array $data) {
        self::$insert_methods[] = 'insert_for_client_expediente';
        self::$inserts[] = $data;
        if (self::$next_result instanceof WP_Error) {
            return self::$next_result;
        }
        if (is_array(self::$next_result)) {
            return self::$next_result;
        }
        return [
            'id' => 56,
            'client_id' => (int) ($data['client_id'] ?? 0),
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'recorded_at' => (string) ($data['recorded_at'] ?? ''),
            'created_at' => (string) ($data['created_at'] ?? ''),
            'updated_at' => null,
        ];
    }

    public static function insert(array $data) {
        throw new RuntimeException('insert legacy no debe llamarse');
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-registro-create-policy.php';
require_once $plugin_root . '/includes/application/expediente/CreateExpedienteRegistroUseCase.php';

$src = file_get_contents($plugin_root . '/includes/application/expediente/CreateExpedienteRegistroUseCase.php');
ac_assert('usa insert_for_expediente', strpos($src, 'insert_for_expediente') !== false);
ac_assert('usa insert_for_client_expediente', strpos($src, 'insert_for_client_expediente') !== false);
ac_assert('usa find_owner_context_by_id', strpos($src, 'find_owner_context_by_id') !== false);
ac_assert('no llama insert legacy', preg_match('/ExpedienteRegistrosRepository::insert\s*\(/', $src) !== 1);
ac_assert('usa exists_by_id', strpos($src, 'exists_by_id') !== false);
ac_assert('un solo current_time', substr_count($src, "current_time('mysql')") === 1);
ac_assert(
    'no lee blog_id del input/request',
    strpos($src, "\$input['blog_id']") === false
    && strpos($src, '$_GET') === false
    && strpos($src, '$_POST') === false
    && strpos($src, '$_REQUEST') === false
);
ac_assert('no hardcodea general', stripos($src, "'general'") === false && stripos($src, '"general"') === false);

$uc = new CreateExpedienteRegistroUseCase();

ExpedientesRepository::$exists = [7 => true];
ExpedientesRepository::$owners = [7 => null];
ExpedientesRepository::$lookups = [];
ExpedienteRegistrosRepository::$inserts = [];
ExpedienteRegistrosRepository::$insert_methods = [];
ExpedienteRegistrosRepository::$next_result = null;
$GLOBALS['aa_test_current_time_calls'] = 0;

$ok = $uc->execute([
    'expediente_id' => '7',
    'title' => '  Nota  ',
    'body' => '  Cuerpo  ',
    'client_id' => 999,
    'blog_id' => 3,
    'recorded_at' => '1999-01-01 00:00:00',
    'created_at' => '1999-01-01 00:00:00',
]);
$record = $ok['data']['record'] ?? [];
$payload = ExpedienteRegistrosRepository::$inserts[0] ?? [];

ac_assert('éxito', !empty($ok['success']));
ac_assert('un solo timestamp', $GLOBALS['aa_test_current_time_calls'] === 1);
ac_assert(
    'recorded_at y created_at iguales al now',
    ($payload['recorded_at'] ?? '') === '2026-08-20 12:00:00'
    && ($payload['created_at'] ?? '') === '2026-08-20 12:00:00'
);
ac_assert('inserta expediente_id correcto', ($payload['expediente_id'] ?? 0) === 7);
ac_assert('padre general usa insert_for_expediente', (ExpedienteRegistrosRepository::$insert_methods[0] ?? '') === 'insert_for_expediente');
ac_assert('payload no incluye client_id', !array_key_exists('client_id', $payload));
ac_assert('DTO sin client_id', !array_key_exists('client_id', $record));
ac_assert('DTO sin expediente_id', !array_key_exists('expediente_id', $record));
ac_assert('title/body trim', ($payload['title'] ?? '') === 'Nota' && ($payload['body'] ?? '') === 'Cuerpo');
ac_assert('campos extra no controlan fechas', ($record['recorded_at'] ?? '') === '2026-08-20 12:00:00');
ac_assert('client_id del input ignorado en padre general', !array_key_exists('client_id', $payload));

ExpedientesRepository::$exists = [8 => true];
ExpedientesRepository::$owners = [8 => 44];
ExpedienteRegistrosRepository::$inserts = [];
ExpedienteRegistrosRepository::$insert_methods = [];
ExpedienteRegistrosRepository::$next_result = null;
$linked = $uc->execute([
    'expediente_id' => 8,
    'title' => 'Hijo',
    'body' => 'Texto',
    'client_id' => 1,
]);
$linked_payload = ExpedienteRegistrosRepository::$inserts[0] ?? [];
$linked_record = $linked['data']['record'] ?? [];
ac_assert('padre vinculado éxito', !empty($linked['success']));
ac_assert(
    'padre vinculado usa insert_for_client_expediente',
    (ExpedienteRegistrosRepository::$insert_methods[0] ?? '') === 'insert_for_client_expediente'
);
ac_assert(
    'padre vinculado escribe ambos IDs del servidor',
    ($linked_payload['client_id'] ?? 0) === 44
    && ($linked_payload['expediente_id'] ?? 0) === 8
);
ac_assert('input client_id no pisa el del padre', ($linked_payload['client_id'] ?? 0) !== 1);
ac_assert('DTO vinculado sin owners', !array_key_exists('client_id', $linked_record)
    && !array_key_exists('expediente_id', $linked_record));

ExpedientesRepository::$lookups = [];
ExpedienteRegistrosRepository::$inserts = [];
$invalid = $uc->execute(['expediente_id' => '01', 'title' => 'A', 'body' => 'B']);
ac_assert(
    'padre inválido no inserta',
    empty($invalid['success'])
    && ($invalid['error']['code'] ?? '') === 'invalid_id'
    && ExpedientesRepository::$lookups === []
    && ExpedienteRegistrosRepository::$inserts === []
);

ExpedientesRepository::$exists = [7 => false];
ExpedientesRepository::$lookups = [];
ExpedienteRegistrosRepository::$inserts = [];
$missing = $uc->execute(['expediente_id' => 7, 'title' => 'A', 'body' => 'B']);
ac_assert(
    'padre inexistente no inserta',
    empty($missing['success'])
    && ($missing['error']['code'] ?? '') === 'not_found'
    && ExpedienteRegistrosRepository::$inserts === []
);

ExpedientesRepository::$exists = [7 => null];
ExpedientesRepository::$lookups = [];
ExpedienteRegistrosRepository::$inserts = [];
$lookupFail = $uc->execute(['expediente_id' => 7, 'title' => 'A', 'body' => 'B']);
ac_assert(
    'fallo de consulta no inserta',
    empty($lookupFail['success'])
    && ($lookupFail['error']['code'] ?? '') === 'lookup_failed'
    && ExpedienteRegistrosRepository::$inserts === []
);

ExpedientesRepository::$exists = [7 => true];
ExpedienteRegistrosRepository::$inserts = [];
ExpedienteRegistrosRepository::$next_result = new WP_Error('db_error', 'Error al guardar el registro.');
$persistFail = $uc->execute(['expediente_id' => 7, 'title' => 'A', 'body' => 'B']);
ac_assert(
    'error de persistencia',
    empty($persistFail['success'])
    && ($persistFail['error']['code'] ?? '') === 'persistence_failed'
    && count(ExpedienteRegistrosRepository::$inserts) === 1
);

$missingTitle = $uc->execute(['expediente_id' => 7, 'title' => '  ', 'body' => 'B']);
ac_assert('título vacío → missing_title', empty($missingTitle['success'])
    && ($missingTitle['error']['code'] ?? '') === 'missing_title');

$longTitle = $uc->execute(['expediente_id' => 7, 'title' => str_repeat('a', 201), 'body' => 'B']);
ac_assert('título largo → title_too_long', empty($longTitle['success'])
    && ($longTitle['error']['code'] ?? '') === 'title_too_long');

$missingBody = $uc->execute(['expediente_id' => 7, 'title' => 'A', 'body' => '']);
ac_assert('cuerpo vacío → missing_body', empty($missingBody['success'])
    && ($missingBody['error']['code'] ?? '') === 'missing_body');

$longBody = $uc->execute(['expediente_id' => 7, 'title' => 'A', 'body' => str_repeat('b', 10001)]);
ac_assert('cuerpo largo → body_too_long', empty($longBody['success'])
    && ($longBody['error']['code'] ?? '') === 'body_too_long');

$ajax_src = file_get_contents($plugin_root . '/includes/http/ajax/ExpedienteRegistrosAjax.php');
$detail_src = file_get_contents($plugin_root . '/includes/admin/ui/modules/expedientes/detail.php');
$schema_src = file_get_contents($plugin_root . '/includes/infrastructure/wp/Schema.php');
$bootstrap = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
ac_assert(
    'AJAX legacy delega CreateExpedienteRegistroForClientUseCase',
    is_string($ajax_src)
    && strpos($ajax_src, 'aa_create_expediente_registro') !== false
    && strpos($ajax_src, 'CreateExpedienteRegistroForClientUseCase') !== false
    && strpos($ajax_src, 'insert_for_expediente') === false
    && strpos($ajax_src, 'CreateExpedienteRegistroUseCase') === false
);
ac_assert(
    'detalle SSR sin UC ni legacy registros JS',
    is_string($detail_src)
    && strpos($detail_src, 'CreateExpedienteRegistroUseCase') === false
    && strpos($detail_src, 'expediente-registros.js') === false
);
ac_assert('schema sigue en DB17', strpos($schema_src, "DB_VERSION = '17'") !== false);
ac_assert(
    'sin registro bootstrap del nuevo UC',
    is_string($bootstrap) && strpos($bootstrap, 'CreateExpedienteRegistroUseCase') === false
);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
