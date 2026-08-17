<?php
/**
 * AC — ListExpedientesUseCase (paginación 15, búsqueda por title, clamp).
 *
 * Ejecutar: php tests/application/expediente/test-list-expedientes-use-case-ac.php
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
    /** @var list<array<string,mixed>> */
    public static $rows = [];
    /** @var array<string,mixed>|null */
    public static $last_list_args = null;

    public static function count_matching(string $title_query): int {
        return count(self::matching($title_query));
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function list_page(string $title_query, int $limit, int $offset): array {
        self::$last_list_args = [
            'title_query' => $title_query,
            'limit' => $limit,
            'offset' => $offset,
        ];

        return array_slice(self::matching($title_query), $offset, $limit);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function matching(string $title_query): array {
        if ($title_query === '') {
            return self::$rows;
        }

        $out = [];
        foreach (self::$rows as $row) {
            if (strpos((string) $row['title'], $title_query) !== false) {
                $out[] = $row;
            }
        }

        return $out;
    }
}

require_once $plugin_root . '/includes/application/expediente/ListExpedientesUseCase.php';

$src = file_get_contents($plugin_root . '/includes/application/expediente/ListExpedientesUseCase.php');
ac_assert('PER_PAGE interno 15', ListExpedientesUseCase::PER_PAGE === 15);
ac_assert('no lee per_page de input', strpos($src, "input['per_page']") === false && strpos($src, '$input["per_page"]') === false);
ac_assert('sin list_recent', strpos($src, 'list_recent') === false);
ac_assert('sin LIST_LIMIT ni cap 100', strpos($src, 'LIST_LIMIT') === false && strpos($src, 'min($limit') === false);

$rows = [];
for ($i = 16; $i >= 1; $i--) {
    $title = $i === 3 ? 'Contrato laboral' : ('Expediente ' . $i);
    $rows[] = [
        'id' => $i,
        'title' => $title,
        'description' => null,
        'created_at' => '2026-08-17 12:00:00',
        'updated_at' => null,
        'category' => [
            'slug' => 'general',
            'name' => 'General',
        ],
    ];
}
ExpedientesRepository::$rows = $rows;

$uc = new ListExpedientesUseCase();

$page1 = $uc->execute(['query' => '', 'page' => 1]);
$ids1 = array_column($page1['data']['expedientes'] ?? [], 'id');
ac_assert('página 1 tiene 15', count($ids1) === 15);
ac_assert('página 1 ids 16..2', $ids1 === range(16, 2));
ac_assert('total 16', ($page1['data']['total'] ?? 0) === 16);
ac_assert('total_pages 2', ($page1['data']['total_pages'] ?? 0) === 2);
ac_assert('per_page siempre 15', ($page1['data']['per_page'] ?? 0) === 15);
ac_assert('page 1 nav', ($page1['data']['has_previous'] ?? true) === false && ($page1['data']['has_next'] ?? false) === true);
ac_assert('list_page limit 15 offset 0', (ExpedientesRepository::$last_list_args['limit'] ?? 0) === 15
    && (ExpedientesRepository::$last_list_args['offset'] ?? -1) === 0);

$page2 = $uc->execute(['page' => 2]);
$ids2 = array_column($page2['data']['expedientes'] ?? [], 'id');
ac_assert('página 2 tiene 1', count($ids2) === 1 && $ids2 === [1]);
ac_assert('sin solape 1 y 2', array_intersect($ids1, $ids2) === []);
ac_assert('resto última página', ($page2['data']['page'] ?? 0) === 2 && ($page2['data']['has_next'] ?? true) === false && ($page2['data']['has_previous'] ?? false) === true);
ac_assert('offset página 2 es 15', (ExpedientesRepository::$last_list_args['offset'] ?? -1) === 15);

$spaces = $uc->execute(['query' => '   ', 'page' => 1]);
ac_assert('query espacios = listado completo', ($spaces['data']['total'] ?? 0) === 16);
ac_assert('query espacios llega vacía al repo', (ExpedientesRepository::$last_list_args['title_query'] ?? 'x') === '');

$hit = $uc->execute(['query' => 'Contrato']);
ac_assert('búsqueda por title con hit', ($hit['data']['total'] ?? 0) === 1 && ($hit['data']['expedientes'][0]['title'] ?? '') === 'Contrato laboral');

$miss = $uc->execute(['query' => 'zzzz']);
ac_assert('búsqueda sin hits: página 1', ($miss['data']['page'] ?? 0) === 1);
ac_assert('búsqueda sin hits: total_pages 0', ($miss['data']['total_pages'] ?? -1) === 0);
ac_assert('búsqueda sin hits: nav false', ($miss['data']['has_previous'] ?? true) === false && ($miss['data']['has_next'] ?? true) === false);
ac_assert('búsqueda sin hits: lista vacía', ($miss['data']['expedientes'] ?? ['x']) === []);

ExpedientesRepository::$rows = [];
$empty = $uc->execute(['page' => 4]);
ac_assert('cero resultados página efectiva 1', ($empty['data']['page'] ?? 0) === 1);
ac_assert('cero resultados total_pages 0', ($empty['data']['total_pages'] ?? -1) === 0);
ac_assert('cero resultados nav false', ($empty['data']['has_previous'] ?? true) === false && ($empty['data']['has_next'] ?? true) === false);

ExpedientesRepository::$rows = $rows;
$clamped_low = $uc->execute(['page' => 0]);
ac_assert('página 0 → 1', ($clamped_low['data']['page'] ?? 0) === 1 && array_column($clamped_low['data']['expedientes'], 'id') === range(16, 2));

$clamped_high = $uc->execute(['page' => 99]);
ac_assert('página 99 → última', ($clamped_high['data']['page'] ?? 0) === 2 && array_column($clamped_high['data']['expedientes'], 'id') === [1]);
ac_assert('página 99 offset última', (ExpedientesRepository::$last_list_args['offset'] ?? -1) === 15);

$ignored = $uc->execute(['page' => 1, 'per_page' => 100, 'query' => '']);
ac_assert('per_page externo ignorado', ($ignored['data']['per_page'] ?? 0) === 15 && count($ignored['data']['expedientes'] ?? []) === 15);
ac_assert('repo no recibe per_page 100', (ExpedientesRepository::$last_list_args['limit'] ?? 0) === 15);

echo "\nResultado: {$passed}/{$total} OK\n";
if ($failed) {
    echo 'Fallidos: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
