<?php
/**
 * Plugin Name: WP Agenda Automatizada
 * Description: Sistema de gestión de citas con integración a Google Calendar, pega este Shortcode donde quieras mostrar tu calendario: [agenda_automatizada]
 * Version: 2.1.9
 * Author: Roberto Tejada
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

    // 🔹 Determinar si el servicio es virtual (antes de recortar fixed::)
    $join_token = null;
    $is_virtual = false;
    if (strpos($servicio, 'fixed::') !== 0 && is_numeric($servicio)) {
        $service_row = class_exists('AssignmentsModel')
            ? AssignmentsModel::get_service_by_id(intval($servicio))
            : false;
        if ($service_row && isset($service_row['attendance_type']) && $service_row['attendance_type'] === 'virtual') {
            $is_virtual = true;
        }
    }

    // 🔹 Generar join_token para servicios virtuales
    if ($is_virtual) {
        $join_token = bin2hex(random_bytes(16));
    }

    // 🔹 Extraer prefijo "fixed::" si existe (guardar solo el nombre del servicio)
    if (strpos($servicio, 'fixed::') === 0) {
        $servicio = substr($servicio, 7); // strlen('fixed::') = 7
    }
    
    // 🔹 Convertir ISO UTC a DateTime en zona horaria local
    try {
        $fechaObj = new DateTime($data['fecha'], new DateTimeZone('UTC'));
        $fechaObj->setTimezone(new DateTimeZone($timezone));
        $fecha = $fechaObj->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Formato de fecha inválido.']);
    }
    
    $nombre       = sanitize_text_field($data['nombre']);
    $telefono_raw = sanitize_text_field($data['telefono']);
    $correo       = isset($data['correo']) ? sanitize_email($data['correo']) : '';

    // 🔹 Validar teléfono obligatorio
    if (empty($telefono_raw)) {
        wp_send_json_error(['message' => 'El teléfono es obligatorio.']);
    }

    // 🔹 Normalizar teléfono a formato canónico
    $telefono = aa_normalize_telefono($telefono_raw);
    if (is_wp_error($telefono)) {
        wp_send_json_error(['message' => $telefono->get_error_message()]);
    }
    
    // 🔹 Obtener duración (validar que sea 30, 60 o 90, por defecto 60)
    $duracion = isset($data['duracion']) ? intval($data['duracion']) : 60;
    if (!in_array($duracion, [30, 60, 90])) {
        $duracion = 60; // Valor por defecto si no es válido
    }

    // 🔹 Buscar o crear cliente usando ClienteService (con reglas de correo mismatch)
    $cliente_id = ClienteService::getOrCreate([
        'nombre' => $nombre,
        'telefono' => $telefono,
        'correo' => $correo
    ]);

    if (is_wp_error($cliente_id)) {
        error_log("❌ Error al obtener/crear cliente: " . $cliente_id->get_error_message());
        wp_send_json_error([
            'message' => $cliente_id->get_error_message()
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

    // 🔹 Incluir join_token en insert_data (null si no es virtual)
    $insert_data['join_token'] = $join_token;

    // 🔹 virtual_link: solo si viene y no vacío (permite null si no viene)
    if (isset($data['virtual_link']) && trim((string) $data['virtual_link']) !== '') {
        $insert_data['virtual_link'] = esc_url_raw(trim($data['virtual_link']));
    }

    // ✅ Inserción en la tabla (reintentos por colisión de join_token en servicios virtuales)
    $max_attempts = $is_virtual ? 3 : 1;
    $result = false;
    for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
        if ($is_virtual && $attempt > 1) {
            $join_token = bin2hex(random_bytes(16));
            $insert_data['join_token'] = $join_token;
            error_log("🔄 [aa_save_reservation] Reintento $attempt por colisión de join_token");
        }
        $result = $wpdb->insert($table, $insert_data);
        if ($result !== false) {
            break;
        }
        if (!$is_virtual || strpos($wpdb->last_error, 'Duplicate entry') === false) {
            break;
        }
    }

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
        'message'    => 'Reserva almacenada correctamente.',
        'id'         => $reserva_id,
        'cliente_id' => $cliente_id,
        'join_token' => $join_token,
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
        virtual_link text DEFAULT NULL,
        join_token varchar(64) DEFAULT NULL,
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

