<?php
/**
 * AC — ListExpedienteRegistrosWithPublicAdjuntosUseCase (B2b / P2).
 *
 * Ejecutar: php tests/application/expediente/test-list-expediente-registros-with-public-adjuntos-use-case-ac.php
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

$iid = '11111111-2222-4333-8444-555555555555';
$op_a = '660e8400-e29b-41d4-a716-446655440000';
$op_b = '770e8400-e29b-41d4-a716-446655440001';
$op_c = '880e8400-e29b-41d4-a716-446655440002';

final class ExpedientesRepository {
    /** @var bool|null */
    public static $exists_result = true;
    public static $exists_calls = 0;
    /** @var int|null */
    public static $last_exists_id = null;

    /** @var array{id:int,client_id:?int}|null */
    public static $owner = ['id' => 7, 'client_id' => 55];
    public static $owner_calls = 0;
    /** @var int|null */
    public static $last_owner_id = null;

    public static function exists_by_id(int $id) {
        self::$exists_calls++;
        self::$last_exists_id = $id;
        return self::$exists_result;
    }

    public static function find_owner_context_by_id(int $id): ?array {
        self::$owner_calls++;
        self::$last_owner_id = $id;
        return self::$owner;
    }
}

final class ListExpedienteRegistrosUseCase {
    public static $calls = 0;
    /** @var array<string,mixed>|null */
    public static $last_input = null;
    /** @var array<string,mixed> */
    public static $result = [
        'success' => true,
        'data' => [
            'records' => [],
            'page' => 1,
            'per_page' => 15,
            'total' => 0,
            'total_pages' => 0,
            'has_previous' => false,
            'has_next' => false,
        ],
    ];

    public function execute(array $input): array {
        self::$calls++;
        self::$last_input = $input;
        return self::$result;
    }
}

final class ExpedienteAdjuntosRepository {
    public static $calls = 0;
    /** @var list<list<int>> */
    public static $args = [];
    /** @var array<int,list<array<string,mixed>>>|null */
    public static $by_record = [];

    /**
     * @param list<int> $record_ids
     * @return array<int,list<array<string,mixed>>>|null
     */
    public static function list_by_record_ids_for_records(array $record_ids): ?array {
        self::$calls++;
        self::$args[] = $record_ids;
        return self::$by_record;
    }
}

require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoVariants.php';
require_once $plugin_root . '/includes/domain/expediente/class-aa-expediente-adjunto-identity-policy.php';
require_once $plugin_root . '/includes/domain/expediente/ExpedienteAdjuntoPublicDto.php';
require_once $plugin_root . '/includes/application/expediente/ListExpedienteRegistrosWithPublicAdjuntosUseCase.php';

$src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/ListExpedienteRegistrosWithPublicAdjuntosUseCase.php'
);
$list_src = (string) file_get_contents(
    $plugin_root . '/includes/application/expediente/ListExpedienteRegistrosUseCase.php'
);
$detail_src = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/modules/expedientes/detail.php'
);

ac_assert('sin gate/nonce/cap en UC enriquecido', strpos($src, 'current_user_can') === false
    && strpos($src, 'check_ajax_referer') === false
    && strpos($src, 'ResolveShellAccessUseCase') === false);
ac_assert('delega ListExpedienteRegistrosUseCase', strpos($src, 'ListExpedienteRegistrosUseCase') !== false);
ac_assert('usa exists_by_id + find_owner_context_by_id', strpos($src, 'exists_by_id') !== false
    && strpos($src, 'find_owner_context_by_id') !== false);
ac_assert('bulk list_by_record_ids_for_records', strpos($src, 'list_by_record_ids_for_records') !== false);
ac_assert('policy validate', strpos($src, 'AA_Expediente_Adjunto_Identity_Policy::validate') !== false);
ac_assert('DTO público', strpos($src, 'ExpedienteAdjuntoPublicDto::from') !== false);
ac_assert('ignora client_id de input (sin leerlo)', strpos($src, "input['client_id']") === false
    && strpos($src, '$input["client_id"]') === false);
