<?php
/**
 * AC — CreateExpedienteRegistroForClientUseCase (DB17 materialización).
 *
 * Ejecutar: php tests/application/expediente/test-create-expediente-registro-for-client-use-case-ac.php
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
        public function get_error_code() {
            return $this->code;
        }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

$GLOBALS['aa_test_current_time_calls'] = 0;
$GLOBALS['aa_test_current_time_value'] = '2026-08-20 15:00:00';
if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        $GLOBALS['aa_test_current_time_calls']++;
        return $GLOBALS['aa_test_current_time_value'];
    }
}

final class ClientsRepository {
    /** @var array<int,array<string,mixed>|null> */
    public static $by_id = [];

    public static function find_by_id(int $client_id): ?array {
        if (!array_key_exists($client_id, self::$by_id)) {
            return null;
        }
        return self::$by_id[$client_id];
    }
}

final class ExpedienteCategoriesRepository {
    /** @var array<string,array<string,mixed>|null> */
    public static $by_slug = [];

    public static function find_by_slug(string $slug): ?array {
        if (!array_key_exists($slug, self::$by_slug)) {
            return null;
        }
        return self::$by_slug[$slug];
    }
}

final class ExpedientesRepository {
    /** @var list<array<string,mixed>> */
    public static $get_or_create_calls = [];
    /** @var int|WP_Error */
    public static $next_parent_id = 10;
    /** @var int */
    public static $stable_parent_id = 10;

    public static function get_or_create_for_client(
        int $client_id,
        string $title,
        int $category_id,
        string $created_at
    ) {
        self::$get_or_create_calls[] = compact('client_id', 'title', 'category_id', 'created_at');
        if (self::$next_parent_id instanceof WP_Error) {
            return self::$next_parent_id;
        }
        return (int) self::$next_parent_id;
    }
}

final class ExpedienteRegistrosRepository {
    /** @var list<array<string,mixed>> */
    public static $inserts = [];
    /** @var array<string,mixed>|WP_Error|null */
    public static $next_result = null;

    public static function insert_for_client_expediente(array $data) {
        self::$inserts[] = $data;
        if (self::$next_result instanceof WP_Error) {
            return self::$next_result;
        }
        if (is_array(self::$next_result)) {
            return self::$next_result;
        }
        return [
            'id' => 100 + count(self::$inserts),
            'client_id' => (int) ($data['client_id'] ?? 0),
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'recorded_at' => (string) ($data['recorded_at'] ?? ''),
            'created_at' => (string) ($data['created_at'] ?? ''),
            'updated_at' => null,
        ];
    }

    public static function insert(array $data) {
        throw new RuntimeException('insert legacy no debe usarse');
    }

    public static function insert_for_expediente(array $data) {
        throw new RuntimeException('insert_for_expediente no debe usarse aquí');
    }
}

global $wpdb;
$wpdb = new class {
    public $last_error = '';
    public $queries = [];
    public $fail_start = false;
    public $fail_commit = false;
    public $commit_count = 0;
    public $rollback_count = 0;
    public $start_count = 0;

    public function query($sql) {
        $sql = (string) $sql;
        $this->queries[] = $sql;
        if ($sql === 'START TRANSACTION') {
            $this->start_count++;
            if ($this->fail_start) {
                $this->last_error = 'cannot start';
                return false;
            }
            return 0;
        }
        if ($sql === 'COMMIT') {
            $this->commit_count++;
            if ($this->fail_commit) {
                $this->last_error = 'commit failed';
                return false;
            }
            return 0;
        }
        if ($sql === 'ROLLBACK') {
            $this->rollback_count++;
            return 0;
        }
        return 0;
    }
};

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-registro-create-policy.php';
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-create-policy.php';
require_once $plugin_root . '/includes/application/expediente/CreateExpedienteRegistroForClientUseCase.php';

$src = file_get_contents($plugin_root . '/includes/application/expediente/CreateExpedienteRegistroForClientUseCase.php');
$repo_src = file_get_contents($plugin_root . '/includes/repositories/ExpedientesRepository.php');

