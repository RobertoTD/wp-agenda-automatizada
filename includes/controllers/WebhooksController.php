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
        // 1. Domain
        $domain = function_exists('aa_get_clean_domain') ? aa_get_clean_domain() : '';

        // 2. Business name
        $business_name = get_option('aa_business_name', get_bloginfo('name'));

        // 3. Logo PNG — Site Icon (preferir 192px, fallback 96px)
        $logo_png_url = get_site_icon_url(192);
        if (!$logo_png_url) {
            $logo_png_url = get_site_icon_url(96);
        }

        // 4. Logo SVG — Customizer attachment (si el theme lo soporta)
        $logo_svg_url = '';
        $svg_attachment_id = get_theme_mod('deoia_svg_logo');
        if ($svg_attachment_id) {
            $svg_attachment_id = absint($svg_attachment_id);
            if ($svg_attachment_id && get_post_mime_type($svg_attachment_id) === 'image/svg+xml') {
                $logo_svg_url = wp_get_attachment_url($svg_attachment_id);
            }
        }

        return new WP_REST_Response(array(
            'domain'       => $domain,
            'businessName' => $business_name,
            'logoPngUrl'   => $logo_png_url ?: '',
            'logoSvgUrl'   => $logo_svg_url ?: '',
        ), 200);
    }
}
