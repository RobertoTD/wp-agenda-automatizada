<?php
/**
 * Resolve Shell Access Use Case
 *
 * Central access decision for the admin shell:
 * - free by default
 * - legal_gate / full only when backend returns subscription_active === true
 *
 * Bootstrap and AJAX retry must call this use case (no duplicated mapping).
 * Does not persist subscription locally. Does not use the ready transient
 * as authority. `pending` is admitted on the result type but never emitted
 * by this synchronous execute().
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/legal/class-aa-shell-access.php';
require_once __DIR__ . '/GetLegalGateStatusUseCase.php';

final class ResolveShellAccessUseCase {

    /** @var GetLegalGateStatusUseCase|null */
    private $legal_status;

    public function __construct(?GetLegalGateStatusUseCase $legal_status = null) {
        $this->legal_status = $legal_status;
    }

    /**
     * @return array{
     *     access: string,
     *     reason: string,
     *     legal: array<string,mixed>
     * }
     */
    public function execute(): array {
        $client_secret = trim((string) get_option('aa_client_secret', ''));
        if ($client_secret === '') {
            return $this->result(
                AA_Shell_Access::ACCESS_FREE,
                AA_Shell_Access::REASON_MISSING_CREDENTIALS,
                $this->failure_legal(
                    'legal_gate_backend_not_configured',
                    'Falta la configuración de conexión con DEOIA.'
                )
            );
        }

        $legal = $this->resolveLegalStatus()->execute();
        return $this->mapLegalToAccess($legal);
    }

    /**
     * @param array<string,mixed> $legal
     * @return array{access: string, reason: string, legal: array<string,mixed>}
     */
    private function mapLegalToAccess(array $legal): array {
        if (empty($legal['success'])) {
            $code = (string) ($legal['error']['code'] ?? '');
            return $this->result(
                AA_Shell_Access::ACCESS_FREE,
                $this->reasonFromErrorCode($code),
                $legal
            );
        }

        $data = isset($legal['data']) && is_array($legal['data']) ? $legal['data'] : [];
        if (!array_key_exists('subscription_active', $data) || !is_bool($data['subscription_active'])) {
            return $this->result(
                AA_Shell_Access::ACCESS_FREE,
                AA_Shell_Access::REASON_UNKNOWN,
                $legal
            );
        }

        if ($data['subscription_active'] !== true) {
            return $this->result(
                AA_Shell_Access::ACCESS_FREE,
                AA_Shell_Access::REASON_NO_SUBSCRIPTION,
                $legal
            );
        }

        $status = (string) ($data['status'] ?? '');
        if ($status === 'ready') {
            return $this->result(
                AA_Shell_Access::ACCESS_FULL,
                AA_Shell_Access::REASON_DOCUMENTS_ACCEPTED,
                $legal
            );
        }

        if (in_array($status, AA_Shell_Access::LEGAL_PENDING_STATUSES, true)) {
            return $this->result(
                AA_Shell_Access::ACCESS_LEGAL_GATE,
                AA_Shell_Access::REASON_DOCUMENTS_PENDING,
                $legal
            );
        }

        return $this->result(
            AA_Shell_Access::ACCESS_FREE,
            AA_Shell_Access::REASON_UNKNOWN,
            $legal
        );
    }

    private function reasonFromErrorCode(string $code): string {
        if ($code === 'legal_gate_backend_not_configured') {
            return AA_Shell_Access::REASON_MISSING_CREDENTIALS;
        }
        if ($code === 'legal_gate_client_not_found') {
            return AA_Shell_Access::REASON_INSTALLATION_MISSING;
        }
        if ($code === 'legal_gate_credentials_invalid') {
            return AA_Shell_Access::REASON_CREDENTIALS_INVALID;
        }
        if (
            $code === 'legal_gate_backend_unreachable'
            || strpos($code, 'unreachable') !== false
            || strpos($code, 'unavailable') !== false
        ) {
            return AA_Shell_Access::REASON_TRANSPORT_ERROR;
        }

        return AA_Shell_Access::REASON_UNKNOWN;
    }

    /**
     * @param array<string,mixed> $legal
     * @return array{access: string, reason: string, legal: array<string,mixed>}
     */
    private function result(string $access, string $reason, array $legal): array {
        return [
            'access' => $access,
            'reason' => $reason,
            'legal'  => $legal,
        ];
    }

    /**
     * @return array{
     *     success: false,
     *     error: array{code: string, message: string},
     *     data: array{can_accept_terms: bool, can_accept_privacy_and_terms: bool}
     * }
     */
    private function failure_legal(string $code, string $message): array {
        $can_manage = current_user_can('manage_options');
        return [
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
            'data'    => [
                'can_accept_terms'             => $can_manage,
                'can_accept_privacy_and_terms' => $can_manage,
            ],
        ];
    }

    private function resolveLegalStatus(): GetLegalGateStatusUseCase {
        if ($this->legal_status instanceof GetLegalGateStatusUseCase) {
            return $this->legal_status;
        }

        return new GetLegalGateStatusUseCase();
    }
}
