<?php
/**
 * Accept Agenda Terms Use Case
 *
 * Records terms acceptance via the Node backend. wp_user_id comes from the
 * authenticated WordPress user (never from the browser as authority).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-legal-gate-backend-client.php';
require_once dirname(__DIR__, 2) . '/domain/legal/class-aa-agenda-terms-consent.php';
require_once __DIR__ . '/GetLegalGateStatusUseCase.php';

final class AcceptAgendaTermsUseCase {

    /** @var AA_Legal_Gate_Backend_Client|null */
    private $client;

    public function __construct(?AA_Legal_Gate_Backend_Client $client = null) {
        $this->client = $client;
    }

    /**
     * @param array{terms_consent?: mixed, terms_document_version?: mixed} $input
     * @return array{
     *     success: true,
     *     data: array{already_accepted: bool, document_version: string, source: string}
     * }|array{
     *     success: false,
     *     error: array{code: string, message: string, current_version?: string|null, shown_version?: string|null}
     * }
     */
    public function execute(array $input): array {
        if (!current_user_can('manage_options')) {
            return $this->failure('legal_gate_forbidden', 'Permisos insuficientes.');
        }

        $consent = $input['terms_consent'] ?? null;
        if ($consent !== true && $consent !== 1 && $consent !== '1' && $consent !== 'true') {
            return $this->failure('terms_consent_required', 'Debes aceptar los Términos.');
        }

        $version = isset($input['terms_document_version'])
            ? sanitize_text_field(trim((string) $input['terms_document_version']))
            : '';
        if ($version === '' || !AA_Agenda_Terms_Consent::version_is_valid($version)) {
            return $this->failure('terms_document_version_invalid', 'Versión de Términos no válida.');
        }

        $wp_user_id = (int) get_current_user_id();
        if ($wp_user_id < 1) {
            return $this->failure('legal_gate_forbidden', 'Usuario no autenticado.');
        }

        $client_secret = (string) get_option('aa_client_secret', '');
        if ($client_secret === '') {
            return $this->failure(
                'legal_gate_backend_not_configured',
                'Falta la configuración de conexión con DEOIA.'
            );
        }

        $backend = $this->resolveClient()->acceptTerms([
            'terms_consent'          => true,
            'terms_document_version' => $version,
            'wp_user_id'             => $wp_user_id,
        ]);

        if (empty($backend['ok'])) {
            $error = [
                'code'    => (string) ($backend['code'] ?? 'legal_gate_backend_error'),
                'message' => (string) ($backend['error'] ?? 'No se pudo registrar la aceptación.'),
            ];
            if (array_key_exists('current_version', $backend)) {
                $error['current_version'] = $backend['current_version'];
            }
            if (array_key_exists('shown_version', $backend)) {
                $error['shown_version'] = $backend['shown_version'];
            }

            return [
                'success' => false,
                'error'   => $error,
            ];
        }

        // Force the next shell boot to re-query status (expecting ready).
        GetLegalGateStatusUseCase::clear_ready_cache_for_current_user();

        return [
            'success' => true,
            'data'    => [
                'already_accepted' => !empty($backend['already_accepted']),
                'document_version' => (string) ($backend['document_version'] ?? $version),
                'source'           => (string) ($backend['source'] ?? ''),
            ],
        ];
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

    private function resolveClient(): AA_Legal_Gate_Backend_Client {
        if ($this->client instanceof AA_Legal_Gate_Backend_Client) {
            return $this->client;
        }

        return new AA_Legal_Gate_Backend_Client();
    }
}
