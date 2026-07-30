<?php
/**
 * Clients AJAX — read-only endpoints for the clients / expedientes UI.
 *
 * Capability aligned with the admin UI module gate (manage_options).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/ClientsRepository.php';

final class ClientsAjax {

    public const ACTION_GET_CLIENTE = 'aa_get_cliente';
    public const NONCE_ACTION = 'aa_get_cliente';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_GET_CLIENTE, [__CLASS__, 'handle_get_cliente']);
    }

    public static function handle_get_cliente(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');

        $client_id = isset($_REQUEST['client_id']) ? absint($_REQUEST['client_id']) : 0;

        if ($client_id < 1) {
            wp_send_json_error(['message' => 'Cliente no válido.'], 400);
        }

        // Ignorar cualquier blog_id enviado por el cliente; el sitio es $wpdb->prefix.
        $cliente = ClientsRepository::find_by_id($client_id);

        if ($cliente === null) {
            wp_send_json_error(['message' => 'Cliente no encontrado.'], 404);
        }

        wp_send_json_success([
            'id' => $cliente['id'],
            'nombre' => $cliente['nombre'],
            'telefono' => $cliente['telefono'],
            'correo' => $cliente['correo'],
        ]);
    }
}
