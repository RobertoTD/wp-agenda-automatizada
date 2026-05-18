<?php
/**
 * Resuelve el cliente LLM efectivo para el chat admin.
 *
 * Reglas SaaS:
 * - WordPress no llama proveedores LLM directamente.
 * - Toda inferencia usa el gateway Node vía AA_Backend_LLM_Client.
 * - Si falta configuración backend, falla explícitamente sin fallback.
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
        $has_secret     = (string) get_option('aa_client_secret', '') !== '';
        $backend_status = (string) get_option('aa_backend_status', '');

        $meta = [
            'managed'        => $managed,
            'has_secret'     => $has_secret,
            'backend_status' => $backend_status,
            'fallback'       => false,
        ];

        return self::resolve_backend($meta);
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private static function resolve_backend(array $meta): array {
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
