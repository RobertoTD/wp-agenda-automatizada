<?php
/**
 * AC — Schema aa_expediente_adjuntos (MC4a2) + DB_VERSION 16.
 *
 * Ejecutar: php tests/repositories/test-expediente-adjuntos-schema-ac.php
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

$schema_src = file_get_contents($schema_file);
ac_assert('Schema readable', is_string($schema_src) && $schema_src !== '');
ac_assert('DB_VERSION is 19', strpos($schema_src, "DB_VERSION = '19'") !== false);
ac_assert('CREATE TABLE aa_expediente_adjuntos', strpos($schema_src, "aa_expediente_adjuntos") !== false);
ac_assert('storage_path varchar(191)', strpos($schema_src, 'storage_path varchar(191) NOT NULL') !== false);
ac_assert('upload_operation_id char(36)', strpos($schema_src, 'upload_operation_id char(36) NOT NULL') !== false);
ac_assert('unique operation ensure_index', strpos($schema_src, 'uq_aa_exp_adj_operation') !== false);
ac_assert('unique storage_path ensure_index', strpos($schema_src, 'uq_aa_exp_adj_storage_path') !== false);
ac_assert('KEY record_id_id', strpos($schema_src, 'KEY record_id_id (record_id, id)') !== false);
ac_assert('KEY client_record', strpos($schema_src, 'KEY client_record (client_id, record_id)') !== false);
ac_assert('sin FOREIGN KEY adjuntos', strpos($schema_src, 'aa_expediente_adjuntos') !== false && !preg_match('/aa_expediente_adjuntos[\s\S]{0,800}FOREIGN KEY/', $schema_src));
// Bloque CREATE adjuntos (anclar al prefix, no al comentario del header).
$adj_start = strpos($schema_src, "\$wpdb->prefix . 'aa_expediente_adjuntos'");
$adj_end = $adj_start !== false ? strpos($schema_src, ') $charset;";', $adj_start) : false;
$adj_block = ($adj_start !== false && $adj_end !== false) ? substr($schema_src, $adj_start, $adj_end - $adj_start) : '';
ac_assert('bloque adjuntos sin columna status', $adj_block !== '' && strpos($adj_block, 'status') === false);
ac_assert('bloque adjuntos sin expediente_id', $adj_block !== '' && strpos($adj_block, 'expediente_id') === false);
ac_assert('usa prefix aa_expediente_adjuntos', $adj_start !== false);
ac_assert(
    'CREATE adjuntos client_id nullable',
    strpos($adj_block, 'client_id bigint(20) unsigned DEFAULT NULL') !== false
    || strpos($adj_block, 'client_id bigint(20) unsigned NULL') !== false
);
ac_assert(
    'CREATE adjuntos no NOT NULL en client_id',
    !preg_match('/client_id bigint\(20\) unsigned NOT NULL/', $adj_block)
);
ac_assert(
    'ensure_expediente_adjuntos_client_id_nullable',
    strpos($schema_src, 'ensure_expediente_adjuntos_client_id_nullable') !== false
);
ac_assert(
    'ALTER MODIFY adjuntos client_id nullable',
    strpos($schema_src, 'aa_expediente_adjuntos') !== false
    && strpos(
        $schema_src,
        'MODIFY COLUMN client_id bigint(20) unsigned NULL DEFAULT NULL'
    ) !== false
);
ac_assert(
    'ensure adjuntos no UPDATE filas',
    !preg_match('/ensure_expediente_adjuntos_client_id_nullable[\s\S]{0,1200}UPDATE\s+/i', $schema_src)
);
ac_assert(
    'ensure adjuntos se invoca antes de update_option aa_db_version',
    strpos($schema_src, 'self::ensure_expediente_adjuntos_client_id_nullable()') !== false
    && strpos($schema_src, 'self::ensure_expediente_adjuntos_client_id_nullable()')
        < strpos($schema_src, "update_option('aa_db_version', self::DB_VERSION)")
);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

if ($wp_load === '' || !is_readable($wp_load)) {
    echo "\n--- adjuntos client_id nullable (wpdb mock) ---\n";

    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin_root . '/');
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }

    require_once $schema_file;

    $ensure = new ReflectionMethod('AA_Schema', 'ensure_expediente_adjuntos_client_id_nullable');
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
        && strpos($wpdb->queries[0], 'ALTER TABLE wp_5_aa_expediente_adjuntos MODIFY COLUMN client_id') !== false
        && strpos($wpdb->queries[0], 'NULL DEFAULT NULL') !== false
    );
    ac_assert(
        'SHOW COLUMNS usa tabla con $wpdb->prefix',
        strpos($wpdb->last_query, 'wp_5_aa_expediente_adjuntos') !== false
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
        && strpos($message, 'aa_expediente_adjuntos') !== false
    );
    ac_assert(
        'fallo ejecutó exactamente un ALTER antes de abortar',
        count($wpdb->queries) === 1
    );

    $fake_version = '17';
    $bumped = false;
    try {
        $wpdb->column_queue = [
            ['Null' => 'NO', 'Field' => 'client_id'],
            ['Null' => 'NO', 'Field' => 'client_id'],
        ];
        $ensure->invoke(null);
        $fake_version = '18';
        $bumped = true;
    } catch (\Throwable $e) {
        // Intencionado: no consolidar versión.
    }
    ac_assert(
        'ALTER fallido no consolida DB 18',
        $bumped === false && $fake_version === '17'
    );

    $wpdb->column_queue = [];
    $wpdb->queries = [];
    $wpdb->last_error = '';
    $threw_missing = false;
    try {
        $ensure->invoke(null);
    } catch (\Throwable $e) {
        $threw_missing = true;
    }
    ac_assert('columna ausente falla cerrado', $threw_missing === true && $wpdb->queries === []);

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
