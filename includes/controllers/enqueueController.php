<?php
defined('ABSPATH') or die('¡Sin acceso directo!');

/**
 * Helper para resolver rutas absolutas en plugin_dir_* con paths relativos.
 */
function wpaa_path($relative) {
    return plugin_dir_path(__FILE__) . '../../' . ltrim($relative, '/');
}

function wpaa_url($relative) {
    return plugin_dir_url(__FILE__) . '../../' . ltrim($relative, '/');
}

/**
 * Registrar un script JS con soporte para módulos ES6.
 */
function wpaa_register_js($handle, $relative_path, $deps = [], $is_module = false) {
    $url  = wpaa_url($relative_path);
    $path = wpaa_path($relative_path);

    wp_enqueue_script($handle, $url, $deps, filemtime($path), true);

    if ($is_module) {
        add_filter('script_loader_tag', function($tag, $h) use ($handle) {
            if ($h === $handle) {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);
    }
}

/**
 * Localización segura y compacta de variables.
 */
function wpaa_localize($handle, $object_name, $data) {
    wp_localize_script($handle, $object_name, $data);
}

/**
 * Obtener locale según timezone.
 */
function wpaa_locale_from_timezone($tz) {
    $map = [
        'America/Mexico_City' => 'es-MX', 'America/Cancun' => 'es-MX',
        'America/Tijuana' => 'es-MX', 'America/Monterrey' => 'es-MX',
        'America/Bogota' => 'es-CO', 'America/Lima' => 'es-PE',
        'America/Argentina/Buenos_Aires' => 'es-AR', 'America/Santiago' => 'es-CL',
        'America/New_York' => 'en-US', 'America/Los_Angeles' => 'en-US',
        'Europe/Madrid' => 'es-ES', 'Europe/London' => 'en-GB'
    ];
    return $map[$tz] ?? 'es-MX';
}

/* ============================================================
   FRONTEND
============================================================ */
// ===============================
// 🔹 FRONTEND: Shortcode [agenda_automatizada]
// ===============================
add_action('wp_enqueue_scripts', 'wpaa_enqueue_frontend_assets');

function wpaa_enqueue_frontend_assets() {
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'agenda_automatizada')) {
        return;
    }

    // CSS
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    wp_enqueue_style('wpaa-calendar-default', wpaa_url('css/calendar-default.css'), [], filemtime(wpaa_path('css/calendar-default.css')));

    // JS externos
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
    wp_enqueue_script('flatpickr-es', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js'], null, true);

    // ✅ IMPORTANTE: Cargar datos locales ANTES de cualquier script
    wpaa_localize_local_availability();

    $frontend_scripts = [
        ['wpaa-date-utils',              'assets/js/utils/dateUtils.js',              [], false],
        ['aa-wpagenda-kernel',           'assets/js/ui-adapters/WPAgenda.js',         ['wpaa-date-utils'], false],
        ['aa-calendar-default-adapter',  'assets/js/ui-adapters/calendarDefaultAdapter.js', ['wpaa-date-utils', 'aa-wpagenda-kernel'], false],
        ['aa-slots-default-adapter',     'assets/js/ui-adapters/slotsDefaultAdapter.js', ['wpaa-date-utils', 'aa-wpagenda-kernel'], false],
        ['aa-modal-default-adapter',     'assets/js/ui-adapters/modalDefaultAdapter.js', ['wpaa-date-utils', 'aa-wpagenda-kernel'], false],
        ['wpaa-calendar-ui',             'assets/js/ui/calendarUI.js', ['flatpickr-js', 'flatpickr-es', 'wpaa-date-utils'], false],
        ['wpaa-slot-selector-ui',        'assets/js/ui/slotSelectorUI.js', ['wpaa-date-utils'], false],
        ['wpaa-proxy-fetch',             'assets/js/services/availability/proxyFetch.js', [], false],
        ['wpaa-combine-local-external',  'assets/js/services/availability/combineLocalExternal.js', [], false],
        ['wpaa-busy-ranges',             'assets/js/services/availability/busyRanges.js', [], false],
        ['wpaa-slot-calculator',         'assets/js/services/availability/slotCalculator.js', ['wpaa-date-utils'], false],
        ['wpaa-availability-service',    'assets/js/services/availabilityService.js', ['wpaa-date-utils', 'wpaa-proxy-fetch', 'wpaa-combine-local-external', 'wpaa-busy-ranges', 'wpaa-slot-calculator'], false],
        ['wpaa-reservation-service',     'assets/js/services/reservationService.js', [], false],
        ['wpaa-availability-controller', 'assets/js/controllers/availabilityController.js', ['wpaa-date-utils', 'wpaa-calendar-ui', 'wpaa-slot-selector-ui', 'wpaa-availability-service', 'aa-wpagenda-kernel', 'aa-calendar-default-adapter', 'aa-slots-default-adapter', 'aa-modal-default-adapter'], false],
        ['wpaa-reservation-controller',  'assets/js/controllers/reservationController.js', ['wpaa-reservation-service', 'aa-wpagenda-kernel', 'aa-calendar-default-adapter', 'aa-slots-default-adapter', 'aa-modal-default-adapter'], false],
        ['wpaa-main-frontend',           'assets/js/main-frontend.js', ['wpaa-availability-controller', 'wpaa-reservation-controller'], false],
    ];

    foreach ($frontend_scripts as [$h, $p, $d, $m]) {
        wpaa_register_js($h, $p, $d, $m);
    }

    wpaa_localize('wpaa-availability-controller', 'aa_backend', [
        'ajax_url'     => admin_url('admin-ajax.php'),
        'action'       => 'aa_get_availability',
        'email'        => get_option('aa_google_email', ''),
        'gsync_status' => get_option('aa_estado_gsync', 'active'),
    ]);

    wpaa_localize('wpaa-availability-controller', 'aa_schedule',      get_option('aa_schedule', []));
    wpaa_localize('wpaa-availability-controller', 'aa_future_window', intval(get_option('aa_future_window', 15)));
    wpaa_localize('wpaa-availability-controller', 'aa_slot_duration', intval(get_option('aa_slot_duration', 60)));

    wpaa_localize('wpaa-reservation-service', 'aa_reservation_config', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('aa_reservation_nonce'),
    ]);

    $timezone = get_option('aa_timezone', 'America/Mexico_City');
    
    wpaa_localize('wpaa-reservation-controller', 'wpaa_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aa_reservation_nonce'),
        'whatsapp_number' => get_option('aa_whatsapp_number', '5215522992290'),
        'timezone' => $timezone,
        'locale' => wpaa_locale_from_timezone($timezone)
    ]);
}

