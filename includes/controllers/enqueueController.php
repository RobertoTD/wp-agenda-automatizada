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
function wpaa_enqueue_frontend_assets() {
    $timezone = get_option('aa_timezone', 'America/Mexico_City');
    $locale   = wpaa_locale_from_timezone($timezone);

    // --- CSS ---
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    wp_enqueue_style('wpaa-styles', wpaa_url('css/styles.css'), [], filemtime(wpaa_path('css/styles.css')));

    // --- JS externos ---
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', ['jquery'], null, true);
    wp_enqueue_script('flatpickr-es', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js'], null, true);

    // --- Scripts del plugin (declaración compacta) ---
    $scripts = [
        ['wpaa-date-utils',           'assets/js/utils/dateUtils.js',              [], false],
        ['wpaa-calendar-ui',          'assets/js/ui/calendarUI.js',               ['flatpickr-js'], true],
        ['wpaa-slot-selector-ui',     'assets/js/ui/slotSelectorUI.js',           [], true],
        ['wpaa-reservation-service',  'assets/js/services/reservationService.js', [], true],
        ['wpaa-availability-controller','assets/js/controllers/availabilityController.js',
                                               ['wpaa-date-utils','wpaa-calendar-ui','wpaa-slot-selector-ui'], true],
        ['wpaa-reservation-controller','assets/js/controllers/reservationController.js',
                                               ['wpaa-reservation-service'], true],
        ['wpaa-main-frontend',        'assets/js/main-frontend.js',
                                               ['wpaa-reservation-controller'], true],
    ];

    foreach ($scripts as [$handle, $path, $deps, $module]) {
        wpaa_register_js($handle, $path, $deps, $module);
    }

    // Datos globales
    $nonce = wp_create_nonce('aa_reservation_nonce');
    wpaa_localize('wpaa-date-utils', 'wpaa_vars', [
        'api_base_url'     => defined('AA_API_BASE_URL') ? AA_API_BASE_URL : '',
        'webhook_url'      => 'https://deoia.app.n8n.cloud/webhook-test/disponibilidad-citas',
        'ajax_url'         => admin_url('admin-ajax.php'),
        'timezone'         => $timezone,
        'locale'           => $locale,
        'whatsapp_number'  => get_option('aa_whatsapp_number', '5215522992290'),
        'business_name'    => get_option('aa_business_name', 'Nuestro negocio'),
        'business_address' => get_option('aa_business_address', ''),
        'is_virtual'       => get_option('aa_is_virtual', 0),
        'nonce'            => $nonce,
    ]);

    wpaa_localize('wpaa-date-utils', 'aa_schedule',       get_option('aa_schedule', []));
    wpaa_localize('wpaa-date-utils', 'aa_future_window',  get_option('aa_future_window', 15));
}
add_action('wp_enqueue_scripts', 'wpaa_enqueue_frontend_assets');

/* ============================================================
   ADMIN
============================================================ */
function wpaa_enqueue_admin_assets($hook) {

    // --- Pantalla principal de ajustes ---
    if ($hook === 'toplevel_page_agenda-automatizada-settings') {
        wp_enqueue_style('flatpickr-css-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        wp_enqueue_script('flatpickr-js-admin', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-es-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js-admin'], null, true);

        wpaa_register_js('aa-admin-schedule', 'js/admin-schedule.js', ['flatpickr-js-admin']);
        wpaa_register_js('aa-admin-controls', 'js/admin-controls.js');
    }

    // --- Panel del asistente ---
    if ($hook === 'toplevel_page_aa_asistant_panel' || $hook === 'agenda-automatizada_page_aa_asistant_panel') {

        wp_enqueue_style('flatpickr-css-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
        wp_enqueue_script('flatpickr-js-admin', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-es-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js-admin'], null, true);

        // Scripts admin declarados
        $admin_scripts = [
            ['wpaa-date-utils-admin',              'assets/js/utils/dateUtils.js',              [], false],
            ['wpaa-reservation-service-admin',     'assets/js/services/reservationService.js',  [], true],
            ['horariosapartados-admin',            'js/horariosapartados.js',                   ['flatpickr-js-admin','wpaa-date-utils-admin'], true],
            ['wpaa-availability-controller-admin', 'assets/js/controllers/availabilityController.js',
                                                   ['wpaa-date-utils-admin'], true],
            ['wpaa-admin-reservation-controller',  'assets/js/controllers/adminReservationController.js',
                                                   ['wpaa-reservation-service-admin'], true],
            ['wpaa-main-admin',                    'assets/js/main-admin.js',
                                                   ['wpaa-admin-reservation-controller','wpaa-date-utils-admin'], true],

            // 🔹 NUEVO: Módulo UI de Próximas Citas (puro UI)
            ['aa-proximas-citas-ui',               'assets/js/ui/proximasCitasUI.js',          [], false],

            // Controlador existente de Próximas Citas
            ['aa-asistant-controls',               'js/asistant-controls.js',                  [], false],
            ['aa-historial-citas',                 'js/historial-citas.js',                    [], false],
            ['aa-proximas-citas',                  'js/proximas-citas.js',                     ['aa-proximas-citas-ui'], false],
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

        wpaa_localize('horariosapartados-admin', 'aa_backend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'action'   => 'aa_get_availability',
            'email'    => $email,
        ]);

        wpaa_localize('horariosapartados-admin', 'aa_schedule',      get_option('aa_schedule', []));
        wpaa_localize('horariosapartados-admin', 'aa_future_window', get_option('aa_future_window', 15));

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

        wpaa_localize('aa-proximas-citas', 'aa_proximas_vars', [
            'nonce' => wp_create_nonce('aa_proximas_citas'),
        ]);
    }
}
add_action('admin_enqueue_scripts', 'wpaa_enqueue_admin_assets');
