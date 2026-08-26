<?php
/**
 * AC — UpdateExpedienteUseCase (edición de título del contenedor).
 *
 * Ejecutar: php tests/application/expediente/test-update-expediente-use-case-ac.php
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
            $this->code = (string) $code;
            $this->message = (string) $message;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        return $GLOBALS['aa_test_now'] ?? '2026-08-26 12:00:00';
    }
}

final class ExpedientesRepository {
    /** @var string normal|lookup_error|missing|disappear|update_error|zero_rows */
    public static $mode = 'normal';
    /** @var array{id:int,title:string}|null */
    public static $row = null;
    /** @var list<array{id:int,title:string,updated_at:string}> */
    public static $updates = [];
    public static $lookup_calls = 0;

    public static function reset(string $mode = 'normal', ?array $row = null): void {
        self::$mode = $mode;
        self::$row = $row;
        self::$updates = [];
        self::$lookup_calls = 0;
    }

    public static function find_title_context_by_id(int $id) {
        self::$lookup_calls++;

        if (self::$mode === 'lookup_error' && self::$lookup_calls === 1) {
            return new WP_Error('db_error', 'fail');
        }
        if (self::$mode === 'lookup_error' && self::$lookup_calls > 1) {
            return new WP_Error('db_error', 'reread fail');
        }
        if (self::$mode === 'missing') {
            return false;
        }
        if (self::$mode === 'disappear') {
            if (self::$lookup_calls === 1) {
                return self::$row;
            }
            return false;
        }
        if (self::$mode === 'reread_fail') {
            if (self::$lookup_calls === 1) {
                return self::$row;
            }
            return new WP_Error('db_error', 'reread');
        }

        if (!is_array(self::$row) || (int) (self::$row['id'] ?? 0) !== $id) {
            return false;
        }

        return self::$row;
    }

    public static function update_title_by_id(int $id, string $title, string $updated_at) {
        self::$updates[] = [
            'id' => $id,
            'title' => $title,
            'updated_at' => $updated_at,
        ];

        if (self::$mode === 'update_error') {
            return new WP_Error('db_error', 'update fail');
        }
        if (self::$mode === 'zero_rows' || self::$mode === 'disappear') {
            return false;
        }

        self::$row = ['id' => $id, 'title' => $title];
        return true;
    }
}

require_once $plugin_root . '/includes/application/expediente/UpdateExpedienteUseCase.php';

$src = (string) file_get_contents($plugin_root . '/includes/application/expediente/UpdateExpedienteUseCase.php');
ac_assert('sin Aggregate_Lock', strpos($src, 'Aggregate_Lock') === false);
ac_assert('reutiliza Create_Policy', strpos($src, 'AA_Expediente_Create_Policy') !== false);
ac_assert('reutiliza Id_Policy', strpos($src, 'AA_Expediente_Id_Policy') !== false);
ac_assert('no absint', strpos($src, 'absint') === false);
ac_assert('DTO expediente id+title', strpos($src, "'expediente'") !== false);

$uc = new UpdateExpedienteUseCase();

ExpedientesRepository::reset('normal', ['id' => 11, 'title' => 'General viejo']);
$ok = $uc->execute(['expediente_id' => 11, 'title' => '  Nuevo general  ', 'client_id' => 999]);
ac_assert('general trim + UPDATE', ($ok['success'] ?? false) === true
    && ($ok['data']['expediente']['title'] ?? '') === 'Nuevo general'
    && ($ok['data']['expediente']['id'] ?? 0) === 11
    && count(ExpedientesRepository::$updates) === 1);
ac_assert('updated_at servidor una vez', (ExpedientesRepository::$updates[0]['updated_at'] ?? '') === '2026-08-26 12:00:00');
ac_assert('DTO sin internals', !isset($ok['data']['client_id'])
    && !isset($ok['data']['expediente']['client_id'])
    && !isset($ok['data']['category_id'])
    && !isset($ok['data']['description']));

