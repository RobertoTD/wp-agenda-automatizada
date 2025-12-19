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
        'ajax_url' => admin_url('admin-ajax.php'),
        'action'   => 'aa_get_availability',
        'email'    => get_option('aa_google_email', ''),
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
    // Aquí debes cargar las reservas confirmadas de la BD
    // Ejemplo (ajustar según tu modelo de datos):
    
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservations';
    
    $confirmed = $wpdb->get_results("
        SELECT fecha_inicio as start, fecha_fin as end, nombre as title, email as attendee 
        FROM $table 
        WHERE estado = 'confirmed' 
        AND fecha_inicio >= NOW()
        ORDER BY fecha_inicio ASC
    ");

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
        'slot_duration' => intval(get_option('aa_slot_duration', 60)),
        'timezone' => get_option('aa_timezone', 'America/Mexico_City'),
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
        'toplevel_page_agenda-automatizada-settings',
        'agenda-automatizada_page_aa_asistant_panel',
        'toplevel_page_aa_asistant_panel'
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

    // --- Panel del asistente ---
    if ($hook === 'toplevel_page_aa_asistant_panel' || $hook === 'agenda-automatizada_page_aa_asistant_panel') {

        // 🔹 Encolar CSS del panel del asistente
        wp_enqueue_style('wpaa-asistant-panel-styles', wpaa_url('css/styles.css'), [], filemtime(wpaa_path('css/styles.css')));

        wp_enqueue_style('flatpickr-css-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        wp_enqueue_script('flatpickr-js-admin', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-es-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js-admin'], null, true);

        // ✅ IMPORTANTE: Cargar datos locales ANTES de cualquier script admin
        wpaa_localize_local_availability('wpaa-availability-controller-admin');

        // Scripts admin declarados
        $admin_scripts = [
            // 🔹 Utilidades
            ['wpaa-date-utils-admin',              'assets/js/utils/dateUtils.js',              [], true],
            
            // 🔹 UI (PRIMERO, antes de controladores)
            ['wpaa-calendar-ui-admin',             'assets/js/ui/calendarUI.js',                
                                                   ['flatpickr-js-admin', 'flatpickr-es-admin'], true],
            ['wpaa-calendar-admin-ui',             'assets/js/ui/calendarAdminUI.js',           
                                                   ['flatpickr-js-admin', 'flatpickr-es-admin'], true],
            ['wpaa-slot-selector-admin-ui',        'assets/js/ui/slotSelectorAdminUI.js',       [], true],
            
            // 🔹 Servicios (AJAX)
            ['wpaa-proxy-fetch-admin',             'assets/js/services/availability/proxyFetch.js', [], true],
            ['wpaa-combine-local-external-admin',  'assets/js/services/availability/combineLocalExternal.js', [], true],
            ['wpaa-busy-ranges-admin',             'assets/js/services/availability/busyRanges.js', [], true],
            ['wpaa-slot-calculator-admin',         'assets/js/services/availability/slotCalculator.js', 
                                                   ['wpaa-date-utils-admin'], true],
            ['wpaa-availability-service-admin',    'assets/js/services/availabilityService.js',
                                                   ['wpaa-date-utils-admin', 'wpaa-proxy-fetch-admin', 'wpaa-combine-local-external-admin', 'wpaa-busy-ranges-admin', 'wpaa-slot-calculator-admin'], true],
            ['wpaa-reservation-service-admin',     'assets/js/services/reservationService.js',  [], true],
            // 🔹 confirmService y adminConfirmController se cargan en el iframe del calendario (index.php)
            // NO cargar aquí para evitar duplicación de event listeners y múltiples alertas
            // ['wpaa-confirm-service',               'assets/js/services/confirmService.js',      [], false],
            
            // 🔹 Módulos UI (renderizado puro)
            // @deprecated: aa-proximas-citas-ui eliminado - el calendario usa calendar-module.js en iframe
            
            // 🔹 Controladores (DESPUÉS de UI y Services)
            ['wpaa-availability-controller-admin', 'assets/js/controllers/availabilityController.js',
                                                   ['wpaa-date-utils-admin', 'wpaa-calendar-admin-ui', 'wpaa-slot-selector-admin-ui', 'wpaa-availability-service-admin'], true],
            ['wpaa-admin-reservation-controller',  'assets/js/controllers/adminReservationController.js',
                                                   ['wpaa-reservation-service-admin'], true],
            // 🔹 adminConfirmController se carga en el iframe del calendario (index.php)
            // NO cargar aquí para evitar duplicación de event listeners y múltiples alertas
            // ['wpaa-admin-confirm-controller',      'assets/js/controllers/adminConfirmController.js',
            //                                        ['wpaa-confirm-service'], false],
            // 🔹 proximasCitasController depende de adminConfirmController, también comentado
            // ['wpaa-proximas-citas-controller',     'assets/js/controllers/proximasCitasController.js',
            //                                        ['wpaa-admin-confirm-controller'], false],
            
            // 🔹 Punto de entrada (ÚLTIMO)
            ['wpaa-main-admin',                    'assets/js/main-admin.js',
                                                   ['wpaa-admin-reservation-controller','wpaa-date-utils-admin', 'wpaa-availability-controller-admin'], true],
            
            // 🔹 Scripts legacy (compatibilidad)
            ['aa-asistant-controls',               'js/asistant-controls.js',                  [], false],
            // @deprecated: aa-historial-citas eliminado - el calendario usa calendar-module.js en iframe
            // 🔹 aa-proximas-citas depende de wpaa-proximas-citas-controller que está comentado
            // ['aa-proximas-citas',                  'js/proximas-citas.js',                     ['wpaa-proximas-citas-controller'], false],
        ];

        foreach ($admin_scripts as [$h, $p, $d, $m]) {
            wpaa_register_js($h, $p, $d, $m);
        }

        // Localize comunes admin
        wpaa_localize('wpaa-date-utils-admin', 'wpaa_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'timezone' => get_option('aa_timezone', 'America/Mexico_City'),
            'locale'   => get_option('aa_locale', 'es-MX'),
            'nonce'    => wp_create_nonce('aa_reservation_nonce'),
        ]);

        $email = sanitize_email(get_option('aa_google_email', ''));

        wpaa_localize('wpaa-availability-controller-admin', 'aa_backend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'action'   => 'aa_get_availability',
            'email'    => $email,
        ]);

        wpaa_localize('wpaa-availability-controller-admin', 'aa_schedule',      get_option('aa_schedule', []));
        wpaa_localize('wpaa-availability-controller-admin', 'aa_future_window', intval(get_option('aa_future_window', 15)));
        wpaa_localize('wpaa-availability-controller-admin', 'aa_slot_duration', intval(get_option('aa_slot_duration', 60)));

        wpaa_localize('aa-asistant-controls', 'aa_asistant_vars', [
            'nonce_confirmar' => wp_create_nonce('aa_confirmar_cita'),
            'nonce_cancelar'  => wp_create_nonce('aa_cancelar_cita'),
            'nonce_crear_cliente' => wp_create_nonce('aa_crear_cliente'),
            'nonce_crear_cita' => wp_create_nonce('aa_reservation_nonce'),
            'nonce_crear_cliente_desde_cita' => wp_create_nonce('aa_crear_cliente_desde_cita'),
            'nonce_editar_cliente' => wp_create_nonce('aa_editar_cliente'),
        ]);

        wpaa_localize('aa-historial-citas', 'aa_historial_vars', [
            'nonce' => wp_create_nonce('aa_historial_citas'),
        ]);

        // 🔹 aa-proximas-citas eliminado - el calendario usa calendar-module.js en iframe
        // wpaa_localize('aa-proximas-citas', 'aa_proximas_vars', [
        //     'nonce' => wp_create_nonce('aa_proximas_citas'),
        // ]);
    }
}
add_action('admin_enqueue_scripts', 'wpaa_enqueue_admin_assets');
