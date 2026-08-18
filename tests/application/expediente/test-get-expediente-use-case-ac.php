<?php
/**
 * AC — GetExpedienteUseCase (lectura de padre por id estricto).
 *
 * Ejecutar: php tests/application/expediente/test-get-expediente-use-case-ac.php
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

final class ExpedientesRepository {
    /** @var array<int,array<string,mixed>> */
    public static $by_id = [];
    /** @var list<int> */
    public static $lookups = [];

    public static function find_by_id(int $id): ?array {
        self::$lookups[] = $id;
        $row = self::$by_id[$id] ?? null;
        return is_array($row) ? $row : null;
    }
}

require_once $plugin_root . '/includes/application/expediente/GetExpedienteUseCase.php';

$src = file_get_contents($plugin_root . '/includes/application/expediente/GetExpedienteUseCase.php');
ac_assert('no usa absint', strpos($src, 'absint(') === false);
ac_assert('no lee blog_id', strpos($src, "['blog_id']") === false
    && strpos($src, '$_GET') === false
    && strpos($src, '$_POST') === false
    && strpos($src, '$_REQUEST') === false);
ac_assert('no toca clientes/registros/adjuntos', strpos($src, 'aa_clientes') === false
    && strpos($src, 'aa_expediente_registros') === false
    && strpos($src, 'aa_expediente_adjuntos') === false
    && strpos($src, 'ClientsRepository') === false);
ac_assert('solo find_by_id', strpos($src, 'find_by_id') !== false
    && strpos($src, 'insert(') === false
    && strpos($src, 'list_page') === false);

$row = [
    'id' => 7,
    'title' => 'Contrato laboral',
    'description' => 'Detalle',
    'created_at' => '2026-08-17 13:00:00',
    'updated_at' => null,
    'category' => ['slug' => 'general', 'name' => 'General'],
];
ExpedientesRepository::$by_id = [7 => $row];

$uc = new GetExpedienteUseCase();

$ok_string = $uc->execute(['expediente_id' => '7', 'blog_id' => '99']);
ac_assert('id string válido + blog_id ignorado', !empty($ok_string['success'])
    && ($ok_string['data']['id'] ?? 0) === 7
    && ExpedientesRepository::$lookups === [7]);

ExpedientesRepository::$lookups = [];
$ok_int = $uc->execute(['expediente_id' => 7]);
ac_assert('id int válido', !empty($ok_int['success']) && ($ok_int['data']['title'] ?? '') === 'Contrato laboral'
    && ExpedientesRepository::$lookups === [7]);

$missing = $uc->execute(['expediente_id' => '99']);
ac_assert(
    'positivo inexistente → not_found',
    empty($missing['success']) && ($missing['error']['code'] ?? '') === 'not_found'
);

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
];

foreach ($invalids as $label => $input) {
    ExpedientesRepository::$lookups = [];
    $result = $uc->execute($input);
    ac_assert(
        'inválido ' . $label . ' → invalid_id sin lookup',
        empty($result['success'])
        && ($result['error']['code'] ?? '') === 'invalid_id'
        && ExpedientesRepository::$lookups === []
    );
}

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
