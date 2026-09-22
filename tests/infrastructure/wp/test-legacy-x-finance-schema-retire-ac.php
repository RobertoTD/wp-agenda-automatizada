<?php
/**
 * AC LEGACY-X bloque 1 — instalación/migración: retirada aa_finance_* + schema canónico.
 *
 * Ejecutar:
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/wp/test-legacy-x-finance-schema-retire-ac.php
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

echo "=== 1. Contención estática Schema DB25 ===\n";
$schema_src = (string) file_get_contents($plugin_root . '/includes/infrastructure/wp/Schema.php');
ac_assert('DB_VERSION = 38', strpos($schema_src, "DB_VERSION = '38'") !== false);
ac_assert('Sin AA_Finance_Schema::install', strpos($schema_src, 'AA_Finance_Schema::install') === false);
ac_assert('Sin require FinanceSchema', strpos($schema_src, 'FinanceSchema.php') === false);
ac_assert('Retira records antes que containers', strpos($schema_src, 'LEGACY_FINANCE_TABLE_RECORDS') !== false
    && strpos($schema_src, 'drop_legacy_finance_table($records_table)') !== false
    && strpos($schema_src, 'drop_legacy_finance_table($containers_table)') !== false
    && strpos($schema_src, 'drop_legacy_finance_table($records_table)')
        < strpos($schema_src, 'drop_legacy_finance_table($containers_table)'));
ac_assert('DROP FOREIGN KEY antes de DROP records', strpos($schema_src, 'DROP FOREIGN KEY') !== false);
ac_assert('FinanceSchema.php ausente', !is_readable($plugin_root . '/includes/infrastructure/wp/FinanceSchema.php'));

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
if ($wp_load === '' || !is_readable($wp_load)) {
    echo "[INFO / SKIP] MySQL harness ausente (AA_WP_ROOT).\n";
    echo "\n--- Resumen: {$passed}/{$total} ---\n";
    exit($failed === [] ? 0 : 1);
}

require_once $wp_load;
require_once $plugin_root . '/includes/infrastructure/wp/Schema.php';
require_once $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';

global $wpdb;
$prior_prefix = $wpdb->prefix;
$prior_db = (string) get_option('aa_db_version', '0');
$temp_prefix = 'tmp_lx1_' . substr(md5((string) microtime(true)), 0, 8) . '_';

$cleanup = static function () use ($wpdb, $temp_prefix): void {
    $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
    $like = $wpdb->esc_like($temp_prefix) . '%';
    $rows = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
    if (is_array($rows)) {
        foreach ($rows as $t) {
            $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
        }
    }
    $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
};

$table_exists = static function (string $table) use ($wpdb): bool {
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    return is_string($found) && $found === $table;
};

echo "\n=== 2. MySQL: DROP ordenado + idempotencia + canónico ===\n";
try {
    $wpdb->prefix = $temp_prefix;
    $cleanup();

    $containers = $temp_prefix . 'aa_finance_containers';
    $records = $temp_prefix . 'aa_finance_records';
    $charset = $wpdb->get_charset_collate();

    $wpdb->query("CREATE TABLE `{$containers}` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        variant_key varchar(64) NOT NULL,
        title varchar(200) NOT NULL,
        details text DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) ENGINE=InnoDB {$charset}");
    $wpdb->query("CREATE TABLE `{$records}` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        container_id bigint(20) unsigned NOT NULL,
        title varchar(200) NOT NULL,
        details text DEFAULT NULL,
        amount decimal(19,2) DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY idx_container (container_id)
    ) ENGINE=InnoDB {$charset}");

    $fk_name = substr(
        'fk_' . rtrim(substr(preg_replace('/[^a-zA-Z0-9_]/', '', $temp_prefix), 0, 32), '_')
        . '_aa_fin_rec_'
        . substr(md5($temp_prefix . ':aa_finance_records:container_id'), 0, 16),
        0,
        64
    );
    $wpdb->query(
        "ALTER TABLE `{$records}` ADD CONSTRAINT `{$fk_name}` FOREIGN KEY (`container_id`) "
        . "REFERENCES `{$containers}` (`id`) ON DELETE CASCADE"
    );
    ac_assert('Fixture legacy con FK creado', $table_exists($containers) && $table_exists($records));

    update_option('aa_db_version', '23');
    AA_Schema::install();

    ac_assert('Tras install: records ausente', !$table_exists($records));
    ac_assert('Tras install: containers ausente', !$table_exists($containers));
    ac_assert(
        'Canónico families presente',
        $table_exists($temp_prefix . AA_Canonical_Schema::TABLE_FAMILIES)
    );
    ac_assert(
        'Canónico record_amount presente',
        $table_exists($temp_prefix . AA_Canonical_Schema::TABLE_RECORD_AMOUNT)
    );
    ac_assert('aa_db_version consolidada a 26', (string) get_option('aa_db_version', '0') === '26');

    // Idempotencia: re-ejecutar sin recrear finance.
    AA_Schema::install();
    ac_assert('Re-install: finance sigue ausente', !$table_exists($records) && !$table_exists($containers));
    ac_assert(
        'Re-install: canónico intacto',
        $table_exists($temp_prefix . AA_Canonical_Schema::TABLE_CONTAINERS)
    );

    // Fallo sin silencio: DROP del padre con FK hijo debe fallar (comprobaciones FK activas).
    $ref = new ReflectionClass('AA_Schema');
    $method = $ref->getMethod('drop_legacy_finance_table');
    $method->setAccessible(true);
    $wpdb->query("CREATE TABLE `{$containers}` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        variant_key varchar(64) NOT NULL DEFAULT 'general',
        title varchar(200) NOT NULL,
        details text DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) ENGINE=InnoDB {$charset}");
    $wpdb->query("CREATE TABLE `{$records}` (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        container_id bigint(20) unsigned NOT NULL,
        title varchar(200) NOT NULL,
        details text DEFAULT NULL,
        amount decimal(19,2) DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY idx_container (container_id)
    ) ENGINE=InnoDB {$charset}");
    $wpdb->query(
        "ALTER TABLE `{$records}` ADD CONSTRAINT `{$fk_name}` FOREIGN KEY (`container_id`) "
        . "REFERENCES `{$containers}` (`id`) ON DELETE CASCADE"
    );
    $wpdb->insert($containers, [
        'variant_key' => 'general',
        'title' => 'p',
        'created_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ]);
    $cid = (int) $wpdb->insert_id;
    $wpdb->insert($records, [
        'container_id' => $cid,
        'title' => 'r',
        'created_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ]);

    $threw = false;
    try {
        $method->invoke(null, $containers);
    } catch (\Throwable $e) {
        $threw = true;
    }
    ac_assert('DROP padre con hijo+FK falla (FK checks)', $threw);
    ac_assert('Tras fallo, child records sigue presente', $table_exists($records));

    ac_assert(
        'maybe_migrate no consolida en catch',
        strpos($schema_src, 'Migración falló') !== false
        && preg_match('/catch\s*\(\s*\\\\?Throwable[^)]*\)[^{]*\{[^}]*error_log/s', $schema_src) === 1
    );
} catch (\Throwable $e) {
    ac_assert('Excepción inesperada: ' . $e->getMessage(), false);
} finally {
    $cleanup();
    $wpdb->prefix = $prior_prefix;
    update_option('aa_db_version', $prior_db);
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";
if ($failed !== []) {
    echo "Fallidos:\n- " . implode("\n- ", $failed) . "\n";
    exit(1);
}
exit(0);
