<?php
/**
 * Controlador: Disponibilidad Local
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '../models/ReservationsModel.php';

/**
 * Hook para encolar datos de disponibilidad local en el FRONTEND
 * Prioridad 20 para ejecutarse DESPUÉS de wpaa_enqueue_frontend_assets (10)
 */
add_action('wp_enqueue_scripts', 'aa_enqueue_local_availability_data', 20);

function aa_enqueue_local_availability_data() {
    global $post;
    $in_content = is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'agenda_automatizada');
    $theme_hero = is_front_page() && get_theme_mod('deoia_show_calendar_in_hero', true);
    if (!$in_content && !$theme_hero) {
        error_log('[aa_local] Frontend: skip (no shortcode en post, no hero)');
        return;
    }
    if (!wp_script_is('wpaa-date-utils', 'enqueued')) {
        error_log("⚠️ [aa_local] wpaa-date-utils NO encolado");
        return;
    }
    
    // Solo reservas FIXED (assignment_id IS NULL) para el flujo legacy
    $local_busy = ReservationsModel::get_internal_busy_slots();
    
    error_log("📊 [AvailabilityController-Frontend] Slots ocupados locales (FIXED): " . count($local_busy));
    
    $availability_config = [
        'local_busy' => $local_busy,
        'slot_duration' => intval(get_option('aa_slot_duration', 60)),
        'timezone' => get_option('aa_timezone', 'America/Mexico_City'),
        'total_confirmed' => ReservationsModel::count_confirmed_fixed(),
    ];
    
    wp_localize_script(
        'wpaa-date-utils',
        'aa_local_availability',
        $availability_config
    );
    
    error_log('[aa_local] Frontend: localizado en wpaa-date-utils, local_busy=' . count($local_busy));
}

/**
 * Hook para encolar datos de disponibilidad local en el ADMIN
 * Prioridad 20 para ejecutarse DESPUÉS de wpaa_enqueue_admin_assets (10)
 */
add_action('admin_enqueue_scripts', 'aa_enqueue_admin_local_availability_data', 20);

function aa_enqueue_admin_local_availability_data($hook) {
    if ($hook !== 'toplevel_page_aa_asistant_panel' && $hook !== 'agenda-automatizada_page_aa_asistant_panel') {
        return;
    }
    
    // ✅ Verificar que el script esté encolado
    if (!wp_script_is('wpaa-availability-controller-admin', 'enqueued')) {
        error_log("⚠️ [AvailabilityController-Admin] wpaa-availability-controller-admin NO está encolado");
        return;
    }
    
    // Solo reservas FIXED (assignment_id IS NULL) para el flujo legacy
    $local_busy = ReservationsModel::get_internal_busy_slots();
    
    error_log("📊 [AvailabilityController-Admin] Slots ocupados locales (FIXED): " . count($local_busy));
    
    $availability_config = [
        'local_busy' => $local_busy,
        'slot_duration' => intval(get_option('aa_slot_duration', 60)),
        'timezone' => get_option('aa_timezone', 'America/Mexico_City'),
        'total_confirmed' => ReservationsModel::count_confirmed_fixed(),
    ];
    
    wp_localize_script(
        'wpaa-availability-controller-admin',
        'aa_local_availability',
        $availability_config
    );
    
    error_log("✅ [AvailabilityController-Admin] Datos locales enviados");
}

/**
 * Endpoint AJAX para obtener disponibilidad local (admin-only)
 * Usado para refrescar disponibilidad después de crear reservas sin recargar la página
 */
add_action('wp_ajax_aa_get_local_availability', 'aa_get_local_availability');

function aa_get_local_availability() {
    // Validar permisos admin
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes']);
        return;
    }
    
    // Obtener datos de disponibilidad local (solo FIXED: assignment_id IS NULL)
    $local_busy = ReservationsModel::get_internal_busy_slots();
    
    $availability_config = [
        'local_busy' => $local_busy,
        'slot_duration' => intval(get_option('aa_slot_duration', 60)),
        'timezone' => get_option('aa_timezone', 'America/Mexico_City'),
        'total_confirmed' => ReservationsModel::count_confirmed_fixed(),
    ];
    
    wp_send_json_success($availability_config);
}