<?php
/**
 * AC — AA_Expediente_Aggregate_Lock (Ciclo A).
 *
 * Ejecutar: php tests/infrastructure/wp/test-aa-expediente-aggregate-lock-ac.php
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
if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'aa_test_db');
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
if (!function_exists('get_current_network_id')) {
    function get_current_network_id() {
        return 1;
    }
}
if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() {
        return 2;
    }
}

global $wpdb;
$wpdb = new class {
    public $prefix = 'wp_';
    public $base_prefix = 'wp_';
    public $last_error = '';
};

require_once $plugin_root . '/includes/infrastructure/wp/class-aa-expediente-aggregate-lock.php';

$logs = [];
$query_map = [];
$connection_id = 42;

$lock = new AA_Expediente_Aggregate_Lock(
    static function (string $sql, array $args = []) use (&$query_map) {
        $query_map[] = ['sql' => $sql, 'args' => $args];
        if (strpos($sql, 'GET_LOCK') !== false) {
            return $query_map['__get_lock'] ?? 1;
        }
        if (strpos($sql, 'IS_USED_LOCK') !== false) {
            return $query_map['__is_used'] ?? 42;
        }
        if (strpos($sql, 'RELEASE_LOCK') !== false) {
            return $query_map['__release'] ?? 1;
        }
        if (strpos($sql, 'CONNECTION_ID') !== false) {
            return $query_map['__conn'] ?? 42;
        }
        return null;
    },
    static function () use (&$connection_id) {
        return $connection_id;
    },
    static function (string $msg) use (&$logs) {
        $logs[] = $msg;
    }
);

// Reset helper for map overrides via closure capture — use a dedicated stateful mock.
$state = new class {
    public $get_lock = 1;
    public $is_used = 42;
    public $release = 1;
    public $conn = 42;
    public $calls = [];
    public $force_null_get = false;
};

$lock = new AA_Expediente_Aggregate_Lock(
    static function (string $sql, array $args = []) use ($state) {
        $state->calls[] = ['sql' => $sql, 'args' => $args];
        if (strpos($sql, 'GET_LOCK') !== false) {
            if ($state->force_null_get) {
                return null;
            }
            return $state->get_lock;
        }
        if (strpos($sql, 'IS_USED_LOCK') !== false) {
            return $state->is_used;
        }
        if (strpos($sql, 'RELEASE_LOCK') !== false) {
            return $state->release;
        }
        if (strpos($sql, 'CONNECTION_ID') !== false) {
            return $state->conn;
        }
        return null;
    },
    static function () use ($state) {
        return $state->conn;
    },
    static function (string $msg) use (&$logs) {
        $logs[] = $msg;
    }
);

ac_assert('kind inválido', is_wp_error($lock->acquire('blog', 1))
    && $lock->acquire('blog', 1)->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_INVALID_SCOPE);
ac_assert('id inválido', is_wp_error($lock->acquire('client', 0)));
ac_assert('timeout inválido', is_wp_error($lock->acquire('client', 1, 99)));

$key_client = $lock->build_key_for_tests('client', 11);
$key_client_b = $lock->build_key_for_tests('client', 11);
$key_client_other = $lock->build_key_for_tests('client', 12);
$key_exp = $lock->build_key_for_tests('expediente', 11);

ac_assert('key determinista', is_string($key_client) && $key_client === $key_client_b);
ac_assert('key distinta por scope id', $key_client !== $key_client_other);
ac_assert('key distinta client vs expediente', $key_client !== $key_exp);
ac_assert('key prefijo aaexp:', is_string($key_client) && strpos($key_client, 'aaexp:') === 0);
ac_assert('key longitud ≤62', is_string($key_client) && strlen($key_client) <= 62);
ac_assert('key longitud exacta 62', is_string($key_client) && strlen($key_client) === 62);

$state->get_lock = 1;
$state->conn = 42;
$state->calls = [];
$lease = $lock->acquire('client', 7, 1);
ac_assert('GET_LOCK 1 → lease', $lease instanceof AA_Expediente_Aggregate_Lock_Lease);
ac_assert('GET_LOCK llamado una vez', count(array_filter($state->calls, static function ($c) {
    return strpos($c['sql'], 'GET_LOCK') !== false;
})) === 1);
ac_assert('timeout 1 en args', ($state->calls[0]['args'][1] ?? null) === 1);
ac_assert('scope en lease', $lease instanceof AA_Expediente_Aggregate_Lock_Lease
    && $lease->scope_kind() === 'client'
    && $lease->scope_id() === 7);

$state->get_lock = 0;
$busy = $lock->acquire('client', 7, 1);
ac_assert('GET_LOCK 0 → resource_busy', is_wp_error($busy)
    && $busy->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_RESOURCE_BUSY);

$state->force_null_get = true;
$null = $lock->acquire('client', 7, 1);
ac_assert('GET_LOCK NULL → coordination_failed', is_wp_error($null)
    && $null->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_COORDINATION_FAILED);
$state->force_null_get = false;

$state->get_lock = 'nope';
$malformed = $lock->acquire('client', 7, 1);
ac_assert('GET_LOCK malformado → coordination_failed', is_wp_error($malformed)
    && $malformed->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_COORDINATION_FAILED);

$state->get_lock = 1;
$state->conn = 0;
$bad_conn = $lock->acquire('client', 7, 1);
ac_assert('CONNECTION_ID inválido → coordination_failed', is_wp_error($bad_conn)
    && $bad_conn->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_COORDINATION_FAILED);
$state->conn = 42;

$state->get_lock = 1;
$lease = $lock->acquire('expediente', 99, 1);
$state->is_used = 42;
$held = $lock->assert_held($lease);
ac_assert('assert_held mismo owner', $held === true);

$state->is_used = 99;
$lost = $lock->assert_held($lease);
ac_assert('assert_held owner distinto → coordination_lost', is_wp_error($lost)
    && $lost->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_COORDINATION_LOST);

$state->is_used = null;
$lost_null = $lock->assert_held($lease);
ac_assert('IS_USED_LOCK NULL → coordination_lost', is_wp_error($lost_null)
    && $lost_null->get_error_code() === AA_Expediente_Aggregate_Lock::ERROR_COORDINATION_LOST);

$state->release = 1;
ac_assert('release 1', $lock->release($lease) === true);

$state->release = 0;
ac_assert('release 0', $lock->release($lease) === false);

$state->release = null;
ac_assert('release NULL', $lock->release($lease) === false);

$logs = [];
$threw = false;
$state->get_lock = 1;
$state->conn = 42;
$lease2 = $lock->acquire('client', 3, 1);
try {
    try {
        throw new RuntimeException('boom');
    } finally {
        $lock->release($lease2);
    }
} catch (RuntimeException $e) {
    $threw = true;
}
ac_assert('finally ejecuta release tras excepción', $threw === true
    && count(array_filter($state->calls, static function ($c) {
        return strpos($c['sql'], 'RELEASE_LOCK') !== false;
    })) >= 1);

$logs = [];
$lock->release(null);
ac_assert('log genérico sin key', $logs !== []
    && strpos($logs[0], 'aaexp:') === false
    && strpos(implode("\n", $logs), 'aaexp:') === false);

$bootstrap = file_get_contents($plugin_root . '/wp-agenda-automatizada.php');
$lock_pos = strpos($bootstrap, 'class-aa-expediente-aggregate-lock.php');
$upload_pos = strpos($bootstrap, 'UploadExpedienteRegistroAdjuntoUseCase.php');
ac_assert(
    'bootstrap carga helper',
    $lock_pos !== false
);
ac_assert(
    'helper antes de Upload UC (si ambos en bootstrap)',
    $upload_pos === false || ($lock_pos !== false && $lock_pos < $upload_pos)
);
ac_assert(
    'sin aa_delete_expediente contenedor',
    !preg_match("/['\"]aa_delete_expediente['\"]/", $bootstrap)
);
ac_assert(
    'sin DeleteExpedienteUseCase',
    strpos($bootstrap, 'DeleteExpedienteUseCase') === false
);

// P3 — storage_quota scope
$logs = [];
$state->get_lock = 1;
$state->conn = 99;
$state->is_used = 1;
$state->release = 1;
$quota = $lock->acquire(AA_Expediente_Aggregate_Lock::SCOPE_STORAGE_QUOTA, AA_Expediente_Aggregate_Lock::STORAGE_QUOTA_SCOPE_ID, 1);
ac_assert('quota acquire ok', $quota instanceof AA_Expediente_Aggregate_Lock_Lease);
ac_assert('quota scope_kind', $quota instanceof AA_Expediente_Aggregate_Lock_Lease && $quota->scope_kind() === 'storage_quota');
ac_assert('quota scope_id', $quota instanceof AA_Expediente_Aggregate_Lock_Lease && $quota->scope_id() === 1);
$bad_quota = $lock->acquire(AA_Expediente_Aggregate_Lock::SCOPE_STORAGE_QUOTA, 2, 1);
ac_assert('quota id≠1 inválido', is_wp_error($bad_quota) && $bad_quota->get_error_code() === 'invalid_lock_scope');

echo "\nResultado: {$passed}/{$total}\n";
if ($failed !== []) {
    echo "Fallidos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
