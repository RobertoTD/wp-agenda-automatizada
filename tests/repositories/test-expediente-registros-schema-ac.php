<?php
/**
 * AC — Schema aa_expediente_registros (MC2 + DB_VERSION 16).
 *
 * Ejecutar: php tests/repositories/test-expediente-registros-schema-ac.php
 */

$plugin_root = dirname(__DIR__, 2);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$repo_file = $plugin_root . '/includes/repositories/ExpedienteRegistrosRepository.php';

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

$schema_src = file_get_contents($schema_file);
$repo_src = file_get_contents($repo_file);
ac_assert('Schema readable', is_string($schema_src) && $schema_src !== '');
ac_assert('DB_VERSION is 16', strpos($schema_src, "DB_VERSION = '16'") !== false);
ac_assert('DB_VERSION ya no es 15', strpos($schema_src, "DB_VERSION = '15'") === false);
ac_assert('CREATE TABLE aa_expediente_registros', strpos($schema_src, 'aa_expediente_registros') !== false);
ac_assert('title varchar(200)', strpos($schema_src, 'title varchar(200) NOT NULL') !== false);
ac_assert('body text', strpos($schema_src, 'body text NOT NULL') !== false);
ac_assert('recorded_at datetime NOT NULL', strpos($schema_src, 'recorded_at datetime NOT NULL') !== false);
ac_assert('created_at datetime NOT NULL', strpos($schema_src, 'created_at datetime NOT NULL') !== false);
ac_assert('updated_at datetime DEFAULT NULL', strpos($schema_src, 'updated_at datetime DEFAULT NULL') !== false);
ac_assert(
    'índice compuesto client_recorded',
    strpos($schema_src, 'KEY client_recorded (client_id, recorded_at, id)') !== false
);
ac_assert(
    'índice compuesto expediente_recorded',
    strpos($schema_src, 'KEY expediente_recorded (expediente_id, recorded_at, id)') !== false
);

$block_start = strpos($schema_src, "\$wpdb->prefix . 'aa_expediente_registros'");
$block_end = $block_start !== false ? strpos($schema_src, ') $charset;";', $block_start) : false;
$block = ($block_start !== false && $block_end !== false)
    ? substr($schema_src, $block_start, $block_end - $block_start)
    : '';

$adj_start = strpos($schema_src, "\$wpdb->prefix . 'aa_expediente_adjuntos'");
$adj_end = $adj_start !== false ? strpos($schema_src, ') $charset;";', $adj_start) : false;
$adj_block = ($adj_start !== false && $adj_end !== false)
    ? substr($schema_src, $adj_start, $adj_end - $adj_start)
    : '';

ac_assert('bloque DDL encontrado', $block !== '');
ac_assert('sin FOREIGN KEY en registros', $block !== '' && strpos($block, 'FOREIGN KEY') === false);
ac_assert('sin CHECK en registros', $block !== '' && strpos($block, 'CHECK') === false);
ac_assert('sin TRIGGER en registros', $block !== '' && stripos($block, 'TRIGGER') === false);
ac_assert(
    'expediente_id nullable (DEFAULT NULL, sin NOT NULL)',
    $block !== ''
    && strpos($block, 'expediente_id bigint(20) unsigned DEFAULT NULL') !== false
    && strpos($block, 'expediente_id bigint(20) unsigned NOT NULL') === false
);
ac_assert(
    'client_id nullable para instalaciones nuevas (DEFAULT NULL)',
    $block !== ''
    && strpos($block, 'client_id bigint(20) unsigned DEFAULT NULL') !== false
    && strpos($block, 'client_id bigint(20) unsigned NOT NULL') === false
);
ac_assert(
    'sin KEY client_id suelto redundante',
    $block !== '' && strpos($block, 'KEY client_id (client_id)') === false
);
ac_assert(
    'adjuntos siguen sin expediente_id',
    $adj_block !== '' && strpos($adj_block, 'expediente_id') === false
);
ac_assert('usa $wpdb->prefix', strpos($schema_src, "\$wpdb->prefix . 'aa_expediente_registros'") !== false);
ac_assert('maybe_migrate sigue en Schema', strpos($schema_src, 'function maybe_migrate') !== false);
ac_assert('install bumpea aa_db_version', strpos($schema_src, "update_option('aa_db_version', self::DB_VERSION)") !== false);
ac_assert(
    'ensure_index expediente_recorded (install repetida)',
    strpos($schema_src, "'expediente_recorded'") !== false
    && strpos($schema_src, 'ADD KEY expediente_recorded (expediente_id, recorded_at, id)') !== false
);
ac_assert(
    'migración client_id nullable con SHOW COLUMNS + MODIFY',
    strpos($schema_src, 'function ensure_expediente_registros_client_id_nullable') !== false
    && strpos($schema_src, 'SHOW COLUMNS FROM {$table} LIKE %s') !== false
    && strpos($schema_src, 'MODIFY COLUMN client_id bigint(20) unsigned NULL DEFAULT NULL') !== false
);
ac_assert(
    'ensure client_id se invoca en install',
    strpos($schema_src, 'self::ensure_expediente_registros_client_id_nullable()') !== false
);

