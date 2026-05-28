<?php
/**
 * Create Billing Portal Session Use Case
 *
 * Orchestrates a server-side request for a Stripe Billing Portal URL from the
 * Node backend. return_url is built in WordPress; Stripe URL is validated here.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-billing-portal-backend-client.php';

final class CreateBillingPortalSessionUseCase {

    /** @var AA_Billing_Portal_Backend_Client|null */
    private $client;

    /**
     * @param AA_Billing_Portal_Backend_Client|null $client Optional inject for tests.
     */
    public function __construct(?AA_Billing_Portal_Backend_Client $client = null) {
        $this->client = $client;
    }

    /**
     * @return array{
     *     success: true,
     *     data: array{url: string}
     * }|array{
     *     success: false,
     *     error: array{code: string, message: string}
     * }
     */
    public function execute(): array {
        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->failure(
                'billing_backend_not_configured',
                'Falta el client secret del backend. Vuelve a vincular la agenda o contacta a soporte.'
            );
        }

        $return_url = $this->buildAccountModuleReturnUrl();
        $backend    = $this->resolveClient()->createSession($return_url);

        if (empty($backend['ok'])) {
            return $this->mapBackendFailure($backend);
        }

        $url = isset($backend['url']) && is_string($backend['url']) ? trim($backend['url']) : '';
        if ($url === '' || !$this->isSafeStripeBillingPortalUrl($url)) {
            return $this->failure(
                'billing_invalid_response',
                'No pudimos abrir la gestión de pago en este momento.'
            );
        }

        return [
            'success' => true,
            'data'    => [
                'url' => $url,
            ],
        ];
    }

    private function resolveClient(): AA_Billing_Portal_Backend_Client {
        if ($this->client instanceof AA_Billing_Portal_Backend_Client) {
            return $this->client;
        }

        return new AA_Billing_Portal_Backend_Client();
    }

    private function buildAccountModuleReturnUrl(): string {
        return admin_url('admin-post.php?action=aa_iframe_content&module=account');
    }

    /**
     * @param array<string,mixed> $backend
     * @return array{success: false, error: array{code: string, message: string}}
     */
    private function mapBackendFailure(array $backend): array {
        $code = (string) ($backend['code'] ?? 'billing_backend_error');

        $messages = [
            'missing_subscription'           => 'No hay suscripción vinculada a esta agenda.',
            'sync_pending'                   => 'Estamos sincronizando tu suscripción. Inténtalo de nuevo en unos minutos.',
            'billing_unavailable'            => 'No pudimos abrir la gestión de pago en este momento.',
            'billing_backend_not_configured' => 'Falta el client secret del backend. Vuelve a vincular la agenda o contacta a soporte.',
            'billing_backend_unreachable'    => 'No se pudo contactar el backend. Inténtalo más tarde.',
            'billing_backend_invalid_response' => 'No pudimos abrir la gestión de pago en este momento.',
            'billing_backend_invalid_request'  => 'No pudimos abrir la gestión de pago en este momento.',
            'billing_invalid_response'       => 'No pudimos abrir la gestión de pago en este momento.',
        ];

        if (isset($messages[$code])) {
            return $this->failure($code, $messages[$code]);
        }

        return $this->failure(
            'billing_backend_error',
            'No pudimos abrir la gestión de pago en este momento.'
        );
    }

    /**
     * @param string $url
     */
    private function isSafeStripeBillingPortalUrl(string $url): bool {
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

        return $scheme === 'https' && $host === 'billing.stripe.com';
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
