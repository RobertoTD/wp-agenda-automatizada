<?php
/**
 * AA Schema — Lifecycle de instalación y migración del plugin.
 *
 * Responsabilidad:
 *  - DDL de las tablas propias del plugin (aa_reservas, aa_notifications,
 *    aa_staff, aa_service_areas, aa_assignments, aa_services,
 *    aa_staff_services, aa_assignment_services,
 *    aa_learning_recommendation_state, aa_task_lists, aa_tasks, aa_task_state,
 *    aa_task_actions, aa_expediente_categories, aa_expedientes,
 *    aa_expediente_registros, aa_expediente_adjuntos).
 *  - Migraciones inline de columnas para instalaciones existentes
 *    (public_calendar, duration_minutes, calendar_uid).
 *  - Inicialización de options con valor por defecto (aa_estado_gsync,
 *    aa_service_schedule, aa_staff_schedule).
 *  - Registro de rewrite rules de las rutas custom y flush al activar.
 *  - **Migraciones automáticas al actualizar el plugin**: comparación
 *    de `DB_VERSION` (constante en código) vs `aa_db_version` (option en BD)
 *    en cada `admin_init`. Si la BD está atrás, re-ejecuta `install()`
 *    (idempotente vía dbDelta) y bumpea el option.
 *
 * Encapsula todo lo que se ejecuta cuando WordPress activa el plugin
 * y todo lo que debe correr cuando el plugin se actualiza a una versión
 * con cambios de esquema.
 *
 * ───────────────────────────────────────────────────────────────────────
 * CÓMO AÑADIR UNA MIGRACIÓN NUEVA EN EL FUTURO
 * ───────────────────────────────────────────────────────────────────────
 * 1. Añade el cambio de esquema dentro de `install()` (CREATE TABLE nueva,
 *    ALTER ADD COLUMN, etc.). dbDelta() es idempotente: si la columna ya
 *    existe no hace nada; si falta la añade.
 * 2. Bumpea la constante `DB_VERSION` (de '1' a '2', a '3', etc.).
 * 3. Listo. La próxima vez que cualquier admin entre al backend, el
 *    `maybe_migrate()` detectará la diferencia, re-ejecutará `install()`
 *    (que aplicará solo lo que falte) y bumpeará el option en BD.
 *
 * Nota: `DB_VERSION` es independiente de la versión del plugin
 * (`Version:` en la cabecera de wp-agenda-automatizada.php). Solo se
 * bumpea cuando hay cambios de esquema, no en cada release.
 * ───────────────────────────────────────────────────────────────────────
 *
 * Deuda técnica explícita (NO se toca en esta extracción):
 *  - El método install() también inicializa options y hace flush de
 *    rewrite rules. Idealmente se separaría en
 *    Schema::install_tables() + Options::seed() + Routes::register(),
 *    pero esa separación se posterga para no inflar este prompt.
 *  - Las funciones de migración en clientes.php
 *    (aa_create_clientes_table, etc.) se siguen invocando como funciones
 *    globales. Migrarlas a esta clase es trabajo de contagio cuando se
 *    toque clientes.php.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\WP
 */

defined('ABSPATH') or die('No direct access');

final class AA_Schema {

    /**
     * Versión actual del esquema en código.
     *
     * Bumpea esta constante cada vez que añadas un cambio de esquema en
     * `install()` (CREATE TABLE nueva, ALTER ADD COLUMN, índice nuevo).
     *
     * Independiente de la versión del plugin. Solo refleja el estado
     * de las tablas/columnas/índices.
     */
    public const DB_VERSION = '15';

    public const OPTION_INSTALLATION_INITIALIZED_AT = 'aa_installation_initialized_at';

    /**
     * Registra el activation hook y el chequeo de migraciones.
     *
     * - `register_activation_hook` cubre la primera instalación
     *   (cuando alguien activa el plugin manualmente).
     * - `add_action('admin_init', ...)` cubre las actualizaciones
     *   (cuando WordPress reemplaza los archivos sin reactivar).
     *
     * @param string $main_plugin_file Path absoluto del archivo principal del plugin.
     */
    public static function register(string $main_plugin_file): void {
        register_activation_hook($main_plugin_file, [__CLASS__, 'install']);
        add_action('admin_init', [__CLASS__, 'maybe_migrate']);
    }

