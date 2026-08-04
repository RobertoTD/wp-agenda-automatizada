<?php
/**
 * Get Legal Gate Status Use Case
 *
 * Read-only fetch of legal gate status from the Node backend (HMAC).
 * Caches a successful `ready` result briefly per user so module navigation
 * does not re-hit the backend. Blocking/error states are never cached.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-legal-gate-backend-client.php';
require_once dirname(__DIR__, 2) . '/domain/legal/class-aa-agenda-terms-consent.php';

final class GetLegalGateStatusUseCase {

    /** @var list<string> */
    private const KNOWN_STATUSES = [
        'ready',
        'needs_terms',
        'privacy_required',
        'provisioning_request_missing',
    ];

    private const READY_CACHE_TTL = 43200; // 12 hours

    /** @var AA_Legal_Gate_Backend_Client|null */
    private $client;

    public function __construct(?AA_Legal_Gate_Backend_Client $client = null) {
        $this->client = $client;
    }

    /**
     * @return array{
     *     success: true,
     *     data: array{
     *         status: string,
     *         privacy_accepted: bool,
     *         terms_accepted: bool,
     *         terms_document: array{version: string, human_url: string}|null,
     *         can_accept_terms: bool
     *     }
     * }|array{
     *     success: false,
     *     error: array{code: string, message: string},
     *     data: array{can_accept_terms: bool}
     * }
     */
    public function execute(bool $force_refresh = false): array {
        $can_accept = current_user_can('manage_options');
        $cache_key  = self::ready_cache_key();

        if (!$force_refresh && $cache_key !== '' && get_transient($cache_key) === '1') {
            return [
                'success' => true,
                'data'    => [
                    'status'           => 'ready',
                    'privacy_accepted' => true,
                    'terms_accepted'   => true,
                    'terms_document'   => null,
                    'can_accept_terms' => false,
                ],
            ];
        }

        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return [
                'success' => false,
                'error'   => [
                    'code'    => 'legal_gate_backend_not_configured',
                    'message' => 'Falta la configuración de conexión con DEOIA.',
                ],
                'data'    => ['can_accept_terms' => $can_accept],
            ];
        }

        $backend = $this->resolveClient()->fetchStatus();
        if (empty($backend['ok'])) {
            return [
                'success' => false,
                'error'   => [
                    'code'    => (string) ($backend['code'] ?? 'legal_gate_backend_error'),
                    'message' => (string) ($backend['error'] ?? 'No se pudo consultar el estado legal.'),
                ],
                'data'    => ['can_accept_terms' => $can_accept],
            ];
        }

        $status = (string) $backend['status'];
        if (!in_array($status, self::KNOWN_STATUSES, true)) {
            return [
                'success' => false,
                'error'   => [
                    'code'    => 'legal_gate_unknown_status',
                    'message' => 'Estado legal desconocido.',
                ],
                'data'    => ['can_accept_terms' => $can_accept],
            ];
        }

        $terms_document = null;
        if ($status === 'needs_terms' && is_array($backend['terms_document'])) {
            $version = (string) ($backend['terms_document']['version'] ?? '');
            $url     = (string) ($backend['terms_document']['human_url'] ?? '');
            if (AA_Agenda_Terms_Consent::version_is_valid($version) && $url !== '') {
                $terms_document = [
                    'version'   => $version,
                    'human_url' => $url === AA_Agenda_Terms_Consent::HUMAN_URL
                        ? $url
                        : AA_Agenda_Terms_Consent::HUMAN_URL,
                ];
            } else {
                return [
                    'success' => false,
                    'error'   => [
                        'code'    => 'legal_gate_backend_invalid_response',
                        'message' => 'Metadatos de Términos incompletos.',
                    ],
                    'data'    => ['can_accept_terms' => $can_accept],
                ];
            }
        }

        if ($status === 'ready' && $cache_key !== '') {
            set_transient($cache_key, '1', self::READY_CACHE_TTL);
        }

        return [
            'success' => true,
            'data'    => [
                'status'           => $status,
                'privacy_accepted' => !empty($backend['privacy_accepted']),
                'terms_accepted'   => !empty($backend['terms_accepted']),
                'terms_document'   => $terms_document,
                'can_accept_terms' => $can_accept && $status === 'needs_terms',
            ],
        ];
    }

    public static function clear_ready_cache_for_current_user(): void {
        $key = self::ready_cache_key();
        if ($key !== '') {
            delete_transient($key);
        }
    }

    private static function ready_cache_key(): string {
        $user_id = (int) get_current_user_id();
        return $user_id > 0 ? 'aa_legal_gate_ready_' . $user_id : '';
    }

    private function resolveClient(): AA_Legal_Gate_Backend_Client {
        if ($this->client instanceof AA_Legal_Gate_Backend_Client) {
            return $this->client;
        }

        return new AA_Legal_Gate_Backend_Client();
    }
}
