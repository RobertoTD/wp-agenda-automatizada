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

        $service = self::build_service();
        $result  = $service->handle($message, $previous_parsed);

        if (!empty($result['ok'])) {
            $data = [
                'reply_text'    => $result['reply_text'],
                'parsed'        => $result['parsed'],
                'intent_result' => $result['intent_result'] ?? null,
            ];
            wp_send_json_success($data);
        }

        $error_data = ['message' => $result['error'] ?? 'Error desconocido.'];
        if (!empty($result['debug'])) {
            $error_data['debug'] = $result['debug'];
        }

        wp_send_json_error($error_data);
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
     * Construye la cadena service → client con require_once local.
     *
     * Configuración inyectable vía constantes PHP (overrides técnicos):
     * - AA_AI_PROVIDER_MODE     'local' | 'backend' | 'cloud'   (default: 'local')
     *
     * Modo 'local' (Ollama directo en la máquina):
     * - AA_AI_LOCAL_BASE_URL   (default: 'http://127.0.0.1:11434')
     * - AA_AI_LOCAL_MODEL      (default: 'qwen2.5:3b')
     * - AA_AI_LOCAL_TIMEOUT    (default: 120)
     *
     * Modo 'backend' (gateway Node con HMAC ya existente del plugin):
     * - AA_AI_BACKEND_PATH     (default: '/ai/parse'; se concatena con AA_API_BASE_URL)
     * - Reutiliza `aa_send_authenticated_request` + `aa_client_secret`.
     *
     * Modo 'cloud' (llamada directa a Ollama Cloud desde WP; no recomendado
     * en producción porque la API key quedaría en WordPress):
     * - AA_AI_CLOUD_API_KEY  (requerida)
     * - AA_AI_CLOUD_BASE_URL (default: 'https://ollama.com')
     * - AA_AI_CLOUD_MODEL    (default: 'ministral-3:8b')
     * - AA_AI_CLOUD_TIMEOUT  (default: 60)
     *
     * Si el modo configurado no tiene credenciales válidas, se cae al
     * cliente local para no romper el flujo en desarrollo.
     *
     * @return AA_Admin_AI_Chat_Service
     */
    private static function build_service() {
        $services_ai = dirname(__DIR__, 2) . '/services/ai';

        require_once $services_ai . '/contracts/interface-aa-llm-client.php';
        require_once $services_ai . '/providers/ollama/class-aa-ollama-client.php';
        require_once $services_ai . '/providers/backend/class-aa-backend-llm-client.php';
        require_once $services_ai . '/chat/class-aa-admin-ai-chat-service.php';

        $client = self::build_llm_client();

        return new AA_Admin_AI_Chat_Service($client);
    }

    /**
     * Elige el proveedor LLM según configuración.
     *
     * Mantiene la composición concreta aquí (no en ai-module.php)
     * mientras el módulo AI no tenga un factory dedicado.
     *
     * @return AA_LLM_Client_Interface
     */
    private static function build_llm_client() {
        $mode = defined('AA_AI_PROVIDER_MODE') ? (string) AA_AI_PROVIDER_MODE : 'local';

        if ($mode === 'backend') {
            $backend_client = self::try_build_backend_client();
            if ($backend_client !== null) {
                return $backend_client;
            }
        }

        if ($mode === 'cloud') {
            $cloud_client = self::try_build_cloud_client();
            if ($cloud_client !== null) {
                return $cloud_client;
            }
        }

        return self::build_local_client();
    }

    /**
     * @return AA_Backend_LLM_Client|null null si falta configuración mínima.
     */
    private static function try_build_backend_client() {
        if (!defined('AA_API_BASE_URL') || !function_exists('aa_send_authenticated_request')) {
            return null;
        }

        $client_secret = get_option('aa_client_secret');
        if (empty($client_secret)) {
            return null;
        }

        $path     = defined('AA_AI_BACKEND_PATH') ? (string) AA_AI_BACKEND_PATH : '/ai/parse';
        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/' . ltrim($path, '/');

        return new AA_Backend_LLM_Client($endpoint);
    }

    /**
     * @return AA_Ollama_Client|null null si falta API key.
     */
    private static function try_build_cloud_client() {
        $api_key = defined('AA_AI_CLOUD_API_KEY') ? (string) AA_AI_CLOUD_API_KEY : '';
        if ($api_key === '') {
            return null;
        }

        $base_url = defined('AA_AI_CLOUD_BASE_URL') ? (string) AA_AI_CLOUD_BASE_URL : 'https://ollama.com';
        $model    = defined('AA_AI_CLOUD_MODEL')    ? (string) AA_AI_CLOUD_MODEL    : 'ministral-3:8b';
        $timeout  = defined('AA_AI_CLOUD_TIMEOUT')  ? (int)    AA_AI_CLOUD_TIMEOUT  : 60;

        return new AA_Ollama_Client($base_url, $model, $timeout, $api_key);
    }

    /**
     * @return AA_Ollama_Client
     */
    private static function build_local_client() {
        $base_url = defined('AA_AI_LOCAL_BASE_URL') ? (string) AA_AI_LOCAL_BASE_URL : 'http://127.0.0.1:11434';
        $model    = defined('AA_AI_LOCAL_MODEL')    ? (string) AA_AI_LOCAL_MODEL    : 'qwen2.5:3b';
        $timeout  = defined('AA_AI_LOCAL_TIMEOUT')  ? (int)    AA_AI_LOCAL_TIMEOUT  : 120;

        return new AA_Ollama_Client($base_url, $model, $timeout, null);
    }
}