    /**
     * Aplica migraciones de esquema si la versión en BD está atrás de
     * la versión en código.
     *
     * Diseñado para ejecutarse en `admin_init`: solo corre cuando un
     * admin entra al backend. Una sola query de get_option() en el caso
     * normal (BD ya al día) — coste despreciable.
     *
     * En caso de error durante install(), no bumpea el option, así que
     * el siguiente admin_init reintentará automáticamente.
     */
    public static function maybe_migrate(): void {
        $stored = get_option('aa_db_version', '0');

        if (version_compare($stored, self::DB_VERSION, '>=')) {
            return; // BD ya al día, no hay nada que hacer
        }

        try {
            self::install();
        } catch (\Throwable $e) {
            error_log('[AA_Schema] Migración falló: ' . $e->getMessage());
            // No bumpeamos el option; el próximo admin_init reintentará.
        }
    }

    /**
     * Callback del activation hook.
     *
     * NO modifica el orden ni la lógica de lo que ya hacía el callback
     * inline en wp-agenda-automatizada.php. Es una reubicación literal.
     */
    public static function install(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            servicio varchar(255) NOT NULL,
            fecha datetime NOT NULL,
            duracion smallint unsigned NOT NULL DEFAULT 60,
            assignment_id bigint(20) unsigned NULL,
            nombre varchar(255) NOT NULL,
            telefono varchar(50) NOT NULL,
            correo varchar(255),
            estado varchar(50) DEFAULT 'pending',
            calendar_uid varchar(255) DEFAULT NULL,
            virtual_link text DEFAULT NULL,
            join_token varchar(64) DEFAULT NULL,
            service_price_snapshot decimal(10,2) DEFAULT NULL,
            amount_charged decimal(10,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY calendar_uid (calendar_uid),
            KEY idx_assignment_id (assignment_id),
            UNIQUE KEY join_token (join_token)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        aa_create_clientes_table();
        aa_add_cliente_column_to_reservas();
        self::add_calendar_uid_column();
        aa_add_join_token_column_to_reservas();

        // 🔹 Crear tabla de notificaciones
        $notifications_table = $wpdb->prefix . 'aa_notifications';
        $notifications_sql = "CREATE TABLE $notifications_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY entity (entity_type, entity_id),
            KEY is_read (is_read),
            KEY type (type)
        ) $charset;";

        dbDelta($notifications_sql);

        // 🔹 Crear tabla de personal (staff)
        $staff_table = $wpdb->prefix . 'aa_staff';
        $staff_sql = "CREATE TABLE $staff_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            active tinyint(1) DEFAULT 1,
            is_hidden tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        dbDelta($staff_sql);

        // 🔹 Crear tabla de zonas de atención (service areas)
        $service_areas_table = $wpdb->prefix . 'aa_service_areas';
        $service_areas_sql = "CREATE TABLE $service_areas_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            description text,
            color text DEFAULT NULL,
            active tinyint(1) DEFAULT 1,
            is_hidden tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        dbDelta($service_areas_sql);

        // Ensure is_hidden column exists for existing staff installs
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$staff_table} LIKE %s", 'is_hidden'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$staff_table} ADD COLUMN is_hidden tinyint(1) NOT NULL DEFAULT 0");
        }

        // Ensure is_hidden column exists for existing service area installs
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$service_areas_table} LIKE %s", 'is_hidden'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$service_areas_table} ADD COLUMN is_hidden tinyint(1) NOT NULL DEFAULT 0");
        }

        // 🔹 Crear tabla de asignaciones (assignments)
        $assignments_table = $wpdb->prefix . 'aa_assignments';
        $assignments_sql = "CREATE TABLE $assignments_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            assignment_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            staff_id bigint(20) unsigned NOT NULL,
            service_area_id bigint(20) unsigned NOT NULL,
            service_key varchar(191) NOT NULL,
            capacity int DEFAULT 1,
            repeat_weekly tinyint(1) DEFAULT 0,
            repeat_until date DEFAULT NULL,
            status varchar(50) DEFAULT 'active',
            is_hidden tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY staff_id (staff_id),
            KEY service_area_id (service_area_id),
            KEY assignment_date (assignment_date),
            KEY status (status)
        ) $charset;";

        dbDelta($assignments_sql);

        // 🔹 Crear tabla de servicios (services)
        $services_table = $wpdb->prefix . 'aa_services';
        $services_sql = "CREATE TABLE $services_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            code varchar(191) NOT NULL,
            description text DEFAULT NULL,
            indicaciones_cita text DEFAULT NULL,
            price decimal(10,2) DEFAULT NULL,
            duration_minutes smallint unsigned DEFAULT NULL,
            active tinyint(1) DEFAULT 1,
            is_hidden tinyint(1) NOT NULL DEFAULT 0,
            public_calendar tinyint(1) NOT NULL DEFAULT 0,
            attendance_type varchar(20) DEFAULT NULL,
            virtual_channel varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY code (code),
            KEY active (active)
        ) $charset;";

        dbDelta($services_sql);

        // Ensure public_calendar column exists for existing installs (no extra migrations)
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$services_table} LIKE %s", 'public_calendar'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$services_table} ADD COLUMN public_calendar tinyint(1) NOT NULL DEFAULT 0");
        }

        // Ensure duration_minutes column exists for existing installs
        $col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$services_table} LIKE %s", 'duration_minutes'));
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$services_table} ADD COLUMN duration_minutes smallint unsigned DEFAULT NULL");
        }

        // 🔹 Crear tabla pivote para relación muchos-a-muchos entre staff y services
        $staff_services_table = $wpdb->prefix . 'aa_staff_services';
        $staff_services_sql = "CREATE TABLE $staff_services_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            staff_id bigint(20) unsigned NOT NULL,
            service_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_staff_service (staff_id, service_id),
            KEY staff_id (staff_id),
            KEY service_id (service_id)
        ) $charset;";

        dbDelta($staff_services_sql);

        // 🔹 Crear tabla pivote para relación muchos-a-muchos entre assignments y services
        $assignment_services_table = $wpdb->prefix . 'aa_assignment_services';
        $assignment_services_sql = "CREATE TABLE $assignment_services_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            assignment_id bigint(20) unsigned NOT NULL,
            service_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY assignment_service (assignment_id, service_id),
            KEY assignment_id (assignment_id),
            KEY service_id (service_id)
        ) $charset;";

        dbDelta($assignment_services_sql);

        // 🔹 Estado por instalación de recomendaciones de aprendizaje (Guías/Aprendizaje)
        $learning_state_table = $wpdb->prefix . 'aa_learning_recommendation_state';
        $learning_state_sql = "CREATE TABLE $learning_state_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            recommendation_key varchar(100) NOT NULL,
            is_completed tinyint(1) NOT NULL DEFAULT 0,
            is_ignored tinyint(1) NOT NULL DEFAULT 0,
            list_override tinyint(1) DEFAULT NULL,
            last_suggested_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            ignored_at datetime DEFAULT NULL,
            is_dismissed tinyint(1) NOT NULL DEFAULT 0,
            dismissed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY recommendation_key (recommendation_key)
        ) $charset;";

        dbDelta($learning_state_sql);

        // 🔹 Listas de tareas (Listas/Tareas — MC1 + MC13O-B1 fuente común)
        $task_lists_table = $wpdb->prefix . 'aa_task_lists';
        $task_lists_sql = "CREATE TABLE $task_lists_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            owner_type varchar(20) NOT NULL DEFAULT 'user',
            source_category varchar(20) NOT NULL DEFAULT 'user',
            origin_key varchar(100) DEFAULT NULL,
            managed_by varchar(20) NOT NULL DEFAULT 'user',
            importance int NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            position int NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY owner_type (owner_type),
            KEY source_category (source_category),
            KEY position (position)
        ) $charset;";

        dbDelta($task_lists_sql);
        self::ensure_index($task_lists_table, 'uniq_list_origin', 'ALTER TABLE ' . $task_lists_table . ' ADD UNIQUE KEY uniq_list_origin (source_category, origin_key)');
        self::ensure_index($task_lists_table, 'source_category', 'ALTER TABLE ' . $task_lists_table . ' ADD KEY source_category (source_category)');

        // 🔹 Tareas por lista (Listas/Tareas — MC1 + MC13O-B1 fuente común)
        $tasks_table = $wpdb->prefix . 'aa_tasks';
        $tasks_sql = "CREATE TABLE $tasks_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            list_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            notes text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            source varchar(20) NOT NULL DEFAULT 'user',
            source_category varchar(20) NOT NULL DEFAULT 'user',
            origin_key varchar(100) DEFAULT NULL,
            managed_by varchar(20) NOT NULL DEFAULT 'user',
            default_bucket varchar(20) NOT NULL DEFAULT 'primary',
            completion_type varchar(20) NOT NULL DEFAULT 'manual',
            completion_fact_key varchar(100) DEFAULT NULL,
            importance int NOT NULL DEFAULT 0,
            due_at datetime DEFAULT NULL,
            execution_available_at datetime DEFAULT NULL,
            position int NOT NULL DEFAULT 0,
            completed_at datetime DEFAULT NULL,
            archived_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY list_id (list_id),
            KEY status (status),
            KEY source_category (source_category),
            KEY due_at (due_at)
        ) $charset;";

        dbDelta($tasks_sql);
        self::ensure_index($tasks_table, 'uniq_task_origin', 'ALTER TABLE ' . $tasks_table . ' ADD UNIQUE KEY uniq_task_origin (source_category, origin_key)');
        self::ensure_index($tasks_table, 'source_category', 'ALTER TABLE ' . $tasks_table . ' ADD KEY source_category (source_category)');

        $archived_at_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$tasks_table} LIKE %s", 'archived_at'));
        if (empty($archived_at_col)) {
            $wpdb->query("ALTER TABLE {$tasks_table} ADD COLUMN archived_at datetime DEFAULT NULL");
        }

        $execution_available_at_col = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$tasks_table} LIKE %s", 'execution_available_at'));
        if (empty($execution_available_at_col)) {
            $wpdb->query("ALTER TABLE {$tasks_table} ADD COLUMN execution_available_at datetime DEFAULT NULL AFTER due_at");
        }

        // 🔹 Señales operativas por tarea (Listas/Tareas — MC13G-A + MC13O-B1 system completion)
        $task_state_table = $wpdb->prefix . 'aa_task_state';
        $task_state_sql = "CREATE TABLE $task_state_table (
            task_id bigint(20) unsigned NOT NULL,
            last_deferred_at datetime DEFAULT NULL,
            defer_until datetime DEFAULT NULL,
            defer_count int NOT NULL DEFAULT 0,
            last_dismissed_at datetime DEFAULT NULL,
            dismiss_until datetime DEFAULT NULL,
            dismiss_count int NOT NULL DEFAULT 0,
            completed_by_system tinyint(1) NOT NULL DEFAULT 0,
            system_completed_at datetime DEFAULT NULL,
            last_system_evaluated_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (task_id)
        ) $charset;";

        dbDelta($task_state_sql);

        // 🔹 Acciones declaradas por tarea (Listas/Tareas — MC13O-B2 schema-only)
        $task_actions_table = $wpdb->prefix . 'aa_task_actions';
        $task_actions_sql = "CREATE TABLE $task_actions_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            task_id bigint(20) unsigned NOT NULL,
            action_key varchar(100) NOT NULL,
            type varchar(20) NOT NULL,
            label varchar(120) NOT NULL,
            placement varchar(20) NOT NULL DEFAULT 'primary',
            category varchar(20) NOT NULL DEFAULT 'mechanical',
            target_status varchar(20) DEFAULT NULL,
            target_module varchar(100) DEFAULT NULL,
            target_setup_focus varchar(100) DEFAULT NULL,
            target_fragment varchar(100) DEFAULT NULL,
            url text DEFAULT NULL,
            handler varchar(100) DEFAULT NULL,
            payload_json longtext DEFAULT NULL,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            position int NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_task_action (task_id, action_key),
            KEY task_id (task_id),
            KEY action_key (action_key),
            KEY type (type),
            KEY enabled (enabled),
            KEY position (position)
        ) $charset;";

        dbDelta($task_actions_sql);

        // 🔹 Registros de expediente (MC2 client_id; DB 15 expediente_id nullable, sin consumidores)
        $expediente_registros_table = $wpdb->prefix . 'aa_expediente_registros';
        $expediente_registros_sql = "CREATE TABLE $expediente_registros_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned NOT NULL,
            expediente_id bigint(20) unsigned DEFAULT NULL,
            title varchar(200) NOT NULL,
            body text NOT NULL,
            recorded_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY client_recorded (client_id, recorded_at, id),
            KEY expediente_recorded (expediente_id, recorded_at, id)
        ) $charset;";

        dbDelta($expediente_registros_sql);
        self::ensure_index(
            $expediente_registros_table,
            'expediente_recorded',
            'ALTER TABLE ' . $expediente_registros_table . ' ADD KEY expediente_recorded (expediente_id, recorded_at, id)'
        );

        // 🔹 Adjuntos finalizados de registros de expediente (MC4a2 — metadatos locales; binario en Supabase)
        $expediente_adjuntos_table = $wpdb->prefix . 'aa_expediente_adjuntos';
        $expediente_adjuntos_sql = "CREATE TABLE $expediente_adjuntos_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            record_id bigint(20) unsigned NOT NULL,
            client_id bigint(20) unsigned NOT NULL,
            upload_operation_id char(36) NOT NULL,
            storage_path varchar(191) NOT NULL,
            mime_type varchar(64) NOT NULL,
            byte_size int unsigned NOT NULL,
            width int unsigned NOT NULL,
            height int unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY record_id_id (record_id, id),
            KEY client_record (client_id, record_id)
        ) $charset;";

        dbDelta($expediente_adjuntos_sql);

        self::ensure_index(
            $expediente_adjuntos_table,
            'uq_aa_exp_adj_operation',
            'ALTER TABLE ' . $expediente_adjuntos_table . ' ADD UNIQUE KEY uq_aa_exp_adj_operation (upload_operation_id)'
        );
        self::ensure_index(
            $expediente_adjuntos_table,
            'uq_aa_exp_adj_storage_path',
            'ALTER TABLE ' . $expediente_adjuntos_table . ' ADD UNIQUE KEY uq_aa_exp_adj_storage_path (storage_path)'
        );

        // 🔹 Catálogo de categorías de expediente (DB 14 — slug estable; seed general)
        $expediente_categories_table = $wpdb->prefix . 'aa_expediente_categories';
        $expediente_categories_sql = "CREATE TABLE $expediente_categories_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(64) NOT NULL,
            name varchar(100) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset;";

        dbDelta($expediente_categories_sql);
        self::ensure_index(
            $expediente_categories_table,
            'slug',
            'ALTER TABLE ' . $expediente_categories_table . ' ADD UNIQUE KEY slug (slug)'
        );

        // 🔹 Expedientes padre (DB 14 — category_id obligatorio; sin client_id ni FK)
        $expedientes_table = $wpdb->prefix . 'aa_expedientes';
        $expedientes_sql = "CREATE TABLE $expedientes_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            description text,
            category_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY category_id (category_id),
            KEY created_id (created_at, id)
        ) $charset;";

        dbDelta($expedientes_sql);

        self::ensure_expediente_category_general();

        // NOTA: FOREIGN KEY constraints no se incluyen aquí porque dbDelta() puede tener problemas
        // con ellos. Si se necesitan, deben agregarse manualmente después de la creación:
        // ALTER TABLE {$wpdb->prefix}aa_staff_services 
        //   ADD CONSTRAINT fk_staff FOREIGN KEY (staff_id) REFERENCES {$wpdb->prefix}aa_staff(id) ON DELETE CASCADE,
        //   ADD CONSTRAINT fk_service FOREIGN KEY (service_id) REFERENCES {$wpdb->prefix}aa_services(id) ON DELETE CASCADE;

        // 🔹 Inicializar estado de sincronización como válido
        if (get_option('aa_estado_gsync') === false) {
            add_option('aa_estado_gsync', 'valid');
        }

        // LEGACY_FIXED_SCHEDULE: default options for deprecated fixed schedule (aa_service_schedule, aa_staff_schedule). Do not extend.
        // 🔹 Inicializar nuevo campo con valor por defecto
        if (get_option('aa_service_schedule') === false) {
            add_option('aa_service_schedule', ''); // ⚠️ Cambia 'aa_nuevo_campo' y el valor por defecto según necesites
        }

        // 🔹 Inicializar campo de personal con valor por defecto
        if (get_option('aa_staff_schedule') === false) {
            add_option('aa_staff_schedule', '');
        }

        // Auto-asignación staff-servicio: ON en instalación nueva, OFF en migraciones existentes.
        if (get_option('aa_auto_assign_staff_services') === false) {
            $is_fresh_install = get_option('aa_db_version', false) === false;
            add_option('aa_auto_assign_staff_services', $is_fresh_install ? '1' : '0');
        }

        // Marca instalaciones realmente nuevas (antes de bump de aa_db_version).
        if (get_option(self::OPTION_INSTALLATION_INITIALIZED_AT, false) === false) {
            $is_fresh_install = get_option('aa_db_version', false) === false;

            if ($is_fresh_install) {
                add_option(self::OPTION_INSTALLATION_INITIALIZED_AT, current_time('mysql'));
            }
        }

        // 🔹 Flush rewrite rules for custom endpoints
        add_rewrite_rule('^agenda-app/?$', 'index.php?aa_agenda_app=1', 'top');
        add_rewrite_rule('^citas-virtuales/?$', 'index.php?aa_citas_virtuales=1', 'top');
        flush_rewrite_rules();

        // Marcar el esquema como actualizado a la versión actual.
        // Esto cubre tanto la primera instalación (vía activation hook)
        // como las migraciones automáticas (vía maybe_migrate()).
        update_option('aa_db_version', self::DB_VERSION);
    }

    /**
     * Crea la categoría de sistema `general` si aún no existe en el blog actual.
     *
     * Idempotente ante reejecución de install() y ante una carrera protegida
     * por UNIQUE(slug): si el INSERT falla pero la fila ya existe, no se
     * considera fallida la migración.
     */
    private static function ensure_expediente_category_general(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'aa_expediente_categories';
        $slug = 'general';

        if (self::expediente_category_id_by_slug($table, $slug) !== null) {
            return;
        }

        $previous_suppress = $wpdb->suppress_errors(true);
        $wpdb->insert(
            $table,
            [
                'slug' => $slug,
                'name' => 'General',
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s']
        );
        $wpdb->suppress_errors($previous_suppress);

        if (self::expediente_category_id_by_slug($table, $slug) !== null) {
            return;
        }

        $error = is_string($wpdb->last_error) && $wpdb->last_error !== ''
            ? $wpdb->last_error
            : 'unknown';

        throw new \RuntimeException(
            '[AA_Schema] No se pudo crear la categoría de expediente general: ' . $error
        );
    }

    /**
     * @return int|null
     */
    private static function expediente_category_id_by_slug(string $table, string $slug): ?int {
        global $wpdb;

        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE slug = %s LIMIT 1",
                $slug
            )
        );

        if ($id === null || $id === false || $id === '') {
            return null;
        }

        $id = (int) $id;

        return $id > 0 ? $id : null;
    }

    /**
     * Asegura índices no cubiertos de forma confiable por dbDelta().
     *
     * @param string $table_name Nombre completo de tabla con prefijo.
     * @param string $index_name Nombre del índice a verificar.
     * @param string $alter_sql  ALTER TABLE idempotente a ejecutar si falta.
     */
    private static function ensure_index(string $table_name, string $index_name, string $alter_sql): void {
        global $wpdb;

        $existing = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                $index_name
            )
        );

        if (!empty($existing)) {
            return;
        }

        $wpdb->query($alter_sql);
    }

    /**
     * Migración: añade la columna calendar_uid a aa_reservas si no existe.
     *
     * Antes era la función global `aa_add_calendar_uid_column()` en el
     * bootstrap. Como solo se invocaba desde el activation hook, se
     * reubica como método privado de esta clase.
     */
    private static function add_calendar_uid_column(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';

        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'calendar_uid'",
                DB_NAME,
                $table
            )
        );

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN calendar_uid varchar(255) DEFAULT NULL AFTER estado");
            $wpdb->query("ALTER TABLE $table ADD INDEX idx_calendar_uid (calendar_uid)");
            error_log("✅ Columna calendar_uid agregada a aa_reservas");
        }
    }
}
