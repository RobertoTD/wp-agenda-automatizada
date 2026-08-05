<?php
/**
 * Accept Agenda Privacy + Terms Use Case (installation-anchored dual gate).
 *
 * Records both acceptances via the Node backend. wp_user_id comes from the
 * authenticated WordPress user (never from the browser as authority).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-legal-gate-backend-client.php';
require_once dirname(__DIR__, 2) . '/domain/legal/class-aa-agenda-terms-consent.php';
require_once dirname(__DIR__, 2) . '/domain/legal/class-aa-agenda-privacy-consent.php';
require_once __DIR__ . '/GetLegalGateStatusUseCase.php';

final class AcceptAgendaPrivacyAndTermsUseCase {

    /** @var AA_Legal_Gate_Backend_Client|null */
    private $client;

    public function __construct(?AA_Legal_Gate_Backend_Client $client = null) {
        $this->client = $client;
    }

    /**
     * @param array{
     *     privacy_consent?: mixed,
     *     privacy_document_version?: mixed,
     *     terms_consent?: mixed,
     *     terms_document_version?: mixed
     * } $input
     * @return array{
     *     success: true,
     *     data: array{
     *         already_accepted: bool,
     *         privacy_document_version: string,
     *         terms_document_version: string,
     *         source: string
     *     }
     * }|array{
     *     success: false,
     *     error: array{code: string, message: string, current_version?: string|null, shown_version?: string|null}
     * }
     */
    public function execute(array $input): array {
        if (!current_user_can('manage_options')) {
            return $this->failure('legal_gate_forbidden', 'Permisos insuficientes.');
        }

        $privacy_consent = $input['privacy_consent'] ?? null;
        if ($privacy_consent !== true && $privacy_consent !== 1 && $privacy_consent !== '1' && $privacy_consent !== 'true') {
            return $this->failure('privacy_consent_required', 'Debes aceptar el Aviso de Privacidad.');
        }

        $terms_consent = $input['terms_consent'] ?? null;
        if ($terms_consent !== true && $terms_consent !== 1 && $terms_consent !== '1' && $terms_consent !== 'true') {
            return $this->failure('terms_consent_required', 'Debes aceptar los Términos.');
        }

        $privacy_version = isset($input['privacy_document_version'])
            ? sanitize_text_field(trim((string) $input['privacy_document_version']))
            : '';
        if ($privacy_version === '' || !AA_Agenda_Privacy_Consent::version_is_valid($privacy_version)) {
            return $this->failure('privacy_notice_version_invalid', 'Versión de Privacidad no válida.');
        }

        $terms_version = isset($input['terms_document_version'])
            ? sanitize_text_field(trim((string) $input['terms_document_version']))
            : '';
        if ($terms_version === '' || !AA_Agenda_Terms_Consent::version_is_valid($terms_version)) {
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

        $backend = $this->resolveClient()->acceptPrivacyAndTerms([
            'privacy_consent'          => true,
            'privacy_document_version' => $privacy_version,
            'terms_consent'            => true,
            'terms_document_version'   => $terms_version,
            'wp_user_id'               => $wp_user_id,
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

        GetLegalGateStatusUseCase::clear_ready_cache_for_current_user();

        return [
            'success' => true,
            'data'    => [
                'already_accepted'         => !empty($backend['already_accepted']),
                'privacy_document_version' => (string) ($backend['privacy_document_version'] ?? $privacy_version),
                'terms_document_version'   => (string) ($backend['terms_document_version'] ?? $terms_version),
                'source'                   => (string) ($backend['source'] ?? ''),
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
