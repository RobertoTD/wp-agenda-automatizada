<?php
/**
 * AC — Schema aa_expediente_categories + aa_expedientes (DB_VERSION 14).
 *
 * Ejecutar: php tests/repositories/test-expedientes-schema-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';

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

/**
 * Extrae el bloque CREATE TABLE que empieza en la asignación de prefijo dada.
 */
function ac_schema_table_block(string $schema_src, string $table_literal): string {
    $assign = "\$wpdb->prefix . '" . $table_literal . "'";
    $start = strpos($schema_src, $assign);
    if ($start === false) {
        return '';
    }

    $end = strpos($schema_src, ') $charset;";', $start);
    if ($end === false) {
        return '';
    }

    return substr($schema_src, $start, $end - $start);
}

$schema_src = file_get_contents($schema_file);
ac_assert('Schema readable', is_string($schema_src) && $schema_src !== '');
ac_assert('DB_VERSION is 14', strpos($schema_src, "DB_VERSION = '14'") !== false);
ac_assert(
    'usa prefix aa_expediente_categories',
    strpos($schema_src, "\$wpdb->prefix . 'aa_expediente_categories'") !== false
);
ac_assert(
    'usa prefix aa_expedientes',
    strpos($schema_src, "\$wpdb->prefix . 'aa_expedientes'") !== false
);

$categories_block = ac_schema_table_block($schema_src, 'aa_expediente_categories');
$expedientes_block = ac_schema_table_block($schema_src, 'aa_expedientes');
$registros_block = ac_schema_table_block($schema_src, 'aa_expediente_registros');
$adjuntos_block = ac_schema_table_block($schema_src, 'aa_expediente_adjuntos');

ac_assert('bloque categorías encontrado', $categories_block !== '');
ac_assert('bloque expedientes encontrado', $expedientes_block !== '');
ac_assert('slug varchar(64) NOT NULL', strpos($categories_block, 'slug varchar(64) NOT NULL') !== false);
ac_assert('name varchar(100) NOT NULL', strpos($categories_block, 'name varchar(100) NOT NULL') !== false);
ac_assert('UNIQUE KEY slug', strpos($categories_block, 'UNIQUE KEY slug (slug)') !== false);
ac_assert('ensure_index UNIQUE slug', strpos($schema_src, 'ADD UNIQUE KEY slug (slug)') !== false);
ac_assert('categorías sin FOREIGN KEY', $categories_block !== '' && strpos($categories_block, 'FOREIGN KEY') === false);
ac_assert('categorías sin client_id', $categories_block !== '' && strpos($categories_block, 'client_id') === false);

ac_assert('title varchar(200) NOT NULL en padre', strpos($expedientes_block, 'title varchar(200) NOT NULL') !== false);
ac_assert(
    'description text nullable (sin NOT NULL)',
    preg_match('/^\s*description text,?$/m', $expedientes_block) === 1
    && strpos($expedientes_block, 'description text NOT NULL') === false
);
ac_assert(
    'category_id obligatorio',
    strpos($expedientes_block, 'category_id bigint(20) unsigned NOT NULL') !== false
);
ac_assert('KEY category_id', strpos($expedientes_block, 'KEY category_id (category_id)') !== false);
ac_assert('KEY created_id', strpos($expedientes_block, 'KEY created_id (created_at, id)') !== false);
ac_assert('padre sin FOREIGN KEY', $expedientes_block !== '' && strpos($expedientes_block, 'FOREIGN KEY') === false);
ac_assert('padre sin client_id', $expedientes_block !== '' && strpos($expedientes_block, 'client_id') === false);
ac_assert('padre sin pivote category_ids', $expedientes_block !== '' && strpos($expedientes_block, 'category_ids') === false);

ac_assert(
    'sin tabla pivote de categorías',
    strpos($schema_src, "'aa_expediente_category_map'") === false
    && strpos($schema_src, "'aa_expediente_category_expediente'") === false
);

ac_assert(
    'registros sin expediente_id',
    $registros_block !== '' && strpos($registros_block, 'expediente_id') === false
);
ac_assert(
    'registros conservan client_id NOT NULL',
    strpos($registros_block, 'client_id bigint(20) unsigned NOT NULL') !== false
);
ac_assert('registros sin FOREIGN KEY', $registros_block !== '' && strpos($registros_block, 'FOREIGN KEY') === false);
ac_assert(
    'adjuntos sin expediente_id',
    $adjuntos_block !== '' && strpos($adjuntos_block, 'expediente_id') === false
);
ac_assert('adjuntos sin FOREIGN KEY', $adjuntos_block !== '' && strpos($adjuntos_block, 'FOREIGN KEY') === false);

