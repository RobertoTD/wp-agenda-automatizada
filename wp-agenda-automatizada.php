<?php
/**
 * Plugin Name: WP Agenda Automatizada
 * Description: Sistema de gestión de citas con integración a Google Calendar, pega este Shortcode donde quieras mostrar tu calendario: [agenda_automatizada]
 * Version: 2.2.6
 * Author: Roberto Tejada
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// ===============================
// 🔹 CONSTANTES DEL PLUGIN
// ===============================
define('AA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AA_PLUGIN_URL', plugin_dir_url(__FILE__));
$plugin_data = get_file_data(__FILE__, ['Version' => 'Version']);
define('AA_PLUGIN_VERSION', $plugin_data['Version']);

// Detectar entorno automáticamente
$site_url = get_site_url();

if (strpos($site_url, 'localhost') !== false || strpos($site_url, '127.0.0.1') !== false) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
} else {
    define('AA_API_BASE_URL', 'https://api.deoia.com');
}

// ===============================
// 🔹 LIBRERÍA PLUGIN UPDATE CHECKER
// ===============================
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

// ===============================
// 🔹 AUTO-UPDATES DESDE GITHUB RELEASES
// ===============================
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/RobertoTD/wp-agenda-automatizada/',
    __FILE__,
    'wp-agenda-automatizada'
);

// Repo privado => necesitas token (ver abajo)
$updateChecker->setBranch('main');

// Que use Releases (assets) en vez de "source code"
$updateChecker->getVcsApi()->enableReleaseAssets();

// ===============================
// 🔹 ORDEN CORRECTO DE INCLUSIÓN
// ===============================

// 1️⃣ Helpers y utilidades base
require_once plugin_dir_path(__FILE__) . 'includes/auth-helper.php';

// 2️⃣ Modelos (acceso a datos)
require_once plugin_dir_path(__FILE__) . 'clientes.php';
require_once plugin_dir_path(__FILE__) . 'includes/models/AssignmentsModel.php';

// 3️⃣ Servicios
require_once plugin_dir_path(__FILE__) . 'includes/services/SyncService.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/ClienteService.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/notificationsService.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/appointmentsService.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/assignmentsService.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/dashboardService.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/ai/ai-module.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/assignments/servicesService.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/assignments/staffService.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/assignments/areasService.php';

// 4️⃣ Controladores (lógica de negocio)
require_once plugin_dir_path(__FILE__) . 'includes/controllers/availability-controller.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/proximasCitasController.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/confirmController.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/WebhooksController.php';

// 5️⃣ Controlador de encolado (DEBE IR DESPUÉS de availability-controller)
require_once plugin_dir_path(__FILE__) . 'includes/controllers/enqueueController.php';

// 6️⃣ Vistas
require_once plugin_dir_path(__FILE__) . 'views/admin-controls.php';

// 7️⃣ Módulos adicionales
require_once plugin_dir_path(__FILE__) . 'asistant-user.php';
require_once plugin_dir_path(__FILE__) . 'historial-citas.php';

// 8️⃣ Admin: Iframe Test (UI aislada)
require_once plugin_dir_path(__FILE__) . 'includes/admin/iframe-test.php';

// 9️⃣ Routes: Agenda App endpoint
require_once plugin_dir_path(__FILE__) . 'includes/routes/agenda-app.php';

// 🔟 Routes: Citas Virtuales portal (join by token)
require_once plugin_dir_path(__FILE__) . 'includes/routes/citas-virtuales.php';

// 1️⃣1️⃣ AI: conexión mínima del endpoint AJAX del chat admin
AA_AI_Module::register();

// ================================
// 🔹 REGISTRO DE WEBHOOKS REST API
// ================================
add_action('rest_api_init', function() {
    $webhooks_controller = new Webhooks_Controller();
    $webhooks_controller->register_routes();
});

// ================================
// 🔹 Endpoint AJAX: Guardar cita desde el frontend
// ================================
add_action('wp_ajax_nopriv_aa_save_reservation', 'aa_save_reservation');
add_action('wp_ajax_aa_save_reservation', 'aa_save_reservation');

function aa_save_reservation() {
    // Leer cuerpo JSON enviado desde JS
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        wp_send_json_error(['message' => 'Datos inválidos.']);
    }

    // ✅ Validar nonce de seguridad
    if (empty($data['nonce']) || !wp_verify_nonce($data['nonce'], 'aa_reservation_nonce')) {
        wp_send_json_error(['message' => 'Error de validación de seguridad (nonce inválido).']);
    }

    // ✅ Validar honeypot (campo invisible anti-bot)
    if (!empty($data['extra_field'])) {
        wp_send_json_error(['message' => 'Detección de bot: envío no permitido.']);
    }

    // Construir input del Use Case (sin nonce/extra_field)
    $input = $data;
    unset($input['nonce'], $input['extra_field']);

    // Cargar y ejecutar Use Case
    require_once __DIR__ . '/includes/application/booking/CreateReservationUseCase.php';
    $useCase = new CreateReservationUseCase();
    $result  = $useCase->execute($input);

    if (!empty($result['success'])) {
        wp_send_json_success($result['data']);
    }

    $error_payload = ['message' => $result['error']['message']];
    if (!empty($result['error']['detail'])) {
        $error_payload['error'] = $result['error']['detail'];
    }
    wp_send_json_error($error_payload);
}

// 🔹 Crear tablas al activar el plugin
register_activation_hook(__FILE__, function() {
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
    aa_add_calendar_uid_column();
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
    
    // NOTA: FOREIGN KEY constraints no se incluyen aquí porque dbDelta() puede tener problemas
    // con ellos. Si se necesitan, deben agregarse manualmente después de la creación:
    // ALTER TABLE {$wpdb->prefix}aa_staff_services 
    //   ADD CONSTRAINT fk_staff FOREIGN KEY (staff_id) REFERENCES {$wpdb->prefix}aa_staff(id) ON DELETE CASCADE,
    //   ADD CONSTRAINT fk_service FOREIGN KEY (service_id) REFERENCES {$wpdb->prefix}aa_services(id) ON DELETE CASCADE;
    
    // 🔹 Inicializar estado de sincronización como válido
    if (get_option('aa_estado_gsync') === false) {
        add_option('aa_estado_gsync', 'valid');
    }
    
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
});

// 🔹 Flush rewrite rules on deactivation
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// ===============================
// 🔹 Función para agregar columna calendar_uid
// ===============================
function aa_add_calendar_uid_column() {
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

// 🔹 Función helper para obtener la hora actual según aa_timezone
function aa_get_current_datetime() {
    $timezone_string = get_option('aa_timezone', 'America/Mexico_City');
    
    try {
        $timezone = new DateTimeZone($timezone_string);
        $now = new DateTime('now', $timezone);
        return $now->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log("❌ Error al obtener zona horaria: " . $e->getMessage());
        return current_time('mysql');
    }
}

// ===============================
// 🟠 SHORTCODE: Formulario de agenda
// ===============================
function wpaa_render_form() {
    ob_start(); ?>

    <form id="agenda-form">

        <!-- Servicio -->
        <select id="servicio" name="servicio" required>
    <option value="">-- Selecciona un servicio --</option>
    <?php
    // Servicios desde asignaciones (solo activos y no ocultos)
    $servicios_bd = [];
    if (class_exists('AssignmentsModel')) {
        $servicios_bd = AssignmentsModel::get_services(true); // true = solo activos (filtra is_hidden = 0 y active = 1)
    }
    
    // Agregar servicios desde la base de datos (solo activos y no ocultos)
    if (!empty($servicios_bd)) {
        foreach ($servicios_bd as $servicio) {
            // Filtrar solo servicios activos (active = 1)
            if (isset($servicio['active']) && intval($servicio['active']) === 1) {
                // Excluir servicios no marcados para calendario público
                if (isset($servicio['public_calendar']) && intval($servicio['public_calendar']) !== 1) {
                    continue;
                }
                $service_id = esc_attr($servicio['id']);
                $service_name = esc_html($servicio['name']);
                // Label informativo en el select: prefijo "• Videollamada" solo para servicios virtuales
                if (isset($servicio['attendance_type']) && $servicio['attendance_type'] === 'virtual') {
                    $service_name = '• Videollamada ' . $service_name;
                }
                echo "<option value='{$service_id}'>{$service_name}</option>";
            }
        }
    }
    
    // Agregar opción de horario fijo si existe
    $service_schedule = get_option('aa_service_schedule', '');
    if (!empty($service_schedule)) {
        $service_name = esc_html($service_schedule);
        $service_value = esc_attr('fixed::' . $service_schedule);
        echo "<option value='{$service_value}'>{$service_name}</option>";
    }
    ?>
    </select>

        <!-- Calendario -->
        <div id="wpagenda-calendar"></div>
        <input type="hidden" id="fecha" name="fecha" required>

        <!-- Personal disponible (nuevo - basado en assignments) -->
        <div id="staff-selector-wrapper" style="display:none;">
            <label for="staff-selector">Personal disponible</label>
            <select id="staff-selector" name="staff_id" disabled>
                <option value="">Selecciona primero fecha y servicio</option>
            </select>
        </div>
        <input type="hidden" id="assignment-id" name="assignment_id">

        <!-- Slots -->
        <div id="aa-slot-title" class="aa-slots-title" style="display:none;"></div>
        <div id="slot-container"></div>
        <input type="hidden" id="slot-selector" name="slot" required>

        <!-- Datos del cliente -->
        <input type="text" id="nombre" name="nombre" placeholder="Nombre" required>
        <input type="tel" id="telefono" name="telefono" placeholder="Teléfono" required>
        <input type="email" id="correo" name="correo" placeholder="Correo" required>

        <!-- Botón enviar -->
        <button type="submit">Agendar</button>

    </form>

    <div id="respuesta-agenda"></div>

    <?php
    return ob_get_clean();
}
add_shortcode('agenda_automatizada', 'wpaa_render_form');

