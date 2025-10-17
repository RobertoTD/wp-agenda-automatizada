<?php
/**
 * Plugin Name: WP Agenda Automatizada
 * Description: Formulario para agendar citas que se conecta con n8n y redirige a WhatsApp.
 * Version: 1.0
 * Author: RobertoTD
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

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

    // ✅ Validación básica de datos requeridos
    if (empty($data['servicio']) || empty($data['fecha']) || empty($data['nombre'])) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }

    // ✅ Sanitización y formato correcto
    $servicio = sanitize_text_field($data['servicio']);
    $fecha    = date('Y-m-d H:i:s', strtotime($data['fecha'])); // Convierte correctamente
    $nombre   = sanitize_text_field($data['nombre']);
    $telefono = sanitize_text_field($data['telefono']);
    $correo   = sanitize_email($data['correo']);

    // ✅ Inserción en la tabla
    $result = $wpdb->insert($table, [
        'servicio'   => $servicio,
        'fecha'      => $fecha,
        'nombre'     => $nombre,
        'telefono'   => $telefono,
        'correo'     => $correo,
        'estado'     => 'pending',
        'created_at' => current_time('mysql')
    ]);

    // ✅ Control de error
    if ($result === false) {
        wp_send_json_error([
            'message' => 'Error al guardar en la base de datos.',
            'error'   => $wpdb->last_error
        ]);
    }

    wp_send_json_success(['message' => 'Reserva almacenada correctamente.']);
}

error_log('✅ aa_save_reservation ejecutada');
error_log(print_r(json_decode(file_get_contents('php://input'), true), true));



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
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});


// ===============================
// 🟢 FRONTEND: Formularios y estilos
// ===============================
function wpaa_enqueue_scripts() {
    // CSS principal
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
    wp_enqueue_style('wpaa-styles', plugin_dir_url(__FILE__) . 'css/styles.css', [], filemtime(plugin_dir_path(__FILE__) . 'css/styles.css'));

    // JS principales
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', ['jquery'], null, true);
    wp_enqueue_script('flatpickr-es', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js'], null, true);

    // JS del formulario (con versión dinámica para evitar caché)
    wp_enqueue_script(
        'wpaa-script',
        plugin_dir_url(__FILE__) . 'js/form-handler.js',
        ['jquery', 'flatpickr-js'],
        filemtime(plugin_dir_path(__FILE__) . 'js/form-handler.js'),
        true
    );

    // 🔹 Variables globales accesibles desde form-handler.js
    wp_localize_script('wpaa-script', 'wpaa_vars', [
        'webhook_url' => 'https://deoia.app.n8n.cloud/webhook-test/disponibilidad-citas',
        'ajax_url' => admin_url('admin-ajax.php')
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

        <label for="correo">Correo (opcional):</label>
        <input type="email" name="correo" id="correo">

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

// Proxy hacia backend (consulta disponibilidad n8n)
require_once plugin_dir_path(__FILE__) . 'availability-proxy.php';

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
            [], // sin dependencias por ahora
            filemtime(plugin_dir_path(__FILE__) . 'js/admin-controls.js'),
            true
        );
    }
});