$index_src = (string) file_get_contents(
    $plugin_root . '/includes/admin/ui/index.php'
);
ac_assert(
    'SSR textual no usa UC enriquecido',
    strpos($list_src, 'WithPublicAdjuntos') === false
    && strpos($detail_src, 'WithPublicAdjuntos') === false
    && strpos($detail_src, 'ListExpedienteRegistrosWithPublicAdjuntosUseCase') === false
    && strpos($index_src, 'ListExpedienteRegistrosWithPublicAdjuntosUseCase') === false
    && strpos($index_src, 'ListExpedienteRegistrosUseCase') !== false
);

function aa_reset_uc(): void {
    global $op_a, $op_b, $op_c;
    ExpedientesRepository::$exists_result = true;
    ExpedientesRepository::$exists_calls = 0;
    ExpedientesRepository::$last_exists_id = null;
    ExpedientesRepository::$owner = ['id' => 7, 'client_id' => 55];
    ExpedientesRepository::$owner_calls = 0;
    ExpedientesRepository::$last_owner_id = null;
    ListExpedienteRegistrosUseCase::$calls = 0;
    ListExpedienteRegistrosUseCase::$last_input = null;
    ListExpedienteRegistrosUseCase::$result = [
        'success' => true,
        'data' => [
            'records' => [],
            'page' => 1,
            'per_page' => 15,
            'total' => 0,
            'total_pages' => 0,
            'has_previous' => false,
            'has_next' => false,
        ],
    ];
    ExpedienteAdjuntosRepository::$calls = 0;
    ExpedienteAdjuntosRepository::$args = [];
    ExpedienteAdjuntosRepository::$by_record = [];
}

function aa_sample_records(): array {
    return [
        [
            'id' => 10,
            'title' => 'A',
            'body' => 'Body A',
            'recorded_at' => '2026-08-20 12:00:00',
            'created_at' => '2026-08-20 12:00:00',
            'updated_at' => null,
        ],
        [
            'id' => 9,
            'title' => 'B',
            'body' => 'Body B',
            'recorded_at' => '2026-08-19 10:00:00',
            'created_at' => '2026-08-19 10:00:00',
            'updated_at' => null,
        ],
    ];
}

$uc = new ListExpedienteRegistrosWithPublicAdjuntosUseCase();

// --- Validación / padre ---

aa_reset_uc();
$bad = $uc->execute(['expediente_id' => '01']);
ac_assert('01 → invalid_id', ($bad['success'] ?? true) === false
    && ($bad['error']['code'] ?? '') === 'invalid_id');
ac_assert('01 sin exists/list/bulk', ExpedientesRepository::$exists_calls === 0
    && ListExpedienteRegistrosUseCase::$calls === 0
    && ExpedienteAdjuntosRepository::$calls === 0);

aa_reset_uc();
ExpedientesRepository::$exists_result = false;
$nf = $uc->execute(['expediente_id' => 7]);
ac_assert('exists false → not_found', ($nf['error']['code'] ?? '') === 'not_found');
ac_assert('not_found sin owner/list/bulk', ExpedientesRepository::$owner_calls === 0
    && ListExpedienteRegistrosUseCase::$calls === 0
    && ExpedienteAdjuntosRepository::$calls === 0);

aa_reset_uc();
ExpedientesRepository::$exists_result = null;
$lf = $uc->execute(['expediente_id' => 7]);
ac_assert('exists null → lookup_failed', ($lf['error']['code'] ?? '') === 'lookup_failed');
ac_assert('lookup exists sin owner/list', ExpedientesRepository::$owner_calls === 0
    && ListExpedienteRegistrosUseCase::$calls === 0);

