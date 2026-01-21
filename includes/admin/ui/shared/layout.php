<?php
/**
 * Shared Layout - Main HTML structure for admin UI
 * 
 * This file provides:
 * - HTML document structure
 * - Tailwind CSS inclusion
 * - Shared header/navigation
 * - Module content area
 * - Shared footer
 * - Shared scripts (utils, services, adapters)
 * - Transversal modals (reservation, etc.)
 * - Admin reservation system (migrated from enqueueController.php)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Get module name and path from parent scope (set in index.php)
$active_module = isset($active_module) ? $active_module : 'settings';
$module_path = isset($module_path) ? $module_path : '';

// ============================================
// 🔹 PREPARE DATA FOR JS (antes del HTML)
// ============================================

// Configuración general
$timezone = get_option('aa_timezone', 'America/Mexico_City');
$schedule = get_option('aa_schedule', []);
$future_window = intval(get_option('aa_future_window', 15));
$slot_duration = intval(get_option('aa_slot_duration', 60));
$email = sanitize_email(get_option('aa_google_email', ''));

// Datos de disponibilidad local (reservas confirmadas)
global $wpdb;
$table = $wpdb->prefix . 'aa_reservas';
$slot_duration_db = intval(get_option('aa_slot_duration', 60));
$timezone_string = get_option('aa_timezone', 'America/Mexico_City');

// Obtener hora actual según timezone del negocio
try {
    $tz = new DateTimeZone($timezone_string);
    $now = new DateTime('now', $tz);
    $now_str = $now->format('Y-m-d H:i:s');
} catch (Exception $e) {
    $now_str = current_time('mysql');
}

$confirmed = $wpdb->get_results($wpdb->prepare("
    SELECT 
        fecha as start,
        DATE_ADD(fecha, INTERVAL duracion MINUTE) as end,
        servicio as title,
        nombre as attendee
    FROM $table 
    WHERE estado = 'confirmed' 
    AND DATE_ADD(fecha, INTERVAL duracion MINUTE) >= %s
    ORDER BY fecha ASC
", $now_str));

$local_busy = [];
foreach ($confirmed as $row) {
    $local_busy[] = [
        'start' => $row->start,
        'end' => $row->end,
        'title' => $row->title,
        'attendee' => $row->attendee
    ];
}

// Send headers
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Automatizada - Admin</title>
    
    <!-- Tailwind CSS (usando constante global para URL limpia) -->
    <link rel="stylesheet" href="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/assets/css/admin.css'); ?>">
    
    <!-- Flatpickr CSS (requerido por calendario y modales) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <?php
    // Cargar jQuery y wp-color-picker (requerido para selector de color en zonas)
    wp_enqueue_script('jquery');
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker', false, ['jquery']);
    wp_print_scripts('jquery');
    wp_print_styles('wp-color-picker');
    wp_print_scripts('wp-color-picker');
    ?>
    
</head>
<body class="flex flex-col min-h-screen" style="background-color: rgb(240, 240, 241);">

<!-- ============================================
     🔹 GLOBAL DATA (antes de cualquier script)
     ============================================ -->
<script>
    // Variables globales requeridas por servicios y controladores
    window.ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    
    // Variables para ReservationService
    window.wpaa_vars = {
        ajax_url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
        timezone: '<?php echo esc_js($timezone); ?>',
        locale: 'es-MX',
        nonce: '<?php echo esc_js(wp_create_nonce('aa_reservation_nonce')); ?>'
    };
    
    window.aa_schedule = <?php echo wp_json_encode($schedule); ?>;
    window.aa_future_window = <?php echo intval($future_window); ?>;
    window.aa_slot_duration = <?php echo intval($slot_duration); ?>;
    
    // Disponibilidad local (reservas confirmadas de BD)
    window.aa_local_availability = {
        local_busy: <?php echo wp_json_encode($local_busy); ?>,
        slot_duration: <?php echo intval($slot_duration); ?>,
        timezone: '<?php echo esc_js($timezone); ?>',
        total_confirmed: <?php echo count($local_busy); ?>
    };
    
    // Nonces para acciones admin
    window.aa_asistant_vars = {
        nonce_confirmar: '<?php echo esc_js(wp_create_nonce('aa_confirmar_cita')); ?>',
        nonce_cancelar: '<?php echo esc_js(wp_create_nonce('aa_cancelar_cita')); ?>',
        nonce_crear_cliente: '<?php echo esc_js(wp_create_nonce('aa_crear_cliente')); ?>',
        nonce_crear_cita: '<?php echo esc_js(wp_create_nonce('aa_reservation_nonce')); ?>',
        nonce_crear_cliente_desde_cita: '<?php echo esc_js(wp_create_nonce('aa_crear_cliente_desde_cita')); ?>',
        nonce_editar_cliente: '<?php echo esc_js(wp_create_nonce('aa_editar_cliente')); ?>'
    };
    
    // Nonces para operaciones de clientes (usado por modal independiente de crear/editar cliente)
    window.AA_CLIENTS_NONCES = {
        crear_cliente: '<?php echo esc_js(wp_create_nonce('aa_crear_cliente')); ?>',
        editar_cliente: '<?php echo esc_js(wp_create_nonce('aa_editar_cliente')); ?>'
    };
</script>

<!-- ============================================
     🔹 SHARED ADMIN JS (orden crítico)
     ============================================ -->

<!-- Shared Admin JS (AAAdmin namespace) -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/assets/js/main.js'); ?>" defer></script>

<!-- Notifications -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/assets/js/notifications.js'); ?>" defer></script>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js" defer></script>

<!-- Utils -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/utils/dateUtils.js'); ?>" defer></script>

<!-- UI Components -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/ui/calendarAdminUI.js'); ?>" defer></script>
<!-- slotSelectorAdminUI.js removido: ya no se usa, el modal renderiza slots directamente con renderAssignmentSlots() -->

<!-- Availability Services (orden de dependencias) -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/availability/busyRanges.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/availability/slotCalculator.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/availabilityService.js'); ?>" defer></script>

<!-- Availability Services - Assignments (parallel, non-legacy) -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/availability/availabilityAssignments.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/availability/busyRangesAssignments.js'); ?>" defer></script>

<!-- Calendar Availability Service (neutral, no UI) -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/availability/calendarAvailabilityService.js'); ?>" defer></script>

<!-- Other Services -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/reservationService.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/adminCalendarService.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/confirmService.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/services/localAvailabilityService.js'); ?>" defer></script>

<!-- Controllers -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/controllers/availabilityController.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/controllers/adminReservationController.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/controllers/adminCalendarController.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/controllers/adminConfirmController.js'); ?>" defer></script>
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/controllers/appointmentsController.js'); ?>" defer></script>

<!-- UI Adapters -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/ui-adapters/datePickerAdapter.js'); ?>" defer></script>

<!-- Reservation Client Controller (requiere AAAdmin.ClientCreateModal) -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/controllers/reservationClientController.js'); ?>" defer></script>

<!-- Admin Reservation Assignment Flow Controller (requiere CalendarAvailabilityService, BusyRanges, SlotCalculator, AAAssignmentsAvailability) -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'assets/js/controllers/adminReservationAssignmentFlowController.js'); ?>" defer></script>

<!-- Transversal Modal: Reservation (último, usa todos los anteriores) -->
<!-- Requiere: calendarAvailabilityService.js, reservationClientController.js y adminReservationAssignmentFlowController.js deben cargarse antes -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/modals/reservation/reservation.js'); ?>" defer></script>

<!-- Transversal Modal: Appointments -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/modals/appointments/appointments-modal.js'); ?>" defer></script>

<!-- Transversal Modal: Assignment -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/modals/assignment/assignment-modal.js'); ?>" defer></script>

<!-- Transversal Modal: Client Create/Edit -->
<script src="<?php echo esc_url(AA_PLUGIN_URL . 'includes/admin/ui/modals/crearcliente/crearcliente.js'); ?>" defer></script>

    <div id="aa-admin-app" class="w-full">
        <?php require_once __DIR__ . '/header.php'; ?>
        
        <main id="aa-admin-content" class="flex-1 px-4 pt-4 pb-4">

            <?php
            // Load module content
            if (file_exists($module_path)) {
                require_once $module_path;
            } else {
                echo '<div class="p-4 bg-red-50 text-red-700 rounded">Módulo no encontrado.</div>';
            }
            ?>
        </main>
        
        <?php require_once __DIR__ . '/footer.php'; ?>
    </div>

    <!-- Shared Modals (base structure) -->
    <?php require_once __DIR__ . '/modals.php'; ?>
    
    <!-- Transversal Modal Templates (uses <template> tag, content not rendered until cloned by JS) -->
    <?php require_once dirname(__DIR__) . '/modals/reservation/index.php'; ?>
    <?php require_once dirname(__DIR__) . '/modals/appointments/index.php'; ?>
    <?php require_once dirname(__DIR__) . '/modals/assignment/index.php'; ?>
    <?php require_once dirname(__DIR__) . '/modals/crearcliente/index.php'; ?>

</body>
</html>
<?php
// Terminate execution to prevent WordPress output
die();