ac_assert('ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)', strpos($repo_src, 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)') !== false);
ac_assert('get_or_create_for_client en repo', strpos($repo_src, 'function get_or_create_for_client') !== false);
ac_assert('sin SELECT tras duplicate en UC', strpos($src, 'find_by_client_id') === false);
ac_assert('usa START TRANSACTION', strpos($src, 'START TRANSACTION') !== false);
ac_assert('usa COMMIT', strpos($src, 'COMMIT') !== false);
ac_assert('usa ROLLBACK', strpos($src, 'ROLLBACK') !== false);
ac_assert('no lee expediente_id del input', strpos($src, "\$input['expediente_id']") === false);
ac_assert('no UPDATE backfill', stripos($src, 'UPDATE') === false);
ac_assert('slug clientes', strpos($src, "'clientes'") !== false);

$uc = new CreateExpedienteRegistroForClientUseCase();

ClientsRepository::$by_id = [
    5 => ['id' => 5, 'nombre' => '  Ana Pérez  ', 'telefono' => '1', 'correo' => ''],
];
ExpedienteCategoriesRepository::$by_slug = [
    'clientes' => ['id' => 9, 'slug' => 'clientes', 'name' => 'Clientes', 'created_at' => '2026-01-01 00:00:00'],
];
ExpedientesRepository::$get_or_create_calls = [];
ExpedientesRepository::$next_parent_id = 77;
ExpedienteRegistrosRepository::$inserts = [];
ExpedienteRegistrosRepository::$next_result = null;
$wpdb->queries = [];
$wpdb->start_count = 0;
$wpdb->commit_count = 0;
$wpdb->rollback_count = 0;
$GLOBALS['aa_test_current_time_calls'] = 0;

$ok = $uc->execute(['client_id' => 5, 'title' => '  Nota  ', 'body' => '  Cuerpo  ']);
ac_assert('primer create éxito', !empty($ok['success']));
ac_assert('responde record legacy', ($ok['data']['record']['id'] ?? 0) > 0
    && ($ok['data']['record']['client_id'] ?? 0) === 5
    && ($ok['data']['record']['title'] ?? '') === 'Nota');
ac_assert('responde expediente_id aditivo', ($ok['data']['expediente_id'] ?? 0) === 77);
ac_assert('un solo current_time', $GLOBALS['aa_test_current_time_calls'] === 1);
ac_assert('START+COMMIT sin rollback', $wpdb->start_count === 1 && $wpdb->commit_count === 1 && $wpdb->rollback_count === 0);
ac_assert(
    'padre título desde nombre servidor',
    (ExpedientesRepository::$get_or_create_calls[0]['title'] ?? '') === 'Ana Pérez'
    && (ExpedientesRepository::$get_or_create_calls[0]['category_id'] ?? 0) === 9
);
ac_assert(
    'hijo bridged ambos IDs',
    (ExpedienteRegistrosRepository::$inserts[0]['client_id'] ?? 0) === 5
    && (ExpedienteRegistrosRepository::$inserts[0]['expediente_id'] ?? 0) === 77
);

// Segundo create reutiliza el mismo parent id del get_or_create.
ExpedientesRepository::$get_or_create_calls = [];
ExpedienteRegistrosRepository::$inserts = [];
ExpedientesRepository::$next_parent_id = 77;
$ok2 = $uc->execute(['client_id' => 5, 'title' => 'Otra', 'body' => 'Más']);
ac_assert('segundo create éxito', !empty($ok2['success']));
ac_assert('segundo reutiliza expediente_id', ($ok2['data']['expediente_id'] ?? 0) === 77);
ac_assert('segundo hijo bridged', (ExpedienteRegistrosRepository::$inserts[0]['expediente_id'] ?? 0) === 77);

$wpdb->start_count = 0;
$wpdb->commit_count = 0;
$wpdb->rollback_count = 0;
$invalid = $uc->execute(['client_id' => 0, 'title' => 'A', 'body' => 'B']);
ac_assert('client_id inválido sin TX', empty($invalid['success'])
    && ($invalid['error']['code'] ?? '') === 'invalid_client'
    && $wpdb->start_count === 0);

$missingTitle = $uc->execute(['client_id' => 5, 'title' => '  ', 'body' => 'B']);
ac_assert('título vacío sin TX', empty($missingTitle['success'])
    && ($missingTitle['error']['code'] ?? '') === 'missing_title');

ClientsRepository::$by_id = [];
$missingClient = $uc->execute(['client_id' => 5, 'title' => 'A', 'body' => 'B']);
ac_assert('cliente inexistente', empty($missingClient['success'])
    && ($missingClient['error']['code'] ?? '') === 'not_found');

ClientsRepository::$by_id = [5 => ['id' => 5, 'nombre' => 'Ana', 'telefono' => '', 'correo' => '']];
ExpedienteCategoriesRepository::$by_slug = [];
$missingCat = $uc->execute(['client_id' => 5, 'title' => 'A', 'body' => 'B']);
ac_assert('categoría ausente fail cerrado', empty($missingCat['success'])
    && ($missingCat['error']['code'] ?? '') === 'category_not_found');

ExpedienteCategoriesRepository::$by_slug = [
    'clientes' => ['id' => 9, 'slug' => 'clientes', 'name' => 'Clientes', 'created_at' => 'x'],
];

// Nombre vacío → fallback
ClientsRepository::$by_id = [12 => ['id' => 12, 'nombre' => '   ', 'telefono' => '', 'correo' => '']];
ExpedientesRepository::$get_or_create_calls = [];
ExpedientesRepository::$next_parent_id = 3;
ExpedienteRegistrosRepository::$inserts = [];
$fallback = $uc->execute(['client_id' => 12, 'title' => 'T', 'body' => 'B']);
ac_assert('fallback Cliente #ID', !empty($fallback['success'])
    && (ExpedientesRepository::$get_or_create_calls[0]['title'] ?? '') === 'Cliente #12');

// Nombre > 200
$long = str_repeat('á', 250);
ClientsRepository::$by_id = [13 => ['id' => 13, 'nombre' => $long, 'telefono' => '', 'correo' => '']];
ExpedientesRepository::$get_or_create_calls = [];
$trimLong = $uc->execute(['client_id' => 13, 'title' => 'T', 'body' => 'B']);
$parentTitle = ExpedientesRepository::$get_or_create_calls[0]['title'] ?? '';
$len = function_exists('mb_strlen') ? mb_strlen($parentTitle) : strlen($parentTitle);
ac_assert('nombre largo truncado ≤200', !empty($trimLong['success']) && $len <= 200 && $len > 0);

// Fallo padre → rollback
ClientsRepository::$by_id = [5 => ['id' => 5, 'nombre' => 'Ana', 'telefono' => '', 'correo' => '']];
ExpedientesRepository::$next_parent_id = new WP_Error('db_error', 'padre fail');
ExpedienteRegistrosRepository::$inserts = [];
$wpdb->rollback_count = 0;
$wpdb->commit_count = 0;
$failParent = $uc->execute(['client_id' => 5, 'title' => 'T', 'body' => 'B']);
ac_assert('fallo padre', empty($failParent['success']) && ($failParent['error']['code'] ?? '') === 'persistence_failed');
ac_assert('fallo padre hace rollback', $wpdb->rollback_count >= 1 && $wpdb->commit_count === 0);
ac_assert('fallo padre no inserta hijo', ExpedienteRegistrosRepository::$inserts === []);

// Fallo hijo → rollback
ExpedientesRepository::$next_parent_id = 88;
ExpedienteRegistrosRepository::$next_result = new WP_Error('db_error', 'hijo fail');
ExpedienteRegistrosRepository::$inserts = [];
$wpdb->rollback_count = 0;
$wpdb->commit_count = 0;
$failChild = $uc->execute(['client_id' => 5, 'title' => 'T', 'body' => 'B']);
ac_assert('fallo hijo', empty($failChild['success']));
ac_assert('fallo hijo rollback', $wpdb->rollback_count >= 1 && $wpdb->commit_count === 0);

// Commit fallido
ExpedienteRegistrosRepository::$next_result = null;
$wpdb->fail_commit = true;
$wpdb->rollback_count = 0;
$failCommit = $uc->execute(['client_id' => 5, 'title' => 'T', 'body' => 'B']);
ac_assert('commit fallido no éxito', empty($failCommit['success']));
ac_assert('commit fallido intenta rollback', $wpdb->rollback_count >= 1);
$wpdb->fail_commit = false;

// Normalización pública del helper
$norm = $uc->normalize_parent_title_from_client('', 99);
ac_assert('helper fallback', $norm === 'Cliente #99');
$norm2 = $uc->normalize_parent_title_from_client('  X  ', 1);
ac_assert('helper trim', $norm2 === 'X');

// Concurrencia conceptual: dos get_or_create con mismo id estable ⇒ un padre lógico
ExpedientesRepository::$next_parent_id = 50;
ExpedienteRegistrosRepository::$next_result = null;
ExpedienteRegistrosRepository::$inserts = [];
$a = $uc->execute(['client_id' => 5, 'title' => 'A1', 'body' => 'B1']);
$b = $uc->execute(['client_id' => 5, 'title' => 'A2', 'body' => 'B2']);
ac_assert(
    'dos creates ⇒ mismo expediente_id',
    !empty($a['success']) && !empty($b['success'])
    && ($a['data']['expediente_id'] ?? 0) === 50
    && ($b['data']['expediente_id'] ?? 0) === 50
);
ac_assert('dos creates ⇒ dos hijos', count(ExpedienteRegistrosRepository::$inserts) === 2);

$schema_src = file_get_contents($plugin_root . '/includes/infrastructure/wp/Schema.php');
ac_assert('cero backfill en schema', !preg_match('/UPDATE\s+.*aa_expediente_registros/i', $schema_src));
ac_assert('cero backfill en UC', !preg_match('/UPDATE\s+/i', $src));

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