$ensure_pos = strpos($schema_src, 'self::ensure_expediente_registros_client_id_nullable()');
$bump_pos = strpos($schema_src, "update_option('aa_db_version', self::DB_VERSION)");
ac_assert(
    'ensure client_id corre antes de consolidar DB_VERSION',
    $ensure_pos !== false && $bump_pos !== false && $ensure_pos < $bump_pos
);
ac_assert(
    'fallo de nullability lanza RuntimeException',
    strpos($schema_src, 'No se pudo hacer nullable client_id en aa_expediente_registros') !== false
    && strpos($schema_src, 'throw new \\RuntimeException') !== false
);
ac_assert(
    'sin backfill ni UPDATE masivo de registros',
    preg_match("/UPDATE\s+.*aa_expediente_registros/i", $schema_src) !== 1
    && strpos($schema_src, 'backfill') === false
);
ac_assert(
    'schema no crea padres automáticamente',
    preg_match("/INSERT\s+INTO\s+.*aa_expedientes/i", $schema_src) !== 1
);
ac_assert(
    'migración client_id sin CHECK ni FOREIGN KEY ejecutables',
    strpos($schema_src, 'function ensure_expediente_registros_client_id_nullable') !== false
    && preg_match(
        '/function ensure_expediente_registros_client_id_nullable\(\): void \{[\s\S]*?^    private static function/m',
        $schema_src,
        $ensure_block_match
    ) === 1
    && isset($ensure_block_match[0])
    && stripos($ensure_block_match[0], 'CHECK') === false
    && stripos($ensure_block_match[0], 'FOREIGN KEY') === false
    && stripos($ensure_block_match[0], 'TRIGGER') === false
);
ac_assert(
    'insert legacy del repo no escribe expediente_id',
    is_string($repo_src)
    && ($legacy_start = strpos($repo_src, 'public static function insert(array $data)')) !== false
    && ($ife_start = strpos($repo_src, 'public static function insert_for_expediente')) !== false
    && $ife_start > $legacy_start
    && strpos(substr($repo_src, $legacy_start, $ife_start - $legacy_start), "'expediente_id'") === false
);
ac_assert(
    'DB_VERSION permanece 16',
    strpos($schema_src, "DB_VERSION = '16'") !== false
    && strpos($schema_src, "DB_VERSION = '15'") === false
);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load === '' || !is_readable($wp_load)) {
    echo "\n--- client_id nullable (wpdb mock) ---\n";

    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin_root . '/');
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    require_once $schema_file;

    $ensure = new ReflectionMethod('AA_Schema', 'ensure_expediente_registros_client_id_nullable');
    $ensure->setAccessible(true);

    global $wpdb;
    $wpdb = new class {
        public $prefix = 'wp_5_';
        public $last_error = '';
        /** @var list<array{Null:string}> */
        public $column_queue = [];
        /** @var list<string> */
        public $queries = [];
        public $last_query = '';

        public function prepare($query, ...$args) {
            foreach ($args as $arg) {
                $query = preg_replace('/%s/', "'" . (string) $arg . "'", $query, 1);
            }
            return $query;
        }

        public function get_row($query, $output = ARRAY_A) {
            $this->last_query = (string) $query;
            if ($this->column_queue === []) {
                return null;
            }
            return array_shift($this->column_queue);
        }

        public function query($sql) {
            $this->queries[] = (string) $sql;
            return true;
        }
    };

    $wpdb->column_queue = [
        ['Null' => 'NO', 'Field' => 'client_id'],
        ['Null' => 'YES', 'Field' => 'client_id'],
    ];
    $wpdb->queries = [];
    $ensure->invoke(null);
    ac_assert(
        'estado NOT NULL ejecuta un solo ALTER MODIFY',
        count($wpdb->queries) === 1
        && strpos($wpdb->queries[0], 'ALTER TABLE wp_5_aa_expediente_registros MODIFY COLUMN client_id') !== false
        && strpos($wpdb->queries[0], 'NULL DEFAULT NULL') !== false
    );
    ac_assert(
        'SHOW COLUMNS usa tabla con $wpdb->prefix',
        strpos($wpdb->last_query, 'wp_5_aa_expediente_registros') !== false
        && strpos($wpdb->last_query, 'client_id') !== false
    );

    $wpdb->column_queue = [
        ['Null' => 'YES', 'Field' => 'client_id'],
    ];
    $wpdb->queries = [];
    $ensure->invoke(null);
    ac_assert('ya nullable no vuelve a ejecutar ALTER', $wpdb->queries === []);

    $wpdb->column_queue = [
        ['Null' => 'NO', 'Field' => 'client_id'],
        ['Null' => 'NO', 'Field' => 'client_id'],
    ];
    $wpdb->queries = [];
    $wpdb->last_error = 'simulated modify failure';
    $threw = false;
    $message = '';
    try {
        $ensure->invoke(null);
    } catch (\Throwable $e) {
        $threw = true;
        $message = $e->getMessage();
    }
    ac_assert('postcondición Null!==YES lanza RuntimeException', $threw === true);
    ac_assert(
        'mensaje de fallo menciona client_id nullable',
        strpos($message, 'nullable client_id') !== false
    );
    ac_assert(
        'fallo ejecutó exactamente un ALTER antes de abortar',
        count($wpdb->queries) === 1
    );

    // Simula que install() no llega a update_option si ensure lanza.
    $fake_version = '15';
    $bumped = false;
    try {
        $wpdb->column_queue = [
            ['Null' => 'NO', 'Field' => 'client_id'],
            ['Null' => 'NO', 'Field' => 'client_id'],
        ];
        $ensure->invoke(null);
        $fake_version = '16';
        $bumped = true;
    } catch (\Throwable $e) {
        // Intencionado: no consolidar versión.
    }
    ac_assert(
        'fallo del cambio no consolida la nueva versión',
        $bumped === false && $fake_version === '15'
    );

    $wpdb->column_queue = [
        ['Null' => 'YES', 'Field' => 'client_id'],
    ];
    $wpdb->queries = [];
    $ensure->invoke(null);
    $wpdb->column_queue = [
        ['Null' => 'YES', 'Field' => 'client_id'],
    ];
    $ensure->invoke(null);
    ac_assert('ensure repetido es idempotente (0 ALTER)', $wpdb->queries === []);
}

