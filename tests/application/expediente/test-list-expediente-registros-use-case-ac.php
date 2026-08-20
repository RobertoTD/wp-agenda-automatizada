<?php
/**
 * AC — ListExpedienteRegistrosUseCase (paginación 15 + id estricto).
 *
 * Ejecutar: php tests/application/expediente/test-list-expediente-registros-use-case-ac.php
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

final class ExpedienteRegistrosRepository {
    /** @var array<int,int> */
    public static $counts = [];
    /** @var array<int,list<array<string,mixed>>> */
    public static $rows_by_expediente = [];
    /** @var list<array<string,mixed>> */
    public static $list_args = [];

    public static function count_by_expediente_id(int $expediente_id): int {
        return (int) (self::$counts[$expediente_id] ?? 0);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function list_by_expediente_id(int $expediente_id, int $limit, int $offset = 0): array {
        self::$list_args[] = [
            'expediente_id' => $expediente_id,
            'limit' => $limit,
            'offset' => $offset,
        ];

        $rows = self::$rows_by_expediente[$expediente_id] ?? [];
        return array_slice($rows, $offset, $limit);
    }
}

require_once $plugin_root . '/includes/application/expediente/ListExpedienteRegistrosUseCase.php';

$src = file_get_contents($plugin_root . '/includes/application/expediente/ListExpedienteRegistrosUseCase.php');
$id_policy_src = file_get_contents($plugin_root . '/includes/domain/expediente/class-aa-expediente-id-policy.php');
ac_assert('PER_PAGE 15', ListExpedienteRegistrosUseCase::PER_PAGE === 15);
ac_assert('validación estricta (sin absint)', strpos($src, 'absint(') === false
    && strpos($src, 'AA_Expediente_Id_Policy::normalize') !== false);
ac_assert(
    'normalizador canónico en policy compartida',
    is_string($id_policy_src) && strpos($id_policy_src, '/^[1-9][0-9]{0,18}$/') !== false
);
ac_assert('sin gate/permisos en use case', strpos($src, 'current_user_can') === false && strpos($src, 'ResolveShellAccessUseCase') === false);
ac_assert('sin check de existencia del padre', strpos($src, 'ExpedientesRepository::find_by_id') === false
    && strpos($src, 'exists_by_id') === false);
ac_assert('sin per_page externo', strpos($src, "input['per_page']") === false && strpos($src, '$input["per_page"]') === false);
ac_assert('normalize_page estricto canónico', strpos($src, 'private function normalize_page') !== false
    && strpos($src, '/^[1-9][0-9]{0,18}$/') !== false);

$uc = new ListExpedienteRegistrosUseCase();

$invalids = [
    'ausente' => [],
    'null' => ['expediente_id' => null],
    'vacío' => ['expediente_id' => ''],
    'cero string' => ['expediente_id' => '0'],
    'cero int' => ['expediente_id' => 0],
    'negativo string' => ['expediente_id' => '-1'],
    'negativo int' => ['expediente_id' => -1],
    'leading zero' => ['expediente_id' => '01'],
    'decimal' => ['expediente_id' => '1.5'],
    'plus' => ['expediente_id' => '+7'],
    'texto' => ['expediente_id' => 'abc'],
    'array' => ['expediente_id' => ['7']],
    'object' => ['expediente_id' => (object) ['id' => 7]],
];

foreach ($invalids as $label => $input) {
    $result = $uc->execute($input);
    ac_assert(
        'inválido ' . $label . ' → invalid_id',
        empty($result['success']) && ($result['error']['code'] ?? '') === 'invalid_id'
    );
}

$rows = [];
for ($i = 40; $i >= 1; $i--) {
    $rows[] = [
        'id' => $i,
        'title' => 'Registro ' . $i,
        'body' => 'Texto ' . $i,
        'recorded_at' => '2026-08-01 10:00:00',
        'created_at' => '2026-08-01 10:00:00',
        'updated_at' => null,
    ];
}

ExpedienteRegistrosRepository::$counts = [7 => 40, 8 => 0];
ExpedienteRegistrosRepository::$rows_by_expediente = [7 => $rows];
ExpedienteRegistrosRepository::$list_args = [];

