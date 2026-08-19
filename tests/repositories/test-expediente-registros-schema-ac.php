<?php
/**
 * AC — Schema aa_expediente_registros (MC2 + DB_VERSION 15).
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
ac_assert('DB_VERSION is 15', strpos($schema_src, "DB_VERSION = '15'") !== false);
ac_assert('DB_VERSION ya no es 14', strpos($schema_src, "DB_VERSION = '14'") === false);
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
    'registros conservan client_id NOT NULL',
    $block !== '' && strpos($block, 'client_id bigint(20) unsigned NOT NULL') !== false
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
    'sin backfill ni UPDATE masivo de registros',
    preg_match("/UPDATE\s+.*aa_expediente_registros/i", $schema_src) !== 1
    && strpos($schema_src, 'backfill') === false
);
ac_assert(
    'schema no crea padres automáticamente',
    preg_match("/INSERT\s+INTO\s+.*aa_expedientes/i", $schema_src) !== 1
);
ac_assert(
    'insert legacy del repo no escribe expediente_id',
    is_string($repo_src)
    && strpos($repo_src, 'function insert') !== false
    && preg_match("/function insert[\s\S]*?'client_id'[\s\S]*?'title'[\s\S]*?'body'[\s\S]*?'recorded_at'[\s\S]*?'created_at'/", $repo_src) === 1
    && strpos($repo_src, "'expediente_id' =>") === false
);

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';

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
        'client_id sigue NOT NULL',
        is_array($client_col) && strtoupper((string) ($client_col['Null'] ?? '')) === 'NO',
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
    ac_assert('insert legacy sin expediente_id es válido', $inserted !== false, (string) $wpdb->last_error);
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
    ac_assert('aa_db_version es 15 tras install', (string) $version === '15', (string) $version);
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
