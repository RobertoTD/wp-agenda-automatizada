<?php
/**
 * Admin AI Chat Controller
 *
 * Frontera HTTP/AJAX del bounded context AI.
 * Solo orquesta: valida permisos, nonce, payload y delega al servicio.
 *
 * Dependencias internas cargadas con require_once local.
 * Cuando el módulo AI se conecte al bootstrap principal, estos
 * require_once se moverán a ai-module.php.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Admin_AI_Chat_Controller {

    /**
     * Registra el endpoint AJAX.
     *
     * @return void
     */
    public static function register() {
        add_action('wp_ajax_aa_admin_ai_chat', [__CLASS__, 'handle']);
    }

    /**
     * Handler del endpoint aa_admin_ai_chat.
     *
     * @return void (termina con wp_send_json_*)
     */
    public static function handle() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer('aa_admin_ai_chat_nonce', 'nonce');

        $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';

        $service = self::build_service();
        $result  = $service->handle($message);

        if (!empty($result['ok'])) {
            wp_send_json_success([
                'reply_text' => $result['reply_text'],
                'parsed'     => $result['parsed'],
            ]);
        }

        $error_data = ['message' => $result['error'] ?? 'Error desconocido.'];
        if (!empty($result['debug'])) {
            $error_data['debug'] = $result['debug'];
        }

        wp_send_json_error($error_data);
    }

    /**
     * Construye la cadena service → client con require_once local.
     *
     * @return AA_Admin_AI_Chat_Service
     */
    private static function build_service() {
        $services_ai = dirname(__DIR__, 2) . '/services/ai';

        require_once $services_ai . '/contracts/interface-aa-llm-client.php';
        require_once $services_ai . '/providers/ollama/class-aa-ollama-client.php';
        require_once $services_ai . '/chat/class-aa-admin-ai-chat-service.php';

        $client = new AA_Ollama_Client();

        return new AA_Admin_AI_Chat_Service($client);
    }
}
