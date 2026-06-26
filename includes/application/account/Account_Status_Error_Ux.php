<?php
/**
 * Human-facing copy and CTA mapping for account status errors.
 *
 * Stable codes are preserved for support; user-visible strings avoid technical terms.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Account_Status_Error_Ux {

    public const REASON_MISSING_CLIENT_SECRET = 'missing_client_secret';

    public const MSG_REQUIRES_LINK =
        'Esta agenda aún no está vinculada a una cuenta DEOIA. Vincula tu cuenta con Google para activar automatizaciones, estado de suscripción y servicios disponibles en tu plan.';

    public const MSG_TEMPORARY_UNAVAILABLE =
        'No pudimos consultar el estado de cuenta en este momento. Intenta más tarde.';

    public const MSG_INCOMPLETE =
        'No pudimos mostrar el estado de cuenta completo. Intenta más tarde.';

    /**
     * @param string $code
     * @param array<string,mixed> $context
     * @return bool
     */
    public static function is_requires_link_code(string $code, array $context = []): bool {
        $code = trim($code);

        if ($code === 'account_client_not_found') {
            return true;
        }

        if ($code === 'account_backend_not_configured') {
            return trim((string) ($context['reason'] ?? '')) === self::REASON_MISSING_CLIENT_SECRET;
        }

        return false;
    }

    /**
     * @param string $code
     * @param array<string,mixed> $context
     * @return string
     */
    public static function user_message_for_code(string $code, array $context = []): string {
        if (self::is_requires_link_code($code, $context)) {
            return self::MSG_REQUIRES_LINK;
        }

        if (trim($code) === 'account_backend_invalid_response') {
            return self::MSG_INCOMPLETE;
        }

        return self::MSG_TEMPORARY_UNAVAILABLE;
    }

    /**
     * @param string $code
     * @param array<string,mixed> $context
     * @return array<int, array{label:string,url:string}>
     */
    public static function actions_for_code(string $code, array $context = []): array {
        if (!self::is_requires_link_code($code, $context)) {
            return [];
        }

        require_once dirname(__DIR__) . '/ai/AI_Setup_Action_Link_Builder.php';

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