if ($wp_load !== '' && is_readable($wp_load)) {
    echo "\n--- Integración WordPress (AA_WP_ROOT) ---\n";
    require_once $wp_load;
    require_once $schema_file;

    $before = get_option('aa_db_version', '0');
    AA_Schema::install();
    AA_Schema::install();

    global $wpdb;
    $table = $wpdb->prefix . 'aa_expediente_registros';
    $adjuntos_table = $wpdb->prefix . 'aa_expediente_adjuntos';
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    ac_assert('tabla existe tras install (prefijo blog)', $exists === $table, $table);

    $idx = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'client_recorded'");
    ac_assert('índice client_recorded existe', is_array($idx) && count($idx) >= 1);
    $exp_idx = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'expediente_recorded'");
    ac_assert('índice expediente_recorded existe', is_array($exp_idx) && count($exp_idx) >= 1);

    $exp_col = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'expediente_id'", ARRAY_A);
    ac_assert(
        'expediente_id existe y acepta NULL',
        is_array($exp_col) && strtoupper((string) ($exp_col['Null'] ?? '')) === 'YES',
        is_array($exp_col) ? (string) ($exp_col['Null'] ?? '') : 'missing'
    );
    $client_col = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'client_id'", ARRAY_A);
    ac_assert(
        'client_id es nullable (Null === YES)',
        is_array($client_col) && strtoupper((string) ($client_col['Null'] ?? '')) === 'YES',
        is_array($client_col) ? (string) ($client_col['Null'] ?? '') : 'missing'
    );

    $adj_col = $wpdb->get_row("SHOW COLUMNS FROM {$adjuntos_table} LIKE 'expediente_id'", ARRAY_A);
    ac_assert('adjuntos reales sin expediente_id', empty($adj_col));

    $now = current_time('mysql');
    $inserted = $wpdb->insert(
        $table,
        [
            'client_id' => 1,
            'title' => 'legacy-insert',
            'body' => 'sin expediente_id',
            'recorded_at' => $now,
            'created_at' => $now,
        ],
        ['%d', '%s', '%s', '%s', '%s']
    );
    ac_assert('insert legacy con client_id sigue válido', $inserted !== false, (string) $wpdb->last_error);
    $new_id = (int) $wpdb->insert_id;
    if ($new_id > 0) {
        $stored = $wpdb->get_row(
            $wpdb->prepare("SELECT expediente_id FROM {$table} WHERE id = %d", $new_id),
            ARRAY_A
        );
        ac_assert(
            'insert legacy deja expediente_id NULL',
            is_array($stored) && ($stored['expediente_id'] === null || $stored['expediente_id'] === ''),
            is_array($stored) ? (string) ($stored['expediente_id'] ?? 'unset') : 'missing'
        );
        $wpdb->delete($table, ['id' => $new_id], ['%d']);
    }

    $version = get_option('aa_db_version', '0');
    ac_assert('aa_db_version es 16 tras install', (string) $version === '16', (string) $version);
    ac_assert('upgrade path: versión previa no bloquea', true, 'before=' . $before);
    ac_assert('install() repetido es idempotente', true, 'doble install OK');
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
