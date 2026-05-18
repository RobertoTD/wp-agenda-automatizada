<?php
/**
 * Resuelve el cliente LLM efectivo para el chat admin.
 *
 * Reglas SaaS:
 * - Tenant gestionado (aa_backend_status=ready o aa_client_secret): solo backend Node.
 * - Sin fallback silencioso a Ollama local/cloud en tenants gestionados.
 * - Si AA_AI_PROVIDER_MODE=backend en dev y falla config: error explícito, sin local.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_LLM_Client_Factory {

    /**
     * @return bool True si la instalación está vinculada al backend SaaS.
     */
    public static function is_managed_tenant(): bool {
        $status = (string) get_option('aa_backend_status', '');
        if ($status === 'ready') {
            return true;
        }

        $secret = (string) get_option('aa_client_secret', '');
        return $secret !== '';
    }

    /**
     * Modo pedido vía constante PHP; default local solo en sitios no gestionados.
     *
     * @return string 'local'|'backend'|'cloud'
     */
    public static function get_requested_mode(): string {
        if (!defined('AA_AI_PROVIDER_MODE')) {
            return 'local';
        }

        $mode = (string) AA_AI_PROVIDER_MODE;
        if (in_array($mode, ['local', 'backend', 'cloud'], true)) {
            return $mode;
        }

        return 'local';
    }

    /**
     * Resuelve el cliente LLM y registra telemetría mínima.
     *
     * @return array{
     *     ok: bool,
     *     client?: AA_LLM_Client_Interface,
     *     effective_mode?: string,
     *     code?: string,
     *     error?: string,
     *     meta: array<string,mixed>
     * }
     */
    public static function resolve(): array {
        $managed        = self::is_managed_tenant();
        $requested      = self::get_requested_mode();
        $has_secret     = (string) get_option('aa_client_secret', '') !== '';
        $backend_status = (string) get_option('aa_backend_status', '');

        $meta = [
            'managed'        => $managed,
            'has_secret'     => $has_secret,
            'backend_status' => $backend_status,
            'requested_mode' => $requested,
            'fallback'       => false,
        ];

        if ($managed) {
            return self::resolve_managed_backend($meta);
        }

        if ($requested === 'backend') {
            return self::resolve_explicit_backend($meta);
        }

        if ($requested === 'cloud') {
            return self::resolve_explicit_cloud($meta);
        }

        return self::resolve_local_dev($meta);
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function resolve_managed_backend(array $meta): array {
        $backend = self::try_build_backend_client();
        if (!empty($backend['ok']) && isset($backend['client'])) {
            $meta['effective_mode'] = 'backend';
            self::log_resolve($meta);

            return [
                'ok'             => true,
                'client'         => $backend['client'],
                'effective_mode' => 'backend',
                'meta'           => $meta,
            ];
        }

        $meta['effective_mode'] = 'backend';
        $meta['reason']         = $backend['reason'] ?? 'backend_unavailable';
        self::log_resolve($meta);

        return [
            'ok'    => false,
            'code'  => $backend['code'] ?? 'ai_backend_not_configured',
            'error' => $backend['error'] ?? 'El asistente de IA no está configurado. Contacta a soporte.',
            'meta'  => $meta,
        ];
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function resolve_explicit_backend(array $meta): array {
        $backend = self::try_build_backend_client();
        if (!empty($backend['ok']) && isset($backend['client'])) {
            $meta['effective_mode'] = 'backend';
            self::log_resolve($meta);

            return [
                'ok'             => true,
                'client'         => $backend['client'],
                'effective_mode' => 'backend',
                'meta'           => $meta,
            ];
        }

        $meta['effective_mode'] = 'backend';
        $meta['reason']         = $backend['reason'] ?? 'backend_unavailable';
        self::log_resolve($meta);

        return [
            'ok'    => false,
            'code'  => $backend['code'] ?? 'ai_backend_not_configured',
            'error' => $backend['error'] ?? 'No se pudo conectar al gateway de IA del backend.',
            'meta'  => $meta,
        ];
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function resolve_explicit_cloud(array $meta): array {
        $cloud = self::try_build_cloud_client();
        if (!empty($cloud['ok']) && isset($cloud['client'])) {
            $meta['effective_mode'] = 'cloud';
            self::log_resolve($meta);

            return [
                'ok'             => true,
                'client'         => $cloud['client'],
                'effective_mode' => 'cloud',
                'meta'           => $meta,
            ];
        }

        $meta['effective_mode'] = 'cloud';
        $meta['reason']         = $cloud['reason'] ?? 'cloud_not_configured';
        self::log_resolve($meta);

        return [
            'ok'    => false,
            'code'  => 'ai_provider_not_configured',
            'error' => $cloud['error'] ?? 'Modo cloud solicitado pero falta AA_AI_CLOUD_API_KEY.',
            'meta'  => $meta,
        ];
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function resolve_local_dev(array $meta): array {
        $meta['effective_mode'] = 'local';
        self::log_resolve($meta);

        return [
            'ok'             => true,
            'client'         => self::build_local_client(),
            'effective_mode' => 'local',
            'meta'           => $meta,
        ];
    }

    /**
     * @return array{ok: bool, client?: AA_Backend_LLM_Client, code?: string, error?: string, reason?: string}
     */
    public static function try_build_backend_client(): array {
        if (!defined('AA_API_BASE_URL') || AA_API_BASE_URL === '') {
            return [
                'ok'     => false,
                'code'   => 'ai_backend_not_configured',
                'error'  => 'AA_API_BASE_URL no está definida.',
                'reason' => 'missing_api_base_url',
            ];
        }

        if (!function_exists('aa_send_authenticated_request')) {
            return [
                'ok'     => false,
                'code'   => 'ai_backend_not_configured',
                'error'  => 'auth-helper no disponible (aa_send_authenticated_request).',
                'reason' => 'missing_auth_helper',
            ];
        }

        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return [
                'ok'     => false,
                'code'   => 'ai_backend_not_configured',
                'error'  => 'Falta el client secret del backend. Vuelve a vincular la agenda o contacta a soporte.',
                'reason' => 'missing_client_secret',
            ];
        }

        $path     = defined('AA_AI_BACKEND_PATH') ? (string) AA_AI_BACKEND_PATH : '/ai/parse';
        $endpoint = rtrim((string) AA_API_BASE_URL, '/') . '/' . ltrim($path, '/');

        return [
            'ok'     => true,
            'client' => new AA_Backend_LLM_Client($endpoint),
        ];
    }

    /**
     * @return array{ok: bool, client?: AA_Ollama_Client, error?: string, reason?: string}
     */
    public static function try_build_cloud_client(): array {
        $api_key = defined('AA_AI_CLOUD_API_KEY') ? (string) AA_AI_CLOUD_API_KEY : '';
        if ($api_key === '') {
            return [
                'ok'     => false,
                'error'  => 'AA_AI_CLOUD_API_KEY no está definida.',
                'reason' => 'missing_cloud_api_key',
            ];
        }

        $base_url = defined('AA_AI_CLOUD_BASE_URL') ? (string) AA_AI_CLOUD_BASE_URL : 'https://ollama.com';
        $model    = defined('AA_AI_CLOUD_MODEL') ? (string) AA_AI_CLOUD_MODEL : 'ministral-3:8b';
        $timeout  = defined('AA_AI_CLOUD_TIMEOUT') ? (int) AA_AI_CLOUD_TIMEOUT : 60;

        return [
            'ok'     => true,
            'client' => new AA_Ollama_Client($base_url, $model, $timeout, $api_key),
        ];
    }

    /**
     * @return AA_Ollama_Client
     */
    public static function build_local_client(): AA_Ollama_Client {
        $base_url = defined('AA_AI_LOCAL_BASE_URL') ? (string) AA_AI_LOCAL_BASE_URL : 'http://127.0.0.1:11434';
        $model    = defined('AA_AI_LOCAL_MODEL') ? (string) AA_AI_LOCAL_MODEL : 'qwen2.5:3b';
        $timeout  = defined('AA_AI_LOCAL_TIMEOUT') ? (int) AA_AI_LOCAL_TIMEOUT : 120;

        return new AA_Ollama_Client($base_url, $model, $timeout, null);
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function log_resolve(array $meta): void {
        if (!function_exists('error_log')) {
            return;
        }

        $line = wp_json_encode(['AA_AI_LLM_RESOLVE' => $meta]);
        if (!is_string($line)) {
            return;
        }

        error_log($line);
    }
}
