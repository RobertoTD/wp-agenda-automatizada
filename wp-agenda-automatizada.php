<?php
/**
 * Plugin Name: WP Agenda Automatizada
 * Description: Sistema de gestión de citas con integración a Google Calendar
 * Version: 2.0.0
 * Author: Tu Nombre
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// ===============================
// 🔹 CONSTANTES DEL PLUGIN
// ===============================
define('AA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Detectar entorno automáticamente
$site_url = get_site_url();

if (strpos($site_url, 'localhost') !== false || strpos($site_url, '127.0.0.1') !== false) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
} else {
    define('AA_API_BASE_URL', 'https://deoia-oauth-backend.onrender.com');
}

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
require_once plugin_dir_path(__FILE__) . 'includes/services/assignments/servicesService.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/assignments/staffService.php';

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
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';

    // Leer cuerpo JSON enviado desde JS
    $data = json_decode(file_get_contents('php://input'), true);

    // ✅ Validar nonce de seguridad
    if (empty($data['nonce']) || !wp_verify_nonce($data['nonce'], 'aa_reservation_nonce')) {
        wp_send_json_error(['message' => 'Error de validación de seguridad (nonce inválido).']);
    }

    // ✅ Validar honeypot (campo invisible anti-bot)
    if (!empty($data['extra_field'])) {
        wp_send_json_error(['message' => 'Detección de bot: envío no permitido.']);
    }

    // ✅ Validación básica de datos requeridos
    if (empty($data['servicio']) || empty($data['fecha']) || empty($data['nombre'])) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }

    // 🔹 Obtener la zona horaria configurada
    $timezone = get_option('aa_timezone', 'America/Mexico_City');
    
    // ✅ Sanitización y conversión de fecha a la zona horaria del negocio
    $servicio = sanitize_text_field($data['servicio']);
    
    // 🔹 Convertir ISO UTC a DateTime en zona horaria local
    try {
        $fechaObj = new DateTime($data['fecha'], new DateTimeZone('UTC'));
        $fechaObj->setTimezone(new DateTimeZone($timezone));
        $fecha = $fechaObj->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Formato de fecha inválido.']);
    }
    
    $nombre   = sanitize_text_field($data['nombre']);
    $telefono = sanitize_text_field($data['telefono']);
    $correo   = sanitize_email($data['correo']);
    
    // 🔹 Obtener duración (validar que sea 30, 60 o 90, por defecto 60)
    $duracion = isset($data['duracion']) ? intval($data['duracion']) : 60;
    if (!in_array($duracion, [30, 60, 90])) {
        $duracion = 60; // Valor por defecto si no es válido
    }

    // 🔹 Buscar o crear cliente usando ClienteService
    try {
        $cliente_id = ClienteService::getOrCreate([
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correo' => $correo
        ]);
    } catch (Exception $e) {
        error_log("❌ Error al obtener/crear cliente: " . $e->getMessage());
        wp_send_json_error([
            'message' => 'Error al procesar los datos del cliente: ' . $e->getMessage()
        ]);
    }

    // ✅ Preparar datos para inserción
    $insert_data = [
        'servicio'   => $servicio,
        'fecha'      => $fecha,
        'duracion'   => $duracion,
        'nombre'     => $nombre,
        'telefono'   => $telefono,
        'correo'     => $correo,
        'id_cliente' => $cliente_id,
        'estado'     => 'pending',
        'created_at' => current_time('mysql')
    ];

    // ✅ Agregar assignment_id si viene en los datos (opcional)
    if (isset($data['assignment_id']) && !empty($data['assignment_id'])) {
        $assignment_id = intval($data['assignment_id']);
        if ($assignment_id > 0) {
            $insert_data['assignment_id'] = $assignment_id;
        }
    }

    // ✅ Inserción en la tabla
    $result = $wpdb->insert($table, $insert_data);

    // ✅ Control de error
    if ($result === false) {
        error_log("❌ Error al insertar reserva: " . $wpdb->last_error);
        wp_send_json_error([
            'message' => 'Error al guardar en la base de datos.',
            'error'   => $wpdb->last_error
        ]);
    }

    // ✅ Retornar ID de la reserva creada
    $reserva_id = $wpdb->insert_id;
    
    if (!$reserva_id) {
        error_log("⚠️ Reserva guardada pero no se obtuvo insert_id");
        wp_send_json_error(['message' => 'Reserva guardada pero ID no disponible.']);
    }

    error_log("✅ Reserva guardada correctamente con ID: $reserva_id (Cliente: $cliente_id)");
    
    // 🔹 Crear notificación para la nueva reserva
    $notifications_table = $wpdb->prefix . 'aa_notifications';
    
    // ✅ Verificar si ya existe una notificación para evitar duplicados
    $existing_notification = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $notifications_table 
        WHERE entity_type = %s AND entity_id = %d AND type = %s",
        'reservation',
        $reserva_id,
        'pending'
    ));
    
    // ✅ Insertar notificación solo si no existe
    if (!$existing_notification) {
        $notification_result = $wpdb->insert($notifications_table, [
            'entity_type' => 'reservation',
            'entity_id'   => $reserva_id,
            'type'        => 'pending',
            'is_read'     => 0
        ]);
        
        if ($notification_result === false) {
            error_log("⚠️ Error al insertar notificación para reserva $reserva_id: " . $wpdb->last_error);
        } else {
            error_log("✅ Notificación creada para reserva $reserva_id");
        }
    } else {
        error_log("ℹ️ Notificación ya existe para reserva $reserva_id, omitiendo inserción");
    }
    
    wp_send_json_success([
        'message' => 'Reserva almacenada correctamente.',
        'id' => $reserva_id,
        'cliente_id' => $cliente_id
    ]);
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
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY calendar_uid (calendar_uid),
        KEY idx_assignment_id (assignment_id)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    aa_create_clientes_table();
    aa_add_cliente_column_to_reservas();
    aa_add_calendar_uid_column();
    
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
        color text DEFAULT NULL,
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
        price decimal(10,2) DEFAULT NULL,
        active tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY code (code),
        KEY active (active)
    ) $charset;";
    
    dbDelta($services_sql);
    
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
    
    // 🔹 Flush rewrite rules for /agenda-app endpoint
    add_rewrite_rule('^agenda-app/?$', 'index.php?aa_agenda_app=1', 'top');
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
    <option value="">Motivo de la cita</option>
    <?php
    $motivos_json = get_option('aa_google_motivo', json_encode(['Cita general']));
    $motivos = json_decode($motivos_json, true);

    if (is_array($motivos) && !empty($motivos)) {
        foreach ($motivos as $motivo) {
            $motivo = esc_html($motivo);
            echo "<option value='{$motivo}'>{$motivo}</option>";
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

