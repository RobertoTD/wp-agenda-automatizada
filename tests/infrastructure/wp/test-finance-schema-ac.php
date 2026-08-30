<?php
/**
 * AC Test — Schema físico de Finanzas (Ciclo 3A1, DB_VERSION 19).
 *
 * Ejecutar:
 *   php tests/infrastructure/wp/test-finance-schema-ac.php
 *
 * Con integración MySQL/MariaDB real (disposable prefix):
 *   AA_WP_ROOT=/var/www/html/wpagenda php tests/infrastructure/wp/test-finance-schema-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$schema_file = $plugin_root . '/includes/infrastructure/wp/Schema.php';
$finance_schema_file = $plugin_root . '/includes/infrastructure/wp/FinanceSchema.php';

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

require_once $finance_schema_file;

echo "=== 1. Análisis estático de Schema y DDL ===\n";

$schema_src = file_get_contents($schema_file);
$finance_schema_src = file_get_contents($finance_schema_file);

ac_assert('Schema.php es legible', is_string($schema_src) && $schema_src !== '');
ac_assert('FinanceSchema.php es legible', is_string($finance_schema_src) && $finance_schema_src !== '');
ac_assert('AA_Schema::DB_VERSION es 19', strpos($schema_src, "DB_VERSION = '19'") !== false);
ac_assert('Schema.php delega en AA_Finance_Schema::install()', strpos($schema_src, 'AA_Finance_Schema::install()') !== false);

$bump_pos = strpos($schema_src, "update_option('aa_db_version', self::DB_VERSION)");
$fin_install_pos = strpos($schema_src, 'AA_Finance_Schema::install()');
ac_assert(
    'AA_Finance_Schema::install() se ejecuta ANTES de consolidar aa_db_version',
    $fin_install_pos !== false && $bump_pos !== false && $fin_install_pos < $bump_pos
);

preg_match('/\$containers_sql\s*=\s*"([^"]+)";/s', $finance_schema_src, $m_c);
$containers_sql_src = $m_c[1] ?? '';

preg_match('/\$records_sql\s*=\s*"([^"]+)";/s', $finance_schema_src, $m_r);
$records_sql_src = $m_r[1] ?? '';

// Verificaciones DDL aa_finance_containers
ac_assert('Define constante TABLE_CONTAINERS', strpos($finance_schema_src, "TABLE_CONTAINERS = 'aa_finance_containers'") !== false);
ac_assert('Contenedores id bigint(20) unsigned NOT NULL AUTO_INCREMENT', strpos($containers_sql_src, 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT') !== false);
ac_assert('Contenedores variant_key varchar(64) NOT NULL', strpos($containers_sql_src, 'variant_key varchar(64) NOT NULL') !== false);
ac_assert('Contenedores variant_key sin DEFAULT en DDL', strpos($containers_sql_src, 'variant_key varchar(64) NOT NULL DEFAULT') === false);
ac_assert('Contenedores title varchar(200) NOT NULL', strpos($containers_sql_src, 'title varchar(200) NOT NULL') !== false);
ac_assert('Contenedores details text DEFAULT NULL', strpos($containers_sql_src, 'details text DEFAULT NULL') !== false);
ac_assert('Contenedores created_at datetime NOT NULL', strpos($containers_sql_src, 'created_at datetime NOT NULL') !== false);
ac_assert('Contenedores PRIMARY KEY  (id) con dos espacios', strpos($containers_sql_src, 'PRIMARY KEY  (id)') !== false);
ac_assert('Contenedores índice compuesto (variant_key, created_at, id)', strpos($containers_sql_src, 'KEY idx_variant_created (variant_key, created_at, id)') !== false);
ac_assert('Contenedores ENGINE=InnoDB explícito', strpos($containers_sql_src, 'ENGINE=InnoDB') !== false);
ac_assert('Contenedores no incluye family_key', strpos($containers_sql_src, 'family_key') === false);
ac_assert('Contenedores no incluye updated_at', strpos($containers_sql_src, 'updated_at') === false);
ac_assert('Contenedores no incluye created_by', strpos($containers_sql_src, 'created_by') === false);

// Verificaciones DDL aa_finance_records
ac_assert('Define constante TABLE_RECORDS', strpos($finance_schema_src, "TABLE_RECORDS = 'aa_finance_records'") !== false);
ac_assert('Registros id bigint(20) unsigned NOT NULL AUTO_INCREMENT', strpos($records_sql_src, 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT') !== false);
ac_assert('Registros container_id bigint(20) unsigned NOT NULL', strpos($records_sql_src, 'container_id bigint(20) unsigned NOT NULL') !== false);
ac_assert('Registros title varchar(200) NOT NULL', strpos($records_sql_src, 'title varchar(200) NOT NULL') !== false);
ac_assert('Registros details text DEFAULT NULL', strpos($records_sql_src, 'details text DEFAULT NULL') !== false);
ac_assert('Registros amount decimal(19,2) DEFAULT NULL', strpos($records_sql_src, 'amount decimal(19,2) DEFAULT NULL') !== false);
ac_assert('Registros amount signed (sin unsigned)', strpos($records_sql_src, 'amount decimal(19,2) unsigned') === false);
ac_assert('Registros created_at datetime NOT NULL', strpos($records_sql_src, 'created_at datetime NOT NULL') !== false);
ac_assert('Registros índice compuesto (container_id, created_at, id)', strpos($records_sql_src, 'KEY idx_container_created (container_id, created_at, id)') !== false);
ac_assert('DDL enviado a dbDelta NO contiene FOREIGN KEY', !preg_match('/CREATE TABLE[^\;]+FOREIGN KEY/i', $containers_sql_src . $records_sql_src));

// Verificaciones de FK y nombrado
ac_assert('Método foreign_key_name presente', strpos($finance_schema_src, 'function foreign_key_name(') !== false);
ac_assert('ensure_foreign_key ejecuta ADD CONSTRAINT FOREIGN KEY ON DELETE CASCADE', strpos($finance_schema_src, 'ON DELETE CASCADE') !== false);

echo "\n=== 2. Pruebas de robustez de foreign_key_name() ===\n";

$fk_default = AA_Finance_Schema::foreign_key_name('wp_');
ac_assert('FK name para wp_ usa caracteres válidos', (bool) preg_match('/^[a-zA-Z0-9_]+$/', $fk_default));
ac_assert('FK name para wp_ tiene longitud <= 64', strlen($fk_default) <= 64);
ac_assert('FK name es determinista ante llamadas repetidas', $fk_default === AA_Finance_Schema::foreign_key_name('wp_'));

$long_a = 'wp_very_long_prefix_with_more_than_50_chars_that_shares_same_start_branch_alpha_';
$long_b = 'wp_very_long_prefix_with_more_than_50_chars_that_shares_same_start_branch_beta_';
$fk_long_a = AA_Finance_Schema::foreign_key_name($long_a);
$fk_long_b = AA_Finance_Schema::foreign_key_name($long_b);

ac_assert('FK name para prefijo largo A tiene longitud <= 64', strlen($fk_long_a) <= 64);
ac_assert('FK name para prefijo largo B tiene longitud <= 64', strlen($fk_long_b) <= 64);
ac_assert('FK names para prefijos largos con mismo inicio NO colisionan', $fk_long_a !== $fk_long_b);

echo "\n=== 3. Pruebas con doble de \$wpdb (Prefijos, FK e Idempotencia) ===\n";

class TestFinanceWpdbMock {
    public $prefix = 'wp_';
    public $last_error = '';
    public $queries = [];
    public $table_status = [];
    public $columns = [];
    public $indexes = [];
    public $create_tables = [];

    public function get_charset_collate(): string {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function query(string $query) {
        $this->queries[] = $query;
        return 1;
    }

    public function prepare(string $query, ...$args): string {
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdf]/', $val, $query, 1);
        }
        return $query;
    }

    public function get_row(string $query, $output = ARRAY_A) {
        $this->queries[] = $query;

        if (preg_match("/SHOW TABLE STATUS WHERE Name = '([^']+)'/i", $query, $m)) {
            $tbl = $m[1];
            return $this->table_status[$tbl] ?? ['Name' => $tbl, 'Engine' => 'InnoDB'];
        }

        if (preg_match("/SHOW CREATE TABLE `?([^`'\s]+)`?/i", $query, $m)) {
            $tbl = $m[1];
            return ['Create Table' => $this->create_tables[$tbl] ?? ''];
        }

        return null;
    }

    public function get_results(string $query, $output = ARRAY_A) {
        $this->queries[] = $query;

        if (preg_match("/SHOW FULL COLUMNS FROM `?([^`'\s]+)`?/i", $query, $m)) {
            $tbl = $m[1];
            return $this->columns[$tbl] ?? [];
        }

        if (preg_match("/SHOW INDEX FROM `?([^`'\s]+)`?/i", $query, $m)) {
            $tbl = $m[1];
            return $this->indexes[$tbl] ?? [];
        }

        return [];
    }

    public function get_var(string $query) {
        $this->queries[] = $query;
        return null;
    }
}

global $wpdb;
$real_wpdb = $wpdb;
$wpdb = new TestFinanceWpdbMock();

function seed_valid_mock_schema(TestFinanceWpdbMock $mock, string $prefix = 'wp_'): void {
    $c_table = $prefix . 'aa_finance_containers';
    $r_table = $prefix . 'aa_finance_records';
    $fk_name = AA_Finance_Schema::foreign_key_name($prefix);

    $mock->prefix = $prefix;
    $mock->table_status[$c_table] = ['Name' => $c_table, 'Engine' => 'InnoDB'];
    $mock->table_status[$r_table] = ['Name' => $r_table, 'Engine' => 'InnoDB'];

    $mock->columns[$c_table] = [
        ['Field' => 'id', 'Type' => 'bigint(20) unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => 'auto_increment'],
        ['Field' => 'variant_key', 'Type' => 'varchar(64)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
        ['Field' => 'title', 'Type' => 'varchar(200)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
        ['Field' => 'details', 'Type' => 'text', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
        ['Field' => 'created_at', 'Type' => 'datetime', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
    ];

    $mock->indexes[$c_table] = [
        ['Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Seq_in_index' => '1'],
        ['Key_name' => 'idx_variant_created', 'Column_name' => 'variant_key', 'Seq_in_index' => '1'],
        ['Key_name' => 'idx_variant_created', 'Column_name' => 'created_at', 'Seq_in_index' => '2'],
        ['Key_name' => 'idx_variant_created', 'Column_name' => 'id', 'Seq_in_index' => '3'],
    ];

    $mock->columns[$r_table] = [
        ['Field' => 'id', 'Type' => 'bigint(20) unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => 'auto_increment'],
        ['Field' => 'container_id', 'Type' => 'bigint(20) unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
        ['Field' => 'title', 'Type' => 'varchar(200)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
        ['Field' => 'details', 'Type' => 'text', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
        ['Field' => 'amount', 'Type' => 'decimal(19,2)', 'Null' => 'YES', 'Default' => null, 'Extra' => ''],
        ['Field' => 'created_at', 'Type' => 'datetime', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
    ];

    $mock->indexes[$r_table] = [
        ['Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Seq_in_index' => '1'],
        ['Key_name' => 'idx_container_created', 'Column_name' => 'container_id', 'Seq_in_index' => '1'],
        ['Key_name' => 'idx_container_created', 'Column_name' => 'created_at', 'Seq_in_index' => '2'],
        ['Key_name' => 'idx_container_created', 'Column_name' => 'id', 'Seq_in_index' => '3'],
    ];

    $mock->create_tables[$r_table] = "CREATE TABLE `{$r_table}` (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `container_id` bigint(20) unsigned NOT NULL,
        CONSTRAINT `{$fk_name}` FOREIGN KEY (`container_id`) REFERENCES `{$c_table}` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
}

// Verificación exitosa en mock
seed_valid_mock_schema($wpdb, 'wp_');
$verify_ok = true;
try {
    AA_Finance_Schema::verify();
} catch (\Throwable $e) {
    $verify_ok = false;
}
ac_assert('AA_Finance_Schema::verify() pasa con mock válido', $verify_ok === true);

// Fallo cerrado: Motor no InnoDB
$wpdb->table_status['wp_aa_finance_containers']['Engine'] = 'MyISAM';
$caught_engine = false;
try {
    AA_Finance_Schema::verify();
} catch (\RuntimeException $e) {
    $caught_engine = (strpos($e->getMessage(), 'Motor inválido') !== false);
}
ac_assert('Fallo cerrado ante motor no-InnoDB', $caught_engine === true);

// Fallo cerrado: Columna con default en variant_key
seed_valid_mock_schema($wpdb, 'wp_');
$wpdb->columns['wp_aa_finance_containers'][1]['Default'] = 'general';
$caught_default = false;
try {
    AA_Finance_Schema::verify();
} catch (\RuntimeException $e) {
    $caught_default = (strpos($e->getMessage(), 'variant_key en wp_aa_finance_containers no debe tener DEFAULT') !== false);
}
ac_assert('Fallo cerrado ante variant_key con DEFAULT', $caught_default === true);

// Fallo cerrado: amount unsigned
seed_valid_mock_schema($wpdb, 'wp_');
$wpdb->columns['wp_aa_finance_records'][4]['Type'] = 'decimal(19,2) unsigned';
$caught_unsigned = false;
try {
    AA_Finance_Schema::verify();
} catch (\RuntimeException $e) {
    $caught_unsigned = (strpos($e->getMessage(), 'amount en wp_aa_finance_records debe ser signed') !== false);
}
ac_assert('Fallo cerrado ante amount unsigned', $caught_unsigned === true);

// Fallo cerrado: Foreign key ausente
seed_valid_mock_schema($wpdb, 'wp_');
$wpdb->create_tables['wp_aa_finance_records'] = "CREATE TABLE `wp_aa_finance_records` (`id` bigint(20)) ENGINE=InnoDB;";
$caught_fk = false;
try {
    AA_Finance_Schema::verify();
} catch (\RuntimeException $e) {
    $caught_fk = (strpos($e->getMessage(), 'con ON DELETE CASCADE ausente') !== false);
}
ac_assert('Fallo cerrado ante foreign key ausente', $caught_fk === true);

// Fallo cerrado: Columna prohibida presente
seed_valid_mock_schema($wpdb, 'wp_');
$wpdb->columns['wp_aa_finance_containers'][] = ['Field' => 'family_key', 'Type' => 'varchar(64)', 'Null' => 'NO'];
$caught_forbidden = false;
try {
    AA_Finance_Schema::verify();
} catch (\RuntimeException $e) {
    $caught_forbidden = (strpos($e->getMessage(), 'Columna no permitida presente') !== false);
}
ac_assert('Fallo cerrado ante columna prohibida (family_key)', $caught_forbidden === true);

// Restaurar $wpdb original
$wpdb = $real_wpdb;

echo "\n=== 4. Integración MySQL / MariaDB real (Prefijo Desechable) ===\n";

if ($has_real_wp) {
    echo "Entorno WordPress conectado en {$wp_root}.\n";
    echo "Base de datos activa: " . DB_NAME . "\n";

    $original_prefix = $wpdb->prefix;
    $temp_prefix_1 = 'tmp_fin_' . substr(md5(uniqid('t1', true)), 0, 8) . '_';
    $temp_prefix_2 = 'tmp_fin_' . substr(md5(uniqid('t2', true)), 0, 8) . '_';

    $cleanup_tables = function(string $p) use ($wpdb) {
        $r_table = $p . AA_Finance_Schema::TABLE_RECORDS;
        $c_table = $p . AA_Finance_Schema::TABLE_CONTAINERS;
        $wpdb->query("DROP TABLE IF EXISTS `{$r_table}`");
        $wpdb->query("DROP TABLE IF EXISTS `{$c_table}`");
    };

    try {
        // 4.1 Instalación y verificación en Prefijo 1
        $wpdb->prefix = $temp_prefix_1;
        $cleanup_tables($temp_prefix_1);

        AA_Finance_Schema::install();
        ac_assert('MySQL real: primera instalación completada sin errores', true);

        $verified_1 = true;
        try {
            AA_Finance_Schema::verify();
        } catch (\Throwable $e) {
            $verified_1 = false;
        }
        ac_assert('MySQL real: postcondiciones verificadas exitosamente', $verified_1);

        // 4.2 Idempotencia en Prefijo 1
        $idempotent_ok = true;
        try {
            AA_Finance_Schema::install();
            AA_Finance_Schema::verify();
        } catch (\Throwable $e) {
            $idempotent_ok = false;
        }
        ac_assert('MySQL real: segunda instalación es 100% idempotente', $idempotent_ok);

        $c1_table = AA_Finance_Schema::containers_table_name();
        $r1_table = AA_Finance_Schema::records_table_name();

        // 4.3 Comprobación de rechazo de registro huérfano (FK real activa)
        $wpdb->suppress_errors(true);
        $insert_orphan = $wpdb->insert(
            $r1_table,
            [
                'container_id' => 99999999, // Inexistente
                'title' => 'Registro huérfano',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );
        $wpdb->suppress_errors(false);
        ac_assert('MySQL real: rechazo estricto de registro huérfano (FK activa)', $insert_orphan === false);

        // 4.4 Inserción de contenedor y registros válidos
        $now = current_time('mysql');
        $wpdb->insert(
            $c1_table,
            [
                'variant_key' => 'general',
                'title' => 'Presupuesto Principal',
                'details' => 'Detalle del presupuesto',
                'created_at' => $now,
            ],
            ['%s', '%s', '%s', '%s']
        );
        $container_id = (int) $wpdb->insert_id;
        ac_assert('MySQL real: contenedor insertado correctamente', $container_id > 0);

        // 4.5 Casos de amount: NULL, 0.00, 1.85, negativo (-250.75)
        $wpdb->insert(
            $r1_table,
            ['container_id' => $container_id, 'title' => 'Sin amount', 'amount' => null, 'created_at' => $now],
            ['%d', '%s', null, '%s']
        );
        $wpdb->insert(
            $r1_table,
            ['container_id' => $container_id, 'title' => 'Cero', 'amount' => '0.00', 'created_at' => $now],
            ['%d', '%s', '%s', '%s']
        );
        $wpdb->insert(
            $r1_table,
            ['container_id' => $container_id, 'title' => 'Decimal positivo', 'amount' => '1.85', 'created_at' => $now],
            ['%d', '%s', '%s', '%s']
        );
        $wpdb->insert(
            $r1_table,
            ['container_id' => $container_id, 'title' => 'Decimal negativo', 'amount' => '-250.75', 'created_at' => $now],
            ['%d', '%s', '%s', '%s']
        );

        $saved_records = $wpdb->get_results(
            $wpdb->prepare("SELECT title, amount FROM `{$r1_table}` WHERE container_id = %d ORDER BY id ASC", $container_id),
            ARRAY_A
        );

        ac_assert('MySQL real: se insertaron 4 registros con diversos amounts', count($saved_records) === 4);
        ac_assert('MySQL real: amount NULL preservado', $saved_records[0]['amount'] === null);
        ac_assert('MySQL real: amount 0.00 preservado', $saved_records[1]['amount'] === '0.00');
        ac_assert('MySQL real: amount 1.85 preservado', $saved_records[2]['amount'] === '1.85');
        ac_assert('MySQL real: amount -250.75 firmado preservado', $saved_records[3]['amount'] === '-250.75');

        // 4.6 Detalles extensos >= 10,000 caracteres
        $large_text = str_repeat('DEOIA Canonical Finance text payload - 1234567890. ', 250); // ~12,750 chars
        $wpdb->insert(
            $r1_table,
            [
                'container_id' => $container_id,
                'title' => 'Registro con texto extenso',
                'details' => $large_text,
                'created_at' => $now,
            ],
            ['%d', '%s', '%s', '%s']
        );
        $large_rec_id = (int) $wpdb->insert_id;
        $retrieved_text = $wpdb->get_var($wpdb->prepare("SELECT details FROM `{$r1_table}` WHERE id = %d", $large_rec_id));
        ac_assert(
            'MySQL real: details extenso (>=10,000 caracteres) almacenado sin truncar',
            $retrieved_text === $large_text && strlen($retrieved_text) >= 10000
        );

        // 4.7 Borrado en cascada (ON DELETE CASCADE)
        $wpdb->query($wpdb->prepare("DELETE FROM `{$c1_table}` WHERE id = %d", $container_id));
        $remaining_records = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$r1_table}` WHERE container_id = %d", $container_id));
        ac_assert('MySQL real: borrar contenedor elimina en cascada todos sus registros', $remaining_records === 0);

        // 4.8 Aislamiento e instalación en Prefijo 2 simultáneo
        $wpdb->prefix = $temp_prefix_2;
        $cleanup_tables($temp_prefix_2);

        AA_Finance_Schema::install();
        AA_Finance_Schema::verify();
        ac_assert('MySQL real: Prefijo 2 instalado y verificado sin interferir con Prefijo 1', true);

        $c2_table = AA_Finance_Schema::containers_table_name();
        $wpdb->insert(
            $c2_table,
            ['variant_key' => 'general', 'title' => 'Contenedor Prefijo 2', 'created_at' => $now],
            ['%s', '%s', '%s']
        );
        $count_p1 = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c1_table}`");
        $count_p2 = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$c2_table}`");
        ac_assert('MySQL real: aislamiento de datos estricto entre prefijo 1 y prefijo 2', $count_p1 === 0 && $count_p2 === 1);

        // 4.9 Reparación de estructura parcial (ej. FK eliminada)
        $fk2_name = AA_Finance_Schema::foreign_key_name($temp_prefix_2);
        $r2_table = AA_Finance_Schema::records_table_name();
        $wpdb->query("ALTER TABLE `{$r2_table}` DROP FOREIGN KEY `{$fk2_name}`");

        $verify_failed_as_expected = false;
        try {
            AA_Finance_Schema::verify();
        } catch (\RuntimeException $e) {
            $verify_failed_as_expected = true;
        }
        ac_assert('MySQL real: verify detecta y rechaza estructura con FK eliminada', $verify_failed_as_expected);

        // Reejecutar install para reparar
        AA_Finance_Schema::install();
        $repaired = true;
        try {
            AA_Finance_Schema::verify();
        } catch (\Throwable $e) {
            $repaired = false;
        }
        ac_assert('MySQL real: install repara automáticamente la foreign key ausente', $repaired);

    } finally {
        // Limpieza garantizada de tablas temporales
        $cleanup_tables($temp_prefix_1);
        $cleanup_tables($temp_prefix_2);
        $wpdb->prefix = $original_prefix;
        echo "Limpieza garantizada: tablas temporales de {$temp_prefix_1} y {$temp_prefix_2} eliminadas.\n";
    }

} else {
    echo "[INFO / SKIP] Integración MySQL real en vivo no configurada (AA_WP_ROOT no definido o no accesible).\n";
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