$page1 = $uc->execute(['expediente_id' => '7', 'page' => 1]);
$page1Rows = $page1['data']['records'] ?? [];
ac_assert('página 1 success', !empty($page1['success']));
ac_assert('página 1 tiene 15', count($page1Rows) === 15);
ac_assert('payload no expone client_id', count($page1Rows) > 0 && !array_key_exists('client_id', $page1Rows[0]));
ac_assert('payload no expone expediente_id', count($page1Rows) > 0 && !array_key_exists('expediente_id', $page1Rows[0]));
ac_assert('total 40', ($page1['data']['total'] ?? 0) === 40);
ac_assert('total_pages 3', ($page1['data']['total_pages'] ?? 0) === 3);
ac_assert('has_previous false en pág 1', ($page1['data']['has_previous'] ?? true) === false);
ac_assert('has_next true en pág 1', ($page1['data']['has_next'] ?? false) === true);
ac_assert('repo list usa limit/offset pág1', (ExpedienteRegistrosRepository::$list_args[0]['limit'] ?? 0) === 15 && (ExpedienteRegistrosRepository::$list_args[0]['offset'] ?? -1) === 0);

$page2 = $uc->execute(['expediente_id' => 7, 'page' => 2]);
$page2Rows = $page2['data']['records'] ?? [];
ac_assert('página 2 tiene 15', count($page2Rows) === 15);
ac_assert('página 2 usa offset 15', (ExpedienteRegistrosRepository::$list_args[1]['offset'] ?? -1) === 15);
ac_assert('page 2 has_previous true', ($page2['data']['has_previous'] ?? false) === true);
ac_assert('page 2 has_next true', ($page2['data']['has_next'] ?? false) === true);

$pageLast = $uc->execute(['expediente_id' => 7, 'page' => 99]);
$pageLastRows = $pageLast['data']['records'] ?? [];
ac_assert('página alta clamp a última', ($pageLast['data']['page'] ?? 0) === 3);
ac_assert('última página tiene resto', count($pageLastRows) === 10);
ac_assert('última página usa offset 30', (ExpedienteRegistrosRepository::$list_args[2]['offset'] ?? -1) === 30);
ac_assert('última has_next false', ($pageLast['data']['has_next'] ?? true) === false);

$pageZero = $uc->execute(['expediente_id' => 7, 'page' => 0]);
ac_assert('page 0 clamp a 1', ($pageZero['data']['page'] ?? 0) === 1);
$pageInvalidText = $uc->execute(['expediente_id' => 7, 'page' => 'abc']);
ac_assert('page texto inválido → 1', ($pageInvalidText['data']['page'] ?? 0) === 1);
$pageInvalidDecimal = $uc->execute(['expediente_id' => 7, 'page' => '1.5']);
ac_assert('page decimal inválido → 1', ($pageInvalidDecimal['data']['page'] ?? 0) === 1);
$pageInvalidSign = $uc->execute(['expediente_id' => 7, 'page' => '+2']);
ac_assert('page con signo inválido → 1', ($pageInvalidSign['data']['page'] ?? 0) === 1);
$pageInvalidLeadingZero = $uc->execute(['expediente_id' => 7, 'page' => '01']);
ac_assert('page leading zero inválido → 1', ($pageInvalidLeadingZero['data']['page'] ?? 0) === 1);
$pageInvalidArray = $uc->execute(['expediente_id' => 7, 'page' => ['2']]);
ac_assert('page array inválido → 1', ($pageInvalidArray['data']['page'] ?? 0) === 1);
$pageInvalidObject = $uc->execute(['expediente_id' => 7, 'page' => (object) ['v' => 2]]);
ac_assert('page objeto inválido → 1', ($pageInvalidObject['data']['page'] ?? 0) === 1);
$pageValidString = $uc->execute(['expediente_id' => 7, 'page' => '2']);
ac_assert('page string canónico válido', ($pageValidString['data']['page'] ?? 0) === 2);

$empty = $uc->execute(['expediente_id' => '8', 'page' => 4]);
ac_assert('cero hijos: success', !empty($empty['success']));
ac_assert('cero hijos: records []', ($empty['data']['records'] ?? ['x']) === []);
ac_assert('cero hijos: page 1', ($empty['data']['page'] ?? 0) === 1);
ac_assert('cero hijos: total_pages 0', ($empty['data']['total_pages'] ?? -1) === 0);
ac_assert('cero hijos: nav false', ($empty['data']['has_previous'] ?? true) === false && ($empty['data']['has_next'] ?? true) === false);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