ExpedientesRepository::reset('normal', ['id' => 22, 'title' => 'Cliente Snapshot']);
$ok = $uc->execute(['expediente_id' => '22', 'title' => 'Título editado']);
ac_assert('relacionado con cliente', ($ok['success'] ?? false) === true
    && ($ok['data']['expediente']['title'] ?? '') === 'Título editado');

ExpedientesRepository::reset('normal', ['id' => 33, 'title' => 'Laboral']);
$ok = $uc->execute(['expediente_id' => 33, 'title' => 'Laboral editado']);
ac_assert('otra categoría igual', ($ok['success'] ?? false) === true);

$bad = $uc->execute(['expediente_id' => '01', 'title' => 'X']);
ac_assert('id inválido', ($bad['error']['code'] ?? '') === 'invalid_id');

$bad = $uc->execute(['expediente_id' => 5, 'title' => '   ']);
ac_assert('vacío', ($bad['error']['code'] ?? '') === 'missing_title');

$bad = $uc->execute(['expediente_id' => 5, 'title' => str_repeat('á', 201)]);
ac_assert('title_too_long multibyte', ($bad['error']['code'] ?? '') === 'title_too_long');

ExpedientesRepository::reset('normal', ['id' => 11, 'title' => 'Igual']);
$ok = $uc->execute(['expediente_id' => 11, 'title' => 'Igual']);
ac_assert('mismo título → cero UPDATE', ($ok['success'] ?? false) === true
    && count(ExpedientesRepository::$updates) === 0
    && ExpedientesRepository::$lookup_calls === 1);

ExpedientesRepository::reset('missing');
$bad = $uc->execute(['expediente_id' => 99, 'title' => 'X']);
ac_assert('not_found', ($bad['error']['code'] ?? '') === 'not_found');

ExpedientesRepository::reset('lookup_error', ['id' => 11, 'title' => 'A']);
$bad = $uc->execute(['expediente_id' => 11, 'title' => 'B']);
ac_assert('lookup_failed', ($bad['error']['code'] ?? '') === 'lookup_failed');

ExpedientesRepository::reset('update_error', ['id' => 11, 'title' => 'A']);
$bad = $uc->execute(['expediente_id' => 11, 'title' => 'B']);
ac_assert('persistence_failed', ($bad['error']['code'] ?? '') === 'persistence_failed');

ExpedientesRepository::reset('disappear', ['id' => 11, 'title' => 'A']);
$bad = $uc->execute(['expediente_id' => 11, 'title' => 'B']);
ac_assert('delete gana → not_found', ($bad['error']['code'] ?? '') === 'not_found'
    && count(ExpedientesRepository::$updates) === 1);

ExpedientesRepository::reset('reread_fail', ['id' => 11, 'title' => 'A']);
$bad = $uc->execute(['expediente_id' => 11, 'title' => 'B']);
ac_assert('relectura fail', ($bad['error']['code'] ?? '') === 'lookup_failed');

ExpedientesRepository::reset('zero_rows', ['id' => 11, 'title' => 'A']);
$ok = $uc->execute(['expediente_id' => 11, 'title' => 'B']);
ac_assert('cero filas + fila intacta → DTO actual', ($ok['success'] ?? false) === true
    && ($ok['data']['expediente']['title'] ?? '') === 'A');

ExpedientesRepository::reset('normal', ['id' => 11, 'title' => 'A']);
$ok = $uc->execute(['expediente_id' => 11, 'title' => 'B']);
ac_assert('relectura título persistido', ($ok['data']['expediente']['title'] ?? '') === 'B');

$repo_src = (string) file_get_contents($plugin_root . '/includes/repositories/ExpedientesRepository.php');
$goc = '';
if (preg_match('/function get_or_create_for_client[\s\S]*?function insert\(/', $repo_src, $m) === 1) {
    $goc = $m[0];
}
ac_assert(
    'get_or_create sin overwrite title',
    strpos($goc, 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)') !== false
    && !preg_match('/ON DUPLICATE KEY UPDATE[\s\S]*title\s*=/', $goc)
);

echo "\nResultado: {$passed}/{$total}" . ($failed ? ' FAIL: ' . implode(', ', $failed) : ' OK') . "\n";
exit($failed ? 1 : 0);
