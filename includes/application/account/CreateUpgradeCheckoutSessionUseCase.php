<?php
/**
 * Create Upgrade Checkout Session Use Case
 *
 * Orchestrates a server-side request for a Stripe Checkout URL to upgrade
 * an existing Freemium agenda to Pro. return_url is built in WordPress;
 * checkout_url is validated here.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-upgrade-checkout-backend-client.php';

final class CreateUpgradeCheckoutSessionUseCase {

    /** @var AA_Upgrade_Checkout_Backend_Client|null */
    private $client;

    /**
     * @param AA_Upgrade_Checkout_Backend_Client|null $client Optional inject for tests.
     */
    public function __construct(?AA_Upgrade_Checkout_Backend_Client $client = null) {
        $this->client = $client;
    }

    /**
     * @return array{
     *     success: true,
     *     data: array{checkout_url: string}
     * }|array{
     *     success: false,
     *     error: array{code: string, message: string}
     * }
     */
    public function execute(): array {
        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->failure(
                'upgrade_backend_not_configured',
                'Falta el client secret del backend. Vuelve a vincular la agenda o contacta a soporte.'
            );
        }

        $return_url = $this->buildAccountModuleReturnUrl();
        $backend    = $this->resolveClient()->createSession($return_url);

        if (empty($backend['ok'])) {
            return $this->mapBackendFailure($backend);
        }

        $checkout_url = isset($backend['checkout_url']) && is_string($backend['checkout_url'])
            ? trim($backend['checkout_url'])
            : '';
        if ($checkout_url === '' || !$this->isSafeStripeCheckoutUrl($checkout_url)) {
            return $this->failure(
                'upgrade_invalid_response',
                'No pudimos abrir el checkout de Pro. Intenta de nuevo.'
            );
        }

        return [
            'success' => true,
            'data'    => [
                'checkout_url' => $checkout_url,
            ],
        ];
    }

    private function resolveClient(): AA_Upgrade_Checkout_Backend_Client {
        if ($this->client instanceof AA_Upgrade_Checkout_Backend_Client) {
            return $this->client;
        }

        return new AA_Upgrade_Checkout_Backend_Client();
    }

    private function buildAccountModuleReturnUrl(): string {
        return admin_url('admin-post.php?action=aa_iframe_content&module=account');
    }

    /**
     * @param array<string,mixed> $backend
     * @return array{success: false, error: array{code: string, message: string}}
     */
    private function mapBackendFailure(array $backend): array {
        $code = (string) ($backend['code'] ?? 'upgrade_backend_error');

        if ($code === 'upgrade_unavailable') {
            return $this->failure(
                $code,
                'Esta cuenta ya no está disponible para upgrade desde este flujo. Actualiza el estado de cuenta e intenta de nuevo.'
            );
        }

        $messages = [
            'missing_installation'           => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
            'missing_subscription'           => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
            'missing_account'                => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
            'missing_customer_email'         => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
            'installation_mismatch'          => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
            'sync_pending'                   => 'Estamos sincronizando tu suscripción. Inténtalo de nuevo en unos minutos.',
            'upgrade_backend_not_configured' => 'Falta el client secret del backend. Vuelve a vincular la agenda o contacta a soporte.',
            'upgrade_backend_unreachable'    => 'No se pudo contactar el backend. Inténtalo más tarde.',
            'upgrade_backend_invalid_response' => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
            'upgrade_backend_invalid_request'  => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
            'upgrade_invalid_response'       => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
            'invalid_return_url'             => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
            'stripe_not_configured'          => 'No pudimos abrir el checkout de Pro. Intenta de nuevo.',
        ];

        if (isset($messages[$code])) {
            return $this->failure($code, $messages[$code]);
        }

        return $this->failure(
            'upgrade_backend_error',
            'No pudimos abrir el checkout de Pro. Intenta de nuevo.'
        );
    }

    /**
     * @param string $url
     */
    private function isSafeStripeCheckoutUrl(string $url): bool {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parsed = wp_parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }

        $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : '';
        $host   = isset($parsed['host']) ? strtolower((string) $parsed['host']) : '';

        return $scheme === 'https' && $host === 'checkout.stripe.com';
    }

    /**
     * @return array{success: false, error: array{code: string, message: string}}
     */
    private function failure(string $code, string $message): array {
        return [
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
    }
}
