<?php
/**
 * Canonical Schema — Persistencia Canónica Universal (PCU-2).
 *
 * Tablas (vacías; sin seeds ni filas):
 * - aa_canonical_families
 * - aa_canonical_containers
 * - aa_canonical_records
 *
 * Patrón técnico: dbDelta → ALTER FK idempotente → verify() fail-closed.
 * No escribe timestamps ni genera public_id (sin filas en PCU-2).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Schema {

    public const TABLE_FAMILIES = 'aa_canonical_families';
    public const TABLE_CONTAINERS = 'aa_canonical_containers';
    public const TABLE_RECORDS = 'aa_canonical_records';

    public static function families_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_FAMILIES;
    }

    public static function containers_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_CONTAINERS;
    }

    public static function records_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_RECORDS;
    }

    /**
     * Nombre determinista del FK containers.family_id → families.id.
     * Longitud máxima en MySQL: 64 caracteres.
     */
    public static function containers_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_containers:family_id',
            'aa_can_cont_'
        );
    }

    /**
     * Nombre determinista del FK records.container_id → containers.id.
     */
    public static function records_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_records:container_id',
            'aa_can_rec_'
        );
    }

    private static function build_foreign_key_name(
        ?string $prefix,
        string $relation_identity,
        string $short_token
    ): string {
        global $wpdb;
        $raw_prefix = $prefix !== null ? $prefix : (string) ($wpdb->prefix ?? '');
        $clean_prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $raw_prefix);
        $prefix_part = rtrim(substr((string) $clean_prefix, 0, 32), '_');

        $hash = substr(md5($raw_prefix . ':' . $relation_identity), 0, 16);
        $base = $prefix_part !== ''
            ? 'fk_' . $prefix_part . '_' . $short_token
            : 'fk_' . $short_token;

        return substr($base . $hash, 0, 64);
    }

    /**
     * Instala las tres tablas universales, aplica FKs y verifica postcondiciones.
     * No inserta, actualiza ni elimina filas.
     *
     * @throws \RuntimeException Si la creación, constraint o verificación falla.
     */
    public static function install(): void {
        global $wpdb;

        $families_table = self::families_table_name();
        $containers_table = self::containers_table_name();
        $records_table = self::records_table_name();
        $charset = $wpdb->get_charset_collate();

        $families_sql = "CREATE TABLE {$families_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            family_key varchar(64) NOT NULL,
            is_enabled tinyint(1) NOT NULL DEFAULT 0,
            seed_version smallint(5) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_family_key (family_key),
            KEY idx_enabled_key (is_enabled, family_key)
        ) ENGINE=InnoDB {$charset};";

        $containers_sql = "CREATE TABLE {$containers_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            family_id bigint(20) unsigned NOT NULL,
            title varchar(200) NOT NULL,
            details text DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_container_public_id (public_id),
            KEY idx_family_updated (family_id, updated_at, id)
        ) ENGINE=InnoDB {$charset};";

        $records_sql = "CREATE TABLE {$records_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            container_id bigint(20) unsigned NOT NULL,
            title varchar(200) NOT NULL,
            details text DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_record_public_id (public_id),
            KEY idx_container_updated (container_id, updated_at, id)
        ) ENGINE=InnoDB {$charset};";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($families_sql);
        dbDelta($containers_sql);
        dbDelta($records_sql);

        self::ensure_containers_family_scope_v22();
        self::ensure_named_indexes();
        self::ensure_foreign_keys();
        self::verify();
    }

    /**
     * Migración DB 21→22: alcance de contenedores por familia (sin variant_key).
     *
     * Orden con FKs activos (nunca desactiva checks):
     * 1) CREATE idx_family_updated
     * 2) Confirmación de existencia
     * 3) DROP idx_family_variant_updated
     * 4) DROP COLUMN variant_key
     *
     * Idempotente: instalaciones nuevas ya llegan sin variant_key.
     *
     * @throws \RuntimeException
     */
    public static function ensure_containers_family_scope_v22(): void {
        global $wpdb;

        $table = self::containers_table_name();
        $cols = self::columns_by_name($table);
        $has_variant = isset($cols['variant_key']);

        self::ensure_named_index(
            $table,
            'idx_family_updated',
            "ALTER TABLE `{$table}` ADD KEY idx_family_updated (family_id, updated_at, id)"
        );

        $idx = $wpdb->get_results(
            $wpdb->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = %s", 'idx_family_updated')
        );
        if (empty($idx)) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] idx_family_updated ausente en {$table} tras ensure v22"
            );
        }

        if (!$has_variant) {
            return;
        }

        $old_idx = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
                'idx_family_variant_updated'
            )
        );
        if (!empty($old_idx)) {
            $drop = $wpdb->query("ALTER TABLE `{$table}` DROP INDEX `idx_family_variant_updated`");
            if ($drop === false) {
                $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : 'DROP INDEX falló';
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] No se pudo eliminar idx_family_variant_updated: {$error}"
                );
            }
        }

        $drop_col = $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN `variant_key`");
        if ($drop_col === false) {
            $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                ? $wpdb->last_error
                : 'DROP COLUMN falló';
            // Idempotencia: si otro proceso ya eliminó la columna, columns_by_name lo confirma.
            $cols_after = self::columns_by_name($table);
            if (isset($cols_after['variant_key'])) {
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] No se pudo eliminar variant_key: {$error}"
                );
            }
        }
    }

    private static function ensure_named_indexes(): void {
        $families = self::families_table_name();
        $containers = self::containers_table_name();
        $records = self::records_table_name();

        self::ensure_named_index(
            $families,
            'uq_family_key',
            "ALTER TABLE `{$families}` ADD UNIQUE KEY uq_family_key (family_key)"
        );
        self::ensure_named_index(
            $families,
            'idx_enabled_key',
            "ALTER TABLE `{$families}` ADD KEY idx_enabled_key (is_enabled, family_key)"
        );
        self::ensure_named_index(
            $containers,
            'uq_container_public_id',
            "ALTER TABLE `{$containers}` ADD UNIQUE KEY uq_container_public_id (public_id)"
        );
        self::ensure_named_index(
            $containers,
            'idx_family_updated',
            "ALTER TABLE `{$containers}` ADD KEY idx_family_updated (family_id, updated_at, id)"
        );
        self::ensure_named_index(
            $records,
            'uq_record_public_id',
            "ALTER TABLE `{$records}` ADD UNIQUE KEY uq_record_public_id (public_id)"
        );
        self::ensure_named_index(
            $records,
            'idx_container_updated',
            "ALTER TABLE `{$records}` ADD KEY idx_container_updated (container_id, updated_at, id)"
        );
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
                "[AA_Canonical_Schema] No se pudo crear índice {$index_name} en {$table}: {$error}"
            );
        }
    }

    public static function ensure_foreign_keys(): void {
        self::ensure_foreign_key(
            self::containers_table_name(),
            self::containers_foreign_key_name(),
            'family_id',
            self::families_table_name(),
            'RESTRICT'
        );
        self::ensure_foreign_key(
            self::records_table_name(),
            self::records_foreign_key_name(),
            'container_id',
            self::containers_table_name(),
            'CASCADE'
        );
    }

    private static function ensure_foreign_key(
        string $table,
        string $fk_name,
        string $column,
        string $referenced_table,
        string $delete_rule
    ): void {
        global $wpdb;

        if (self::foreign_key_exists($table, $fk_name)) {
            return;
        }

        $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fk_name}` "
            . "FOREIGN KEY (`{$column}`) REFERENCES `{$referenced_table}` (`id`) "
            . "ON DELETE {$delete_rule}";
        $result = $wpdb->query($sql);

        if ($result === false && !empty($wpdb->last_error)) {
            if (!self::foreign_key_exists($table, $fk_name)) {
                throw new \RuntimeException(
                    '[AA_Canonical_Schema] Error al agregar foreign key: ' . $wpdb->last_error
                );
            }
        }
    }

    /**
     * @throws \RuntimeException Si cualquier postcondición no se cumple.
     */
    public static function verify(): void {
        $families = self::families_table_name();
        $containers = self::containers_table_name();
        $records = self::records_table_name();

        self::verify_table_existence_and_engine($families);
        self::verify_table_existence_and_engine($containers);
        self::verify_table_existence_and_engine($records);

        self::verify_families_structure($families);
        self::verify_containers_structure($containers);
        self::verify_records_structure($records);

        self::verify_foreign_key(
            $containers,
            $families,
            self::containers_foreign_key_name(),
            'family_id',
            'RESTRICT'
        );
        self::verify_foreign_key(
            $records,
            $containers,
            self::records_foreign_key_name(),
            'container_id',
            'CASCADE'
        );
    }

    private static function foreign_key_exists(string $table, string $fk_name): bool {
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
                    $table,
                    $fk_name
                ),
                ARRAY_A
            );
            if (is_array($row) && !empty($row['CONSTRAINT_NAME'])) {
                return true;
            }
        }

        $show = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_A);
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
            $wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table),
            ARRAY_A
        );

        if (!is_array($status) || empty($status['Name'])) {
            $like = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($like !== $table) {
                throw new \RuntimeException("[AA_Canonical_Schema] Tabla ausente: {$table}");
            }
        }

        $engine = strtoupper((string) ($status['Engine'] ?? ''));
        if ($engine !== '' && $engine !== 'INNODB') {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] Motor inválido para tabla {$table}: {$engine} (se requiere InnoDB)"
            );
        }
    }

    private static function verify_families_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['id', 'family_key', 'is_enabled', 'seed_version', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        $forbidden = [
            'label', 'copy', 'amount', 'currency', 'created_by', 'owner_user_id',
            'deleted_at', 'public_id', 'variant_key', 'title', 'details',
        ];
        self::assert_forbidden_columns($table, $cols, $forbidden);

        self::assert_id_column($table, $cols['id']);
        self::assert_varchar_not_null_no_default($table, $cols['family_key'], 'family_key', 64);
        self::assert_tinyint_not_null_default_zero($table, $cols['is_enabled'], 'is_enabled');
        self::assert_seed_version($table, $cols['seed_version']);
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_index($table, 'uq_family_key', ['family_key']);
        self::verify_composite_index($table, ['is_enabled', 'family_key']);
    }

    private static function verify_containers_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = [
            'id', 'public_id', 'family_id', 'title', 'details', 'created_at', 'updated_at',
        ];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        $forbidden = [
            'amount', 'currency', 'created_by', 'owner_user_id', 'deleted_at',
            'family_key', 'variant_key', 'sku', 'phone', 'email', 'image', 'tag',
        ];
        self::assert_forbidden_columns($table, $cols, $forbidden);

        self::assert_id_column($table, $cols['id']);
        self::assert_public_id($table, $cols['public_id']);
        self::assert_bigint_unsigned_not_null($table, $cols['family_id'], 'family_id');
        self::assert_varchar_not_null($table, $cols['title'], 'title', 200);
        self::assert_details_nullable($table, $cols['details']);
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_index($table, 'uq_container_public_id', ['public_id']);
        self::verify_composite_index($table, ['family_id', 'updated_at', 'id']);
    }

    private static function verify_records_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['id', 'public_id', 'container_id', 'title', 'details', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        $forbidden = [
            'amount', 'currency', 'created_by', 'owner_user_id', 'deleted_at',
            'family_key', 'variant_key', 'sku', 'phone', 'email', 'image', 'tag',
        ];
        self::assert_forbidden_columns($table, $cols, $forbidden);

        self::assert_id_column($table, $cols['id']);
        self::assert_public_id($table, $cols['public_id']);
        self::assert_bigint_unsigned_not_null($table, $cols['container_id'], 'container_id');
        self::assert_varchar_not_null($table, $cols['title'], 'title', 200);
        self::assert_details_nullable($table, $cols['details']);
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_index($table, 'uq_record_public_id', ['public_id']);
        self::verify_composite_index($table, ['container_id', 'updated_at', 'id']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function columns_by_name(string $table): array {
        global $wpdb;

        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
        if (!is_array($columns) || empty($columns)) {
            throw new \RuntimeException("[AA_Canonical_Schema] No se pudieron leer las columnas de {$table}");
        }

        $cols_by_name = [];
        foreach ($columns as $col) {
            $cols_by_name[$col['Field']] = $col;
        }

        return $cols_by_name;
    }

    /**
     * @param array<string, array<string, mixed>> $cols
     * @param list<string> $forbidden
     */
    private static function assert_forbidden_columns(string $table, array $cols, array $forbidden): void {
        foreach ($forbidden as $field) {
            if (isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna no permitida presente en {$table}: {$field}");
            }
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_id_column(string $table, array $col): void {
        if (
            stripos((string) $col['Type'], 'bigint') === false
            || strtoupper((string) $col['Null']) !== 'NO'
            || stripos((string) $col['Extra'], 'auto_increment') === false
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para id en {$table}");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_public_id(string $table, array $col): void {
        if (
            stripos((string) $col['Type'], 'char(36)') === false
            || strtoupper((string) $col['Null']) !== 'NO'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para public_id en {$table}");
        }
        if ($col['Default'] !== null) {
            throw new \RuntimeException("[AA_Canonical_Schema] public_id en {$table} no debe tener DEFAULT");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_bigint_unsigned_not_null(string $table, array $col, string $name): void {
        $type = (string) $col['Type'];
        if (
            stripos($type, 'bigint') === false
            || strtoupper((string) $col['Null']) !== 'NO'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_varchar_not_null_no_default(
        string $table,
        array $col,
        string $name,
        int $length
    ): void {
        self::assert_varchar_not_null($table, $col, $name, $length);
        if ($col['Default'] !== null) {
            throw new \RuntimeException("[AA_Canonical_Schema] {$name} en {$table} no debe tener DEFAULT");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_varchar_not_null(
        string $table,
        array $col,
        string $name,
        int $length
    ): void {
        $needle = 'varchar(' . $length . ')';
        if (
            stripos((string) $col['Type'], $needle) === false
            || strtoupper((string) $col['Null']) !== 'NO'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_details_nullable(string $table, array $col): void {
        if (
            stripos((string) $col['Type'], 'text') === false
            || strtoupper((string) $col['Null']) !== 'YES'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para details en {$table}");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_datetime_not_null_no_default(
        string $table,
        array $col,
        string $name
    ): void {
        if (
            stripos((string) $col['Type'], 'datetime') === false
            || strtoupper((string) $col['Null']) !== 'NO'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
        $extra = strtoupper((string) ($col['Extra'] ?? ''));
        if (strpos($extra, 'ON UPDATE') !== false || strpos($extra, 'DEFAULT_GENERATED') !== false) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] {$name} en {$table} no debe tener DEFAULT/ON UPDATE horarios"
            );
        }
        if ($col['Default'] !== null) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] {$name} en {$table} no debe tener DEFAULT horario"
            );
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_tinyint_not_null_default_zero(
        string $table,
        array $col,
        string $name
    ): void {
        if (
            stripos((string) $col['Type'], 'tinyint') === false
            || strtoupper((string) $col['Null']) !== 'NO'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
        $default = $col['Default'];
        if ($default === null || (string) $default !== '0') {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] {$name} en {$table} debe tener DEFAULT 0"
            );
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_seed_version(string $table, array $col): void {
        $type = (string) $col['Type'];
        if (
            (stripos($type, 'smallint') === false && stripos($type, 'int') === false)
            || strtoupper((string) $col['Null']) !== 'NO'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para seed_version en {$table}");
        }
        $default = $col['Default'];
        if ($default === null || (string) $default !== '0') {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] seed_version en {$table} debe tener DEFAULT 0"
            );
        }
    }

    /**
     * @param list<string> $expected_columns
     */
    private static function verify_index(string $table, string $key_name, array $expected_columns): void {
        global $wpdb;

        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        if (!is_array($indexes)) {
            throw new \RuntimeException("[AA_Canonical_Schema] No se pudieron leer los índices de {$table}");
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
                "[AA_Canonical_Schema] Índice {$key_name} en {$table} no coincide con las columnas esperadas"
            );
        }
    }

    /**
     * @param list<string> $expected_columns
     */
    private static function verify_composite_index(string $table, array $expected_columns): void {
        global $wpdb;

        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
        if (!is_array($indexes)) {
            throw new \RuntimeException("[AA_Canonical_Schema] No se pudieron leer los índices de {$table}");
        }

        $by_key = [];
        foreach ($indexes as $idx) {
            $key = $idx['Key_name'];
            $by_key[$key][(int) $idx['Seq_in_index']] = $idx['Column_name'];
        }

        foreach ($by_key as $cols) {
            ksort($cols);
            if (array_values($cols) === array_values($expected_columns)) {
                return;
            }
        }

        $expected_str = implode(', ', $expected_columns);
        throw new \RuntimeException(
            "[AA_Canonical_Schema] Índice compuesto ({$expected_str}) ausente en {$table}"
        );
    }

    private static function verify_foreign_key(
        string $table,
        string $referenced_table,
        string $fk_name,
        string $column,
        string $expected_delete_rule
    ): void {
        global $wpdb;

        $accepted_rules = [$expected_delete_rule];
        // MySQL trata RESTRICT y NO ACTION como equivalentes.
        if ($expected_delete_rule === 'RESTRICT') {
            $accepted_rules[] = 'NO ACTION';
        }

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
                    $table,
                    $fk_name
                ),
                ARRAY_A
            );

            if (is_array($row) && !empty($row['CONSTRAINT_NAME'])) {
                $rule = strtoupper((string) $row['DELETE_RULE']);
                if (!in_array($rule, $accepted_rules, true)) {
                    throw new \RuntimeException(
                        "[AA_Canonical_Schema] DELETE_RULE para {$fk_name} no es {$expected_delete_rule} (es {$row['DELETE_RULE']})"
                    );
                }
                if ($row['REFERENCED_TABLE_NAME'] !== $referenced_table) {
                    throw new \RuntimeException(
                        "[AA_Canonical_Schema] Foreign key {$fk_name} referencia tabla errónea: {$row['REFERENCED_TABLE_NAME']}"
                    );
                }
                return;
            }
        }

        $show = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_A);
        if (!is_array($show)) {
            throw new \RuntimeException("[AA_Canonical_Schema] No se pudo obtener SHOW CREATE TABLE de {$table}");
        }

        $create_sql = (string) ($show['Create Table'] ?? reset($show) ?? '');
        $rule_pattern = $expected_delete_rule === 'RESTRICT'
            ? '(?:RESTRICT|NO ACTION)'
            : preg_quote($expected_delete_rule, '/');
        $pattern = '/CONSTRAINT\s+[`"]?' . preg_quote($fk_name, '/')
            . '[`"]?\s+FOREIGN KEY\s+\([`"]?' . preg_quote($column, '/')
            . '[`"]?\)\s+REFERENCES\s+[`"]?' . preg_quote($referenced_table, '/')
            . '[`"]?\s+\([`"]?id[`"]?\)\s+ON DELETE ' . $rule_pattern . '/i';

        if (!preg_match($pattern, $create_sql)) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] Foreign key {$fk_name} con ON DELETE {$expected_delete_rule} ausente o malformada en {$table}"
            );
        }
    }
}
