<?php
/**
 * Finance Schema — Definición, creación física y verificación del esquema de Finanzas.
 *
 * Tablas:
 * - aa_finance_containers
 * - aa_finance_records
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

final class AA_Finance_Schema {

    public const TABLE_CONTAINERS = 'aa_finance_containers';
    public const TABLE_RECORDS = 'aa_finance_records';

    /**
     * Nombre de la tabla de contenedores con prefijo actual de $wpdb.
     */
    public static function containers_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_CONTAINERS;
    }

    /**
     * Nombre de la tabla de registros con prefijo actual de $wpdb.
     */
    public static function records_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_RECORDS;
    }

    /**
     * Nombre único y determinista del constraint de foreign key, aislado por prefijo de blog.
     * Incorpora hash de la identidad completa del prefijo y relación para evitar colisiones
     * en prefijos largos truncados.
     * Longitud máxima en MySQL: 64 caracteres.
     */
    public static function foreign_key_name(?string $prefix = null): string {
        global $wpdb;
        $raw_prefix = $prefix !== null ? $prefix : (string) ($wpdb->prefix ?? '');
        $clean_prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $raw_prefix);
        $prefix_part = rtrim(substr($clean_prefix, 0, 32), '_');
        
        $hash = substr(md5($raw_prefix . ':aa_finance_records:container_id'), 0, 16);
        $base = $prefix_part !== '' ? 'fk_' . $prefix_part . '_aa_fin_rec_' : 'fk_aa_fin_rec_';
        
        return substr($base . $hash, 0, 64);
    }

    /**
     * Instala las tablas físicas de Finanzas, aplica el constraint de integridad y verifica postcondiciones.
     *
     * @throws \RuntimeException Si la creación, constraint o verificación falla.
     */
    public static function install(): void {
        global $wpdb;

        $containers_table = self::containers_table_name();
        $records_table = self::records_table_name();
        $charset = $wpdb->get_charset_collate();

        // 1. DDL de tabla de contenedores (sin foreign key para dbDelta)
        $containers_sql = "CREATE TABLE {$containers_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            variant_key varchar(64) NOT NULL,
            title varchar(200) NOT NULL,
            details text DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_variant_created (variant_key, created_at, id),
            KEY idx_variant_updated (variant_key, updated_at, id)
        ) ENGINE=InnoDB {$charset};";

        // 2. DDL de tabla de registros (sin foreign key para dbDelta)
        $records_sql = "CREATE TABLE {$records_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            container_id bigint(20) unsigned NOT NULL,
            title varchar(200) NOT NULL,
            details text DEFAULT NULL,
            amount decimal(19,2) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_container_created (container_id, created_at, id),
            KEY idx_container_updated (container_id, updated_at, id)
        ) ENGINE=InnoDB {$charset};";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($containers_sql);
        dbDelta($records_sql);

        // 3. Columnas e índices canónicos (dbDelta no es fiable para todos los casos)
        self::ensure_updated_at_columns();
        self::ensure_canonical_read_indexes();

        // 4. Aplicar Foreign Key real con ON DELETE CASCADE de forma idempotente
        self::ensure_foreign_key();

        // 5. Verificación estricta de postcondiciones (fail-closed)
        self::verify();
    }

    /**
     * Garantiza updated_at datetime NOT NULL en ambas tablas Finance (SB1-4A).
     */
    private static function ensure_updated_at_columns(): void {
        self::ensure_datetime_not_null_column(self::containers_table_name(), 'updated_at');
        self::ensure_datetime_not_null_column(self::records_table_name(), 'updated_at');
    }

    /**
     * Índices de lectura canónica futura (SB1-4B) sin retirar los de created_at.
     */
    private static function ensure_canonical_read_indexes(): void {
        $containers_table = self::containers_table_name();
        $records_table = self::records_table_name();

        self::ensure_named_index(
            $containers_table,
            'idx_variant_updated',
            'ALTER TABLE `' . $containers_table . '` ADD KEY idx_variant_updated (variant_key, updated_at, id)'
        );
        self::ensure_named_index(
            $records_table,
            'idx_container_updated',
            'ALTER TABLE `' . $records_table . '` ADD KEY idx_container_updated (container_id, updated_at, id)'
        );
    }

    private static function ensure_datetime_not_null_column(string $table, string $column): void {
        global $wpdb;

        $existing = self::column_definition($table, $column);
        if ($existing !== null) {
            $null_ok = strtoupper((string) ($existing['Null'] ?? '')) === 'NO';
            $type_ok = stripos((string) ($existing['Type'] ?? ''), 'datetime') !== false;
            if ($null_ok && $type_ok) {
                return;
            }
            $wpdb->query("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` datetime NOT NULL");
        } else {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` datetime NOT NULL");
        }

        $existing = self::column_definition($table, $column);
        if (
            $existing === null
            || stripos((string) ($existing['Type'] ?? ''), 'datetime') === false
            || strtoupper((string) ($existing['Null'] ?? '')) !== 'NO'
        ) {
            $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                ? $wpdb->last_error
                : 'columna inválida tras ALTER';
            throw new \RuntimeException(
                "[AA_Finance_Schema] No se pudo asegurar {$column} en {$table}: {$error}"
            );
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function column_definition(string $table, string $column): ?array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    private static function ensure_named_index(string $table, string $index_name, string $alter_sql): void {
        global $wpdb;

        $existing = $wpdb->get_results(
            $wpdb->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index_name)
        );
        if (!empty($existing)) {
            return;
        }

        $wpdb->query($alter_sql);

        $existing = $wpdb->get_results(
            $wpdb->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index_name)
        );
        if (empty($existing)) {
            $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                ? $wpdb->last_error
                : 'índice ausente tras ALTER';
            throw new \RuntimeException(
                "[AA_Finance_Schema] No se pudo crear índice {$index_name} en {$table}: {$error}"
            );
        }
    }

    /**
     * Agrega el constraint de foreign key con ON DELETE CASCADE de forma idempotente.
     */
    public static function ensure_foreign_key(): void {
        global $wpdb;

        $containers_table = self::containers_table_name();
        $records_table = self::records_table_name();
        $fk_name = self::foreign_key_name();

        if (self::foreign_key_exists($records_table, $fk_name)) {
            return;
        }

        $sql = "ALTER TABLE `{$records_table}` ADD CONSTRAINT `{$fk_name}` FOREIGN KEY (`container_id`) REFERENCES `{$containers_table}` (`id`) ON DELETE CASCADE";
        $result = $wpdb->query($sql);

        if ($result === false && !empty($wpdb->last_error)) {
            if (!self::foreign_key_exists($records_table, $fk_name)) {
                throw new \RuntimeException(
                    '[AA_Finance_Schema] Error al agregar foreign key: ' . $wpdb->last_error
                );
            }
        }
    }

    /**
     * Verifica exhaustivamente las postcondiciones del esquema físico de Finanzas.
     *
     * @throws \RuntimeException Si cualquier postcondición no se cumple.
     */
    public static function verify(): void {
        $containers_table = self::containers_table_name();
        $records_table = self::records_table_name();
        $fk_name = self::foreign_key_name();

        // 1. Existencia y motor InnoDB
        self::verify_table_existence_and_engine($containers_table);
        self::verify_table_existence_and_engine($records_table);

        // 2. Columnas e índices de aa_finance_containers
        self::verify_containers_structure($containers_table);

        // 3. Columnas e índices de aa_finance_records
        self::verify_records_structure($records_table);

        // 4. Foreign Key con regla ON DELETE CASCADE
        self::verify_foreign_key($records_table, $containers_table, $fk_name);
    }

    private static function foreign_key_exists(string $records_table, string $fk_name): bool {
        global $wpdb;

        if (defined('DB_NAME') && DB_NAME) {
            $schema = DB_NAME;
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT CONSTRAINT_NAME 
                     FROM information_schema.REFERENTIAL_CONSTRAINTS 
                     WHERE CONSTRAINT_SCHEMA = %s 
                       AND TABLE_NAME = %s 
                       AND CONSTRAINT_NAME = %s",
                    $schema,
                    $records_table,
                    $fk_name
                ),
                ARRAY_A
            );
            if (is_array($row) && !empty($row['CONSTRAINT_NAME'])) {
                return true;
            }
        }

        $show = $wpdb->get_row("SHOW CREATE TABLE `{$records_table}`", ARRAY_A);
        if (is_array($show)) {
            $create_sql = (string) ($show['Create Table'] ?? reset($show) ?? '');
            if (preg_match('/CONSTRAINT\s+[`"]?' . preg_quote($fk_name, '/') . '[`"]?\s+FOREIGN KEY/i', $create_sql)) {
                return true;
            }
        }

        return false;
    }

    private static function verify_table_existence_and_engine(string $table): void {
        global $wpdb;

        $status = $wpdb->get_row(
            $wpdb->prepare("SHOW TABLE STATUS WHERE Name = %s", $table),
            ARRAY_A
        );

        if (!is_array($status) || empty($status['Name'])) {
            $like = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($like !== $table) {
                throw new \RuntimeException("[AA_Finance_Schema] Tabla ausente: {$table}");
            }
        }

        $engine = strtoupper((string) ($status['Engine'] ?? ''));
        if ($engine !== '' && $engine !== 'INNODB') {
            throw new \RuntimeException(
                "[AA_Finance_Schema] Motor inválido para tabla {$table}: {$engine} (se requiere InnoDB)"
            );
        }
    }

    private static function verify_containers_structure(string $table): void {
        global $wpdb;

        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
        if (!is_array($columns) || empty($columns)) {
            throw new \RuntimeException("[AA_Finance_Schema] No se pudieron leer las columnas de {$table}");
        }

        $cols_by_name = [];
        foreach ($columns as $col) {
            $cols_by_name[$col['Field']] = $col;
        }

        // Columnas requeridas y exactas
        $expected = ['id', 'variant_key', 'title', 'details', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols_by_name[$field])) {
                throw new \RuntimeException("[AA_Finance_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        // Columnas no permitidas
        $forbidden = ['family_key', 'created_by', 'currency', 'is_permanent', 'is_system', 'deleted_at', 'financial_date'];
        foreach ($forbidden as $field) {
            if (isset($cols_by_name[$field])) {
                throw new \RuntimeException("[AA_Finance_Schema] Columna no permitida presente en {$table}: {$field}");
            }
        }

        // Verificaciones de tipos y nullabilidad
        $id = $cols_by_name['id'];
        if (stripos((string) $id['Type'], 'bigint') === false || strtoupper((string) $id['Null']) !== 'NO' || stripos((string) $id['Extra'], 'auto_increment') === false) {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para id en {$table}");
        }

        $variant = $cols_by_name['variant_key'];
        if (stripos((string) $variant['Type'], 'varchar(64)') === false || strtoupper((string) $variant['Null']) !== 'NO') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para variant_key en {$table}");
        }
        if ($variant['Default'] !== null) {
            throw new \RuntimeException("[AA_Finance_Schema] variant_key en {$table} no debe tener DEFAULT");
        }

        $title = $cols_by_name['title'];
        if (stripos((string) $title['Type'], 'varchar(200)') === false || strtoupper((string) $title['Null']) !== 'NO') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para title en {$table}");
        }

        $details = $cols_by_name['details'];
        if (stripos((string) $details['Type'], 'text') === false || strtoupper((string) $details['Null']) !== 'YES') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para details en {$table}");
        }

        $created = $cols_by_name['created_at'];
        if (stripos((string) $created['Type'], 'datetime') === false || strtoupper((string) $created['Null']) !== 'NO') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para created_at en {$table}");
        }

        $updated = $cols_by_name['updated_at'];
        if (stripos((string) $updated['Type'], 'datetime') === false || strtoupper((string) $updated['Null']) !== 'NO') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para updated_at en {$table}");
        }

        // Verificación de índices
        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_composite_index($table, ['variant_key', 'created_at', 'id']);
        self::verify_composite_index($table, ['variant_key', 'updated_at', 'id']);
    }

    private static function verify_records_structure(string $table): void {
        global $wpdb;

        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
        if (!is_array($columns) || empty($columns)) {
            throw new \RuntimeException("[AA_Finance_Schema] No se pudieron leer las columnas de {$table}");
        }

        $cols_by_name = [];
        foreach ($columns as $col) {
            $cols_by_name[$col['Field']] = $col;
        }

        // Columnas requeridas y exactas
        $expected = ['id', 'container_id', 'title', 'details', 'amount', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols_by_name[$field])) {
                throw new \RuntimeException("[AA_Finance_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        // Columnas no permitidas (no duplicar variant_key ni family_key)
        $forbidden = ['family_key', 'variant_key', 'created_by', 'currency', 'is_permanent', 'is_system', 'deleted_at'];
        foreach ($forbidden as $field) {
            if (isset($cols_by_name[$field])) {
                throw new \RuntimeException("[AA_Finance_Schema] Columna no permitida presente en {$table}: {$field}");
            }
        }

        // Verificaciones de tipos y nullabilidad
        $id = $cols_by_name['id'];
        if (stripos((string) $id['Type'], 'bigint') === false || strtoupper((string) $id['Null']) !== 'NO' || stripos((string) $id['Extra'], 'auto_increment') === false) {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para id en {$table}");
        }

        $container_id = $cols_by_name['container_id'];
        if (stripos((string) $container_id['Type'], 'bigint') === false || strtoupper((string) $container_id['Null']) !== 'NO') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para container_id en {$table}");
        }

        $title = $cols_by_name['title'];
        if (stripos((string) $title['Type'], 'varchar(200)') === false || strtoupper((string) $title['Null']) !== 'NO') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para title en {$table}");
        }

        $details = $cols_by_name['details'];
        if (stripos((string) $details['Type'], 'text') === false || strtoupper((string) $details['Null']) !== 'YES') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para details en {$table}");
        }

        $amount = $cols_by_name['amount'];
        if (stripos((string) $amount['Type'], 'decimal(19,2)') === false || strtoupper((string) $amount['Null']) !== 'YES') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para amount en {$table}");
        }
        if (stripos((string) $amount['Type'], 'unsigned') !== false) {
            throw new \RuntimeException("[AA_Finance_Schema] amount en {$table} debe ser signed (no unsigned)");
        }

        $created = $cols_by_name['created_at'];
        if (stripos((string) $created['Type'], 'datetime') === false || strtoupper((string) $created['Null']) !== 'NO') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para created_at en {$table}");
        }

        $updated = $cols_by_name['updated_at'];
        if (stripos((string) $updated['Type'], 'datetime') === false || strtoupper((string) $updated['Null']) !== 'NO') {
            throw new \RuntimeException("[AA_Finance_Schema] Definición inválida para updated_at en {$table}");
        }

        // Verificación de índices
        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_composite_index($table, ['container_id', 'created_at', 'id']);
        self::verify_composite_index($table, ['container_id', 'updated_at', 'id']);
    }

    private static function verify_foreign_key(string $records_table, string $containers_table, string $fk_name): void {
        global $wpdb;

        // Intentar vía INFORMATION_SCHEMA
        if (defined('DB_NAME') && DB_NAME) {
            $schema = DB_NAME;
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE, rc.REFERENCED_TABLE_NAME 
                     FROM information_schema.REFERENTIAL_CONSTRAINTS rc 
                     WHERE rc.CONSTRAINT_SCHEMA = %s 
                       AND rc.TABLE_NAME = %s 
                       AND rc.CONSTRAINT_NAME = %s",
                    $schema,
                    $records_table,
                    $fk_name
                ),
                ARRAY_A
            );

            if (is_array($row) && !empty($row['CONSTRAINT_NAME'])) {
                if (strtoupper((string) $row['DELETE_RULE']) !== 'CASCADE') {
                    throw new \RuntimeException(
                        "[AA_Finance_Schema] DELETE_RULE para {$fk_name} no es CASCADE (es {$row['DELETE_RULE']})"
                    );
                }
                if ($row['REFERENCED_TABLE_NAME'] !== $containers_table) {
                    throw new \RuntimeException(
                        "[AA_Finance_Schema] Foreign key {$fk_name} referencia tabla errónea: {$row['REFERENCED_TABLE_NAME']}"
                    );
                }
                return;
            }
        }

        // Fallback robusto vía SHOW CREATE TABLE
        $show = $wpdb->get_row("SHOW CREATE TABLE `{$records_table}`", ARRAY_A);
        if (!is_array($show)) {
            throw new \RuntimeException("[AA_Finance_Schema] No se pudo obtener SHOW CREATE TABLE de {$records_table}");
        }

        $create_sql = (string) ($show['Create Table'] ?? reset($show) ?? '');
        $pattern = '/CONSTRAINT\s+[`"]?' . preg_quote($fk_name, '/') . '[`"]?\s+FOREIGN KEY\s+\([`"]?container_id[`"]?\)\s+REFERENCES\s+[`"]?' . preg_quote($containers_table, '/') . '[`"]?\s+\([`"]?id[`"]?\)\s+ON DELETE CASCADE/i';

        if (!preg_match($pattern, $create_sql)) {
            throw new \RuntimeException(
                "[AA_Finance_Schema] Foreign key {$fk_name} con ON DELETE CASCADE ausente o malformada en {$records_table}"
            );
        }
    }

    private static function verify_index(string $table, string $key_name, array $expected_columns): void {
        global $wpdb;

        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        if (!is_array($indexes)) {
            throw new \RuntimeException("[AA_Finance_Schema] No se pudieron leer los índices de {$table}");
        }

        $found = [];
        foreach ($indexes as $idx) {
            if ($idx['Key_name'] === $key_name) {
                $found[(int) $idx['Seq_in_index']] = $idx['Column_name'];
            }
        }
        ksort($found);

        if (array_values($found) !== array_values($expected_columns)) {
            throw new \RuntimeException(
                "[AA_Finance_Schema] Índice {$key_name} en {$table} no coincide con las columnas esperadas"
            );
        }
    }

    private static function verify_composite_index(string $table, array $expected_columns): void {
        global $wpdb;

        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        if (!is_array($indexes)) {
            throw new \RuntimeException("[AA_Finance_Schema] No se pudieron leer los índices de {$table}");
        }

        $by_key = [];
        foreach ($indexes as $idx) {
            $key = $idx['Key_name'];
            $by_key[$key][(int) $idx['Seq_in_index']] = $idx['Column_name'];
        }

        foreach ($by_key as $key_name => $cols) {
            ksort($cols);
            if (array_values($cols) === array_values($expected_columns)) {
                return; // Encontrado
            }
        }

        $expected_str = implode(', ', $expected_columns);
        throw new \RuntimeException(
            "[AA_Finance_Schema] Índice compuesto ({$expected_str}) ausente en {$table}"
        );
    }
}