/**
 * Inyectar disponibilidad local ANTES de scripts JS
 */
function wpaa_localize_local_availability($script_handle = 'wpaa-availability-controller') {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    $slot_duration = intval(get_option('aa_slot_duration', 60));
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

    wp_localize_script($script_handle, 'aa_local_availability', [
        'local_busy' => $local_busy,
        'slot_duration' => $slot_duration,
        'timezone' => $timezone_string,
        'total_confirmed' => count($local_busy)
    ]);
}

/* ============================================================
   ADMIN
============================================================ */
function wpaa_enqueue_admin_assets($hook) {

    // 🔹 Estilos comunes para páginas del plugin (solo contenedor del iframe, NO Tailwind)
    // IMPORTANTE: Tailwind CSS (admin.css) se carga SOLO dentro del iframe via layout.php
    // NO debe cargarse aquí para evitar afectar el admin legacy de WordPress
    $plugin_pages = [
        'toplevel_page_agenda-automatizada-settings'
    ];
    
    if (in_array($hook, $plugin_pages)) {
        // Solo cargar estilos comunes para el contenedor del iframe (NO Tailwind)
        wp_enqueue_style(
            'wpaa-admin-common-styles',
            wpaa_url('css/styles.css'),
            [],
            filemtime(wpaa_path('css/styles.css'))
        );
    }

    // --- Pantalla principal de ajustes ---
    // Note: Settings page now uses iframe-based UI, so JS is loaded inside the iframe
    // No global enqueue needed here - JS is loaded by the iframe UI module
}
add_action('admin_enqueue_scripts', 'wpaa_enqueue_admin_assets');
