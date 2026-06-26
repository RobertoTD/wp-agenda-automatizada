<?php
/**
 * Human-facing copy and CTA mapping for admin AI chat errors.
 *
 * Stable codes are preserved for debug; user-visible strings avoid technical terms.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Chat_Error_Ux {

    public const MSG_REQUIRES_ACCOUNT =
        'El Asistente IA requiere una cuenta DEOIA activa. Vincula tu cuenta para habilitar el acceso disponible en tu plan.';

    public const MSG_PLAN_DISABLED =
        'Tu plan actual no incluye consultas del Asistente IA. Revisa tu cuenta DEOIA para ver qué está disponible.';

    public const MSG_QUOTA_EXCEEDED =
        'Has alcanzado el límite de consultas de IA para este período.';

    public const MSG_CONNECTION_UNAVAILABLE =
        'No pude conectarme con el asistente en este momento. Intenta de nuevo más tarde.';

    public const MSG_PROVIDER_UNAVAILABLE =
        'El servicio de IA no está disponible en este momento. Intenta más tarde.';

    public const MSG_TEMPORARY_UNAVAILABLE =
        'El servicio no está disponible temporalmente. Intenta más tarde.';

    public const MSG_GENERIC_UNAVAILABLE =
        'No es posible procesar la consulta en este momento.';

    private const REQUIRES_ACCOUNT_CODES = [
        'ai_backend_not_configured' => true,
        'no_installation_id'        => true,
    ];

    /**
     * @param string $code
     * @return bool
     */
    public static function is_requires_account_code(string $code): bool {
        return isset(self::REQUIRES_ACCOUNT_CODES[trim($code)]);
    }

    /**
     * @param string $code
     * @param string $provider_error Ignored for mapped codes (kept for signature / future use).
     * @return string
     */
    public static function user_message_for_code(string $code, string $provider_error = ''): string {
        unset($provider_error);

        switch (trim($code)) {
            case 'ai_backend_not_configured':
            case 'no_installation_id':
                return self::MSG_REQUIRES_ACCOUNT;
            case 'backend_disabled':
                return self::MSG_PLAN_DISABLED;
            case 'quota_exceeded':
                return self::MSG_QUOTA_EXCEEDED;
            case 'ai_not_configured':
                return self::MSG_PROVIDER_UNAVAILABLE;
            case 'quota_service_unavailable':
                return self::MSG_TEMPORARY_UNAVAILABLE;
            case 'quota_denied':
                return self::MSG_GENERIC_UNAVAILABLE;
            case 'ai_unavailable':
                return self::MSG_CONNECTION_UNAVAILABLE;
            default:
                return self::MSG_CONNECTION_UNAVAILABLE;
        }
    }

    /**
     * @param string $code
     * @return array<int, array{label:string,url:string}>
     */
    public static function actions_for_code(string $code): array {
        if (!self::is_requires_account_code($code)) {
            return [];
        }

        require_once __DIR__ . '/AI_Setup_Action_Link_Builder.php';

        $action = (new AA_AI_Setup_Action_Link_Builder())->build_action_for_key('google_calendar_connect');
        if ($action === null) {
            return [];
        }

        return [
            [
                'label' => $action['label'],
                'url'   => $action['url'],
            ],
        ];
    }
}
