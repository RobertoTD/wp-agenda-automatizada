<?php
/**
 * Push Subscription AJAX — register Web Push subscriptions via backend HMAC bridge (MC4).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-push-backend-client.php';

class PushSubscriptionAjax {

    public const NONCE_ACTION = 'aa_push_subscription_nonce';
    public const ACTION_REGISTER = 'aa_register_push_subscription';
    public const ACTION_CONFIG = 'aa_get_push_config';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_REGISTER, [__CLASS__, 'handle_register']);
        add_action('wp_ajax_' . self::ACTION_CONFIG, [__CLASS__, 'handle_get_config']);
    }

    public static function handle_register(): void {
        self::authorize();

        $subscription = self::parseSubscriptionFromPost();
        if ($subscription === null) {
            wp_send_json_error([
                'ok'    => false,
                'error' => 'invalid_subscription',
            ], 400);
        }

        $backend = static::resolveBackendClient();
        $result  = $backend->registerSubscription($subscription);

        if (!empty($result['ok'])) {
            wp_send_json_success([
                'ok'           => true,
                'registration' => $result['registration'],
                'first_test'   => $result['first_test'],
            ]);
        }

        $code = (string) ($result['code'] ?? 'push_backend_unavailable');

        if ($code === 'invalid_subscription') {
            wp_send_json_error(['ok' => false, 'error' => 'invalid_subscription'], 400);
        }

        if ($code === 'no_installation_id' || $code === 'endpoint_conflict') {
            wp_send_json_error(['ok' => false, 'error' => $code], 409);
        }

        wp_send_json_error(['ok' => false, 'error' => 'push_backend_unavailable'], 503);
    }

    public static function handle_get_config(): void {
        self::authorize();

        $backend = static::resolveBackendClient();
        $result  = $backend->getVapidPublicKey();

        if (!empty($result['ok']) && !empty($result['vapid_public_key'])) {
            wp_send_json_success([
                'vapidPublicKey' => (string) $result['vapid_public_key'],
            ]);
        }

        wp_send_json_error([
            'ok'    => false,
            'error' => 'push_config_unavailable',
        ], 503);
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');
    }

    /**
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}}|null
     */
    public static function parseSubscriptionFromPost(): ?array {
        $endpoint = isset($_POST['endpoint'])
            ? sanitize_text_field(wp_unslash((string) $_POST['endpoint']))
            : '';
        $p256dh = isset($_POST['p256dh'])
            ? sanitize_text_field(wp_unslash((string) $_POST['p256dh']))
            : '';
        $auth = isset($_POST['auth'])
            ? sanitize_text_field(wp_unslash((string) $_POST['auth']))
            : '';

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return null;
        }

        return [
            'endpoint' => $endpoint,
            'keys'     => [
                'p256dh' => $p256dh,
                'auth'   => $auth,
            ],
        ];
    }

    protected static function resolveBackendClient(): AA_Push_Backend_Client {
        return new AA_Push_Backend_Client();
    }
}
