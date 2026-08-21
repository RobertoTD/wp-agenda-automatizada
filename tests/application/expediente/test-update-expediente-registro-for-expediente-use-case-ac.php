<?php
/**
 * AC — UpdateExpedienteRegistroForExpedienteUseCase.
 *
 * Ejecutar: php tests/application/expediente/test-update-expediente-registro-for-expediente-use-case-ac.php
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
        private $message;
        public function __construct($code = '', $message = '') {
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
$GLOBALS['aa_test_current_time_value'] = '2026-08-20 15:00:00';
if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        $GLOBALS['aa_test_current_time_calls']++;
        return $GLOBALS['aa_test_current_time_value'];
    }
}

final class ExpedientesRepository {
    /** @var array<int,bool|null> */
    public static $exists = [];
    /** @var array<int,mixed> */
    public static $owners = [];
    public static $exists_calls = 0;
    public static $owner_calls = 0;

    public static function reset(): void {
        self::$exists = [];
        self::$owners = [];
        self::$exists_calls = 0;
        self::$owner_calls = 0;
    }

    public static function exists_by_id(int $id) {
        self::$exists_calls++;
        if (!array_key_exists($id, self::$exists)) {
            return false;
        }
        return self::$exists[$id];
    }

    public static function find_owner_context_by_id(int $id): ?array {
        self::$owner_calls++;
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
    /** @var array{array|false|null}|null keyed by "record:exp" */
    public static $find = [];
    /** @var list<array<string,mixed>> */
    public static $updates = [];
    /** @var true|WP_Error|null */
    public static $update_result = true;
    public static $find_calls = 0;
    public static $update_calls = 0;

    public static function reset(): void {
        self::$find = [];
        self::$updates = [];
        self::$update_result = true;
        self::$find_calls = 0;
        self::$update_calls = 0;
    }

    public static function find_by_id_for_expediente(int $record_id, int $expediente_id) {
        self::$find_calls++;
        $key = $record_id . ':' . $expediente_id;
        if (!array_key_exists($key, self::$find)) {
            return false;
        }
        $val = self::$find[$key];
        if (is_array($val) && isset($val['__queue']) && is_array($val['__queue'])) {
            $next = array_shift($val['__queue']);
            self::$find[$key] = $val;
            return $next;
        }
        return $val;
    }

    public static function update_title_body_for_expediente(
        int $record_id,
        int $expediente_id,
        string $title,
        string $body,
        string $updated_at
    ) {
        self::$update_calls++;
        self::$updates[] = compact('record_id', 'expediente_id', 'title', 'body', 'updated_at');
        return self::$update_result;
    }

    public static function update_title_body(...$args) {
        throw new RuntimeException('update legacy no debe llamarse');
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-registro-create-policy.php';
require_once $plugin_root . '/includes/application/expediente/UpdateExpedienteRegistroForExpedienteUseCase.php';

$src = (string) file_get_contents($plugin_root . '/includes/application/expediente/UpdateExpedienteRegistroForExpedienteUseCase.php');
$repo_src = (string) file_get_contents($plugin_root . '/includes/repositories/ExpedienteRegistrosRepository.php');

ac_assert('usa Create Policy', strpos($src, 'AA_Expediente_Registro_Create_Policy') !== false);
ac_assert('usa update_title_body_for_expediente', strpos($src, 'update_title_body_for_expediente') !== false);
ac_assert('un solo current_time', substr_count($src, "current_time('mysql')") === 1);
ac_assert('no absint', strpos($src, 'absint') === false);
ac_assert('no $_POST', strpos($src, '$_POST') === false);
ac_assert('repo método canónico', strpos($repo_src, 'function update_title_body_for_expediente') !== false);
$fnStart = strpos($repo_src, 'function update_title_body_for_expediente');
$fnEnd = strpos($repo_src, 'function insert(', $fnStart !== false ? $fnStart : 0);
$fnChunk = ($fnStart !== false && $fnEnd !== false)
    ? substr($repo_src, $fnStart, $fnEnd - $fnStart)
    : '';
ac_assert(
    'repo WHERE id+expediente_id sin client_id en where',
    $fnChunk !== ''
    && strpos($fnChunk, "'id' => \$record_id") !== false
    && strpos($fnChunk, "'expediente_id' => \$expediente_id") !== false
    && strpos($fnChunk, "'client_id'") === false
);

$uc = new UpdateExpedienteRegistroForExpedienteUseCase();

function seed_ok_client(): array {
    ExpedientesRepository::reset();
    ExpedienteRegistrosRepository::reset();
    ExpedientesRepository::$exists = [5 => true];
    ExpedientesRepository::$owners = [5 => 42];
    $row = [
        'id' => 14,
        'expediente_id' => 5,
        'client_id' => 42,
        'title' => 'Viejo',
        'body' => 'Cuerpo viejo',
        'recorded_at' => '2026-08-01 10:00:00',
        'created_at' => '2026-08-01 10:00:00',
        'updated_at' => null,
    ];
    ExpedienteRegistrosRepository::$find['14:5'] = [
        '__queue' => [$row, array_merge($row, [
            'title' => 'Nuevo',
            'body' => 'Cuerpo nuevo',
            'updated_at' => '2026-08-20 15:00:00',
        ])],
    ];
    $GLOBALS['aa_test_current_time_calls'] = 0;
    return $row;
}

// --- IDs inválidos: cero DB ---
ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
$bad = $uc->execute(['expediente_id' => '01', 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('ID inválido → invalid_id', ($bad['error']['code'] ?? '') === 'invalid_id');
ac_assert('ID inválido sin exists', ExpedientesRepository::$exists_calls === 0);
ac_assert('ID inválido sin update', ExpedienteRegistrosRepository::$update_calls === 0);

$bad2 = $uc->execute(['expediente_id' => 5, 'record_id' => 0, 'title' => 'A', 'body' => 'B']);
ac_assert('record_id 0 → invalid_id', ($bad2['error']['code'] ?? '') === 'invalid_id');

// --- Texto inválido ---
ExpedientesRepository::reset();
$empty = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => '  ', 'body' => 'B']);
ac_assert('título vacío → missing_title', ($empty['error']['code'] ?? '') === 'missing_title');
ac_assert('texto inválido sin DB', ExpedientesRepository::$exists_calls === 0);

$long = $uc->execute([
    'expediente_id' => 5,
    'record_id' => 14,
    'title' => str_repeat('a', 201),
    'body' => 'B',
]);
ac_assert('título largo → title_too_long', ($long['error']['code'] ?? '') === 'title_too_long');

// --- Éxito cliente ---
seed_ok_client();
$ok = $uc->execute([
    'expediente_id' => '5',
    'record_id' => '14',
    'title' => '  Nuevo  ',
    'body' => '  Cuerpo nuevo  ',
    'client_id' => 999,
]);
$dto = $ok['data']['record'] ?? [];
$upd = ExpedienteRegistrosRepository::$updates[0] ?? [];
ac_assert('cliente OK success', !empty($ok['success']));
ac_assert('un current_time', $GLOBALS['aa_test_current_time_calls'] === 1);
ac_assert('update payload', ($upd['record_id'] ?? 0) === 14 && ($upd['expediente_id'] ?? 0) === 5);
ac_assert('title/body trim', ($upd['title'] ?? '') === 'Nuevo' && ($upd['body'] ?? '') === 'Cuerpo nuevo');
ac_assert('updated_at now', ($upd['updated_at'] ?? '') === '2026-08-20 15:00:00');
ac_assert('DTO sin owners', !array_key_exists('client_id', $dto) && !array_key_exists('expediente_id', $dto));
ac_assert('DTO sin adjuntos', !array_key_exists('adjuntos', $dto) && !array_key_exists('adjunto', $dto));
ac_assert('DTO campos', ($dto['id'] ?? 0) === 14 && ($dto['title'] ?? '') === 'Nuevo');
ac_assert('dos finds (pre+post)', ExpedienteRegistrosRepository::$find_calls === 2);
ac_assert('un update', ExpedienteRegistrosRepository::$update_calls === 1);

// --- Éxito general ---
ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedientesRepository::$exists = [9 => true];
ExpedientesRepository::$owners = [9 => null];
$grow = [
    'id' => 21,
    'expediente_id' => 9,
    'client_id' => null,
    'title' => 'G',
    'body' => 'B',
    'recorded_at' => '2026-08-01 10:00:00',
    'created_at' => '2026-08-01 10:00:00',
    'updated_at' => null,
];
ExpedienteRegistrosRepository::$find['21:9'] = [
    '__queue' => [$grow, array_merge($grow, ['title' => 'G2', 'body' => 'B2', 'updated_at' => '2026-08-20 15:00:00'])],
];
$GLOBALS['aa_test_current_time_calls'] = 0;
$gok = $uc->execute(['expediente_id' => 9, 'record_id' => 21, 'title' => 'G2', 'body' => 'B2']);
ac_assert('general OK', !empty($gok['success']));

// --- Owners string MySQL ---
ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedientesRepository::$exists = [5 => true];
ExpedientesRepository::$owners = [5 => '42'];
$rowS = [
    'id' => 14,
    'expediente_id' => '5',
    'client_id' => '42',
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => '2026-08-01 10:00:00',
    'created_at' => '2026-08-01 10:00:00',
    'updated_at' => null,
];
ExpedienteRegistrosRepository::$find['14:5'] = [
    '__queue' => [$rowS, array_merge($rowS, ['title' => 'X', 'body' => 'Y', 'updated_at' => '2026-08-20 15:00:00', 'client_id' => '42', 'expediente_id' => '5'])],
];
$sok = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'X', 'body' => 'Y']);
ac_assert('owners string MySQL OK', !empty($sok['success']));

