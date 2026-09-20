<?php
/**
 * Canonical Schema — Persistencia Canónica Universal (PCU-2 + C1a + DB 25 + images DB 26).
 *
 * Tablas base (sin seeds de filas en el instalador):
 * - aa_canonical_families
 * - aa_canonical_containers
 * - aa_canonical_records
 *
 * Extensiones de capacidades (C1a / DB 23; repertorio renombrado en DB 25):
 * - aa_canonical_family_capabilities (antes aa_canonical_family_capability_defaults)
 * - aa_canonical_container_capabilities
 * - aa_canonical_record_amount (tabla de valores; uso en A1a+)
 * - aa_canonical_record_phone (valores E.164; DB 32)
 * - aa_canonical_record_whatsapp (valores E.164; DB 33)
 * - aa_canonical_record_email (correo ASCII; DB 34)
 * - aa_canonical_contact_dossier (recurso de la solution: contacto→lista Archivo 1:1; DB 35)
 * - aa_canonical_contact_dossier_applications (aplicación por lista; DB 36)
 * - DB 37 retira filas legacy `dossier` de configuración de capabilities.
 *
 * Images Ciclo 1 (DB 26; sin semántica de producto todavía):
 * - aa_canonical_record_images
 * - aa_canonical_image_upload_operations
 * - aa_canonical_purge_runs
 *
 * IMG-3a (DB 27): columnas operativas `upload_intent` / `upload_objects_json` en ops
 * (credenciales de subida; nullable para filas históricas incompletas).
 *
 * IMG-5 incremento 2 (DB 28): corrida durable de purge + inventario congelado
 * (`aa_canonical_purge_inventory_items`). Sin FK a records/containers.
 *
 * IMG-5 incremento 3 (DB 29): intención de envío HMAC (`accept_intent_batch_seq`,
 * `seal_intent_at`) y marca de cancelación local (`cancelled_at`) en purge_runs.
 *
 * IMG-5 incremento 4 (DB 30): checkpoints de retiro local post-sello de lista
 * (`local_retire_after_inventory_id`, `local_retire_after_record_id`) en purge_runs.
 * IMG-5 / DB 31: `record_id` nullable en purge_runs (dueño durable de scope=image).
 *
 * Patrón técnico: dbDelta → ensure columnas v27/v28/v29/v30 → migración v25 repertorio → ALTER FK → verify() fail-closed.
 * No escribe timestamps ni genera public_id (salvo copia de filas en migración v25).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Schema {

    public const TABLE_FAMILIES = 'aa_canonical_families';
    public const TABLE_CONTAINERS = 'aa_canonical_containers';
    public const TABLE_RECORDS = 'aa_canonical_records';
    public const TABLE_FAMILY_CAPABILITIES = 'aa_canonical_family_capabilities';
    public const TABLE_CONTAINER_CAPABILITIES = 'aa_canonical_container_capabilities';
    public const TABLE_RECORD_AMOUNT = 'aa_canonical_record_amount';
    public const TABLE_RECORD_PHONE = 'aa_canonical_record_phone';
    public const TABLE_RECORD_WHATSAPP = 'aa_canonical_record_whatsapp';
    public const TABLE_RECORD_EMAIL = 'aa_canonical_record_email';
    public const TABLE_RECORD_COMPLETION = 'aa_canonical_record_completion';
    public const TABLE_CONTACT_DOSSIER = 'aa_canonical_contact_dossier';
    public const TABLE_CONTACT_DOSSIER_APPLICATIONS = 'aa_canonical_contact_dossier_applications';
    public const TABLE_RECORD_IMAGES = 'aa_canonical_record_images';
    public const TABLE_IMAGE_UPLOAD_OPERATIONS = 'aa_canonical_image_upload_operations';
    public const TABLE_PURGE_RUNS = 'aa_canonical_purge_runs';
    public const TABLE_PURGE_INVENTORY_ITEMS = 'aa_canonical_purge_inventory_items';

    /** Status estables de aa_canonical_image_upload_operations (sin fila committed). */
    public const IMAGE_UPLOAD_STATUS_ADMITTED = 'admitted';
    public const IMAGE_UPLOAD_STATUS_CLEANUP_NEEDED = 'cleanup_needed';

    public const PURGE_CAPTURE_STATUS_PENDING = 'pending';
    public const PURGE_CAPTURE_STATUS_CONFLICT = 'conflict';
    public const PURGE_INVENTORY_SOURCE_IMAGE = 'image';
    public const PURGE_INVENTORY_SOURCE_OPERATION = 'operation';
    public const PURGE_INVENTORY_SOURCE_BOTH = 'both';
    public const PURGE_PREPARED_BATCH_MAX_ITEMS = 50;
    public const PURGE_CURSOR_KIND_SOURCE_KEYSET = 'source_keyset';

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

    public static function family_capabilities_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_FAMILY_CAPABILITIES;
    }

    public static function container_capabilities_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_CONTAINER_CAPABILITIES;
    }

    public static function record_amount_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_RECORD_AMOUNT;
    }

    public static function record_phone_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_RECORD_PHONE;
    }

    public static function record_whatsapp_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_RECORD_WHATSAPP;
    }

    public static function record_email_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_RECORD_EMAIL;
    }

    public static function record_completion_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_RECORD_COMPLETION;
    }

    public static function contact_dossier_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_CONTACT_DOSSIER;
    }

    public static function contact_dossier_applications_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_CONTACT_DOSSIER_APPLICATIONS;
    }

    public static function record_images_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_RECORD_IMAGES;
    }

    public static function image_upload_operations_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_IMAGE_UPLOAD_OPERATIONS;
    }

    public static function purge_runs_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_PURGE_RUNS;
    }

    public static function purge_inventory_items_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_PURGE_INVENTORY_ITEMS;
    }

    /**
     * Nombre físico legacy (solo migración DB 25).
     */
    private static function legacy_family_capability_defaults_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'aa_canonical_family_capability_defaults';
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

    /**
     * FK family_capabilities.family_id → families.id.
     */
    public static function family_capabilities_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_family_capabilities:family_id',
            'aa_can_fcap_'
        );
    }

    /**
     * FK legacy family_capability_defaults.family_id (solo drop en migración DB 25).
     */
    private static function legacy_family_capability_defaults_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_family_capability_defaults:family_id',
            'aa_can_fcd_'
        );
    }

    /**
     * FK container_capabilities.container_id → containers.id.
     */
    public static function container_capabilities_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_container_capabilities:container_id',
            'aa_can_cc_'
        );
    }

    /**
     * FK record_amount.record_id → records.id.
     */
    public static function record_amount_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_record_amount:record_id',
            'aa_can_amt_'
        );
    }

    /**
     * FK record_phone.record_id → records.id.
     */
    public static function record_phone_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_record_phone:record_id',
            'aa_can_phn_'
        );
    }

    /**
     * FK record_whatsapp.record_id → records.id.
     */
    public static function record_whatsapp_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_record_whatsapp:record_id',
            'aa_can_wa_'
        );
    }

    /**
     * FK record_email.record_id → records.id.
     */
    public static function record_email_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_record_email:record_id',
            'aa_can_eml_'
        );
    }

    public static function record_completion_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name($prefix, 'aa_canonical_record_completion:record_id', 'aa_can_cmp_');
    }

    /**
     * FK contact_dossier.contact_record_id → records.id (CASCADE).
     */
    public static function contact_dossier_contact_record_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_contact_dossier:contact_record_id',
            'aa_can_cdr_'
        );
    }

    /**
     * FK contact_dossier.archive_container_id → containers.id (CASCADE).
     */
    public static function contact_dossier_archive_container_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_contact_dossier:archive_container_id',
            'aa_can_cac_'
        );
    }

    /**
     * FK contact_dossier_applications.contact_container_id → containers.id (CASCADE).
     */
    public static function contact_dossier_applications_container_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_contact_dossier_applications:contact_container_id',
            'aa_can_cda_'
        );
    }

    /**
     * FK record_images.record_id → records.id (RESTRICT).
     */
    public static function record_images_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_record_images:record_id',
            'aa_can_img_'
        );
    }

    /**
     * FK image_upload_operations.record_id → records.id (RESTRICT).
     */
    public static function image_upload_operations_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_image_upload_operations:record_id',
            'aa_can_iup_'
        );
    }

    /**
     * FK purge_inventory_items.purge_run_id → purge_runs.id (CASCADE).
     * No hay FK hacia records ni containers.
     */
    public static function purge_inventory_items_foreign_key_name(?string $prefix = null): string {
        return self::build_foreign_key_name(
            $prefix,
            'aa_canonical_purge_inventory_items:purge_run_id',
            'aa_can_piv_'
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
     * Instala tablas universales y extensiones de capacidades, aplica FKs y verifica.
     * No inserta filas de producto; la migración v25 puede copiar filas del repertorio legacy.
     *
     * @throws \RuntimeException Si la creación, constraint o verificación falla.
     */
    public static function install(): void {
        global $wpdb;

        $families_table = self::families_table_name();
        $containers_table = self::containers_table_name();
        $records_table = self::records_table_name();
        $container_capabilities_table = self::container_capabilities_table_name();
        $record_amount_table = self::record_amount_table_name();
        $record_phone_table = self::record_phone_table_name();
        $record_whatsapp_table = self::record_whatsapp_table_name();
        $record_email_table = self::record_email_table_name();
        $record_completion_table = self::record_completion_table_name();
        $contact_dossier_table = self::contact_dossier_table_name();
        $contact_dossier_applications_table = self::contact_dossier_applications_table_name();
        $record_images_table = self::record_images_table_name();
        $image_upload_operations_table = self::image_upload_operations_table_name();
        $purge_runs_table = self::purge_runs_table_name();
        $purge_inventory_table = self::purge_inventory_items_table_name();
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

        $container_capabilities_sql = "CREATE TABLE {$container_capabilities_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            container_id bigint(20) unsigned NOT NULL,
            capability_key varchar(64) NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_container_capability (container_id, capability_key),
            KEY idx_capability_key (capability_key)
        ) ENGINE=InnoDB {$charset};";

        $record_amount_sql = "CREATE TABLE {$record_amount_table} (
            record_id bigint(20) unsigned NOT NULL,
            amount decimal(19,2) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (record_id)
        ) ENGINE=InnoDB {$charset};";

        $record_phone_sql = "CREATE TABLE {$record_phone_table} (
            record_id bigint(20) unsigned NOT NULL,
            phone varchar(16) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (record_id)
        ) ENGINE=InnoDB {$charset};";

        $record_whatsapp_sql = "CREATE TABLE {$record_whatsapp_table} (
            record_id bigint(20) unsigned NOT NULL,
            whatsapp varchar(16) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (record_id)
        ) ENGINE=InnoDB {$charset};";

        $record_email_sql = "CREATE TABLE {$record_email_table} (
            record_id bigint(20) unsigned NOT NULL,
            email varchar(254) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (record_id)
        ) ENGINE=InnoDB {$charset};";

        $record_completion_sql = "CREATE TABLE {$record_completion_table} (
            record_id bigint(20) unsigned NOT NULL,
            completed_at datetime NOT NULL,
            PRIMARY KEY  (record_id),
            KEY idx_completed_at (completed_at, record_id)
        ) ENGINE=InnoDB {$charset};";

        $contact_dossier_sql = "CREATE TABLE {$contact_dossier_table} (
            contact_record_id bigint(20) unsigned NOT NULL,
            archive_container_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (contact_record_id),
            UNIQUE KEY uq_dossier_archive_container (archive_container_id)
        ) ENGINE=InnoDB {$charset};";

        $contact_dossier_applications_sql = "CREATE TABLE {$contact_dossier_applications_table} (
            contact_container_id bigint(20) unsigned NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (contact_container_id)
        ) ENGINE=InnoDB {$charset};";

        $record_images_sql = "CREATE TABLE {$record_images_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            record_id bigint(20) unsigned NOT NULL,
            upload_operation_id char(36) NOT NULL,
            storage_path varchar(191) NOT NULL,
            content_sha256 char(64) NOT NULL,
            mime_type varchar(64) NOT NULL,
            byte_size int unsigned NOT NULL,
            width int unsigned NOT NULL,
            height int unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_record_image_operation (upload_operation_id),
            UNIQUE KEY uq_record_image_storage_path (storage_path),
            KEY idx_record_image_record_id (record_id, id)
        ) ENGINE=InnoDB {$charset};";

        $image_upload_operations_sql = "CREATE TABLE {$image_upload_operations_table} (
            upload_operation_id char(36) NOT NULL,
            record_id bigint(20) unsigned NOT NULL,
            storage_path varchar(191) NOT NULL,
            content_sha256 char(64) NOT NULL,
            mime_type varchar(64) NOT NULL,
            byte_size int unsigned NOT NULL,
            width int unsigned NOT NULL,
            height int unsigned NOT NULL,
            status varchar(32) NOT NULL,
            expires_at datetime NOT NULL,
            backend_intent_exp_ms bigint(20) unsigned DEFAULT NULL,
            upload_intent mediumtext DEFAULT NULL,
            upload_objects_json mediumtext DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (upload_operation_id),
            KEY idx_image_op_status_expires (status, expires_at),
            KEY idx_image_op_record_status (record_id, status),
            KEY idx_image_op_storage_path (storage_path)
        ) ENGINE=InnoDB {$charset};";

        $purge_runs_sql = "CREATE TABLE {$purge_runs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scope varchar(32) NOT NULL,
            target_id bigint(20) unsigned NOT NULL,
            family_key varchar(64) NOT NULL,
            status varchar(32) NOT NULL,
            cursor_kind varchar(32) NOT NULL,
            cursor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            cursor_operation_id char(36) DEFAULT NULL,
            deleted_ok int unsigned NOT NULL DEFAULT 0,
            failed_count int unsigned NOT NULL DEFAULT 0,
            mandate_id char(36) DEFAULT NULL,
            container_id bigint(20) unsigned NOT NULL DEFAULT 0,
            capture_status varchar(32) NOT NULL DEFAULT 'pending',
            images_read_after_id bigint(20) unsigned NOT NULL DEFAULT 0,
            ops_read_after_operation_id char(36) DEFAULT NULL,
            images_source_exhausted tinyint(1) NOT NULL DEFAULT 0,
            ops_source_exhausted tinyint(1) NOT NULL DEFAULT 0,
            capture_complete tinyint(1) NOT NULL DEFAULT 0,
            batches_prepared tinyint(1) NOT NULL DEFAULT 0,
            prepared_batch_count int unsigned NOT NULL DEFAULT 0,
            last_accepted_batch_seq int unsigned DEFAULT NULL,
            sealed_at datetime DEFAULT NULL,
            capture_conflict_code varchar(64) DEFAULT NULL,
            accept_intent_batch_seq int unsigned DEFAULT NULL,
            seal_intent_at datetime DEFAULT NULL,
            cancelled_at datetime DEFAULT NULL,
            local_retire_after_inventory_id bigint(20) unsigned NOT NULL DEFAULT 0,
            local_retire_after_record_id bigint(20) unsigned NOT NULL DEFAULT 0,
            record_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_purge_scope_target_status (scope, target_id, status),
            KEY idx_purge_mandate_id (mandate_id),
            KEY idx_purge_container_status (container_id, status),
            KEY idx_purge_record_status (record_id, status)
        ) ENGINE=InnoDB {$charset};";

        $purge_inventory_sql = "CREATE TABLE {$purge_inventory_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            purge_run_id bigint(20) unsigned NOT NULL,
            upload_operation_id char(36) NOT NULL,
            wp_record_id bigint(20) unsigned NOT NULL,
            content_sha256 char(64) NOT NULL,
            byte_size int unsigned NOT NULL,
            storage_path varchar(191) NOT NULL,
            source varchar(32) NOT NULL,
            batch_seq int unsigned DEFAULT NULL,
            position_in_batch int unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_purge_inventory_operation (purge_run_id, upload_operation_id),
            KEY idx_purge_inventory_batch (purge_run_id, batch_seq, position_in_batch)
        ) ENGINE=InnoDB {$charset};";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($families_sql);
        dbDelta($containers_sql);
        dbDelta($records_sql);
        dbDelta($container_capabilities_sql);
        dbDelta($record_amount_sql);
        dbDelta($record_phone_sql);
        dbDelta($record_whatsapp_sql);
        dbDelta($record_email_sql);
        dbDelta($record_completion_sql);
        dbDelta($contact_dossier_sql);
        dbDelta($contact_dossier_applications_sql);
        dbDelta($record_images_sql);
        dbDelta($image_upload_operations_sql);
        dbDelta($purge_runs_sql);
        dbDelta($purge_inventory_sql);

        self::ensure_image_upload_operations_credentials_v27();
        self::ensure_purge_capture_v28();
        self::ensure_purge_retire_intent_v29();
        self::ensure_purge_container_local_retire_v30();
        self::ensure_purge_image_retire_v31();
        self::ensure_family_capabilities_v25();
        self::retire_legacy_dossier_capability_configuration_v37();
        self::ensure_containers_family_scope_v22();
        self::ensure_named_indexes();
        self::ensure_foreign_keys();
        self::verify();
    }

    /**
     * IMG-3a / DB 27: credenciales de admisión en ops (aditivo, reanudable).
     * No inventa intent/URLs para filas históricas; columnas nullable.
     *
     * @throws \RuntimeException
     */
    public static function ensure_image_upload_operations_credentials_v27(): void {
        $table = self::image_upload_operations_table_name();
        if (!self::physical_table_exists($table)) {
            return;
        }

        self::ensure_nullable_mediumtext_column($table, 'upload_intent');
        self::ensure_nullable_mediumtext_column($table, 'upload_objects_json');
    }

    /**
     * IMG-5 / DB 28: columnas de captura durable en purge_runs + tabla de inventario.
     * last_accepted_batch_seq / sealed_at quedan NULL hasta evidencia remota (inc. 3+).
     *
     * @throws \RuntimeException
     */
    public static function ensure_purge_capture_v28(): void {
        $runs = self::purge_runs_table_name();
        if (self::physical_table_exists($runs)) {
            self::ensure_column_definition($runs, 'mandate_id', 'char(36) DEFAULT NULL');
            self::ensure_column_definition($runs, 'container_id', 'bigint(20) unsigned NOT NULL DEFAULT 0');
            self::ensure_column_definition($runs, 'capture_status', "varchar(32) NOT NULL DEFAULT 'pending'");
            self::ensure_column_definition($runs, 'images_read_after_id', 'bigint(20) unsigned NOT NULL DEFAULT 0');
            self::ensure_column_definition($runs, 'ops_read_after_operation_id', 'char(36) DEFAULT NULL');
            self::ensure_column_definition($runs, 'images_source_exhausted', 'tinyint(1) NOT NULL DEFAULT 0');
            self::ensure_column_definition($runs, 'ops_source_exhausted', 'tinyint(1) NOT NULL DEFAULT 0');
            self::ensure_column_definition($runs, 'capture_complete', 'tinyint(1) NOT NULL DEFAULT 0');
            self::ensure_column_definition($runs, 'batches_prepared', 'tinyint(1) NOT NULL DEFAULT 0');
            self::ensure_column_definition($runs, 'prepared_batch_count', 'int unsigned NOT NULL DEFAULT 0');
            self::ensure_column_definition($runs, 'last_accepted_batch_seq', 'int unsigned DEFAULT NULL');
            self::ensure_column_definition($runs, 'sealed_at', 'datetime DEFAULT NULL');
            self::ensure_column_definition($runs, 'capture_conflict_code', 'varchar(64) DEFAULT NULL');
            self::ensure_named_index(
                $runs,
                'idx_purge_mandate_id',
                "ALTER TABLE `{$runs}` ADD KEY idx_purge_mandate_id (mandate_id)"
            );
            self::ensure_named_index(
                $runs,
                'idx_purge_container_status',
                "ALTER TABLE `{$runs}` ADD KEY idx_purge_container_status (container_id, status)"
            );
        }

        $inventory = self::purge_inventory_items_table_name();
        if (!self::physical_table_exists($inventory)) {
            global $wpdb;
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$inventory} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                purge_run_id bigint(20) unsigned NOT NULL,
                upload_operation_id char(36) NOT NULL,
                wp_record_id bigint(20) unsigned NOT NULL,
                content_sha256 char(64) NOT NULL,
                byte_size int unsigned NOT NULL,
                storage_path varchar(191) NOT NULL,
                source varchar(32) NOT NULL,
                batch_seq int unsigned DEFAULT NULL,
                position_in_batch int unsigned DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uq_purge_inventory_operation (purge_run_id, upload_operation_id),
                KEY idx_purge_inventory_batch (purge_run_id, batch_seq, position_in_batch)
            ) ENGINE=InnoDB {$charset};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }

    /**
     * IMG-5 inc. 3 / DB 29: intención de envío HMAC y cancelación local.
     * NULL = ninguna intención/cancelación. No renumera tandas existentes.
     *
     * @throws \RuntimeException
     */
    public static function ensure_purge_retire_intent_v29(): void {
        $runs = self::purge_runs_table_name();
        if (!self::physical_table_exists($runs)) {
            return;
        }

        self::ensure_column_definition($runs, 'accept_intent_batch_seq', 'int unsigned DEFAULT NULL');
        self::ensure_column_definition($runs, 'seal_intent_at', 'datetime DEFAULT NULL');
        self::ensure_column_definition($runs, 'cancelled_at', 'datetime DEFAULT NULL');
    }

    /**
     * IMG-5 inc. 4 / DB 30: checkpoints de retiro local post-sello (lista).
     * 0 = ninguna identidad/registro de inventario aún retirado en esta corrida.
     * Keyset, no OFFSET: id de inventario / id de registro ya procesado.
     *
     * @throws \RuntimeException
     */
    public static function ensure_purge_container_local_retire_v30(): void {
        $runs = self::purge_runs_table_name();
        if (!self::physical_table_exists($runs)) {
            return;
        }

        self::ensure_column_definition(
            $runs,
            'local_retire_after_inventory_id',
            'bigint(20) unsigned NOT NULL DEFAULT 0'
        );
        self::ensure_column_definition(
            $runs,
            'local_retire_after_record_id',
            'bigint(20) unsigned NOT NULL DEFAULT 0'
        );
    }

    /**
     * IMG-5 inc. 5 / DB 31: record_id durable en corridas (scope=image).
     * NULL = no aplica (p. ej. scope=container) o corrida histórica sin dueño sellado.
     *
     * @throws \RuntimeException
     */
    public static function ensure_purge_image_retire_v31(): void {
        $runs = self::purge_runs_table_name();
        if (!self::physical_table_exists($runs)) {
            return;
        }

        self::ensure_column_definition(
            $runs,
            'record_id',
            'bigint(20) unsigned DEFAULT NULL'
        );
        self::ensure_named_index(
            $runs,
            'idx_purge_record_status',
            "ALTER TABLE `{$runs}` ADD KEY idx_purge_record_status (record_id, status)"
        );
    }

    /**
     * @throws \RuntimeException
     */
    private static function ensure_column_definition(string $table, string $column, string $definition): void {
        global $wpdb;

        $cols = self::columns_by_name($table);
        if (isset($cols[$column])) {
            return;
        }

        $safe_table = str_replace('`', '``', $table);
        $safe_column = str_replace('`', '``', $column);
        $wpdb->last_error = '';
        $added = $wpdb->query(
            "ALTER TABLE `{$safe_table}` ADD COLUMN `{$safe_column}` {$definition}"
        );
        if ($added === false) {
            $cols_after = self::columns_by_name($table);
            if (!isset($cols_after[$column])) {
                $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : "ADD COLUMN {$column} falló";
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] No se pudo añadir {$column} en {$table}: {$error}"
                );
            }
        }
    }

    /**
     * @throws \RuntimeException
     */
    private static function ensure_nullable_mediumtext_column(string $table, string $column): void {
        global $wpdb;

        $cols = self::columns_by_name($table);
        if (isset($cols[$column])) {
            return;
        }

        $safe_table = str_replace('`', '``', $table);
        $safe_column = str_replace('`', '``', $column);
        $wpdb->last_error = '';
        $added = $wpdb->query(
            "ALTER TABLE `{$safe_table}` ADD COLUMN `{$safe_column}` mediumtext DEFAULT NULL"
        );
        if ($added === false) {
            $cols_after = self::columns_by_name($table);
            if (!isset($cols_after[$column])) {
                $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : "ADD COLUMN {$column} falló";
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] No se pudo añadir {$column} en {$table}: {$error}"
                );
            }
        }
    }

    /**
     * Migración DB 24→25: repertorio de capabilities por familia.
     *
     * Renombra aa_canonical_family_capability_defaults → aa_canonical_family_capabilities
     * y la columna is_enabled → is_default. Idempotente; sin dual-write.
     *
     * @throws \RuntimeException
     */
    public static function ensure_family_capabilities_v25(): void {
        global $wpdb;

        $new = self::family_capabilities_table_name();
        $old = self::legacy_family_capability_defaults_table_name();
        $old_exists = self::physical_table_exists($old);
        $new_exists = self::physical_table_exists($new);

        if (!$old_exists && !$new_exists) {
            self::create_family_capabilities_table();
        } elseif ($old_exists && !$new_exists) {
            self::drop_foreign_key_if_present(
                $old,
                self::legacy_family_capability_defaults_foreign_key_name()
            );
            $wpdb->last_error = '';
            $renamed = $wpdb->query(
                'RENAME TABLE `' . str_replace('`', '``', $old) . '` TO `'
                . str_replace('`', '``', $new) . '`'
            );
            if ($renamed === false) {
                $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : 'RENAME TABLE falló';
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] No se pudo renombrar repertorio legacy a {$new}: {$error}"
                );
            }
            self::drop_foreign_key_if_present(
                $new,
                self::legacy_family_capability_defaults_foreign_key_name()
            );
        } elseif ($old_exists && $new_exists) {
            self::ensure_family_capabilities_is_default_column($new);
            self::copy_legacy_family_capability_defaults_into_new($old, $new);
            self::assert_new_covers_legacy_family_capability_rows($old, $new);
            self::drop_foreign_key_if_present(
                $old,
                self::legacy_family_capability_defaults_foreign_key_name()
            );
            $wpdb->last_error = '';
            $dropped = $wpdb->query(
                'DROP TABLE IF EXISTS `' . str_replace('`', '``', $old) . '`'
            );
            if ($dropped === false) {
                $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : 'DROP TABLE falló';
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] No se pudo eliminar tabla legacy {$old}: {$error}"
                );
            }
        }

        if (!self::physical_table_exists($new)) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] Tabla de repertorio ausente tras ensure v25: {$new}"
            );
        }

        self::ensure_family_capabilities_is_default_column($new);

        if (self::physical_table_exists($old)) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] Tabla legacy de repertorio sigue presente tras v25: {$old}"
            );
        }

        $cols = self::columns_by_name($new);
        if (!isset($cols['is_default'])) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] Columna is_default ausente en {$new} tras ensure v25"
            );
        }
        if (isset($cols['is_enabled'])) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] Columna is_enabled residual en {$new} tras ensure v25"
            );
        }
    }

    /**
     * DB 37: `dossier` dejó de ser capability. Elimina únicamente sus filas
     * de repertorio/asignación, sin tocar el recurso relacional de la solution.
     *
     * @throws \RuntimeException
     */
    public static function retire_legacy_dossier_capability_configuration_v37(): void {
        global $wpdb;

        foreach ([
            self::container_capabilities_table_name(),
            self::family_capabilities_table_name(),
        ] as $table) {
            if (!self::physical_table_exists($table)) {
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] Tabla ausente al retirar capability dossier: {$table}"
                );
            }
            $wpdb->last_error = '';
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    'DELETE FROM `' . str_replace('`', '``', $table) . '` WHERE capability_key = %s',
                    'dossier'
                )
            );
            if ($deleted === false || $wpdb->last_error !== '') {
                $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : 'DELETE falló';
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] No se pudo retirar capability dossier de {$table}: {$error}"
                );
            }
        }
    }

    private static function create_family_capabilities_table(): void {
        global $wpdb;

        $family_capabilities_table = self::family_capabilities_table_name();
        $charset = $wpdb->get_charset_collate();

        $family_capabilities_sql = "CREATE TABLE {$family_capabilities_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            family_id bigint(20) unsigned NOT NULL,
            capability_key varchar(64) NOT NULL,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_family_capability (family_id, capability_key),
            KEY idx_capability_key (capability_key)
        ) ENGINE=InnoDB {$charset};";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($family_capabilities_sql);

        if (!self::physical_table_exists($family_capabilities_table)) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] No se pudo crear {$family_capabilities_table}"
            );
        }
    }

    /**
     * @throws \RuntimeException
     */
    private static function ensure_family_capabilities_is_default_column(string $table): void {
        global $wpdb;

        $cols = self::columns_by_name($table);
        $has_default = isset($cols['is_default']);
        $has_enabled = isset($cols['is_enabled']);

        if ($has_default && !$has_enabled) {
            return;
        }

        $safe = str_replace('`', '``', $table);

        if (!$has_default && $has_enabled) {
            $wpdb->last_error = '';
            $changed = $wpdb->query(
                "ALTER TABLE `{$safe}` CHANGE COLUMN `is_enabled` `is_default` tinyint(1) NOT NULL DEFAULT 0"
            );
            if ($changed === false) {
                $cols_after = self::columns_by_name($table);
                if (!isset($cols_after['is_default']) || isset($cols_after['is_enabled'])) {
                    $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                        ? $wpdb->last_error
                        : 'CHANGE COLUMN falló';
                    throw new \RuntimeException(
                        "[AA_Canonical_Schema] No se pudo renombrar is_enabled→is_default en {$table}: {$error}"
                    );
                }
            }
            return;
        }

        if ($has_default && $has_enabled) {
            $wpdb->last_error = '';
            $copied = $wpdb->query(
                "UPDATE `{$safe}` SET `is_default` = `is_enabled`"
            );
            if ($copied === false) {
                $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : 'UPDATE is_default falló';
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] No se pudo copiar is_enabled→is_default en {$table}: {$error}"
                );
            }
            $wpdb->last_error = '';
            $dropped = $wpdb->query("ALTER TABLE `{$safe}` DROP COLUMN `is_enabled`");
            if ($dropped === false) {
                $cols_after = self::columns_by_name($table);
                if (isset($cols_after['is_enabled'])) {
                    $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                        ? $wpdb->last_error
                        : 'DROP COLUMN is_enabled falló';
                    throw new \RuntimeException(
                        "[AA_Canonical_Schema] No se pudo eliminar is_enabled en {$table}: {$error}"
                    );
                }
            }
            return;
        }

        $wpdb->last_error = '';
        $added = $wpdb->query(
            "ALTER TABLE `{$safe}` ADD COLUMN `is_default` tinyint(1) NOT NULL DEFAULT 0"
        );
        if ($added === false) {
            $cols_after = self::columns_by_name($table);
            if (!isset($cols_after['is_default'])) {
                $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                    ? $wpdb->last_error
                    : 'ADD COLUMN is_default falló';
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] No se pudo añadir is_default en {$table}: {$error}"
                );
            }
        }
    }

    /**
     * @throws \RuntimeException
     */
    private static function copy_legacy_family_capability_defaults_into_new(
        string $old,
        string $new
    ): void {
        global $wpdb;

        $old_cols = self::columns_by_name($old);
        $flag_col = isset($old_cols['is_default'])
            ? 'is_default'
            : (isset($old_cols['is_enabled']) ? 'is_enabled' : null);
        if ($flag_col === null) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] Tabla legacy {$old} sin is_default ni is_enabled"
            );
        }

        $safe_old = str_replace('`', '``', $old);
        $safe_new = str_replace('`', '``', $new);
        $safe_flag = str_replace('`', '``', $flag_col);

        $wpdb->last_error = '';
        $copied = $wpdb->query(
            "INSERT IGNORE INTO `{$safe_new}`
                (family_id, capability_key, is_default, created_at, updated_at)
             SELECT family_id, capability_key, `{$safe_flag}`, created_at, updated_at
             FROM `{$safe_old}`"
        );
        if ($copied === false) {
            $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
                ? $wpdb->last_error
                : 'INSERT IGNORE falló';
            throw new \RuntimeException(
                "[AA_Canonical_Schema] No se pudo copiar repertorio legacy {$old}→{$new}: {$error}"
            );
        }
    }

    /**
     * @throws \RuntimeException
     */
    private static function assert_new_covers_legacy_family_capability_rows(
        string $old,
        string $new
    ): void {
        global $wpdb;

        $old_cols = self::columns_by_name($old);
        $flag_col = isset($old_cols['is_default'])
            ? 'is_default'
            : (isset($old_cols['is_enabled']) ? 'is_enabled' : null);
        if ($flag_col === null) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] Tabla legacy {$old} sin columna de flag para verificar copia"
            );
        }

        $safe_old = str_replace('`', '``', $old);
        $safe_flag = str_replace('`', '``', $flag_col);
        $rows = $wpdb->get_results(
            "SELECT family_id, capability_key, `{$safe_flag}` AS flag_value
             FROM `{$safe_old}`",
            ARRAY_A
        );
        if (!is_array($rows)) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] No se pudieron leer filas legacy de {$old}"
            );
        }

        foreach ($rows as $row) {
            $family_id = (int) ($row['family_id'] ?? 0);
            $capability_key = (string) ($row['capability_key'] ?? '');
            $expected = (int) ($row['flag_value'] ?? 0);
            $found = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT is_default FROM `' . str_replace('`', '``', $new)
                    . '` WHERE family_id = %d AND capability_key = %s LIMIT 1',
                    $family_id,
                    $capability_key
                ),
                ARRAY_A
            );
            if (!is_array($found)) {
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] Fila de repertorio no copiada a {$new}: "
                    . "{$family_id}/{$capability_key}"
                );
            }
            if ((int) ($found['is_default'] ?? -1) !== $expected) {
                throw new \RuntimeException(
                    "[AA_Canonical_Schema] Valor is_default no preservado en {$new}: "
                    . "{$family_id}/{$capability_key}"
                );
            }
        }
    }

    private static function physical_table_exists(string $table): bool {
        global $wpdb;

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return is_string($found) && $found === $table;
    }

    private static function drop_foreign_key_if_present(string $table, string $fk_name): void {
        global $wpdb;

        if (!self::physical_table_exists($table) || !self::foreign_key_exists($table, $fk_name)) {
            return;
        }

        $wpdb->last_error = '';
        $result = $wpdb->query(
            'ALTER TABLE `' . str_replace('`', '``', $table) . '` DROP FOREIGN KEY `'
            . str_replace('`', '``', $fk_name) . '`'
        );
        if ($result === false && self::foreign_key_exists($table, $fk_name)) {
            throw new \RuntimeException(
                '[AA_Canonical_Schema] No se pudo eliminar FK ' . $fk_name . ' en ' . $table . ': '
                . (string) $wpdb->last_error
            );
        }
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
        self::ensure_foreign_key(
            self::family_capabilities_table_name(),
            self::family_capabilities_foreign_key_name(),
            'family_id',
            self::families_table_name(),
            'RESTRICT'
        );
        self::ensure_foreign_key(
            self::container_capabilities_table_name(),
            self::container_capabilities_foreign_key_name(),
            'container_id',
            self::containers_table_name(),
            'CASCADE'
        );
        self::ensure_foreign_key(
            self::record_amount_table_name(),
            self::record_amount_foreign_key_name(),
            'record_id',
            self::records_table_name(),
            'CASCADE'
        );
        self::ensure_foreign_key(
            self::record_phone_table_name(),
            self::record_phone_foreign_key_name(),
            'record_id',
            self::records_table_name(),
            'CASCADE'
        );
        self::ensure_foreign_key(
            self::record_whatsapp_table_name(),
            self::record_whatsapp_foreign_key_name(),
            'record_id',
            self::records_table_name(),
            'CASCADE'
        );
        self::ensure_foreign_key(
            self::record_email_table_name(),
            self::record_email_foreign_key_name(),
            'record_id',
            self::records_table_name(),
            'CASCADE'
        );
        self::ensure_foreign_key(
            self::record_completion_table_name(),
            self::record_completion_foreign_key_name(),
            'record_id',
            self::records_table_name(),
            'CASCADE'
        );
        self::ensure_foreign_key(
            self::contact_dossier_table_name(),
            self::contact_dossier_contact_record_foreign_key_name(),
            'contact_record_id',
            self::records_table_name(),
            'CASCADE'
        );
        self::ensure_foreign_key(
            self::contact_dossier_applications_table_name(),
            self::contact_dossier_applications_container_foreign_key_name(),
            'contact_container_id',
            self::containers_table_name(),
            'CASCADE'
        );
        self::ensure_foreign_key(
            self::contact_dossier_table_name(),
            self::contact_dossier_archive_container_foreign_key_name(),
            'archive_container_id',
            self::containers_table_name(),
            'CASCADE'
        );
        self::ensure_foreign_key(
            self::record_images_table_name(),
            self::record_images_foreign_key_name(),
            'record_id',
            self::records_table_name(),
            'RESTRICT'
        );
        self::ensure_foreign_key(
            self::image_upload_operations_table_name(),
            self::image_upload_operations_foreign_key_name(),
            'record_id',
            self::records_table_name(),
            'RESTRICT'
        );
        self::ensure_foreign_key(
            self::purge_inventory_items_table_name(),
            self::purge_inventory_items_foreign_key_name(),
            'purge_run_id',
            self::purge_runs_table_name(),
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

        if (!self::physical_table_exists($table) || !self::physical_table_exists($referenced_table)) {
            return;
        }

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
        $family_capabilities = self::family_capabilities_table_name();
        $container_capabilities = self::container_capabilities_table_name();
        $record_amount = self::record_amount_table_name();
        $record_phone = self::record_phone_table_name();
        $record_whatsapp = self::record_whatsapp_table_name();
        $record_email = self::record_email_table_name();
        $record_completion = self::record_completion_table_name();
        $contact_dossier = self::contact_dossier_table_name();
        $contact_dossier_applications = self::contact_dossier_applications_table_name();
        $record_images = self::record_images_table_name();
        $image_upload_operations = self::image_upload_operations_table_name();
        $purge_runs = self::purge_runs_table_name();
        $purge_inventory = self::purge_inventory_items_table_name();

        self::verify_table_existence_and_engine($families);
        self::verify_table_existence_and_engine($containers);
        self::verify_table_existence_and_engine($records);
        self::verify_table_existence_and_engine($family_capabilities);
        self::verify_table_existence_and_engine($container_capabilities);
        self::verify_table_existence_and_engine($record_amount);
        self::verify_table_existence_and_engine($record_phone);
        self::verify_table_existence_and_engine($record_whatsapp);
        self::verify_table_existence_and_engine($record_email);
        self::verify_table_existence_and_engine($record_completion);
        self::verify_table_existence_and_engine($contact_dossier);
        self::verify_table_existence_and_engine($contact_dossier_applications);
        self::verify_table_existence_and_engine($record_images);
        self::verify_table_existence_and_engine($image_upload_operations);
        self::verify_table_existence_and_engine($purge_runs);
        self::verify_table_existence_and_engine($purge_inventory);

        self::verify_families_structure($families);
        self::verify_containers_structure($containers);
        self::verify_records_structure($records);
        self::verify_family_capabilities_structure($family_capabilities);
        self::verify_container_capabilities_structure($container_capabilities);
        self::verify_record_amount_structure($record_amount);
        self::verify_record_phone_structure($record_phone);
        self::verify_record_whatsapp_structure($record_whatsapp);
        self::verify_record_email_structure($record_email);
        self::verify_contact_dossier_structure($contact_dossier);
        self::verify_contact_dossier_applications_structure($contact_dossier_applications);
        self::verify_record_images_structure($record_images);
        self::verify_image_upload_operations_structure($image_upload_operations);
        self::verify_purge_runs_structure($purge_runs);
        self::verify_purge_inventory_items_structure($purge_inventory);

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
        self::verify_foreign_key(
            $family_capabilities,
            $families,
            self::family_capabilities_foreign_key_name(),
            'family_id',
            'RESTRICT'
        );
        self::verify_foreign_key(
            $container_capabilities,
            $containers,
            self::container_capabilities_foreign_key_name(),
            'container_id',
            'CASCADE'
        );
        self::verify_foreign_key(
            $record_amount,
            $records,
            self::record_amount_foreign_key_name(),
            'record_id',
            'CASCADE'
        );
        self::verify_foreign_key(
            $record_phone,
            $records,
            self::record_phone_foreign_key_name(),
            'record_id',
            'CASCADE'
        );
        self::verify_foreign_key(
            $record_whatsapp,
            $records,
            self::record_whatsapp_foreign_key_name(),
            'record_id',
            'CASCADE'
        );
        self::verify_foreign_key(
            $record_email,
            $records,
            self::record_email_foreign_key_name(),
            'record_id',
            'CASCADE'
        );
        self::verify_foreign_key(
            $record_completion,
            $records,
            self::record_completion_foreign_key_name(),
            'record_id',
            'CASCADE'
        );
        self::verify_foreign_key(
            $contact_dossier,
            $records,
            self::contact_dossier_contact_record_foreign_key_name(),
            'contact_record_id',
            'CASCADE'
        );
        self::verify_foreign_key(
            $contact_dossier_applications,
            $containers,
            self::contact_dossier_applications_container_foreign_key_name(),
            'contact_container_id',
            'CASCADE'
        );
        self::verify_foreign_key(
            $contact_dossier,
            $containers,
            self::contact_dossier_archive_container_foreign_key_name(),
            'archive_container_id',
            'CASCADE'
        );
        self::verify_foreign_key(
            $record_images,
            $records,
            self::record_images_foreign_key_name(),
            'record_id',
            'RESTRICT'
        );
        self::verify_foreign_key(
            $image_upload_operations,
            $records,
            self::image_upload_operations_foreign_key_name(),
            'record_id',
            'RESTRICT'
        );
        self::verify_foreign_key(
            $purge_inventory,
            $purge_runs,
            self::purge_inventory_items_foreign_key_name(),
            'purge_run_id',
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
            'family_key', 'variant_key', 'sku', 'phone', 'whatsapp', 'email', 'image', 'tag',
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
            'family_key', 'variant_key', 'sku', 'phone', 'whatsapp', 'email', 'image', 'tag',
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

    private static function verify_family_capabilities_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['id', 'family_id', 'capability_key', 'is_default', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'amount', 'config_json', 'payload', 'family_key', 'variant_key', 'is_enabled',
        ]);

        self::assert_id_column($table, $cols['id']);
        self::assert_bigint_unsigned_not_null($table, $cols['family_id'], 'family_id');
        self::assert_varchar_not_null_no_default($table, $cols['capability_key'], 'capability_key', 64);
        self::assert_tinyint_not_null_default_zero($table, $cols['is_default'], 'is_default');
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_index($table, 'uq_family_capability', ['family_id', 'capability_key']);
        self::verify_index($table, 'idx_capability_key', ['capability_key']);
    }

    private static function verify_container_capabilities_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['id', 'container_id', 'capability_key', 'is_active', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'amount', 'config_json', 'payload', 'family_key', 'variant_key',
        ]);

        self::assert_id_column($table, $cols['id']);
        self::assert_bigint_unsigned_not_null($table, $cols['container_id'], 'container_id');
        self::assert_varchar_not_null_no_default($table, $cols['capability_key'], 'capability_key', 64);
        self::assert_tinyint_not_null_default_zero($table, $cols['is_active'], 'is_active');
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_index($table, 'uq_container_capability', ['container_id', 'capability_key']);
        self::verify_index($table, 'idx_capability_key', ['capability_key']);
    }

    private static function verify_record_amount_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['record_id', 'amount', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'id', 'currency', 'family_key', 'variant_key', 'title', 'details',
        ]);

        self::assert_bigint_unsigned_not_null($table, $cols['record_id'], 'record_id');
        self::assert_decimal_amount_not_null($table, $cols['amount']);
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['record_id']);
    }

    private static function verify_record_phone_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['record_id', 'phone', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'id', 'family_key', 'variant_key', 'title', 'details', 'amount', 'whatsapp',
        ]);

        self::assert_bigint_unsigned_not_null($table, $cols['record_id'], 'record_id');
        self::assert_varchar_not_null_no_default($table, $cols['phone'], 'phone', 16);
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['record_id']);
    }

    private static function verify_record_whatsapp_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['record_id', 'whatsapp', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'id', 'family_key', 'variant_key', 'title', 'details', 'amount', 'phone', 'email',
        ]);

        self::assert_bigint_unsigned_not_null($table, $cols['record_id'], 'record_id');
        self::assert_varchar_not_null_no_default($table, $cols['whatsapp'], 'whatsapp', 16);
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['record_id']);
    }

    private static function verify_record_email_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['record_id', 'email', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'id', 'family_key', 'variant_key', 'title', 'details', 'amount', 'phone', 'whatsapp',
        ]);

        self::assert_bigint_unsigned_not_null($table, $cols['record_id'], 'record_id');
        self::assert_varchar_not_null_no_default($table, $cols['email'], 'email', 254);
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['record_id']);
    }

    private static function verify_contact_dossier_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['contact_record_id', 'archive_container_id', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'id', 'family_key', 'variant_key', 'title', 'details', 'amount', 'phone', 'whatsapp', 'email',
        ]);

        self::assert_bigint_unsigned_not_null($table, $cols['contact_record_id'], 'contact_record_id');
        self::assert_bigint_unsigned_not_null($table, $cols['archive_container_id'], 'archive_container_id');
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['contact_record_id']);
        self::verify_index($table, 'uq_dossier_archive_container', ['archive_container_id']);
    }

    private static function verify_contact_dossier_applications_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = ['contact_container_id', 'is_active', 'created_at', 'updated_at'];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'id', 'solution_key', 'context_type', 'config_json', 'payload', 'family_key',
        ]);

        self::assert_bigint_unsigned_not_null($table, $cols['contact_container_id'], 'contact_container_id');
        self::assert_tinyint_not_null_default_zero($table, $cols['is_active'], 'is_active');
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');
        self::verify_index($table, 'PRIMARY', ['contact_container_id']);
    }

    private static function verify_record_images_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = [
            'id', 'record_id', 'upload_operation_id', 'storage_path', 'content_sha256',
            'mime_type', 'byte_size', 'width', 'height', 'created_at',
        ];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'client_id', 'family_key', 'amount', 'status',
        ]);

        self::assert_id_column($table, $cols['id']);
        self::assert_bigint_unsigned_not_null($table, $cols['record_id'], 'record_id');
        self::assert_char_not_null_no_default($table, $cols['upload_operation_id'], 'upload_operation_id', 36);
        self::assert_varchar_not_null_no_default($table, $cols['storage_path'], 'storage_path', 191);
        self::assert_char_not_null_no_default($table, $cols['content_sha256'], 'content_sha256', 64);
        self::assert_varchar_not_null_no_default($table, $cols['mime_type'], 'mime_type', 64);
        self::assert_int_unsigned_not_null($table, $cols['byte_size'], 'byte_size');
        self::assert_int_unsigned_not_null($table, $cols['width'], 'width');
        self::assert_int_unsigned_not_null($table, $cols['height'], 'height');
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');

        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_index($table, 'uq_record_image_operation', ['upload_operation_id']);
        self::verify_index($table, 'uq_record_image_storage_path', ['storage_path']);
        self::verify_composite_index($table, ['record_id', 'id']);
    }

    private static function verify_image_upload_operations_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = [
            'upload_operation_id', 'record_id', 'storage_path', 'content_sha256', 'mime_type',
            'byte_size', 'width', 'height', 'status', 'expires_at', 'backend_intent_exp_ms',
            'upload_intent', 'upload_objects_json',
            'created_at', 'updated_at',
        ];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'id', 'client_id', 'committed',
        ]);

        self::assert_char_not_null_no_default($table, $cols['upload_operation_id'], 'upload_operation_id', 36);
        self::assert_bigint_unsigned_not_null($table, $cols['record_id'], 'record_id');
        self::assert_varchar_not_null_no_default($table, $cols['storage_path'], 'storage_path', 191);
        self::assert_char_not_null_no_default($table, $cols['content_sha256'], 'content_sha256', 64);
        self::assert_varchar_not_null_no_default($table, $cols['mime_type'], 'mime_type', 64);
        self::assert_int_unsigned_not_null($table, $cols['byte_size'], 'byte_size');
        self::assert_int_unsigned_not_null($table, $cols['width'], 'width');
        self::assert_int_unsigned_not_null($table, $cols['height'], 'height');
        self::assert_varchar_not_null_no_default($table, $cols['status'], 'status', 32);
        self::assert_datetime_not_null_no_default($table, $cols['expires_at'], 'expires_at');
        self::assert_bigint_unsigned_nullable($table, $cols['backend_intent_exp_ms'], 'backend_intent_exp_ms');
        self::assert_mediumtext_nullable($table, $cols['upload_intent'], 'upload_intent');
        self::assert_mediumtext_nullable($table, $cols['upload_objects_json'], 'upload_objects_json');
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['upload_operation_id']);
        self::verify_composite_index($table, ['status', 'expires_at']);
        self::verify_composite_index($table, ['record_id', 'status']);
        self::verify_index($table, 'idx_image_op_storage_path', ['storage_path']);
    }

    private static function verify_purge_runs_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = [
            'id', 'scope', 'target_id', 'family_key', 'status', 'cursor_kind', 'cursor_id',
            'cursor_operation_id', 'deleted_ok', 'failed_count',
            'mandate_id', 'container_id', 'capture_status',
            'images_read_after_id', 'ops_read_after_operation_id',
            'images_source_exhausted', 'ops_source_exhausted',
            'capture_complete', 'batches_prepared', 'prepared_batch_count',
            'last_accepted_batch_seq', 'sealed_at', 'capture_conflict_code',
            'accept_intent_batch_seq', 'seal_intent_at', 'cancelled_at',
            'local_retire_after_inventory_id', 'local_retire_after_record_id',
            'record_id',
            'created_at', 'updated_at',
        ];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_id_column($table, $cols['id']);
        self::assert_varchar_not_null_no_default($table, $cols['scope'], 'scope', 32);
        self::assert_bigint_unsigned_not_null($table, $cols['target_id'], 'target_id');
        self::assert_varchar_not_null_no_default($table, $cols['family_key'], 'family_key', 64);
        self::assert_varchar_not_null_no_default($table, $cols['status'], 'status', 32);
        self::assert_varchar_not_null_no_default($table, $cols['cursor_kind'], 'cursor_kind', 32);
        self::assert_bigint_unsigned_not_null($table, $cols['cursor_id'], 'cursor_id');
        self::assert_char_nullable($table, $cols['cursor_operation_id'], 'cursor_operation_id', 36);
        self::assert_int_unsigned_not_null($table, $cols['deleted_ok'], 'deleted_ok');
        self::assert_int_unsigned_not_null($table, $cols['failed_count'], 'failed_count');
        self::assert_char_nullable($table, $cols['mandate_id'], 'mandate_id', 36);
        self::assert_bigint_unsigned_not_null($table, $cols['container_id'], 'container_id');
        self::assert_varchar_not_null($table, $cols['capture_status'], 'capture_status', 32);
        if ((string) ($cols['capture_status']['Default'] ?? '') !== 'pending') {
            throw new \RuntimeException("[AA_Canonical_Schema] capture_status en {$table} debe DEFAULT pending");
        }
        self::assert_bigint_unsigned_not_null($table, $cols['images_read_after_id'], 'images_read_after_id');
        self::assert_char_nullable($table, $cols['ops_read_after_operation_id'], 'ops_read_after_operation_id', 36);
        self::assert_tinyint_not_null_default_zero($table, $cols['images_source_exhausted'], 'images_source_exhausted');
        self::assert_tinyint_not_null_default_zero($table, $cols['ops_source_exhausted'], 'ops_source_exhausted');
        self::assert_tinyint_not_null_default_zero($table, $cols['capture_complete'], 'capture_complete');
        self::assert_tinyint_not_null_default_zero($table, $cols['batches_prepared'], 'batches_prepared');
        self::assert_int_unsigned_not_null($table, $cols['prepared_batch_count'], 'prepared_batch_count');
        self::assert_int_unsigned_nullable($table, $cols['last_accepted_batch_seq'], 'last_accepted_batch_seq');
        self::assert_datetime_nullable($table, $cols['sealed_at'], 'sealed_at');
        self::assert_varchar_nullable($table, $cols['capture_conflict_code'], 'capture_conflict_code', 64);
        self::assert_int_unsigned_nullable($table, $cols['accept_intent_batch_seq'], 'accept_intent_batch_seq');
        self::assert_datetime_nullable($table, $cols['seal_intent_at'], 'seal_intent_at');
        self::assert_datetime_nullable($table, $cols['cancelled_at'], 'cancelled_at');
        self::assert_bigint_unsigned_not_null($table, $cols['local_retire_after_inventory_id'], 'local_retire_after_inventory_id');
        self::assert_bigint_unsigned_not_null($table, $cols['local_retire_after_record_id'], 'local_retire_after_record_id');
        self::assert_bigint_unsigned_nullable($table, $cols['record_id'], 'record_id');
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');
        self::assert_datetime_not_null_no_default($table, $cols['updated_at'], 'updated_at');

        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_composite_index($table, ['scope', 'target_id', 'status']);
        self::verify_index($table, 'idx_purge_mandate_id', ['mandate_id']);
        self::verify_composite_index($table, ['container_id', 'status']);
        self::verify_composite_index($table, ['record_id', 'status']);
    }

    private static function verify_purge_inventory_items_structure(string $table): void {
        $cols = self::columns_by_name($table);

        $expected = [
            'id', 'purge_run_id', 'upload_operation_id', 'wp_record_id',
            'content_sha256', 'byte_size', 'storage_path', 'source',
            'batch_seq', 'position_in_batch', 'created_at',
        ];
        foreach ($expected as $field) {
            if (!isset($cols[$field])) {
                throw new \RuntimeException("[AA_Canonical_Schema] Columna requerida ausente en {$table}: {$field}");
            }
        }

        self::assert_forbidden_columns($table, $cols, [
            'record_id', 'container_id', 'family_key', 'mandate_id',
        ]);

        self::assert_id_column($table, $cols['id']);
        self::assert_bigint_unsigned_not_null($table, $cols['purge_run_id'], 'purge_run_id');
        self::assert_char_not_null_no_default($table, $cols['upload_operation_id'], 'upload_operation_id', 36);
        self::assert_bigint_unsigned_not_null($table, $cols['wp_record_id'], 'wp_record_id');
        self::assert_char_not_null_no_default($table, $cols['content_sha256'], 'content_sha256', 64);
        self::assert_int_unsigned_not_null($table, $cols['byte_size'], 'byte_size');
        self::assert_varchar_not_null_no_default($table, $cols['storage_path'], 'storage_path', 191);
        self::assert_varchar_not_null_no_default($table, $cols['source'], 'source', 32);
        self::assert_int_unsigned_nullable($table, $cols['batch_seq'], 'batch_seq');
        self::assert_int_unsigned_nullable($table, $cols['position_in_batch'], 'position_in_batch');
        self::assert_datetime_not_null_no_default($table, $cols['created_at'], 'created_at');

        self::verify_index($table, 'PRIMARY', ['id']);
        self::verify_index($table, 'uq_purge_inventory_operation', ['purge_run_id', 'upload_operation_id']);
        self::verify_composite_index($table, ['purge_run_id', 'batch_seq', 'position_in_batch']);
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_decimal_amount_not_null(string $table, array $col): void {
        $type = strtolower((string) $col['Type']);
        if (
            strpos($type, 'decimal(19,2)') === false
            || strtoupper((string) $col['Null']) !== 'NO'
            || strpos($type, 'unsigned') !== false
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para amount en {$table}");
        }
        if ($col['Default'] !== null) {
            throw new \RuntimeException("[AA_Canonical_Schema] amount en {$table} no debe tener DEFAULT");
        }
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
    private static function assert_bigint_unsigned_nullable(string $table, array $col, string $name): void {
        $type = (string) $col['Type'];
        if (
            stripos($type, 'bigint') === false
            || strtoupper((string) $col['Null']) !== 'YES'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_mediumtext_nullable(string $table, array $col, string $name): void {
        $type = strtolower((string) $col['Type']);
        if (
            strpos($type, 'mediumtext') === false
            || strtoupper((string) $col['Null']) !== 'YES'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_int_unsigned_not_null(string $table, array $col, string $name): void {
        $type = strtolower((string) $col['Type']);
        if (
            strpos($type, 'bigint') !== false
            || !preg_match('/int(\(\d+\))?\s+unsigned/', $type)
            || strtoupper((string) $col['Null']) !== 'NO'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_char_not_null_no_default(
        string $table,
        array $col,
        string $name,
        int $length
    ): void {
        $needle = 'char(' . $length . ')';
        if (
            stripos((string) $col['Type'], $needle) === false
            || strtoupper((string) $col['Null']) !== 'NO'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
        if ($col['Default'] !== null) {
            throw new \RuntimeException("[AA_Canonical_Schema] {$name} en {$table} no debe tener DEFAULT");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_char_nullable(
        string $table,
        array $col,
        string $name,
        int $length
    ): void {
        $needle = 'char(' . $length . ')';
        if (
            stripos((string) $col['Type'], $needle) === false
            || strtoupper((string) $col['Null']) !== 'YES'
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
    /**
     * @param array<string, mixed> $col
     */
    private static function assert_int_unsigned_nullable(string $table, array $col, string $name): void {
        $type = strtolower((string) $col['Type']);
        if (
            strpos($type, 'bigint') !== false
            || !preg_match('/int(\(\d+\))?\s+unsigned/', $type)
            || strtoupper((string) $col['Null']) !== 'YES'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_datetime_nullable(string $table, array $col, string $name): void {
        if (
            stripos((string) $col['Type'], 'datetime') === false
            || strtoupper((string) $col['Null']) !== 'YES'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
        }
        $extra = strtoupper((string) ($col['Extra'] ?? ''));
        if (strpos($extra, 'ON UPDATE') !== false || strpos($extra, 'DEFAULT_GENERATED') !== false) {
            throw new \RuntimeException(
                "[AA_Canonical_Schema] {$name} en {$table} no debe tener DEFAULT/ON UPDATE horarios"
            );
        }
    }

    /**
     * @param array<string, mixed> $col
     */
    private static function assert_varchar_nullable(
        string $table,
        array $col,
        string $name,
        int $length
    ): void {
        $needle = 'varchar(' . $length . ')';
        if (
            stripos((string) $col['Type'], $needle) === false
            || strtoupper((string) $col['Null']) !== 'YES'
        ) {
            throw new \RuntimeException("[AA_Canonical_Schema] Definición inválida para {$name} en {$table}");
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
