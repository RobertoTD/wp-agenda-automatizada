<?php
/**
 * AC Test — Persistencia Canónica Universal (PCU-2 / DB_VERSION 21).
 *
 * Ejecutar:
 *   php tests/infrastructure/wp/test-canonical-schema-ac.php
 *
 * Con integración MySQL real (prefijos desechables):
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/wp/test-canonical-schema-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$canonical_schema_file = $plugin_root . '/includes/infrastructure/wp/CanonicalSchema.php';

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

$wp_root = getenv('AA_WP_ROOT') ?: '';
$wp_load = $wp_root !== '' ? rtrim($wp_root, '/') . '/wp-load.php' : '';
$has_real_wp = ($wp_load !== '' && is_readable($wp_load));

if ($has_real_wp) {
    require_once $wp_load;
} else {
    if (!defined('ABSPATH')) {
        define('ABSPATH', $plugin_root . '/');
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
}

require_once $canonical_schema_file;

echo "=== 1. Análisis estático ===\n";

$schema_src = file_get_contents($schema_file);
$canonical_src = file_get_contents($canonical_schema_file);

ac_assert('Schema.php es legible', is_string($schema_src) && $schema_src !== '');
ac_assert('CanonicalSchema.php es legible', is_string($canonical_src) && $canonical_src !== '');
ac_assert("AA_Schema::DB_VERSION es '21'", strpos($schema_src, "DB_VERSION = '21'") !== false);
ac_assert('Schema.php delega en AA_Canonical_Schema::install()', strpos($schema_src, 'AA_Canonical_Schema::install()') !== false);

$bump_pos = strpos($schema_src, "update_option('aa_db_version', self::DB_VERSION)");
$fin_pos = strpos($schema_src, 'AA_Finance_Schema::install()');
$can_pos = strpos($schema_src, 'AA_Canonical_Schema::install()');
ac_assert(
    'Finance install antes de Canonical install',
    $fin_pos !== false && $can_pos !== false && $fin_pos < $can_pos
);
ac_assert(
    'Canonical install antes de consolidar aa_db_version',
    $can_pos !== false && $bump_pos !== false && $can_pos < $bump_pos
);

ac_assert('TABLE_FAMILIES constante', strpos($canonical_src, "TABLE_FAMILIES = 'aa_canonical_families'") !== false);
ac_assert('TABLE_CONTAINERS constante', strpos($canonical_src, "TABLE_CONTAINERS = 'aa_canonical_containers'") !== false);
ac_assert('TABLE_RECORDS constante', strpos($canonical_src, "TABLE_RECORDS = 'aa_canonical_records'") !== false);

preg_match('/\$families_sql\s*=\s*"([^"]+)";/s', $canonical_src, $m_f);
$families_sql = $m_f[1] ?? '';
preg_match('/\$containers_sql\s*=\s*"([^"]+)";/s', $canonical_src, $m_c);
$containers_sql = $m_c[1] ?? '';
preg_match('/\$records_sql\s*=\s*"([^"]+)";/s', $canonical_src, $m_r);
$records_sql = $m_r[1] ?? '';

ac_assert('Families PRIMARY KEY  (id) con dos espacios', strpos($families_sql, 'PRIMARY KEY  (id)') !== false);
ac_assert('Families family_key varchar(64) NOT NULL', strpos($families_sql, 'family_key varchar(64) NOT NULL') !== false);
ac_assert('Families is_enabled tinyint(1) NOT NULL DEFAULT 0', strpos($families_sql, 'is_enabled tinyint(1) NOT NULL DEFAULT 0') !== false);
ac_assert('Families seed_version smallint(5) unsigned NOT NULL DEFAULT 0', strpos($families_sql, 'seed_version smallint(5) unsigned NOT NULL DEFAULT 0') !== false);
ac_assert('Families UNIQUE uq_family_key', strpos($families_sql, 'UNIQUE KEY uq_family_key (family_key)') !== false);
ac_assert('Families idx_enabled_key', strpos($families_sql, 'KEY idx_enabled_key (is_enabled, family_key)') !== false);
ac_assert('Families ENGINE=InnoDB', strpos($families_sql, 'ENGINE=InnoDB') !== false);
ac_assert('Families sin DEFAULT CURRENT_TIMESTAMP', strpos($families_sql, 'CURRENT_TIMESTAMP') === false);
ac_assert('Families sin public_id', strpos($families_sql, 'public_id') === false);

ac_assert('Containers public_id char(36) NOT NULL', strpos($containers_sql, 'public_id char(36) NOT NULL') !== false);
ac_assert('Containers UNIQUE public_id', strpos($containers_sql, 'UNIQUE KEY uq_container_public_id (public_id)') !== false);
ac_assert('Containers idx_family_variant_updated', strpos($containers_sql, 'KEY idx_family_variant_updated (family_id, variant_key, updated_at, id)') !== false);
ac_assert('Containers sin amount', strpos($containers_sql, 'amount') === false);
ac_assert('Containers sin family_key', strpos($containers_sql, 'family_key') === false);
ac_assert('Containers sin FOREIGN KEY en dbDelta DDL', stripos($containers_sql, 'FOREIGN KEY') === false);

ac_assert('Records public_id char(36) NOT NULL', strpos($records_sql, 'public_id char(36) NOT NULL') !== false);
ac_assert('Records UNIQUE public_id', strpos($records_sql, 'UNIQUE KEY uq_record_public_id (public_id)') !== false);
ac_assert('Records idx_container_updated', strpos($records_sql, 'KEY idx_container_updated (container_id, updated_at, id)') !== false);
ac_assert('Records sin family_key', strpos($records_sql, 'family_key') === false);
ac_assert('Records sin variant_key', strpos($records_sql, 'variant_key') === false);
ac_assert('Records sin amount', strpos($records_sql, 'amount') === false);
ac_assert('Records sin FOREIGN KEY en dbDelta DDL', stripos($records_sql, 'FOREIGN KEY') === false);

ac_assert('FK RESTRICT presente', strpos($canonical_src, "ON DELETE RESTRICT") !== false || strpos($canonical_src, "'RESTRICT'") !== false);
ac_assert('FK CASCADE presente', strpos($canonical_src, "ON DELETE CASCADE") !== false || strpos($canonical_src, "'CASCADE'") !== false);
ac_assert('Sin current_time en CanonicalSchema', strpos($canonical_src, 'current_time') === false);
ac_assert('Sin gmdate en CanonicalSchema (escritura es PCU-3)', strpos($canonical_src, 'gmdate') === false);

$has_insert = (bool) preg_match('/\bINSERT\b/i', $canonical_src);
ac_assert('Instalador sin INSERT SQL', !$has_insert && strpos($canonical_src, '$wpdb->insert') === false);
ac_assert('Instalador sin UPDATE de filas', strpos($canonical_src, '$wpdb->update') === false);
ac_assert('DDL families sin amount', stripos($families_sql, 'amount') === false);
ac_assert('DDL containers sin amount', stripos($containers_sql, 'amount') === false);
ac_assert('DDL records sin amount', stripos($records_sql, 'amount') === false);
ac_assert('Sin aa_finance en CanonicalSchema', stripos($canonical_src, 'aa_finance') === false);
ac_assert('Sin vocabulario Expedientes', stripos($canonical_src, 'expediente') === false);
ac_assert('Sin AJAX/bindings/UI', stripos($canonical_src, 'ajax') === false && stripos($canonical_src, 'binding') === false);

echo "\n=== 2. Robustez de nombres de FK ===\n";

$fk_cont = AA_Canonical_Schema::containers_foreign_key_name('wp_');
$fk_rec = AA_Canonical_Schema::records_foreign_key_name('wp_');
ac_assert('FK containers name válido', (bool) preg_match('/^[a-zA-Z0-9_]+$/', $fk_cont) && strlen($fk_cont) <= 64);
ac_assert('FK records name válido', (bool) preg_match('/^[a-zA-Z0-9_]+$/', $fk_rec) && strlen($fk_rec) <= 64);
ac_assert('FK containers determinista', $fk_cont === AA_Canonical_Schema::containers_foreign_key_name('wp_'));
ac_assert('FK records determinista', $fk_rec === AA_Canonical_Schema::records_foreign_key_name('wp_'));
ac_assert('FK containers ≠ records', $fk_cont !== $fk_rec);

$long_a = 'wp_very_long_prefix_with_more_than_50_chars_that_shares_same_start_branch_alpha_';
$long_b = 'wp_very_long_prefix_with_more_than_50_chars_that_shares_same_start_branch_beta_';
ac_assert(
    'FK containers distingue prefijos largos',
    AA_Canonical_Schema::containers_foreign_key_name($long_a)
        !== AA_Canonical_Schema::containers_foreign_key_name($long_b)
);

if ($has_real_wp) {
    echo "\n=== 3. Integración MySQL real ===\n";
    echo 'Host: ' . DB_HOST . ' | Base: ' . DB_NAME . "\n";

    global $wpdb;
    $original_prefix = $wpdb->prefix;
    $temp_prefix_1 = 'tmp_can1_' . substr(md5(uniqid('c1', true)), 0, 8) . '_';
    $temp_prefix_2 = 'tmp_can2_' . substr(md5(uniqid('c2', true)), 0, 8) . '_';

    $cleanup_tables = static function (string $p) use ($wpdb): void {
        if (strpos($p, 'tmp_can') !== 0) {
            return;
        }
        $tables = [
            $p . AA_Canonical_Schema::TABLE_RECORDS,
            $p . AA_Canonical_Schema::TABLE_CONTAINERS,
            $p . AA_Canonical_Schema::TABLE_FAMILIES,
            $p . 'aa_finance_records',
            $p . 'aa_finance_containers',
            $p . 'aa_expedientes',
            $p . 'aa_expediente_registros',
        ];
        foreach ($tables as $t) {
            $wpdb->query("DROP TABLE IF EXISTS `{$t}`");
        }
    };

    try {
        // --- Prefijo 1: instalación limpia ---
        $wpdb->prefix = $temp_prefix_1;
        $cleanup_tables($temp_prefix_1);

        AA_Canonical_Schema::install();
        AA_Canonical_Schema::verify();
        ac_assert('MySQL: install+verify limpio en prefijo 1', true);

        $f1 = AA_Canonical_Schema::families_table_name();
        $c1 = AA_Canonical_Schema::containers_table_name();
        $r1 = AA_Canonical_Schema::records_table_name();

        ac_assert('MySQL: tres tablas existen', $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $f1)) === $f1
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $c1)) === $c1
            && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $r1)) === $r1);

        $count_f = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f1}`");
        $count_c = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c1}`");
        $count_r = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$r1}`");
        ac_assert('MySQL: tablas vacías tras install normal', $count_f === 0 && $count_c === 0 && $count_r === 0);

        $engine_f = strtoupper((string) $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s', DB_NAME, $f1)));
        ac_assert('MySQL: families InnoDB', $engine_f === 'INNODB');

        $pub_col = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM `{$c1}` LIKE %s", 'public_id'), ARRAY_A);
        ac_assert(
            'MySQL: containers public_id char(36) NOT NULL',
            is_array($pub_col)
            && stripos((string) $pub_col['Type'], 'char(36)') !== false
            && strtoupper((string) $pub_col['Null']) === 'NO'
        );

        // Idempotencia ×3
        AA_Canonical_Schema::install();
        AA_Canonical_Schema::install();
        AA_Canonical_Schema::verify();
        ac_assert('MySQL: install() ×3 idempotente + verify OK', true);

        $idx_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
                DB_NAME,
                $c1,
                'uq_container_public_id'
            )
        );
        ac_assert('MySQL: UNIQUE public_id sin duplicar tras reinstall', $idx_count === 1);

        // Upgrade path: simular DB 20 con Finance presente, luego Canonical
        if (!class_exists('AA_Finance_Schema')) {
            require_once $plugin_root . '/includes/infrastructure/wp/FinanceSchema.php';
        }
        AA_Finance_Schema::install();
        $fin_c = AA_Finance_Schema::containers_table_name();
        $fin_r = AA_Finance_Schema::records_table_name();
        $now = '2026-01-15 12:00:00';
        $wpdb->insert(
            $fin_c,
            ['variant_key' => 'general', 'title' => 'Legacy Finance', 'created_at' => $now, 'updated_at' => $now],
            ['%s', '%s', '%s', '%s']
        );
        $fin_count_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$fin_c}`");

        // Re-install canonical (upgrade additive)
        AA_Canonical_Schema::install();
        $fin_count_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$fin_c}`");
        ac_assert('MySQL: Finance intacto tras Canonical install', $fin_count_before === 1 && $fin_count_after === 1);
        ac_assert(
            'MySQL: canonical sigue vacío tras coexistir con Finance',
            (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f1}`") === 0
            && (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c1}`") === 0
        );

        // Integridad RESTRICT / CASCADE con fixtures de test solamente
        $wpdb->insert(
            $f1,
            [
                'family_key' => 'finance',
                'is_enabled' => 0,
                'seed_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%d', '%s', '%s']
        );
        $family_id = (int) $wpdb->insert_id;
        $wpdb->insert(
            $c1,
            [
                'public_id' => '11111111-1111-4111-8111-111111111111',
                'family_id' => $family_id,
                'variant_key' => 'general',
                'title' => 'Contenedor test',
                'details' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );
        $container_id = (int) $wpdb->insert_id;
        $wpdb->insert(
            $r1,
            [
                'public_id' => '22222222-2222-4222-8222-222222222222',
                'container_id' => $container_id,
                'title' => 'Registro test',
                'details' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );

        $wpdb->last_error = '';
        $restrict_failed = false;
        $wpdb->query($wpdb->prepare("DELETE FROM `{$f1}` WHERE id = %d", $family_id));
        if ($wpdb->last_error !== '' || (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$f1}` WHERE id = %d", $family_id)) === 1) {
            // DELETE should fail or leave the row when RESTRICT/NO ACTION applies
            $restrict_failed = ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$f1}` WHERE id = %d", $family_id)) === 1);
        }
        ac_assert('MySQL: RESTRICT impide borrar familia con contenedores', $restrict_failed === true);

        $wpdb->query($wpdb->prepare("DELETE FROM `{$c1}` WHERE id = %d", $container_id));
        $remaining_records = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$r1}` WHERE container_id = %d", $container_id));
        ac_assert('MySQL: CASCADE elimina registros al borrar contenedor', $remaining_records === 0);

        // Limpiar fixtures del prefijo 1 (familia huérfana sin contenedores)
        $wpdb->query("DELETE FROM `{$f1}`");
        ac_assert(
            'MySQL: tras limpiar fixtures, tablas canónicas vacías',
            (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f1}`") === 0
            && (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c1}`") === 0
            && (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$r1}`") === 0
        );

        // Prefijo 2: aislamiento
        $wpdb->prefix = $temp_prefix_2;
        $cleanup_tables($temp_prefix_2);
        AA_Canonical_Schema::install();
        AA_Canonical_Schema::verify();
        $f2 = AA_Canonical_Schema::families_table_name();
        $wpdb->insert(
            $f2,
            [
                'family_key' => 'archive',
                'is_enabled' => 1,
                'seed_version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%d', '%s', '%s']
        );
        $count_p1 = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f1}`");
        $count_p2 = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$f2}`");
        ac_assert('MySQL: aislamiento entre prefijos', $count_p1 === 0 && $count_p2 === 1);

        // verify detecta FK ausente
        $fk2 = AA_Canonical_Schema::records_foreign_key_name($temp_prefix_2);
        $r2 = AA_Canonical_Schema::records_table_name();
        $wpdb->query("ALTER TABLE `{$r2}` DROP FOREIGN KEY `{$fk2}`");
        $verify_failed = false;
        try {
            AA_Canonical_Schema::verify();
        } catch (\RuntimeException $e) {
            $verify_failed = true;
        }
        ac_assert('MySQL: verify falla sin FK records', $verify_failed);

        AA_Canonical_Schema::install();
        $repaired = true;
        try {
            AA_Canonical_Schema::verify();
        } catch (\Throwable $e) {
            $repaired = false;
        }
        ac_assert('MySQL: install repara FK ausente', $repaired);

        // Simular upgrade AA_Schema con prefijo desechable; restaurar option global.
        if (!class_exists('AA_Schema')) {
            require_once $schema_file;
        }
        $upgrade_prefix = 'tmp_canu_' . substr(md5(uniqid('u', true)), 0, 8) . '_';
        $prior_db_version = get_option('aa_db_version', '0');
        $cleanup_upgrade = static function () use ($wpdb, $upgrade_prefix): void {
            if (strpos($upgrade_prefix, 'tmp_canu_') !== 0) {
                return;
            }
            $wpdb->query('SET FOREIGN_KEY_CHECKS=0');
            $like = $wpdb->esc_like($upgrade_prefix) . '%';
            $rows = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
            if (is_array($rows)) {
                foreach ($rows as $t) {
                    $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '``', (string) $t) . '`');
                }
            }
            $wpdb->query('SET FOREIGN_KEY_CHECKS=1');
        };
        $wpdb->prefix = $upgrade_prefix;
        $cleanup_upgrade();
        try {
            update_option('aa_db_version', '20');
            AA_Schema::install();
            $stored = (string) get_option('aa_db_version', '0');
            ac_assert("MySQL: AA_Schema::install deja aa_db_version=21", $stored === '21');
            $uf = $wpdb->prefix . AA_Canonical_Schema::TABLE_FAMILIES;
            $uc = $wpdb->prefix . AA_Canonical_Schema::TABLE_CONTAINERS;
            $ur = $wpdb->prefix . AA_Canonical_Schema::TABLE_RECORDS;
            ac_assert(
                'MySQL: upgrade Schema crea tres tablas canónicas',
                $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $uf)) === $uf
                && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $uc)) === $uc
                && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ur)) === $ur
            );
            ac_assert(
                'MySQL: upgrade Schema deja canónicas vacías',
                (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$uf}`") === 0
                && (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$uc}`") === 0
                && (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$ur}`") === 0
            );
        } finally {
            $cleanup_upgrade();
            update_option('aa_db_version', $prior_db_version);
        }

    } finally {
        $cleanup_tables($temp_prefix_1);
        $cleanup_tables($temp_prefix_2);
        $wpdb->prefix = $original_prefix;
        echo "Limpieza garantizada: tablas temporales eliminadas.\n";
    }
} else {
    echo "\n[INFO / SKIP] Integración MySQL real no configurada (AA_WP_ROOT no definido o inaccesible).\n";
}

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