// --- Int padre + string hijo (wpdb mixto) ---
ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedientesRepository::$exists = [5 => true];
ExpedientesRepository::$owners = [5 => 4];
$rowMix = [
    'id' => 14,
    'expediente_id' => 5,
    'client_id' => '4',
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => '2026-08-01 10:00:00',
    'created_at' => '2026-08-01 10:00:00',
    'updated_at' => null,
];
ExpedienteRegistrosRepository::$find['14:5'] = [
    '__queue' => [$rowMix, array_merge($rowMix, [
        'title' => 'X',
        'body' => 'Y',
        'updated_at' => '2026-08-20 15:00:00',
    ])],
];
$mix = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'X', 'body' => 'Y']);
ac_assert('padre int + hijo string canónico OK', !empty($mix['success']));

// --- Fail-closed: '' / espacios / no canónicos no son general ---
ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedientesRepository::$exists = [5 => true];
ExpedientesRepository::$owners = [5 => ''];
ExpedienteRegistrosRepository::$find['14:5'] = [
    'id' => 14,
    'expediente_id' => 5,
    'client_id' => null,
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => 'x',
    'created_at' => 'x',
    'updated_at' => null,
];
$parentEmpty = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('padre client_id=\'\' → not_found', ($parentEmpty['error']['code'] ?? '') === 'not_found');
ac_assert('padre \'\' sin UPDATE', ExpedienteRegistrosRepository::$update_calls === 0);
ac_assert(
    'padre \'\' mensaje sin filtrar owner',
    strpos((string) ($parentEmpty['error']['message'] ?? ''), "''") === false
    && strpos((string) ($parentEmpty['error']['message'] ?? ''), 'client_id') === false
);

ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedientesRepository::$exists = [5 => true];
ExpedientesRepository::$owners = [5 => null];
ExpedienteRegistrosRepository::$find['14:5'] = [
    'id' => 14,
    'expediente_id' => 5,
    'client_id' => '',
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => 'x',
    'created_at' => 'x',
    'updated_at' => null,
];
$childEmpty = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('registro client_id=\'\' → not_found', ($childEmpty['error']['code'] ?? '') === 'not_found');
ac_assert('registro \'\' sin UPDATE', ExpedienteRegistrosRepository::$update_calls === 0);

