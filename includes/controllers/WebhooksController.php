<?php
/**
 * Webhooks Controller
 * 
 * Maneja webhooks entrantes del backend de OAuth.
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Api
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Webhooks_Controller
 * 
 * Controlador REST para procesar webhooks entrantes.
 */
class Webhooks_Controller extends WP_REST_Controller {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->namespace = 'aa/v1';
        $this->rest_base = 'webhooks';
    }

    /**
     * Registra las rutas del controlador.
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/sync-status', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'handle_sync_status'),
                'permission_callback' => '__return_true', // Público - validaremos el payload
                'args'                => $this->get_sync_status_params(),
            ),
        ));

        // 🔹 Reminders bulk — recibe appointment_ids del worker Node y devuelve datos de citas
        register_rest_route($this->namespace, '/' . $this->rest_base . '/reminders-bulk', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'handle_reminders_bulk'),
                'permission_callback' => '__return_true',
                'args'                => $this->get_reminders_bulk_params(),
            ),
        ));

        // 🔹 Branding endpoint — devuelve logo y nombre del negocio al backend Node
        register_rest_route($this->namespace, '/branding', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'handle_branding'),
                'permission_callback' => '__return_true',
            ),
        ));
    }

    /**
     * Define los parámetros esperados para el endpoint sync-status.
     *
     * @return array Parámetros de validación.
     */
    protected function get_sync_status_params() {
        return array(
            'status' => array(
                'required'          => true,
                'type'              => 'string',
                'description'       => 'Estado de sincronización (invalid o valid)',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($param, $request, $key) {
                    return in_array($param, array('invalid', 'valid'), true);
                },
            ),
        );
    }

    /**
     * Maneja el webhook de cambio de estado de sincronización.
     *
     * @param WP_REST_Request $request Objeto de solicitud REST.
     * @return WP_REST_Response|WP_Error Respuesta de la API.
     */
    public function handle_sync_status($request) {
        // Obtener el parámetro de estado
        $status = $request->get_param('status');

        // Delegar la lógica de negocio al servicio
        $result = SyncService::update_sync_status($status);

        // Verificar si el servicio reportó un fallo
        if (!$result['success']) {
            return new WP_Error(
                'sync_update_failed',
                $result['message'],
                array('status' => 400)
            );
        }

        // Respuesta exitosa
        return new WP_REST_Response($result, 200);
    }

    /**
     * Devuelve los datos de branding del negocio al backend Node.
     *
     * GET /wp-json/aa/v1/branding
     *
     * @param WP_REST_Request $request Objeto de solicitud REST.
     * @return WP_REST_Response Datos de branding.
     */
    public function handle_branding($request) {
        // 1. Validar token (X-AA-Webhook-Token)
        $provided = $request->get_header('X-AA-Webhook-Token');
        $stored_token = get_option('aa_webhook_token', '');
        if (empty($stored_token) || empty($provided) || !hash_equals($stored_token, $provided)) {
            return new WP_Error('forbidden', 'Forbidden', array('status' => 403));
        }

        // 2. Domain
        $domain = function_exists('aa_get_clean_domain') ? aa_get_clean_domain() : '';

        // 3. Business name
        $business_name = get_option('aa_business_name', get_bloginfo('name'));

        // 4. Logo PNG — Site Icon (preferir 192px, fallback 96px)
        $logo_png_url = get_site_icon_url(192);
        if (!$logo_png_url) {
            $logo_png_url = get_site_icon_url(96);
        }

        // 5. Logo SVG — Customizer attachment (si el theme lo soporta)
        $logo_svg_url = '';
        $svg_attachment_id = get_theme_mod('deoia_svg_logo');
        if ($svg_attachment_id) {
            $svg_attachment_id = absint($svg_attachment_id);
            if ($svg_attachment_id && get_post_mime_type($svg_attachment_id) === 'image/svg+xml') {
                $logo_svg_url = wp_get_attachment_url($svg_attachment_id);
            }
        }

        // 6. Business address & phone (WhatsApp)
        $business_address = get_option('aa_business_address', '');
        $whatsapp = get_option('aa_whatsapp_number', '');

        return new WP_REST_Response(array(
            'domain'          => $domain,
            'businessName'    => $business_name,
            'logoPngUrl'      => $logo_png_url ?: '',
            'logoSvgUrl'      => $logo_svg_url ?: '',
            'businessAddress' => $business_address,
            'whatsapp'        => $whatsapp,
        ), 200);
    }

    // =========================================================================
    // 🔹 Reminders Bulk
    // =========================================================================

    /**
     * Parámetros para el endpoint reminders-bulk.
     *
     * @return array
     */
    protected function get_reminders_bulk_params() {
        return array(
            'appointment_ids' => array(
                'required'          => true,
                'type'              => 'array',
                'description'       => 'Array de IDs de reservas (max 200)',
                'sanitize_callback' => function ($value) {
                    if (!is_array($value)) {
                        $value = array();
                    }
                    // Convertir a ints positivos, únicos, máximo 200
                    $ids = array_values(array_unique(array_filter(
                        array_map('absint', $value),
                        function ($id) { return $id > 0; }
                    )));
                    return array_slice($ids, 0, 200);
                },
                'validate_callback' => function ($value) {
                    return is_array($value) && !empty($value);
                },
            ),
        );
    }

    /**
     * POST /wp-json/aa/v1/webhooks/reminders-bulk
     *
     * Recibe { appointment_ids: [461,462,...] } del worker Node.
     * Devuelve datos de citas confirmadas con email para envío de recordatorios.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_reminders_bulk($request) {
        // 🔹 Validación condicional de token
        $expected_token = get_option('aa_webhook_token', '');

        if (!empty($expected_token)) {
            $provided_token = $request->get_header('x-aa-webhook-token');

            if (empty($provided_token) || !hash_equals($expected_token, $provided_token)) {
                return new WP_Error(
                    'unauthorized',
                    'Invalid or missing webhook token.',
                    array('status' => 401)
                );
            }
        }
        // TODO: enable token validation — set aa_webhook_token in wp_options
        //       and configure the same value in the Node backend (agenda_clients.webhook_token).

        $ids = (array) $request->get_param('appointment_ids');

        if (empty($ids)) {
            return new WP_Error(
                'invalid_params',
                'appointment_ids vacío después de sanitización.',
                array('status' => 400)
            );
        }

        require_once plugin_dir_path(__FILE__) . '../services/RemindersService.php';

        $result = RemindersService::build_bulk_payload($ids);

        return new WP_REST_Response($result, 200);
    }
}
