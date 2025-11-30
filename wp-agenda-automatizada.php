<?php
/**
 * Plugin Name: WP Agenda Automatizada
 * Description: Sistema de gestión de citas con integración a Google Calendar
 * Version: 2.0.0
 * Author: Tu Nombre
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

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

// 3️⃣ Servicios
require_once plugin_dir_path(__FILE__) . 'includes/services/availability-proxy.php';
require_once plugin_dir_path(__FILE__) . 'includes/services/SyncService.php';

// 4️⃣ Controladores (lógica de negocio)
require_once plugin_dir_path(__FILE__) . 'includes/controllers/availability-controller.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/proximasCitasController.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/confirmController.php';
require_once plugin_dir_path(__FILE__) . 'includes/controllers/WebhooksController.php';

// 5️⃣ Controlador de encolado (DEBE IR DESPUÉS de availability-controller)
require_once plugin_dir_path(__FILE__) . 'includes/controllers/enqueueController.php';

// 6️⃣ Vistas
require_once plugin_dir_path(__FILE__) . 'views/asistant-controls.php';
require_once plugin_dir_path(__FILE__) . 'views/admin-controls.php';

// 7️⃣ Módulos adicionales
require_once plugin_dir_path(__FILE__) . 'asistant-user.php';
require_once plugin_dir_path(__FILE__) . 'historial-citas.php';

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

    // 🔹 Buscar o crear cliente (función modularizada)
    $cliente_id = aa_get_or_create_cliente($nombre, $telefono, $correo);

    // ✅ Inserción en la tabla
    $result = $wpdb->insert($table, [
        'servicio'   => $servicio,
        'fecha'      => $fecha,
        'nombre'     => $nombre,
        'telefono'   => $telefono,
        'correo'     => $correo,
        'id_cliente' => $cliente_id,
        'estado'     => 'pending',
        'created_at' => current_time('mysql')
    ]);

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
        nombre varchar(255) NOT NULL,
        telefono varchar(50) NOT NULL,
        correo varchar(255),
        estado varchar(50) DEFAULT 'pending',
        calendar_uid varchar(255) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY calendar_uid (calendar_uid)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    aa_create_clientes_table();
    aa_add_cliente_column_to_reservas();
    aa_add_calendar_uid_column();
    
    // 🔹 Inicializar estado de sincronización como válido
    if (get_option('aa_estado_gsync') === false) {
        add_option('aa_estado_gsync', 'valid');
    }
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

    <?php
    $business_name = esc_html( get_option('aa_business_name', 'Nuestro negocio') );
    ?>
    <div class="aa-form-instruction">
        <p>
            Agenda tu cita con <strong><?php echo $business_name; ?></strong> automáticamente llenando los siguientes campos. 
            Recibirás un correo de confirmación.
        </p>
    </div>

    <form id="agenda-form">
        <label for="servicio">Servicio:</label>
        <select id="servicio" name="servicio" required>
            <?php
            $motivos_json = get_option('aa_google_motivo', json_encode(['Cita general']));
            $motivos = json_decode($motivos_json, true);

            if (is_array($motivos) && !empty($motivos)) {
                foreach ($motivos as $motivo) {
                    $motivo = esc_html($motivo);
                    echo "<option value='{$motivo}'>{$motivo}</option>";
                }
            } else {
                echo "<option value='Cita general'>Cita general</option>";
            }
            ?>
        </select>

        <label for="fecha">Fecha deseada:</label>
        <input type="text" id="fecha" name="fecha" required>
        <div id="slot-container"></div>
        
        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" id="nombre" required>

        <label for="telefono">Teléfono:</label>
        <input type="tel" name="telefono" id="telefono" required>

        <label for="correo">Correo:</label>
        <input type="email" name="correo" id="correo" required>

        <button type="submit">Agendar</button>
    </form>
    <div id="respuesta-agenda"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('agenda_automatizada', 'wpaa_render_form');

