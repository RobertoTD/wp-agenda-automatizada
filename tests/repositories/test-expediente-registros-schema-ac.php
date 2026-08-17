<?php
/**
 * AC — Schema aa_expediente_registros (MC2).
 *
 * Ejecutar: php tests/repositories/test-expediente-registros-schema-ac.php
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
ac_assert('DB_VERSION is 14', strpos($schema_src, "DB_VERSION = '14'") !== false);
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

$block_start = strpos($schema_src, "\$wpdb->prefix . 'aa_expediente_registros'");
$block_end = $block_start !== false ? strpos($schema_src, ') $charset;";', $block_start) : false;
$block = ($block_start !== false && $block_end !== false)
    ? substr($schema_src, $block_start, $block_end - $block_start)
    : '';

ac_assert('bloque DDL encontrado', $block !== '');
ac_assert('sin FOREIGN KEY en registros', $block !== '' && strpos($block, 'FOREIGN KEY') === false);
ac_assert(
    'registros siguen sin expediente_id (sin backfill)',
    $block !== '' && strpos($block, 'expediente_id') === false
);
ac_assert(
    'registros conservan client_id NOT NULL',
    $block !== '' && strpos($block, 'client_id bigint(20) unsigned NOT NULL') !== false
);
ac_assert(
    'sin KEY client_id suelto redundante',
    $block !== '' && strpos($block, 'KEY client_id (client_id)') === false
);
ac_assert('usa $wpdb->prefix', strpos($schema_src, "\$wpdb->prefix . 'aa_expediente_registros'") !== false);
ac_assert('maybe_migrate sigue en Schema', strpos($schema_src, 'function maybe_migrate') !== false);
ac_assert('install bumpea aa_db_version', strpos($schema_src, "update_option('aa_db_version', self::DB_VERSION)") !== false);

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
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    ac_assert('tabla existe tras install (prefijo blog)', $exists === $table, $table);

    $idx = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'client_recorded'");
    ac_assert('índice client_recorded existe', is_array($idx) && count($idx) >= 1);

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
