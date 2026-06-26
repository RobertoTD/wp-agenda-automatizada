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

        $previous_parsed = self::read_previous_parsed_from_post();

        $resolution = self::resolve_llm_client();
        if (empty($resolution['ok'])) {
            $error_data = self::build_chat_ajax_error_data(
                (string) ($resolution['code'] ?? 'ai_backend_not_configured'),
                isset($resolution['error']) ? (string) $resolution['error'] : ''
            );
            wp_send_json_error($error_data);
        }

        $service = self::build_service($resolution['client']);
        $result  = $service->handle($message, $previous_parsed);

        if (!empty($result['ok'])) {
            $data = [
                'reply_text'    => $result['reply_text'],
                'parsed'        => $result['parsed'],
                'intent_result' => $result['intent_result'] ?? null,
            ];
            wp_send_json_success($data);
        }

        $error_data = self::build_chat_ajax_error_data(
            !empty($result['code']) ? (string) $result['code'] : 'ai_unavailable',
            isset($result['error']) ? (string) $result['error'] : 'Error desconocido.'
        );

        if (!empty($result['debug'])) {
            $error_data['debug'] = $result['debug'];
        }

        wp_send_json_error($error_data);
    }

    /**
     * @param string $code
     * @param string $provider_error Raw error for debug only; not exposed as user message.
     * @return array<string, mixed>
     */
    private static function build_chat_ajax_error_data(string $code, string $provider_error = ''): array {
        self::load_chat_error_ux();

        $error_data = [
            'message' => AA_AI_Chat_Error_Ux::user_message_for_code($code, $provider_error),
            'code'    => $code,
        ];

        $actions = AA_AI_Chat_Error_Ux::actions_for_code($code);
        if ($actions !== []) {
            $error_data['actions'] = $actions;
        }

        return $error_data;
    }

    private static function load_chat_error_ux(): void {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        require_once dirname(__DIR__, 2) . '/application/ai/AI_Chat_Error_Ux.php';
        $loaded = true;
    }

    /**
     * Lee y decodifica el snapshot `previous_parsed` del POST.
     *
     * Defensa en profundidad: si la clave falta, no es string, o el
     * JSON es inválido / no es un array asociativo, devuelve `null`
     * para que el service trate la petición como turno aislado. NUNCA
     * responde 4xx por este motivo: un sessionStorage corrupto en el
     * cliente no debe romper la conversación.
     *
     * @return array<string,mixed>|null
     */
    private static function read_previous_parsed_from_post(): ?array {
        if (!isset($_POST['previous_parsed']) || !is_string($_POST['previous_parsed'])) {
            return null;
        }

        $raw = wp_unslash($_POST['previous_parsed']);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array{
     *     ok: bool,
     *     client?: AA_LLM_Client_Interface,
     *     code?: string,
     *     error?: string,
     *     meta?: array<string,mixed>
     * }
     */
    private static function resolve_llm_client(): array {
        self::load_llm_dependencies();

        return AA_AI_LLM_Client_Factory::resolve();
    }

    /**
     * @param AA_LLM_Client_Interface $client
     * @return AA_Admin_AI_Chat_Service
     */
    private static function build_service(AA_LLM_Client_Interface $client) {
        self::load_llm_dependencies();

        return new AA_Admin_AI_Chat_Service($client);
    }

    /**
     * Carga contratos, gateway backend y factory.
     */
    private static function load_llm_dependencies(): void {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        $services_ai       = dirname(__DIR__, 2) . '/services/ai';
        $infrastructure_ai = dirname(__DIR__, 2) . '/infrastructure/ai';

        require_once $services_ai . '/contracts/interface-aa-llm-client.php';
        require_once $services_ai . '/providers/backend/class-aa-backend-llm-client.php';
        require_once $infrastructure_ai . '/class-aa-ai-llm-client-factory.php';
        require_once $services_ai . '/chat/class-aa-admin-ai-chat-service.php';

        $loaded = true;
    }
}
