<?php
defined('ABSPATH') or die('¡Sin acceso directo!');

/**
 * Localiza variables compartidas para scripts del plugin, garantizando que
 * AA_API_BASE_URL esté disponible.
 */
function wpaa_localize_shared_variables($handle, $object_name, array $additional_data = []) {
    $shared_data = array_merge([
        'api_base_url' => defined('AA_API_BASE_URL') ? AA_API_BASE_URL : '',
    ], $additional_data);

    wp_localize_script($handle, $object_name, $shared_data);
}

/**
 * Encola los scripts y estilos necesarios para el frontend.
 */
function wpaa_enqueue_frontend_assets() {
    $timezone = get_option('aa_timezone', 'America/Mexico_City');

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

    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
    wp_enqueue_style('wpaa-styles', plugin_dir_url(__FILE__) . '../../css/styles.css', [], filemtime(plugin_dir_path(__FILE__) . '../../css/styles.css'));

    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', ['jquery'], null, true);
    wp_enqueue_script('flatpickr-es', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js'], null, true);

    wp_enqueue_script(
        'wpaa-date-utils',
        plugin_dir_url(__FILE__) . '../../assets/js/utils/dateUtils.js',
        [],
        filemtime(plugin_dir_path(__FILE__) . '../../assets/js/utils/dateUtils.js'),
        true
    );

    $nonce = wp_create_nonce('aa_reservation_nonce');

    wpaa_localize_shared_variables('wpaa-date-utils', 'wpaa_vars', [
        'webhook_url' => 'https://deoia.app.n8n.cloud/webhook-test/disponibilidad-citas',
        'ajax_url' => admin_url('admin-ajax.php'),
        'timezone' => $timezone,
        'locale' => $locale,
        'whatsapp_number' => get_option('aa_whatsapp_number', '5215522992290'),
        'business_name' => get_option('aa_business_name', 'Nuestro negocio'),
        'business_address' => get_option('aa_business_address', ''),
        'is_virtual' => get_option('aa_is_virtual', 0),
        'nonce' => $nonce,
    ]);

    wp_localize_script('wpaa-date-utils', 'aa_schedule', get_option('aa_schedule', []));
    wp_localize_script('wpaa-date-utils', 'aa_future_window', get_option('aa_future_window', 15));

    wp_enqueue_script(
        'wpaa-calendar-ui',
        plugin_dir_url(__FILE__) . '../../assets/js/ui/calendarUI.js',
        ['flatpickr-js'],
        filemtime(plugin_dir_path(__FILE__) . '../../assets/js/ui/calendarUI.js'),
        true
    );

    wp_enqueue_script(
        'wpaa-slot-selector-ui',
        plugin_dir_url(__FILE__) . '../../assets/js/ui/slotSelectorUI.js',
        [],
        filemtime(plugin_dir_path(__FILE__) . '../../assets/js/ui/slotSelectorUI.js'),
        true
    );

    wp_enqueue_script(
        'wpaa-reservation-service',
        plugin_dir_url(__FILE__) . '../../assets/js/services/reservationService.js',
        [],
        filemtime(plugin_dir_path(__FILE__) . '../../assets/js/services/reservationService.js'),
        true
    );

    wp_enqueue_script(
        'wpaa-availability-controller',
        plugin_dir_url(__FILE__) . '../../assets/js/controllers/availabilityController.js',
        ['wpaa-date-utils', 'wpaa-calendar-ui', 'wpaa-slot-selector-ui'],
        filemtime(plugin_dir_path(__FILE__) . '../../assets/js/controllers/availabilityController.js'),
        true
    );

    wp_enqueue_script(
        'wpaa-reservation-controller',
        plugin_dir_url(__FILE__) . '../../assets/js/controllers/reservationController.js',
        ['wpaa-reservation-service'],
        filemtime(plugin_dir_path(__FILE__) . '../../assets/js/controllers/reservationController.js'),
        true
    );

    add_filter('script_loader_tag', function($tag, $handle) {
        if (in_array($handle, [
            'wpaa-calendar-ui',
            'wpaa-slot-selector-ui',
            'wpaa-reservation-service',
            'wpaa-availability-controller',
            'wpaa-reservation-controller'
        ])) {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }, 10, 2);

    wp_enqueue_script(
        'wpaa-main-frontend',
        plugin_dir_url(__FILE__) . '../../assets/js/main-frontend.js',
        [
            'jquery',
            'flatpickr-js',
            'wpaa-date-utils',
            'wpaa-calendar-ui',
            'wpaa-slot-selector-ui',
            'wpaa-reservation-service',
            'wpaa-availability-controller',
            'wpaa-reservation-controller'
        ],
        filemtime(plugin_dir_path(__FILE__) . '../../assets/js/main-frontend.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'wpaa_enqueue_frontend_assets');

/**
 * Encola los scripts y estilos necesarios para el área de administración.
 */
function wpaa_enqueue_admin_assets($hook) {
    if ($hook === 'toplevel_page_agenda-automatizada-settings') {
        wp_enqueue_style('flatpickr-css-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
        wp_enqueue_script('flatpickr-js-admin', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-locale-es-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js-admin'], null, true);
        wp_enqueue_script(
            'aa-admin-schedule',
            plugin_dir_url(__FILE__) . '../../js/admin-schedule.js',
            ['flatpickr-js-admin'],
            filemtime(plugin_dir_path(__FILE__) . '../../js/admin-schedule.js'),
            true
        );
        wp_enqueue_script(
            'aa-admin-controls',
            plugin_dir_url(__FILE__) . '../../js/admin-controls.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../../js/admin-controls.js'),
            true
        );
    }

    if ($hook === 'toplevel_page_aa_asistant_panel' || $hook === 'agenda-automatizada_page_aa_asistant_panel') {
        wp_enqueue_style('flatpickr-css-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], null);
        wp_enqueue_script('flatpickr-js-admin', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
        wp_enqueue_script('flatpickr-locale-es-admin', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js', ['flatpickr-js-admin'], null, true);

        wp_enqueue_script(
            'wpaa-date-utils-admin',
            plugin_dir_url(__FILE__) . '../../assets/js/utils/dateUtils.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../../assets/js/utils/dateUtils.js'),
            true
        );

        wp_enqueue_script(
            'wpaa-reservation-service-admin',
            plugin_dir_url(__FILE__) . '../../assets/js/services/reservationService.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../../assets/js/services/reservationService.js'),
            true
        );

        wpaa_localize_shared_variables('wpaa-date-utils-admin', 'wpaa_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'timezone' => get_option('aa_timezone', 'America/Mexico_City'),
            'locale' => get_option('aa_locale', 'es-MX'),
            'nonce' => wp_create_nonce('aa_reservation_nonce'),
        ]);

        wp_enqueue_script(
            'horariosapartados-admin',
            plugin_dir_url(__FILE__) . '../../js/horariosapartados.js',
            ['flatpickr-js-admin', 'wpaa-date-utils-admin'],
            filemtime(plugin_dir_path(__FILE__) . '../../js/horariosapartados.js'),
            true
        );

        $email = sanitize_email(get_option('aa_google_email', ''));
        wp_localize_script('horariosapartados-admin', 'aa_backend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'action'   => 'aa_get_availability',
            'email'    => $email,
        ]);

        wp_localize_script('horariosapartados-admin', 'aa_schedule', get_option('aa_schedule', []));
        wp_localize_script('horariosapartados-admin', 'aa_future_window', get_option('aa_future_window', 15));

        wp_enqueue_script(
            'wpaa-availability-controller-admin',
            plugin_dir_url(__FILE__) . '../../assets/js/controllers/availabilityController.js',
            ['wpaa-date-utils-admin'],
            filemtime(plugin_dir_path(__FILE__) . '../../assets/js/controllers/availabilityController.js'),
            true
        );

        wp_enqueue_script(
            'wpaa-admin-reservation-controller',
            plugin_dir_url(__FILE__) . '../../assets/js/controllers/adminReservationController.js',
            ['wpaa-reservation-service-admin'],
            filemtime(plugin_dir_path(__FILE__) . '../../assets/js/controllers/adminReservationController.js'),
            true
        );

        wp_enqueue_script(
            'wpaa-main-admin',
            plugin_dir_url(__FILE__) . '../../assets/js/main-admin.js',
            [
                'horariosapartados-admin',
                'flatpickr-js-admin',
                'wpaa-date-utils-admin',
                'wpaa-reservation-service-admin',
                'wpaa-availability-controller-admin',
                'wpaa-admin-reservation-controller'
            ],
            filemtime(plugin_dir_path(__FILE__) . '../../assets/js/main-admin.js'),
            true
        );

        add_filter('script_loader_tag', function($tag, $handle) {
            if (in_array($handle, [
                'wpaa-reservation-service-admin',
                'wpaa-availability-controller-admin',
                'wpaa-admin-reservation-controller',
                'wpaa-main-admin'
            ])) {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);

        wp_enqueue_script(
            'aa-asistant-controls',
            plugin_dir_url(__FILE__) . '../../js/asistant-controls.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../../js/asistant-controls.js'),
            true
        );

        wp_enqueue_script(
            'aa-historial-citas',
            plugin_dir_url(__FILE__) . '../../js/historial-citas.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../../js/historial-citas.js'),
            true
        );

        wp_enqueue_script(
            'aa-proximas-citas',
            plugin_dir_url(__FILE__) . '../../js/proximas-citas.js',
            [],
            filemtime(plugin_dir_path(__FILE__) . '../../js/proximas-citas.js'),
            true
        );

        wp_localize_script('aa-asistant-controls', 'aa_asistant_vars', [
            'nonce_confirmar' => wp_create_nonce('aa_confirmar_cita'),
            'nonce_cancelar' => wp_create_nonce('aa_cancelar_cita'),
            'nonce_crear_cliente' => wp_create_nonce('aa_crear_cliente'),
            'nonce_crear_cita' => wp_create_nonce('aa_reservation_nonce'),
            'nonce_crear_cliente_desde_cita' => wp_create_nonce('aa_crear_cliente_desde_cita'),
            'nonce_editar_cliente' => wp_create_nonce('aa_editar_cliente'),
        ]);

        wp_localize_script('aa-historial-citas', 'aa_historial_vars', [
            'nonce' => wp_create_nonce('aa_historial_citas'),
        ]);

        wp_localize_script('aa-proximas-citas', 'aa_proximas_vars', [
            'nonce' => wp_create_nonce('aa_proximas_citas'),
        ]);
    }
}
add_action('admin_enqueue_scripts', 'wpaa_enqueue_admin_assets');
