<?php
/**
 * Get Account Status Use Case
 *
 * Orchestrates a read-only fetch of commercial account status from the
 * Node backend. No billing rules — backend remains source of truth.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-account-status-backend-client.php';

final class GetAccountStatusUseCase {

    /** @var AA_Account_Status_Backend_Client|null */
    private $client;

    /** @var list<string> */
    private const ALLOWED_KEYS = [
        'plan_tier',
        'stripe_status',
        'effective_access_tier',
        'billing_state',
        'current_period_end',
        'cancel_at',
        'is_cancel_scheduled',
        'sync_pending',
        'payment_action_required',
        'messages',
    ];

    /**
     * @param AA_Account_Status_Backend_Client|null $client Optional inject for tests.
     */
    public function __construct(?AA_Account_Status_Backend_Client $client = null) {
        $this->client = $client;
    }

    /**
     * @return array{
     *     success: true,
     *     data: array{account_status: array<string,mixed>}
     * }|array{
     *     success: false,
     *     error: array{code: string, message: string}
     * }
     */
    public function execute(): array {
        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->failure(
                'account_backend_not_configured',
                'Falta el client secret del backend. Vuelve a vincular la agenda o contacta a soporte.'
            );
        }

        $backend = $this->resolveClient()->fetch();

        if (empty($backend['ok'])) {
            return $this->failure(
                (string) ($backend['code'] ?? 'account_backend_error'),
                (string) ($backend['error'] ?? 'No se pudo consultar el estado de cuenta.')
            );
        }

        $sanitized = $this->sanitizeAccountStatus($backend['account_status']);

        return [
            'success' => true,
            'data'    => [
                'account_status' => $sanitized,
            ],
        ];
    }

    private function resolveClient(): AA_Account_Status_Backend_Client {
        if ($this->client instanceof AA_Account_Status_Backend_Client) {
            return $this->client;
        }

        return new AA_Account_Status_Backend_Client();
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function sanitizeAccountStatus(array $raw): array {
        $out = [];

        foreach (self::ALLOWED_KEYS as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }

            $value = $raw[$key];

            if ($key === 'messages') {
                $out[$key] = $this->normalizeMessages($value);
                continue;
            }

            if ($key === 'is_cancel_scheduled' || $key === 'sync_pending' || $key === 'payment_action_required') {
                $out[$key] = (bool) $value;
                continue;
            }

            if ($value === null) {
                $out[$key] = null;
                continue;
            }

            if (is_scalar($value)) {
                $trimmed = trim((string) $value);
                $out[$key] = $trimmed === '' ? null : $trimmed;
            }
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeMessages($value): array {
        if (!is_array($value)) {
            return [];
        }

        $messages = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }
            $text = trim($item);
            if ($text !== '') {
                $messages[] = $text;
            }
        }

        return $messages;
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