aa_reset_uc();
ExpedientesRepository::$owner = null;
$ownerNull = $uc->execute(['expediente_id' => 7]);
ac_assert('owner null tras exists → lookup_failed', ($ownerNull['error']['code'] ?? '') === 'lookup_failed');
ac_assert('owner null sin list/bulk', ListExpedienteRegistrosUseCase::$calls === 0
    && ExpedienteAdjuntosRepository::$calls === 0
    && ExpedientesRepository::$exists_calls === 1
    && ExpedientesRepository::$owner_calls === 1);

// --- Padre cliente con adjuntos v1 ---

aa_reset_uc();
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => aa_sample_records(),
        'page' => 1,
        'per_page' => 15,
        'total' => 2,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
$path_10_a = ExpedienteAdjuntoVariants::build_client_original_path($iid, 55, 10, $op_a);
$path_10_b = ExpedienteAdjuntoVariants::build_client_original_path($iid, 55, 10, $op_b);
$path_9 = ExpedienteAdjuntoVariants::build_client_original_path($iid, 55, 9, $op_c);
ExpedienteAdjuntosRepository::$by_record = [
    10 => [
        [
            'id' => 302,
            'record_id' => 10,
            'client_id' => 55,
            'upload_operation_id' => $op_a,
            'storage_path' => $path_10_a,
            'mime_type' => 'image/jpeg',
            'byte_size' => 2048,
            'width' => 800,
            'height' => 600,
            'created_at' => '2026-08-20 13:00:00',
        ],
        [
            'id' => 301,
            'record_id' => 10,
            'client_id' => 55,
            'upload_operation_id' => $op_b,
            'storage_path' => $path_10_b,
            'mime_type' => 'image/jpeg',
            'byte_size' => 1024,
            'width' => 400,
            'height' => 300,
            'created_at' => '2026-08-20 12:30:00',
        ],
    ],
    9 => [
        [
            'id' => 201,
            'record_id' => 9,
            'client_id' => 55,
            'upload_operation_id' => $op_c,
            'storage_path' => $path_9,
            'mime_type' => 'image/jpeg',
            'byte_size' => 512,
            'width' => 100,
            'height' => 80,
            'created_at' => '2026-08-19 11:00:00',
        ],
    ],
    99 => [
        [
            'id' => 999,
            'record_id' => 99,
            'client_id' => 55,
            'byte_size' => 1,
            'width' => 1,
            'height' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'storage_path' => '/other',
        ],
    ],
];

$ok = $uc->execute([
    'expediente_id' => '7',
    'page' => '1',
    'client_id' => 999,
]);
ac_assert('cliente+adjuntos v1 → success', ($ok['success'] ?? false) === true);
ac_assert('paginación preservada', ($ok['data']['total'] ?? -1) === 2
    && ($ok['data']['per_page'] ?? 0) === 15
    && ($ok['data']['page'] ?? 0) === 1);
ac_assert('una sola llamada bulk', ExpedienteAdjuntosRepository::$calls === 1);
ac_assert(
    'bulk con ids de la página (sin client_id)',
    (ExpedienteAdjuntosRepository::$args[0] ?? null) === [10, 9]
);
ac_assert(
    'client_id POST no llega al list UC',
    !array_key_exists('client_id', ListExpedienteRegistrosUseCase::$last_input ?? [])
    && (ListExpedienteRegistrosUseCase::$last_input['expediente_id'] ?? null) === 7
);

$r0 = $ok['data']['records'][0] ?? [];
$r1 = $ok['data']['records'][1] ?? [];
ac_assert('campos textuales + adjuntos/adjunto', array_key_exists('title', $r0)
    && array_key_exists('body', $r0)
    && array_key_exists('adjuntos', $r0)
    && array_key_exists('adjunto', $r0));
ac_assert('agrupa record 10 → 2 adjuntos', count($r0['adjuntos'] ?? []) === 2);
ac_assert('orden id DESC en record 10', ($r0['adjuntos'][0]['id'] ?? 0) === 302
    && ($r0['adjuntos'][1]['id'] ?? 0) === 301);