$malformedOwners = [' ', '0', 0, -1, '04', '4.0', 4.5, true, false, [], (object) ['id' => 1]];
foreach ($malformedOwners as $i => $badOwner) {
    ExpedientesRepository::reset();
    ExpedienteRegistrosRepository::reset();
    ExpedientesRepository::$exists = [5 => true];
    ExpedientesRepository::$owners = [5 => $badOwner];
    ExpedienteRegistrosRepository::$find['14:5'] = [
        'id' => 14,
        'expediente_id' => 5,
        'client_id' => null,
        'title' => 'A',
        'body' => 'B',
        'recorded_at' => 'x',
        'created_at' => 'x',
        'updated_at' => null,
    ];
    $bad = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
    $label = is_scalar($badOwner) ? var_export($badOwner, true) : gettype($badOwner);
    ac_assert(
        "owner malformado {$label} → not_found",
        ($bad['error']['code'] ?? '') === 'not_found'
    );
    ac_assert(
        "owner malformado {$label} sin UPDATE",
        ExpedienteRegistrosRepository::$update_calls === 0
    );
    ac_assert(
        "owner malformado {$label} sin filtrar en error",
        !array_key_exists('client_id', $bad['error'] ?? [])
        && strpos((string) ($bad['error']['message'] ?? ''), 'client_id') === false
        && ($bad['data']['record'] ?? null) === null
    );
}

