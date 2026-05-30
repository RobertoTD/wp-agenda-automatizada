<?php
/**
 * Sync Service
 * 
 * Servicio para gestionar el estado de sincronización con Google Calendar.
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Services
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class SyncService
 * 
 * Maneja la lógica de negocio relacionada con el estado de sincronización.
 */
class SyncService {

    /**
     * Actualiza el estado de sincronización de Google Calendar.
     *
     * @param string $status Estado de sincronización ('invalid' o 'valid')
     * @return array Array con 'success' (bool) y 'message' (string)
     * @throws Exception Si el estado no es válido o la actualización falla
     */
    public static function update_sync_status($status) {
        // Validación estricta: solo se acepta 'invalid' en este momento
        if ($status !== 'invalid') {
            return array(
                'success' => false,
                'message' => 'Solo se acepta el estado "invalid" en este momento'
            );
        }

        // Intentar actualizar la opción de estado de sincronización
        $updated = update_option('aa_estado_gsync', 'invalid');

        // Verificar si la actualización fue exitosa
        if (!$updated && get_option('aa_estado_gsync') !== 'invalid') {
            return array(
                'success' => false,
                'message' => 'No se pudo actualizar el estado de sincronización'
            );
        }

        // Registrar el evento en logs
        if (function_exists('error_log')) {
            error_log(sprintf(
                '[WP Agenda] Estado de sincronización actualizado a: %s en %s',
                $status,
                current_time('mysql')
            ));
        }

        // Retornar éxito
        return array(
            'success' => true,
            'message' => 'Estado de sincronización actualizado correctamente',
            'status'  => $status
        );
    }

    /**
     * Obtiene el estado actual de sincronización.
     *
     * @return string Estado actual ('valid' o 'invalid')
     */
    public static function get_sync_status() {
        return get_option('aa_estado_gsync', 'valid');
    }

    /**
     * Restablece el estado de sincronización a válido.
     *
     * @return bool True si se actualizó correctamente, false en caso contrario
     */
    public static function reset_sync_status() {
        $updated = update_option('aa_estado_gsync', 'valid');
        
        if ($updated || get_option('aa_estado_gsync') === 'valid') {
            if (function_exists('error_log')) {
                error_log(sprintf(
                    '[WP Agenda] Estado de sincronización restablecido a: valid en %s',
                    current_time('mysql')
                ));
            }
            return true;
        }
        
        return false;
    }

    /**
     * Verifica si el estado de sincronización es inválido.
     *
     * @return bool True si el estado es 'invalid', false en caso contrario
     */
    public static function is_sync_invalid() {
        return self::get_sync_status() === 'invalid';
    }

    /**
     * Whether a Google account email was stored after a prior OAuth connection.
     *
     * @return bool
     */
    public static function has_google_connection() {
        $email = get_option('aa_google_email', '');

        return is_string($email) && trim($email) !== '';
    }

    /**
     * Whether sync is invalid and the site had connected Google before (reconnect case).
     *
     * @return bool
     */
    public static function needs_reconnect() {
        return self::has_google_connection() && self::is_sync_invalid();
    }

    /**
     * Genera la URL de autorización OAuth de Google.
     * Incluye flow_id y provision_challenge para el canje seguro de secretos.
     *
     * @return string URL completa para iniciar el flujo OAuth
     */
    public static function get_auth_url() {
        $backend_url = AA_API_BASE_URL . '/oauth/authorize';
        $state = home_url();
        $redirect_uri = admin_url('admin-post.php?action=aa_connect_google');
        $contact_email = get_option('admin_email', '');

        // Provision PKCE-like: verifier stays local, only challenge travels to backend
        $flow_id = wp_generate_uuid4();
        $provision_verifier = bin2hex(random_bytes(32));
        $provision_challenge = hash('sha256', $provision_verifier);

        set_transient('aa_oauth_flow_' . $flow_id, $provision_verifier, 10 * MINUTE_IN_SECONDS);

        return $backend_url
            . '?state=' . urlencode($state)
            . '&redirect_uri=' . urlencode($redirect_uri)
            . '&contact_email=' . urlencode($contact_email)
            . '&flow_id=' . urlencode($flow_id)
            . '&provision_challenge=' . urlencode($provision_challenge);
    }

