<?php
/**
 * AC Test — FinanceContainerRepository (Ciclo 3A2).
 *
 * Ejecutar:
 *   php tests/repositories/test-finance-container-repository-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$repo_file = $plugin_root . '/includes/repositories/FinanceContainerRepository.php';

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

echo "=== 1. Análisis estático de FinanceContainerRepository ===\n";

ac_assert('Archivo FinanceContainerRepository.php existe y es legible', is_readable($repo_file));
$repo_src = file_get_contents($repo_file);
ac_assert('Contenido legible', is_string($repo_src) && $repo_src !== '');

ac_assert('Define constante PAGE_SIZE = 15', strpos($repo_src, 'public const PAGE_SIZE = 15;') !== false);
ac_assert('No contiene DTOs ni interfaces', strpos($repo_src, 'interface ') === false && strpos($repo_src, 'abstract class ') === false);
ac_assert('No contiene float casts ni %f', strpos($repo_src, '(float)') === false && strpos($repo_src, 'floatval(') === false && strpos($repo_src, '%f') === false);

echo "\n=== 2. Pruebas con doble de \$wpdb ===\n";

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!function_exists('current_time')) {
    function current_time(string $type = 'mysql'): string {
        return '2026-08-29 18:00:00';
    }
}

class TestFinanceContainerWpdbMock {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    public $last_query = '';
    public $inserts = [];
    public $rows = [];
    public $results = [];
    public $vars = [];
    public $deleted_rows = 1;
    public $query_log = [];
    public $update_result = 1;
    public $updated = null;

    public function prepare(string $query, ...$args): string {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        $this->last_query = $query;
        return $query;
    }

    public function insert(string $table, array $data, array $format = []) {
        $this->inserts[] = ['table' => $table, 'data' => $data, 'format' => $format];
        if ($this->last_error !== '') {
            return false;
        }
        $this->insert_id = 42;
        return 1;
    }

    public function get_row(string $query, $output = ARRAY_A) {
        $this->query_log[] = $query;
        if ($this->last_error !== '') {
            return null;
        }
        return array_shift($this->rows) ?: null;
    }

    public function get_results(string $query, $output = ARRAY_A) {
        $this->query_log[] = $query;
        if ($this->last_error !== '') {
            return [];
        }
        return array_shift($this->results) ?: [];
    }

    public function get_var(string $query) {
        $this->query_log[] = $query;
        if ($this->last_error !== '') {
            return null;
        }
        return array_shift($this->vars) ?? 0;
    }

    public function delete(string $table, array $where, array $where_format = []) {
        $this->query_log[] = ['delete' => $table, 'where' => $where];
        if ($this->last_error !== '') {
            return false;
        }
        return $this->deleted_rows;
    }

    public function update($table, array $data, array $where, $format = null, $where_format = null) {
        $this->updated = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'format' => $format,
            'where_format' => $where_format,
        ];
        $this->query_log[] = ['update' => $table, 'where' => $where];
        if ($this->last_error !== '') {
            return false;
        }
        return $this->update_result;
    }
}

global $wpdb;
$wpdb = new TestFinanceContainerWpdbMock();

require_once $repo_file;

// 2.1 create()
$created = FinanceContainerRepository::create('general', 'Presupuesto Oficina', 'Detalles varios');
ac_assert('create() devuelve array con id poblado', is_array($created) && $created['id'] === 42);
ac_assert('create() conserva variant_key y title', $created['variant_key'] === 'general' && $created['title'] === 'Presupuesto Oficina');
ac_assert('create() conserva details y created_at', $created['details'] === 'Detalles varios' && $created['created_at'] === '2026-08-29 18:00:00');

// Precondiciones de create()
$caught_empty_variant = false;
try {
    FinanceContainerRepository::create('', 'Presupuesto');
} catch (\InvalidArgumentException $e) {
    $caught_empty_variant = true;
}
ac_assert('create() con variant_key vacía lanza InvalidArgumentException', $caught_empty_variant);

$caught_empty_title = false;
try {
    FinanceContainerRepository::create('general', '');
} catch (\InvalidArgumentException $e) {
    $caught_empty_title = true;
}
ac_assert('create() con title vacío lanza InvalidArgumentException', $caught_empty_title);

// Error SQL en create()
$wpdb->last_error = 'Table does not exist';
$caught_create_err = false;
try {
    FinanceContainerRepository::create('general', 'Test');
} catch (\RuntimeException $e) {
    $caught_create_err = (strpos($e->getMessage(), 'Error al crear el contenedor financiero') !== false);
}
ac_assert('create() ante error SQL lanza RuntimeException', $caught_create_err);
$wpdb->last_error = '';

// 2.2 find_by_id()
$wpdb->rows[] = [
    'id' => '42',
    'variant_key' => 'general',
    'title' => 'Contenedor 42',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$found = FinanceContainerRepository::find_by_id(42);
ac_assert('find_by_id(42) devuelve contenedor', is_array($found) && $found['id'] === 42 && $found['title'] === 'Contenedor 42');
ac_assert('find_by_id() hidrata details null', $found['details'] === null);

// Inexistente devuelve null
$wpdb->rows = [];
$not_found = FinanceContainerRepository::find_by_id(999);
ac_assert('find_by_id() para inexistente devuelve null sin lanzar excepción', $not_found === null);

// Precondición de ID < 1
$caught_invalid_id = false;
try {
    FinanceContainerRepository::find_by_id(0);
} catch (\InvalidArgumentException $e) {
    $caught_invalid_id = true;
}
ac_assert('find_by_id(0) lanza InvalidArgumentException', $caught_invalid_id);

// 2.3 list_by_variant()
$wpdb->results[] = [
    ['id' => '2', 'variant_key' => 'general', 'title' => 'C2', 'details' => 'D2', 'created_at' => '2026-08-29 10:00:00'],
    ['id' => '1', 'variant_key' => 'general', 'title' => 'C1', 'details' => null, 'created_at' => '2026-08-29 09:00:00'],
];
$list = FinanceContainerRepository::list_by_variant('general', 1);
ac_assert('list_by_variant() devuelve lista de arrays', count($list) === 2 && $list[0]['id'] === 2 && $list[1]['id'] === 1);
ac_assert('list_by_variant() usa query con LIMIT 15 OFFSET 0', strpos($wpdb->last_query, 'LIMIT 15 OFFSET 0') !== false);

// Paginación página 2
$wpdb->results[] = [];
$list_p2 = FinanceContainerRepository::list_by_variant('general', 2);
ac_assert('list_by_variant(general, 2) usa LIMIT 15 OFFSET 15', strpos($wpdb->last_query, 'LIMIT 15 OFFSET 15') !== false);
ac_assert('list_by_variant() página vacía devuelve []', $list_p2 === []);

// Precondición página < 1
$caught_invalid_page = false;
try {
    FinanceContainerRepository::list_by_variant('general', 0);
} catch (\InvalidArgumentException $e) {
    $caught_invalid_page = true;
}
ac_assert('list_by_variant() con page=0 lanza InvalidArgumentException', $caught_invalid_page);

// 2.4 count_by_variant()
$wpdb->vars[] = '5';
$count = FinanceContainerRepository::count_by_variant('general');
ac_assert('count_by_variant() devuelve int 5', $count === 5);

// 2.5 delete()
$wpdb->deleted_rows = 1;
$del_ok = FinanceContainerRepository::delete(42);
ac_assert('delete(42) con 1 fila eliminada devuelve true', $del_ok === true);

$wpdb->deleted_rows = 0;
$del_zero = FinanceContainerRepository::delete(999);
ac_assert('delete(999) con 0 filas eliminadas devuelve false', $del_zero === false);

$caught_del_invalid_id = false;
try {
    FinanceContainerRepository::delete(-1);
} catch (\InvalidArgumentException $e) {
    $caught_del_invalid_id = true;
}
ac_assert('delete(-1) lanza InvalidArgumentException', $caught_del_invalid_id);

echo "\n=== 2.6 update() ===\n";

// 2.6.1 Precondiciones locales
$caught_update_invalid_id = false;
try {
    FinanceContainerRepository::update(0, 'general', 'Título', null);
} catch (\InvalidArgumentException $e) {
    $caught_update_invalid_id = true;
}
ac_assert('update() con id < 1 lanza InvalidArgumentException', $caught_update_invalid_id);
ac_assert('update() con id inválido no ejecutó UPDATE', $wpdb->updated === null);

$caught_update_empty_variant = false;
try {
    FinanceContainerRepository::update(1, '', 'Título', null);
} catch (\InvalidArgumentException $e) {
    $caught_update_empty_variant = true;
}
ac_assert('update() con variant_key vacía lanza InvalidArgumentException', $caught_update_empty_variant);

$caught_update_empty_title = false;
try {
    FinanceContainerRepository::update(1, 'general', '', null);
} catch (\InvalidArgumentException $e) {
    $caught_update_empty_title = true;
}
ac_assert('update() con title vacío lanza InvalidArgumentException', $caught_update_empty_title);

// 2.6.2 Payload SET/WHERE/formatos y retorno 1 + relectura
$wpdb->updated = null;
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '42',
    'variant_key' => 'general',
    'title' => 'Nuevo título',
    'details' => 'Nuevos detalles',
    'created_at' => '2026-08-29 12:00:00',
];
$updated_ok = FinanceContainerRepository::update(42, 'general', 'Nuevo título', 'Nuevos detalles');
ac_assert('update() con retorno 1 devuelve fila autoritativa', is_array($updated_ok) && $updated_ok['id'] === 42 && $updated_ok['title'] === 'Nuevo título');
ac_assert('update() SET exacto title/details', ($wpdb->updated['data']['title'] ?? '') === 'Nuevo título' && ($wpdb->updated['data']['details'] ?? '') === 'Nuevos detalles');
ac_assert('update() WHERE exacto id/variant_key', ($wpdb->updated['where']['id'] ?? null) === 42 && ($wpdb->updated['where']['variant_key'] ?? '') === 'general');
ac_assert('update() formatos title/details string', ($wpdb->updated['format'][0] ?? '') === '%s' && ($wpdb->updated['format'][1] ?? '') === '%s');

$wpdb->updated = null;
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '43',
    'variant_key' => 'general',
    'title' => 'Solo título',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
FinanceContainerRepository::update(43, 'general', 'Solo título', null);
ac_assert('update() details null usa formato NULL', $wpdb->updated['format'][1] === null);

// 2.6.3 false → RuntimeException
$wpdb->last_error = 'Update failed';
$caught_update_sql = false;
try {
    FinanceContainerRepository::update(44, 'general', 'Título', null);
} catch (\RuntimeException $e) {
    $caught_update_sql = (strpos($e->getMessage(), 'Error al actualizar el contenedor financiero') !== false);
}
ac_assert('update() ante error SQL lanza RuntimeException', $caught_update_sql);
$wpdb->last_error = '';

// 2.6.4 Tipo inesperado
$wpdb->update_result = 'oops';
$caught_update_unexpected = false;
try {
    FinanceContainerRepository::update(45, 'general', 'Título', null);
} catch (\RuntimeException $e) {
    $caught_update_unexpected = true;
}
ac_assert('update() con tipo inesperado lanza RuntimeException', $caught_update_unexpected);

// 2.6.5 Retorno > 1
$wpdb->update_result = 2;
$caught_update_multi = false;
try {
    FinanceContainerRepository::update(46, 'general', 'Título', null);
} catch (\RuntimeException $e) {
    $caught_update_multi = (strpos($e->getMessage(), 'más de una fila') !== false);
}
ac_assert('update() con retorno > 1 lanza RuntimeException', $caught_update_multi);

// 2.6.6 Retorno 0 idempotente
$wpdb->update_result = 0;
$wpdb->rows[] = [
    'id' => '47',
    'variant_key' => 'general',
    'title' => 'Igual',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$updated_idempotent = FinanceContainerRepository::update(47, 'general', 'Igual', null);
ac_assert('update() retorno 0 con valores iguales devuelve fila idempotente', is_array($updated_idempotent) && $updated_idempotent['title'] === 'Igual');

// 2.6.7 Retorno 0 fila ausente → null
$wpdb->update_result = 0;
$wpdb->rows = [];
$updated_gone = FinanceContainerRepository::update(48, 'general', 'Título', null);
ac_assert('update() retorno 0 con fila ausente devuelve null', $updated_gone === null);

// 2.6.8 Retorno 0 variante discordante → null
$wpdb->update_result = 0;
$wpdb->rows[] = [
    'id' => '49',
    'variant_key' => 'special',
    'title' => 'Otra variante',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$updated_wrong_variant = FinanceContainerRepository::update(49, 'general', 'Otra variante', null);
ac_assert('update() retorno 0 con variante discordante devuelve null', $updated_wrong_variant === null);

// 2.6.9 Retorno 0 valores distintos → RuntimeException
$wpdb->update_result = 0;
$wpdb->rows[] = [
    'id' => '50',
    'variant_key' => 'general',
    'title' => 'Persistido',
    'details' => 'DB',
    'created_at' => '2026-08-29 12:00:00',
];
$caught_update_mismatch = false;
try {
    FinanceContainerRepository::update(50, 'general', 'Intento', 'Distinto');
} catch (\RuntimeException $e) {
    $caught_update_mismatch = (strpos($e->getMessage(), 'sin efecto con valores distintos') !== false);
}
ac_assert('update() retorno 0 con valores distintos lanza RuntimeException', $caught_update_mismatch);

// 2.6.10 Retorno 1 relectura ausente → null
$wpdb->update_result = 1;
$wpdb->rows = [];
$updated_gone_after_one = FinanceContainerRepository::update(51, 'general', 'Ausente', null);
ac_assert('update() retorno 1 con relectura ausente devuelve null', $updated_gone_after_one === null);

// 2.6.11 Retorno 1 identidad discordante → RuntimeException
$wpdb->update_result = 1;
$wpdb->rows[] = [
    'id' => '53',
    'variant_key' => 'general',
    'title' => 'Discordante',
    'details' => null,
    'created_at' => '2026-08-29 12:00:00',
];
$caught_update_identity = false;
try {
    FinanceContainerRepository::update(52, 'general', 'Discordante', null);
} catch (\RuntimeException $e) {
    $caught_update_identity = (strpos($e->getMessage(), 'Identidad discordante') !== false);
}
ac_assert('update() retorno 1 con id discordante lanza RuntimeException', $caught_update_identity);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
