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

// 1️⃣b Infrastructure: WP schema lifecycle (activation hook)
require_once plugin_dir_path(__FILE__) . 'includes/infrastructure/wp/Schema.php';

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
// 🔹 Endpoint AJAX: Guardar cita (capa http/ajax)
// ================================
require_once __DIR__ . '/includes/http/ajax/ReservationsAjax.php';
ReservationsAjax::register();

// ===============================
// 🔹 Schema lifecycle: registra el activation hook con AA_Schema::install
// ===============================
AA_Schema::register(__FILE__);

// 🔹 Flush rewrite rules on deactivation
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

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