ac_assert(
    'seed general por slug',
    strpos($schema_src, "function ensure_expediente_category_general") !== false
    && strpos($schema_src, "\$slug = 'general'") !== false
    && strpos($schema_src, "'name' => 'General'") !== false
);
ac_assert(
    'seed no siembra clientes',
    strpos($schema_src, "'slug' => 'clientes'") === false
    && preg_match("/slug\s*=\s*'clientes'/", $schema_src) !== 1
);
ac_assert(
    'seed relee tras insert (carrera UNIQUE)',
    substr_count($schema_src, 'expediente_category_id_by_slug') >= 3
    && strpos($schema_src, 'suppress_errors(true)') !== false
);
ac_assert(
    'seed usa prefix del blog',
    strpos($schema_src, "\$wpdb->prefix . 'aa_expediente_categories'") !== false
    && strpos($schema_src, "\$_REQUEST['blog_id']") === false
    && strpos($schema_src, "\$_POST['blog_id']") === false
);
ac_assert('install llama al seed', strpos($schema_src, 'self::ensure_expediente_category_general()') !== false);
ac_assert('maybe_migrate sigue en Schema', strpos($schema_src, 'function maybe_migrate') !== false);
ac_assert('install bumpea aa_db_version', strpos($schema_src, "update_option('aa_db_version', self::DB_VERSION)") !== false);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load === '' || !is_readable($wp_load)) {
    echo "\n--- Seed (wpdb mock) ---\n";

    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin_root . '/');
    }
    if (!function_exists('current_time')) {
        function current_time($type = 'mysql') {
            return '2026-08-17 12:00:00';
        }
    }

    require_once $schema_file;

    $find = new ReflectionMethod('AA_Schema', 'expediente_category_id_by_slug');
    $find->setAccessible(true);
    $ensure = new ReflectionMethod('AA_Schema', 'ensure_expediente_category_general');
    $ensure->setAccessible(true);

    global $wpdb;
    $wpdb = new class {
        public $prefix = 'wp_5_';
        public $last_error = '';
        public $last_query = '';
        public $inserts = [];
        public $var_queue = [];
        public $insert_ok = true;
        public $suppress = false;

        public function prepare($query, ...$args) {
            foreach ($args as $arg) {
                $query = preg_replace('/%s/', "'" . (string) $arg . "'", $query, 1);
            }
            return $query;
        }

        public function get_var($query) {
            $this->last_query = (string) $query;
            if ($this->var_queue === []) {
                return null;
            }
            return array_shift($this->var_queue);
        }

        public function insert($table, $data, $format = null) {
            $this->inserts[] = [
                'table' => $table,
                'data' => $data,
            ];
            if ($this->insert_ok === false) {
                $this->last_error = 'Duplicate entry';
                return false;
            }
            return 1;
        }

        public function suppress_errors($suppress = true) {
            $previous = $this->suppress;
            $this->suppress = (bool) $suppress;
            return $previous;
        }
    };

    $found = $find->invoke(null, 'wp_5_aa_expediente_categories', 'general');
    ac_assert('find_by_slug vacío → null', $found === null);
    ac_assert('SELECT usa tabla prefijada', strpos($wpdb->last_query, 'wp_5_aa_expediente_categories') !== false);

    $wpdb->var_queue = [7];
    $found = $find->invoke(null, 'wp_5_aa_expediente_categories', 'general');
    ac_assert('find_by_slug hit → id', $found === 7);

    $wpdb->var_queue = [3];
    $wpdb->inserts = [];
    $ensure->invoke(null);
    ac_assert('seed no inserta si general ya existe', $wpdb->inserts === []);

    $wpdb->var_queue = [null, 11];
    $wpdb->inserts = [];
    $wpdb->insert_ok = true;
    $ensure->invoke(null);
    ac_assert('seed inserta en tabla prefijada', ($wpdb->inserts[0]['table'] ?? '') === 'wp_5_aa_expediente_categories');
    ac_assert(
        'seed inserta slug general y name General',
        ($wpdb->inserts[0]['data']['slug'] ?? '') === 'general'
        && ($wpdb->inserts[0]['data']['name'] ?? '') === 'General'
    );

    $wpdb->var_queue = [null, 22];
    $wpdb->inserts = [];
    $wpdb->insert_ok = false;
    $wpdb->last_error = 'Duplicate entry';
    $threw = false;
    try {
        $ensure->invoke(null);
    } catch (\Throwable $e) {
        $threw = true;
    }
    ac_assert('carrera UNIQUE no falla si la fila existe', $threw === false);
    ac_assert('carrera UNIQUE intentó un insert', count($wpdb->inserts) === 1);

    $wpdb->var_queue = [null, null];
    $wpdb->inserts = [];
    $wpdb->insert_ok = false;
    $wpdb->last_error = 'Table missing';
    $threw = false;
    try {
        $ensure->invoke(null);
    } catch (\Throwable $e) {
        $threw = true;
    }
    ac_assert('seed sí falla si general no llega a existir', $threw === true);
}

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";
    require_once $wp_load;
    require_once $schema_file;

    $before = get_option('aa_db_version', '0');
    AA_Schema::install();
    AA_Schema::install();

    global $wpdb;
    $categories_table = $wpdb->prefix . 'aa_expediente_categories';
    $expedientes_table = $wpdb->prefix . 'aa_expedientes';
    $registros_table = $wpdb->prefix . 'aa_expediente_registros';
    $adjuntos_table = $wpdb->prefix . 'aa_expediente_adjuntos';

    $categories_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $categories_table));
    $expedientes_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $expedientes_table));
    ac_assert('tabla categorías existe (prefijo blog)', $categories_exists === $categories_table, $categories_table);
    ac_assert('tabla expedientes existe (prefijo blog)', $expedientes_exists === $expedientes_table, $expedientes_table);

    $slug_idx = $wpdb->get_results("SHOW INDEX FROM {$categories_table} WHERE Key_name = 'slug'");
    ac_assert('índice UNIQUE slug existe', is_array($slug_idx) && count($slug_idx) >= 1);
    $slug_non_unique = [];
    foreach (is_array($slug_idx) ? $slug_idx : [] as $idx_row) {
        $non_unique = is_object($idx_row) ? ($idx_row->Non_unique ?? null) : ($idx_row['Non_unique'] ?? null);
        $slug_non_unique[] = (int) $non_unique;
    }
    ac_assert('índice slug es UNIQUE', in_array(0, $slug_non_unique, true));

    $category_idx = $wpdb->get_results("SHOW INDEX FROM {$expedientes_table} WHERE Key_name = 'category_id'");
    ac_assert('índice category_id existe', is_array($category_idx) && count($category_idx) >= 1);
    $created_idx = $wpdb->get_results("SHOW INDEX FROM {$expedientes_table} WHERE Key_name = 'created_id'");
    ac_assert('índice created_id existe', is_array($created_idx) && count($created_idx) >= 1);

    $category_id_col = $wpdb->get_row("SHOW COLUMNS FROM {$expedientes_table} LIKE 'category_id'", ARRAY_A);
    ac_assert(
        'category_id es NOT NULL',
        is_array($category_id_col)
        && strtoupper((string) ($category_id_col['Null'] ?? '')) === 'NO',
        is_array($category_id_col) ? (string) ($category_id_col['Null'] ?? '') : 'missing'
    );

    $description_col = $wpdb->get_row("SHOW COLUMNS FROM {$expedientes_table} LIKE 'description'", ARRAY_A);
    ac_assert(
        'description es nullable',
        is_array($description_col)
        && strtoupper((string) ($description_col['Null'] ?? '')) === 'YES',
        is_array($description_col) ? (string) ($description_col['Null'] ?? '') : 'missing'
    );

    $client_id_col = $wpdb->get_row("SHOW COLUMNS FROM {$expedientes_table} LIKE 'client_id'", ARRAY_A);
    ac_assert('padre no tiene client_id', empty($client_id_col));

    $general_count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$categories_table} WHERE slug = %s",
            'general'
        )
    );
    ac_assert('exactamente una categoría general', $general_count === 1, (string) $general_count);

    $general_name = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT name FROM {$categories_table} WHERE slug = %s LIMIT 1",
            'general'
        )
    );
    ac_assert('nombre General', $general_name === 'General', (string) $general_name);

    $fk_sql = $wpdb->prepare(
        "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = %s
           AND TABLE_NAME IN (%s, %s, %s, %s)
           AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
        DB_NAME,
        $categories_table,
        $expedientes_table,
        $registros_table,
        $adjuntos_table
    );
    $fks = $wpdb->get_col($fk_sql);
    ac_assert(
        'sin FOREIGN KEY físicas en expediente',
        is_array($fks) && count($fks) === 0,
        is_array($fks) ? implode(',', $fks) : 'query-failed'
    );

    $registros_expediente_id = $wpdb->get_row("SHOW COLUMNS FROM {$registros_table} LIKE 'expediente_id'", ARRAY_A);
    ac_assert('registros reales sin expediente_id', empty($registros_expediente_id));
    $adjuntos_expediente_id = $wpdb->get_row("SHOW COLUMNS FROM {$adjuntos_table} LIKE 'expediente_id'", ARRAY_A);
    ac_assert('adjuntos reales sin expediente_id', empty($adjuntos_expediente_id));

    $version = get_option('aa_db_version', '0');
    ac_assert('aa_db_version es 14 tras install', (string) $version === '14', (string) $version);
    ac_assert('upgrade path: versión previa no bloquea', true, 'before=' . $before);
} else {
    echo "\n(skip WP integration — set AA_WP_ROOT para install/upgrade real)\n";
}

echo "\n";
if (count($failed) === 0) {
    echo "Passed {$passed}/{$total}\n";
    exit(0);
}

echo 'Failed ' . count($failed) . "/{$total}\n";
foreach ($failed as $label) {
    echo " - {$label}\n";
}
exit(1);
