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
}
