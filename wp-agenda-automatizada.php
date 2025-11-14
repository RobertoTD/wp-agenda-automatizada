<?php
/**
 * Plugin Name: WP Agenda Automatizada
 * Description: Formulario para agendar citas que se conecta con n8n y redirige a WhatsApp.
 * Version: 1.0
 * Author: RobertoTD
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Detectar entorno automáticamente
$site_url = get_site_url();

if (strpos($site_url, 'localhost') !== false || strpos($site_url, '127.0.0.1') !== false) {
    define('AA_API_BASE_URL', 'http://localhost:3000');
} else {
    define('AA_API_BASE_URL', 'https://deoia-oauth-backend.onrender.com');
}

// 🔹 Incluir helper de autenticación
require_once plugin_dir_path(__FILE__) . 'includes/auth-helper.php';

// 🔹 Incluir módulo de gestión de clientes
require_once plugin_dir_path(__FILE__) . 'clientes.php';

// Crear tabla para reservas al activar el plugin
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
        'id_cliente' => $cliente_id, // 🔹 Relación con tabla de clientes
        'estado'     => 'pending',
        'created_at' => current_time('mysql')
    ]);

    // ✅ Control de error (PRIMERO validar si falló)
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
    
    // 🔹 Crear tabla de clientes y agregar columna id_cliente
    aa_create_clientes_table();
    aa_add_cliente_column_to_reservas();
    
    // 🔹 Agregar columna calendar_uid si no existe (para instalaciones existentes)
    aa_add_calendar_uid_column();
});

// ===============================
// 🔹 Función para agregar columna calendar_uid a instalaciones existentes
// ===============================
function aa_add_calendar_uid_column() {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // Verificar si la columna ya existe
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
        // Fallback a hora de WordPress
        return current_time('mysql');
    }
}

// ===============================
// 🟢 FRONTEND: Formularios y estilos
// ===============================
function wpaa_enqueue_scripts() {
    // 🔹 Obtener zona horaria y calcular locale
    $timezone = get_option('aa_timezone', 'America/Mexico_City');
    
    // 🔹 Mapeo de zonas horarias a locales
    $timezone_to_locale = [
        'America/Mexico_City' => 'es-MX',
        'America/Cancun' => 'es-MX',
        'America/Tijuana' => 'es-MX',
        'America/Monterrey' => 'es-MX',
        'America/Bogota' => 'es-CO',
        'America/Lima' => 'es-PE',
        'America/Argentina/Buenos_Aires' => 'es-AR',
        'America/Santiago' => 'es-CL',
        'America/New_York' => 'en-US',
        'America/Los_Angeles' => 'en-US',
        'Europe/Madrid' => 'es-ES',
        'Europe/London' => 'en-GB',
    ];
    
    $locale = $timezone_to_locale[$timezone] ?? 'es-MX';

    // CSS principal
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
    wp_enqueue_style('wpaa-styles', plugin_dir_url(__FILE__) . 'css/styles.css', [], filemtime(plugin_dir_path(__FILE__) . 'css/styles.css'));

    // JS principales
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', ['jquery'], null, true);
    wp_enqueue_script('flatpickr-es', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js'], null, true);

    // 🔹 PRIMERO: Encolar utilidades de fecha (sin dependencias, scope global)
    wp_enqueue_script(
        'wpaa-date-utils',
        plugin_dir_url(__FILE__) . 'assets/js/utils/dateUtils.js',
        [],
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/utils/dateUtils.js'),
        true
    );

    // 🔹 SEGUNDO: Encolar módulo UI del calendario (ES6 module con wrapper incluido)
    wp_enqueue_script(
        'wpaa-calendar-ui',
        plugin_dir_url(__FILE__) . 'assets/js/ui/calendarUI.js',
        ['flatpickr-js'],
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/ui/calendarUI.js'),
        true
    );
    
    // 🔹 TERCERO: Encolar módulo UI del selector de slots (ES6 module)
    wp_enqueue_script(
        'wpaa-slot-selector-ui',
        plugin_dir_url(__FILE__) . 'assets/js/ui/slotSelectorUI.js',
        [],
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/ui/slotSelectorUI.js'),
        true
    );
    
    // 🔹 CUARTO: Encolar servicio de reservas (ES6 module)
    wp_enqueue_script(
        'wpaa-reservation-service',
        plugin_dir_url(__FILE__) . 'assets/js/services/reservationService.js',
        [],
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/services/reservationService.js'),
        true
    );
    
    // 🔹 Marcar como módulos ES6
    add_filter('script_loader_tag', function($tag, $handle) {
        if (in_array($handle, ['wpaa-calendar-ui', 'wpaa-slot-selector-ui', 'wpaa-reservation-service'])) {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }, 10, 2);

    // 🔹 QUINTO: JS del formulario (depende de todos los módulos anteriores)
    wp_enqueue_script(
        'wpaa-script',
        plugin_dir_url(__FILE__) . 'js/form-handler.js',
        ['jquery', 'flatpickr-js', 'wpaa-date-utils', 'wpaa-calendar-ui', 'wpaa-slot-selector-ui', 'wpaa-reservation-service'],
        filemtime(plugin_dir_path(__FILE__) . 'js/form-handler.js'),
        true
    );

    $nonce = wp_create_nonce('aa_reservation_nonce');

    // 🔹 Variables globales accesibles desde form-handler.js
    wp_localize_script('wpaa-script', 'wpaa_vars', [
        'webhook_url' => 'https://deoia.app.n8n.cloud/webhook-test/disponibilidad-citas',
        'ajax_url' => admin_url('admin-ajax.php'),
        'timezone' => $timezone,
        'locale' => $locale,
        'whatsapp_number' => get_option('aa_whatsapp_number', '5215522992290'),
        'business_name' => get_option('aa_business_name', 'Nuestro negocio'),
        'business_address' => get_option('aa_business_address', ''),
        'is_virtual' => get_option('aa_is_virtual', 0),
        'nonce' => $nonce
    ]);

    // 🔹 Configuración del admin exportada al frontend
    wp_localize_script('wpaa-script', 'aa_schedule', get_option('aa_schedule', []));
    wp_localize_script('wpaa-script', 'aa_future_window', get_option('aa_future_window', 15));
}
add_action('wp_enqueue_scripts', 'wpaa_enqueue_scripts');

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
            // Obtener los motivos guardados (JSON → array)
            $motivos_json = get_option('aa_google_motivo', json_encode(['Cita general']));
            $motivos = json_decode($motivos_json, true);

            // Validar que sea un array
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

// ===============================
// 🔵 ADMIN: Configuración de agenda
// ===============================
require_once plugin_dir_path(__FILE__) . 'admin-controls.php';

require_once plugin_dir_path(__FILE__) . 'asistant-controls.php';

// 🔹 Incluir módulo de confirmación y cancelación de citas
require_once plugin_dir_path(__FILE__) . 'conf-cancel.php';

// 🔹 Incluir módulo de gestión de usuarios asistentes
require_once plugin_dir_path(__FILE__) . 'asistant-user.php';

// 🔹 Incluir módulo de historial de citas
require_once plugin_dir_path(__FILE__) . 'historial-citas.php';

// 🔹 Incluir módulo de próximas citas
require_once plugin_dir_path(__FILE__) . 'proximas-citas.php';

// Proxy hacia backend (consulta disponibilidad Google Calendar)
require_once plugin_dir_path(__FILE__) . 'availability-proxy.php';

// 🔹 Incluir el nuevo archivo de confirmación de correos
require_once plugin_dir_path(__FILE__) . 'confirmacioncorreos.php';

// Scripts solo para el área de administración
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'toplevel_page_agenda-automatizada-settings') {
        wp_enqueue_style('flatpickr-css-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
        wp_enqueue_script('flatpickr-js-admin', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-locale-es-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js-admin'], null, true);
        wp_enqueue_script(
            'aa-admin-schedule',
            plugin_dir_url(__FILE__) . 'js/admin-schedule.js',
            ['flatpickr-js-admin'],
            filemtime(plugin_dir_path(__FILE__) . 'js/admin-schedule.js'),
            true
        );
        wp_enqueue_script(
            'aa-admin-controls',
            plugin_dir_url(__FILE__) . 'js/admin-controls.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'js/admin-controls.js'),
            true
        );
    }
    
    // 🔹 Encolar script del panel del asistente
    if ($hook === 'toplevel_page_aa_asistant_panel' || $hook === 'agenda-automatizada_page_aa_asistant_panel') {
        // 🔹 Encolar Flatpickr
        wp_enqueue_style('flatpickr-css-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
        wp_enqueue_script('flatpickr-js-admin', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-locale-es-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js-admin'], null, true);
        
        // 🔹 PRIMERO: Encolar utilidades de fecha para admin
        wp_enqueue_script(
            'wpaa-date-utils-admin',
            plugin_dir_url(__FILE__) . 'assets/js/utils/dateUtils.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/utils/dateUtils.js'),
            true
        );
        
        // 🔹 SEGUNDO: Encolar servicio de reservas para admin (ES6 module)
        wp_enqueue_script(
            'wpaa-reservation-service-admin',
            plugin_dir_url(__FILE__) . 'assets/js/services/reservationService.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/services/reservationService.js'),
            true
        );
        
        // 🔹 Marcar como módulo ES6
        add_filter('script_loader_tag', function($tag, $handle) {
            if ($handle === 'wpaa-reservation-service-admin') {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);
        
        // 🔹 TERCERO: Pasar variables globales para que reservationService las use
        wp_localize_script('wpaa-date-utils-admin', 'wpaa_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'timezone' => get_option('aa_timezone', 'America/Mexico_City'),
            'locale' => get_option('aa_locale', 'es-MX'),
            'nonce' => wp_create_nonce('aa_reservation_nonce')
        ]);
        
        // 🔹 Encolar horariosapartados.js para disponibilidad
        wp_enqueue_script(
            'horariosapartados-admin',
            plugin_dir_url(__FILE__) . 'js/horariosapartados.js',
            ['flatpickr-js-admin', 'wpaa-date-utils-admin'],
            filemtime(plugin_dir_path(__FILE__) . 'js/horariosapartados.js'),
            true
        );
        
        // 🔹 Pasar variables de backend al admin
        $email = sanitize_email(get_option('aa_google_email', ''));
        wp_localize_script('horariosapartados-admin', 'aa_backend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'action'   => 'aa_get_availability',
            'email'    => $email
        ]);
        
        // 🔹 Exportar configuración de horarios
        wp_localize_script('horariosapartados-admin', 'aa_schedule', get_option('aa_schedule', []));
        wp_localize_script('horariosapartados-admin', 'aa_future_window', get_option('aa_future_window', 15));
        
        // 🔹 CUARTO: Encolar form-handler-admin.js (depende de reservationService)
        wp_enqueue_script(
            'aa-form-handler-admin',
            plugin_dir_url(__FILE__) . 'js/form-handler-admin.js',
            ['horariosapartados-admin', 'flatpickr-js-admin', 'wpaa-date-utils-admin', 'wpaa-reservation-service-admin'],
            filemtime(plugin_dir_path(__FILE__) . 'js/form-handler-admin.js'),
            true
        );
        
        wp_enqueue_script(
            'aa-asistant-controls',
            plugin_dir_url(__FILE__) . 'js/asistant-controls.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'js/asistant-controls.js'),
            true
        );
        
        // 🔹 Encolar historial de citas
        wp_enqueue_script(
            'aa-historial-citas',
            plugin_dir_url(__FILE__) . 'js/historial-citas.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'js/historial-citas.js'),
            true
        );
        
        // 🔹 Encolar próximas citas
        wp_enqueue_script(
            'aa-proximas-citas',
            plugin_dir_url(__FILE__) . 'js/proximas-citas.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'js/proximas-citas.js'),
            true
        );
        
        // 🔹 Pasar nonces al JavaScript
        wp_localize_script('aa-asistant-controls', 'aa_asistant_vars', [
            'nonce_confirmar' => wp_create_nonce('aa_confirmar_cita'),
            'nonce_cancelar' => wp_create_nonce('aa_cancelar_cita'),
            'nonce_crear_cliente' => wp_create_nonce('aa_crear_cliente'),
            'nonce_crear_cita' => wp_create_nonce('aa_reservation_nonce'),
            'nonce_crear_cliente_desde_cita' => wp_create_nonce('aa_crear_cliente_desde_cita'),
            'nonce_editar_cliente' => wp_create_nonce('aa_editar_cliente'),
        ]);
        
        // 🔹 Nonce para historial
        wp_localize_script('aa-historial-citas', 'aa_historial_vars', [
            'nonce' => wp_create_nonce('aa_historial_citas'),
        ]);
        
        // 🔹 Nonce para próximas citas
        wp_localize_script('aa-proximas-citas', 'aa_proximas_vars', [
            'nonce' => wp_create_nonce('aa_proximas_citas'),
        ]);
    }
});
