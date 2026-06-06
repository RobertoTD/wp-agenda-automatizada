<?php
/**
 * AA Schema — Lifecycle de instalación y migración del plugin.
 *
 * Responsabilidad:
 *  - DDL de las tablas propias del plugin (aa_reservas, aa_notifications,
 *    aa_staff, aa_service_areas, aa_assignments, aa_services,
 *    aa_staff_services, aa_assignment_services,
 *    aa_learning_recommendation_state, aa_task_lists, aa_tasks, aa_task_state).
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
    public const DB_VERSION = '5';

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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset;";

        dbDelta($service_areas_sql);

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

        // 🔹 Listas de tareas (Listas/Tareas — MC1)
        $task_lists_table = $wpdb->prefix . 'aa_task_lists';
        $task_lists_sql = "CREATE TABLE $task_lists_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text DEFAULT NULL,
            owner_type varchar(20) NOT NULL DEFAULT 'user',
            importance int NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            position int NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY owner_type (owner_type),
            KEY position (position)
        ) $charset;";

        dbDelta($task_lists_sql);

        // 🔹 Tareas por lista (Listas/Tareas — MC1)
        $tasks_table = $wpdb->prefix . 'aa_tasks';
        $tasks_sql = "CREATE TABLE $tasks_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            list_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            notes text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            source varchar(20) NOT NULL DEFAULT 'user',
            importance int NOT NULL DEFAULT 0,
            due_at datetime DEFAULT NULL,
            position int NOT NULL DEFAULT 0,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY list_id (list_id),
            KEY status (status),
            KEY due_at (due_at)
        ) $charset;";

        dbDelta($tasks_sql);

        // 🔹 Señales operativas por tarea (Listas/Tareas — MC13G-A)
        $task_state_table = $wpdb->prefix . 'aa_task_state';
        $task_state_sql = "CREATE TABLE $task_state_table (
            task_id bigint(20) unsigned NOT NULL,
            last_deferred_at datetime DEFAULT NULL,
            defer_until datetime DEFAULT NULL,
            defer_count int NOT NULL DEFAULT 0,
            last_dismissed_at datetime DEFAULT NULL,
            dismiss_until datetime DEFAULT NULL,
            dismiss_count int NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (task_id)
        ) $charset;";

        dbDelta($task_state_sql);

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
