<?php
/**
 * Legal Gate AJAX — status refresh + terms acceptance.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/application/legal/GetLegalGateStatusUseCase.php';
require_once dirname(__DIR__, 2) . '/application/legal/AcceptAgendaTermsUseCase.php';

final class LegalGateAjax {

    public static function register(): void {
        add_action('wp_ajax_aa_get_legal_gate_status', [__CLASS__, 'handleStatus']);
        add_action('wp_ajax_aa_accept_agenda_terms', [__CLASS__, 'handleAccept']);
    }

    public static function handleStatus(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autenticado.', 'code' => 'legal_gate_forbidden'], 403);
        }

        check_ajax_referer('aa_legal_gate_nonce', '_wpnonce');

        // Retry always bypasses the ready-session cache.
        $result = (new GetLegalGateStatusUseCase())->execute(true);
        if (!empty($result['success'])) {
            wp_send_json_success($result['data']);
        }

        $error = $result['error'] ?? [];
        wp_send_json_error(
            [
                'message'          => (string) ($error['message'] ?? 'No se pudo consultar el estado legal.'),
                'code'             => (string) ($error['code'] ?? 'legal_gate_backend_error'),
                'can_accept_terms' => !empty($result['data']['can_accept_terms']),
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

        $error = $result['error'] ?? [];
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
        } elseif (in_array($code, [
            'terms_document_version_outdated',
            'privacy_acceptance_required',
            'provisioning_request_missing',
        ], true)) {
            $http = 409;
        } elseif (strpos($code, 'unreachable') !== false || strpos($code, 'not_configured') !== false) {
            $http = 503;
        }

        wp_send_json_error($payload, $http);
    }
}