// --- not_found / mismatch ---
ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedientesRepository::$exists = [5 => false];
$nf = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('expediente inexistente', ($nf['error']['code'] ?? '') === 'not_found');
ac_assert('inexistente sin update', ExpedienteRegistrosRepository::$update_calls === 0);

ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedientesRepository::$exists = [5 => null];
$lf = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('exists null → lookup_failed', ($lf['error']['code'] ?? '') === 'lookup_failed');

seed_ok_client();
ExpedienteRegistrosRepository::$find['14:5'] = false;
$nr = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('registro inexistente', ($nr['error']['code'] ?? '') === 'not_found');

seed_ok_client();
ExpedienteRegistrosRepository::$find['14:5'] = [
    'id' => 14,
    'expediente_id' => 5,
    'client_id' => 99,
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => 'x',
    'created_at' => 'x',
    'updated_at' => null,
];
$mm = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('owner cliente distinto → not_found', ($mm['error']['code'] ?? '') === 'not_found');
ac_assert('mismatch sin update', ExpedienteRegistrosRepository::$update_calls === 0);

ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedientesRepository::$exists = [9 => true];
ExpedientesRepository::$owners = [9 => null];
ExpedienteRegistrosRepository::$find['21:9'] = [
    'id' => 21,
    'expediente_id' => 9,
    'client_id' => 7,
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => 'x',
    'created_at' => 'x',
    'updated_at' => null,
];
$pg = $uc->execute(['expediente_id' => 9, 'record_id' => 21, 'title' => 'A', 'body' => 'B']);
ac_assert('padre general + hijo cliente → not_found', ($pg['error']['code'] ?? '') === 'not_found');

ExpedientesRepository::reset();
ExpedienteRegistrosRepository::reset();
ExpedientesRepository::$exists = [5 => true];
ExpedientesRepository::$owners = [5 => 42];
ExpedienteRegistrosRepository::$find['14:5'] = [
    'id' => 14,
    'expediente_id' => 5,
    'client_id' => null,
    'title' => 'A',
    'body' => 'B',
    'recorded_at' => 'x',
    'created_at' => 'x',
    'updated_at' => null,
];
$pc = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('padre cliente + hijo NULL → not_found', ($pc['error']['code'] ?? '') === 'not_found');

// --- cero filas + relectura ---
seed_ok_client();
ExpedienteRegistrosRepository::$update_result = true; // 0 rows still true
$zero = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'Nuevo', 'body' => 'Cuerpo nuevo']);
ac_assert('cero filas + relectura OK', !empty($zero['success']));

seed_ok_client();
ExpedienteRegistrosRepository::$find['14:5'] = [
    '__queue' => [
        [
            'id' => 14,
            'expediente_id' => 5,
            'client_id' => 42,
            'title' => 'A',
            'body' => 'B',
            'recorded_at' => 'x',
            'created_at' => 'x',
            'updated_at' => null,
        ],
        false,
    ],
];
$gone = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('cero filas + relectura false → not_found', ($gone['error']['code'] ?? '') === 'not_found');

seed_ok_client();
ExpedienteRegistrosRepository::$find['14:5'] = [
    '__queue' => [
        [
            'id' => 14,
            'expediente_id' => 5,
            'client_id' => 42,
            'title' => 'A',
            'body' => 'B',
            'recorded_at' => 'x',
            'created_at' => 'x',
            'updated_at' => null,
        ],
        null,
    ],
];
$rlf = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('relectura null → lookup_failed', ($rlf['error']['code'] ?? '') === 'lookup_failed');

seed_ok_client();
ExpedienteRegistrosRepository::$update_result = new WP_Error('db_error', 'boom');
$sqlf = $uc->execute(['expediente_id' => 5, 'record_id' => 14, 'title' => 'A', 'body' => 'B']);
ac_assert('update false → persistence_failed', ($sqlf['error']['code'] ?? '') === 'persistence_failed');

echo "\nResultado: {$passed}/{$total}\n";
if ($failed) {
    echo 'Fallos: ' . implode(', ', $failed) . "\n";
    exit(1);
}
exit(0);