    /**
     * Maneja el éxito de la autenticación OAuth.
     * Actualiza el email y secret del cliente, y resetea el estado de sincronización a válido.
     *
     * @param string $email Email de la cuenta de Google conectada
     * @param string $secret Secret del cliente OAuth
     * @param string $webhook_token Token para autenticar webhooks entrantes del backend (opcional)
     * @return bool True si se actualizó correctamente
     */
    public static function handle_oauth_success($email, $secret, $webhook_token = '') {
        // Actualizar opciones de WordPress
        update_option('aa_google_email', sanitize_email($email));
        update_option('aa_client_secret', sanitize_text_field($secret));

        if (!empty($webhook_token)) {
            update_option('aa_webhook_token', sanitize_text_field($webhook_token));
        }
        
        // Resetear el estado de sincronización a válido
        $reset_success = self::reset_sync_status();
        
        // Registrar en logs
        if (function_exists('error_log')) {
            error_log(sprintf(
                '[WP Agenda] OAuth exitoso - Email: %s, webhook_token: %s, Estado sync: valid en %s',
                $email,
                !empty($webhook_token) ? 'set' : 'not_provided',
                current_time('mysql')
            ));
        }
        
        return $reset_success;
    }

    /**
     * Canjea el provision_code por los secretos reales vía POST server-to-server.
     *
     * @param string $flow_id    Identificador del flujo OAuth
     * @param string $provision_code  Código de un solo uso recibido en el redirect
     * @return array|WP_Error  Array asociativo con email, client_secret, webhook_token, o WP_Error
     */
    public static function redeem_secrets($flow_id, $provision_code) {
        $transient_key = 'aa_oauth_flow_' . $flow_id;
        $provision_verifier = get_transient($transient_key);

        if (empty($provision_verifier)) {
            return new WP_Error('missing_verifier', 'No se encontró el verifier para este flow_id (expirado o inválido).');
        }

        $endpoint = AA_API_BASE_URL . '/oauth/redeem-secrets';

        $response = wp_remote_post($endpoint, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array(
                'flow_id'             => $flow_id,
                'provision_code'      => $provision_code,
                'provision_verifier'  => $provision_verifier,
            )),
            'timeout' => 15,
        ));

        // Limpiar transient independientemente del resultado
        delete_transient($transient_key);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['client_secret'])) {
            $error_msg = isset($body['error']) ? $body['error'] : 'Respuesta inesperada del backend';
            return new WP_Error('redeem_failed', $error_msg);
        }

        return $body;
    }

    /**
     * Notifica al backend sobre la desconexión de Google Calendar.
     * Envía una petición autenticada con HMAC al endpoint /oauth/service.
     *
     * @return array|WP_Error Respuesta del backend o error
     */
    public static function notify_backend_disconnect_google() {
        // Asegurar que el helper esté disponible
        if (!function_exists('aa_get_clean_domain')) {
            require_once dirname(dirname(__FILE__)) . '/auth-helper.php';
        }
        
        if (!function_exists('aa_send_authenticated_request')) {
            error_log('[WP Agenda] Error: aa_send_authenticated_request no disponible');
            return new WP_Error('helper_unavailable', 'Función de autenticación no disponible');
        }

        // Construir payload
        $domain = aa_get_clean_domain();
        $payload = [
            'domain' => $domain,
            'service' => 'disconnect_google',
        ];

        // Construir endpoint
        $endpoint = AA_API_BASE_URL . '/oauth/service';

        // Enviar petición autenticada
        $response = aa_send_authenticated_request($endpoint, 'POST', $payload);

        // Manejar respuesta
        if (is_wp_error($response)) {
            error_log(sprintf(
                '[WP Agenda] Error al notificar desconexión al backend: %s',
                $response->get_error_message()
            ));
            return $response;
        }

        // Verificar código de respuesta HTTP
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 200 && $response_code < 300) {
            error_log(sprintf(
                '[WP Agenda] Backend notificado correctamente de desconexión (code: %d, domain: %s)',
                $response_code,
                $domain
            ));
        } else {
            error_log(sprintf(
                '[WP Agenda] Backend respondió con error al notificar desconexión (code: %d, body: %s)',
                $response_code,
                $response_body
            ));
        }

        return [
            'code' => $response_code,
            'body' => $response_body,
        ];
    }
}
