<?php
/**
 * Legal Gate AJAX — status refresh + terms / dual privacy+terms acceptance.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/legal/ResolveShellAccessUseCase.php';
require_once dirname(__DIR__, 2) . '/application/legal/AcceptAgendaTermsUseCase.php';
require_once dirname(__DIR__, 2) . '/application/legal/AcceptAgendaPrivacyAndTermsUseCase.php';
require_once dirname(__DIR__, 2) . '/domain/legal/class-aa-shell-access.php';

final class LegalGateAjax {

    public static function register(): void {
        add_action('wp_ajax_aa_get_legal_gate_status', [__CLASS__, 'handleStatus']);
        add_action('wp_ajax_aa_accept_agenda_terms', [__CLASS__, 'handleAccept']);
        add_action('wp_ajax_aa_accept_agenda_privacy_and_terms', [__CLASS__, 'handleAcceptPrivacyAndTerms']);
    }

    public static function handleStatus(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autenticado.', 'code' => 'legal_gate_forbidden'], 403);
        }

        check_ajax_referer('aa_legal_gate_nonce', '_wpnonce');

        $result = (new ResolveShellAccessUseCase())->execute();
        $access = (string) ($result['access'] ?? '');
        $reason = (string) ($result['reason'] ?? '');
        $legal  = isset($result['legal']) && is_array($result['legal']) ? $result['legal'] : [];

        $payload = [
            'access' => $access,
            'reason' => $reason,
        ];

        if (
            $access === AA_Shell_Access::ACCESS_FREE
            || $access === AA_Shell_Access::ACCESS_FULL
            || $access === AA_Shell_Access::ACCESS_LEGAL_GATE
        ) {
            if (!empty($legal['success']) && isset($legal['data']) && is_array($legal['data'])) {
                $payload = array_merge($payload, $legal['data']);
            }
            wp_send_json_success($payload);
        }

        $error = isset($legal['error']) && is_array($legal['error']) ? $legal['error'] : [];
        wp_send_json_error(
            [
                'message'                      => (string) ($error['message'] ?? 'No se pudo consultar el estado de acceso.'),
                'code'                         => (string) ($error['code'] ?? 'legal_gate_backend_error'),
                'access'                       => $access !== '' ? $access : AA_Shell_Access::ACCESS_FREE,
                'reason'                       => $reason !== '' ? $reason : AA_Shell_Access::REASON_UNKNOWN,
                'can_accept_terms'             => !empty($legal['data']['can_accept_terms']),
                'can_accept_privacy_and_terms' => !empty($legal['data']['can_accept_privacy_and_terms']),
            ],
            500
        );
    }

    public static function handleAccept(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.', 'code' => 'legal_gate_forbidden'], 403);
        }

        check_ajax_referer('aa_legal_gate_nonce', '_wpnonce');

        $consent_raw = isset($_POST['terms_consent']) ? wp_unslash($_POST['terms_consent']) : null;
        $version_raw = isset($_POST['terms_document_version'])
            ? sanitize_text_field(wp_unslash((string) $_POST['terms_document_version']))
            : '';

        $result = (new AcceptAgendaTermsUseCase())->execute([
            'terms_consent'          => $consent_raw,
            'terms_document_version' => $version_raw,
        ]);

        if (!empty($result['success'])) {
            wp_send_json_success($result['data']);
        }

        self::sendAcceptError($result['error'] ?? [], [
            'terms_document_version_outdated',
            'privacy_acceptance_required',
            'provisioning_request_missing',
        ]);
    }

    public static function handleAcceptPrivacyAndTerms(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.', 'code' => 'legal_gate_forbidden'], 403);
        }

        check_ajax_referer('aa_legal_gate_nonce', '_wpnonce');

        $privacy_consent = isset($_POST['privacy_consent']) ? wp_unslash($_POST['privacy_consent']) : null;
        $terms_consent   = isset($_POST['terms_consent']) ? wp_unslash($_POST['terms_consent']) : null;
        $privacy_version = isset($_POST['privacy_document_version'])
            ? sanitize_text_field(wp_unslash((string) $_POST['privacy_document_version']))
            : '';
        $terms_version = isset($_POST['terms_document_version'])
            ? sanitize_text_field(wp_unslash((string) $_POST['terms_document_version']))
            : '';

        $result = (new AcceptAgendaPrivacyAndTermsUseCase())->execute([
            'privacy_consent'          => $privacy_consent,
            'privacy_document_version' => $privacy_version,
            'terms_consent'            => $terms_consent,
            'terms_document_version'   => $terms_version,
        ]);

        if (!empty($result['success'])) {
            wp_send_json_success($result['data']);
        }

        self::sendAcceptError($result['error'] ?? [], [
            'privacy_notice_version_outdated',
            'terms_document_version_outdated',
            'partial_acceptance_exists',
            'legal_gate_status_invalid',
            'legal_gate_use_terms_endpoint',
            'privacy_acceptance_required',
            'provisioning_request_missing',
        ]);
    }

    /**
     * @param array<string, mixed> $error
     * @param list<string> $conflict_codes
     */
    private static function sendAcceptError(array $error, array $conflict_codes): void {
        $payload = [
            'message' => (string) ($error['message'] ?? 'No se pudo registrar la aceptación.'),
            'code'    => (string) ($error['code'] ?? 'legal_gate_backend_error'),
        ];
        if (array_key_exists('current_version', $error)) {
            $payload['current_version'] = $error['current_version'];
        }
        if (array_key_exists('shown_version', $error)) {
            $payload['shown_version'] = $error['shown_version'];
        }

        $http = 400;
        $code = $payload['code'];
        if (in_array($code, ['legal_gate_forbidden'], true)) {
            $http = 403;
        } elseif (in_array($code, ['legal_gate_client_not_found'], true)) {
            $http = 404;
        } elseif (in_array($code, $conflict_codes, true)) {
            $http = 409;
        } elseif (
            strpos($code, 'unreachable') !== false
            || strpos($code, 'not_configured') !== false
            || strpos($code, 'unavailable') !== false
        ) {
            $http = 503;
        }

        wp_send_json_error($payload, $http);
    }
}
