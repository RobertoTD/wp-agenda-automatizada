<?php
/**
 * AC — CreateExpedienteUseCase.
 *
 * Ejecutar: php tests/application/expediente/test-create-expediente-use-case-ac.php
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

if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        return '2026-08-17 13:00:00';
    }
}

final class ExpedienteCategoriesRepository {
    /** @var array<string,array<string,mixed>> */
    public static $by_slug = [];
    /** @var list<string> */
    public static $lookups = [];

    public static function find_by_slug(string $slug): ?array {
        self::$lookups[] = $slug;
        $row = self::$by_slug[$slug] ?? null;
        return is_array($row) ? $row : null;
    }
}

final class ExpedientesRepository {
    /** @var list<array<string,mixed>> */
    public static $inserted = [];
    public static $next_id = 7;
    public static $fail = false;

    public static function insert(array $data): ?array {
        self::$inserted[] = $data;
        if (self::$fail) {
            return null;
        }
        return [
            'id' => self::$next_id,
            'title' => (string) $data['title'],
            'description' => $data['description'] ?? null,
            'created_at' => (string) $data['created_at'],
            'updated_at' => null,
        ];
    }
}

require_once $plugin_root . '/includes/application/expediente/CreateExpedienteUseCase.php';

$src = file_get_contents($plugin_root . '/includes/application/expediente/CreateExpedienteUseCase.php');
ac_assert('sin client_id en payload', strpos($src, "'client_id'") === false && strpos($src, '"client_id"') === false);
ac_assert('sin tablas legacy', strpos($src, 'aa_expediente_registros') === false
    && strpos($src, 'aa_expediente_adjuntos') === false);

ExpedienteCategoriesRepository::$by_slug = [
    'general' => [
        'id' => 3,
        'slug' => 'general',
        'name' => 'General',
        'created_at' => '2026-08-01 00:00:00',
    ],
    'laboral' => [
        'id' => 4,
        'slug' => 'laboral',
        'name' => 'Laboral',
        'created_at' => '2026-08-01 00:00:00',
    ],
];

$uc = new CreateExpedienteUseCase();

$ok = $uc->execute([
    'title' => '  Contrato  ',
    'description' => '  ',
]);
ac_assert('crea con título recortado', ($ok['success'] ?? false) === true && ($ok['data']['title'] ?? '') === 'Contrato');
ac_assert('description vacía → null', array_key_exists('description', $ok['data'] ?? []) && $ok['data']['description'] === null);
ac_assert('categoría default general', ($ok['data']['category']['slug'] ?? '') === 'general');
ac_assert('lookup general', end(ExpedienteCategoriesRepository::$lookups) === 'general');
ac_assert('insert category_id de general', (int) (ExpedientesRepository::$inserted[0]['category_id'] ?? 0) === 3);
ac_assert('created_at de current_time', (ExpedientesRepository::$inserted[0]['created_at'] ?? '') === '2026-08-17 13:00:00');
ac_assert('payload público con created_at', ($ok['data']['created_at'] ?? '') === '2026-08-17 13:00:00');
ac_assert('insert sin client_id', !array_key_exists('client_id', ExpedientesRepository::$inserted[0]));

$missing = $uc->execute(['title' => '   ']);
ac_assert('título en blanco rechazado', ($missing['success'] ?? true) === false && ($missing['error']['code'] ?? '') === 'missing_title');
ac_assert('título en blanco no inserta', count(ExpedientesRepository::$inserted) === 1);

$unknown = $uc->execute([
    'title' => 'X',
    'category_slug' => 'no-existe',
]);
ac_assert('slug inexistente no inserta', ($unknown['success'] ?? true) === false && ($unknown['error']['code'] ?? '') === 'category_not_found');
ac_assert('slug inexistente no deja huérfano', count(ExpedientesRepository::$inserted) === 1);

$laboral = $uc->execute([
    'title' => 'Demanda',
    'description' => 'Detalle',
    'category_slug' => ' laboral ',
]);
ac_assert('slug explícito laboral', ($laboral['data']['category']['slug'] ?? '') === 'laboral');
ac_assert('description conservada', ($laboral['data']['description'] ?? null) === 'Detalle');

$long = $uc->execute(['title' => str_repeat('a', 201)]);
ac_assert('título largo rechazado', ($long['success'] ?? true) === false && ($long['error']['code'] ?? '') === 'title_too_long');

ExpedientesRepository::$fail = true;
$persist = $uc->execute(['title' => 'Falla']);
ac_assert('persistencia fallida', ($persist['success'] ?? true) === false && ($persist['error']['code'] ?? '') === 'persistence_failed');

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