ac_assert('adjunto === adjuntos[0]', ($r0['adjunto'] ?? null) === ($r0['adjuntos'][0] ?? null));
ac_assert('agrupa record 9 → 1 adjunto', count($r1['adjuntos'] ?? []) === 1
    && ($r1['adjunto']['id'] ?? 0) === 201);
ac_assert(
    'DTO solo 5 campos públicos',
    array_keys($r0['adjuntos'][0]) === ['id', 'width', 'height', 'byte_size', 'created_at']
);
$blob = json_encode($ok['data']);
ac_assert(
    'sin owners/paths/tokens/URLs firmadas',
    strpos($blob, 'storage_path') === false
    && strpos($blob, 'upload_operation_id') === false
    && strpos($blob, 'mime_type') === false
    && strpos($blob, '/clients/') === false
    && strpos($blob, 'signed') === false
    && strpos($blob, '"client_id"') === false
    && strpos($blob, '"expediente_id"') === false
    && strpos($blob, '"blog_id"') === false
);
ac_assert('excluye adjuntos de record 99', strpos($blob, '"id":999') === false
    && strpos($blob, '"id":999,') === false);

// --- Relacionado v2 ---

aa_reset_uc();
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => [aa_sample_records()[0]],
        'page' => 1,
        'per_page' => 15,
        'total' => 1,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
$path_v2 = ExpedienteAdjuntoVariants::build_expediente_record_original_path($iid, 7, 10, $op_a);
ExpedienteAdjuntosRepository::$by_record = [
    10 => [
        [
            'id' => 401,
            'record_id' => 10,
            'client_id' => 55,
            'upload_operation_id' => $op_a,
            'storage_path' => $path_v2,
            'mime_type' => 'image/jpeg',
            'byte_size' => 900,
            'width' => 200,
            'height' => 150,
            'created_at' => '2026-08-21 10:00:00',
        ],
    ],
];
$v2ok = $uc->execute(['expediente_id' => 7]);
ac_assert('relacionado v2 → success', ($v2ok['success'] ?? false) === true);
ac_assert('relacionado v2 adjunto', ($v2ok['data']['records'][0]['adjunto']['id'] ?? 0) === 401);

// --- Registro sin adjuntos ---

aa_reset_uc();
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => [
            [
                'id' => 11,
                'title' => 'Solo texto',
                'body' => 'x',
                'recorded_at' => '2026-08-01 00:00:00',
                'created_at' => '2026-08-01 00:00:00',
                'updated_at' => null,
            ],
        ],
        'page' => 1,
        'per_page' => 15,
        'total' => 1,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
ExpedienteAdjuntosRepository::$by_record = [];
$noAdj = $uc->execute(['expediente_id' => 7]);
$nr = $noAdj['data']['records'][0] ?? [];
ac_assert('sin adjuntos → colecciones vacías', ($nr['adjuntos'] ?? null) === []
    && array_key_exists('adjunto', $nr) && $nr['adjunto'] === null);
ac_assert('sin adjuntos aún llama bulk una vez', ExpedienteAdjuntosRepository::$calls === 1);

// --- Padre general (client_id NULL) ---

aa_reset_uc();
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => aa_sample_records(),
        'page' => 1,
        'per_page' => 15,
        'total' => 2,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
ExpedienteAdjuntosRepository::$by_record = [];
$general = $uc->execute(['expediente_id' => 7]);
ac_assert('general vacío → success', ($general['success'] ?? false) === true);
ac_assert('general vacío llama bulk', ExpedienteAdjuntosRepository::$calls === 1);
ac_assert(
    'general vacío adjuntos vacíos',
    ($general['data']['records'][0]['adjuntos'] ?? null) === []
    && $general['data']['records'][0]['adjunto'] === null
    && ($general['data']['records'][1]['adjuntos'] ?? null) === []
    && $general['data']['records'][1]['adjunto'] === null
);

// General con adjunto v2 coherente
aa_reset_uc();
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => null];
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => [aa_sample_records()[0]],
        'page' => 1,
        'per_page' => 15,
        'total' => 1,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
$path_g = ExpedienteAdjuntoVariants::build_expediente_record_original_path($iid, 7, 10, $op_a);
ExpedienteAdjuntosRepository::$by_record = [
    10 => [
        [
            'id' => 501,
            'record_id' => 10,
            'client_id' => null,
            'upload_operation_id' => $op_a,
            'storage_path' => $path_g,
            'mime_type' => 'image/jpeg',
            'byte_size' => 640,
            'width' => 320,
            'height' => 240,
            'created_at' => '2026-08-22 08:00:00',
        ],
    ],
];
$gadj = $uc->execute(['expediente_id' => 7]);
ac_assert('general v2 → success', ($gadj['success'] ?? false) === true);
ac_assert('general v2 incluye adjunto', ($gadj['data']['records'][0]['adjunto']['id'] ?? 0) === 501);

// --- Página vacía ---

aa_reset_uc();
ExpedientesRepository::$owner = ['id' => 7, 'client_id' => 55];
$empty = $uc->execute(['expediente_id' => 7]);
ac_assert('página vacía → success', ($empty['success'] ?? false) === true
    && ($empty['data']['records'] ?? null) === []);
ac_assert('página vacía sin bulk', ExpedienteAdjuntosRepository::$calls === 0);

// --- Inconsistencia fail-closed ---

aa_reset_uc();
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => aa_sample_records(),
        'page' => 1,
        'per_page' => 15,
        'total' => 2,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
ExpedienteAdjuntosRepository::$by_record = [
    10 => [
        [
            'id' => 302,
            'record_id' => 10,
            'client_id' => 55,
            'upload_operation_id' => $op_a,
            'storage_path' => '/secret',
            'mime_type' => 'image/jpeg',
            'byte_size' => 2048,
            'width' => 800,
            'height' => 600,
            'created_at' => '2026-08-20 13:00:00',
        ],
    ],
];
$incon = $uc->execute(['expediente_id' => 7]);
ac_assert('path inválido → adjunto_inconsistent', ($incon['error']['code'] ?? '') === 'adjunto_inconsistent');

// --- Bulk SQL null → lookup_failed ---

aa_reset_uc();
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => aa_sample_records(),
        'page' => 1,
        'per_page' => 15,
        'total' => 2,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
ExpedienteAdjuntosRepository::$by_record = null;
$sqlFail = $uc->execute(['expediente_id' => 7]);
ac_assert('bulk null → lookup_failed', ($sqlFail['error']['code'] ?? '') === 'lookup_failed');

// --- Bulk vacío OK ---

aa_reset_uc();
ListExpedienteRegistrosUseCase::$result = [
    'success' => true,
    'data' => [
        'records' => aa_sample_records(),
        'page' => 1,
        'per_page' => 15,
        'total' => 2,
        'total_pages' => 1,
        'has_previous' => false,
        'has_next' => false,
    ],
];
ExpedienteAdjuntosRepository::$by_record = [];
$soft = $uc->execute(['expediente_id' => 7]);
ac_assert('bulk vacío → success', ($soft['success'] ?? false) === true);
ac_assert(
    'bulk vacío colecciones vacías',
    ($soft['data']['records'][0]['adjuntos'] ?? null) === []
    && $soft['data']['records'][0]['adjunto'] === null
    && ($soft['data']['records'][1]['adjuntos'] ?? null) === []
);

// --- Propagación fallo list UC ---

aa_reset_uc();
ListExpedienteRegistrosUseCase::$result = [
    'success' => false,
    'error' => ['code' => 'invalid_id', 'message' => 'Expediente no válido.'],
];
$prop = $uc->execute(['expediente_id' => 7]);
ac_assert('propaga error del list UC', ($prop['error']['code'] ?? '') === 'invalid_id');
ac_assert('error list sin bulk', ExpedienteAdjuntosRepository::$calls === 0);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
