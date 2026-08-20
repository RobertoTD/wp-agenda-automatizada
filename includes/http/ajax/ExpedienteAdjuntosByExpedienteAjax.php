<?php
/**
 * Expediente Adjuntos by Expediente AJAX — sign-read canónico (B3a).
 *
 * Frontera HTTP por expediente_id. Reutiliza nonce by-expediente.
 * Sin caller UI todavía; attach/delete canónicos fuera de alcance.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('GetExpedienteAdjuntoReadUrlForExpedienteUseCase')) {
    require_once dirname(__DIR__, 2) . '/application/expediente/GetExpedienteAdjuntoReadUrlForExpedienteUseCase.php';
}
if (!class_exists('ExpedienteRegistrosAjax')) {
    require_once dirname(__DIR__, 2) . '/http/ajax/ExpedienteRegistrosAjax.php';
}

final class ExpedienteAdjuntosByExpedienteAjax {

    public const ACTION_SIGN_READ = 'aa_sign_expediente_adjunto_read_for_expediente';
    public const NONCE_ACTION = 'aa_expediente_registros_by_expediente_nonce';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_SIGN_READ, [__CLASS__, 'handle_sign_read']);
    }

    public static function handle_sign_read(): void {
        if (!self::authorize()) {
            return;
        }

        $result = (new GetExpedienteAdjuntoReadUrlForExpedienteUseCase())->execute([
            'expediente_id' => self::read_positive_id('expediente_id'),
            'record_id' => self::read_positive_id('record_id'),
            'attachment_id' => self::read_positive_id('attachment_id'),
            'variant' => self::read_variant(),
        ]);

        if (empty($result['success'])) {
            $error = $result['error'] ?? [];
            $code = (string) ($error['code'] ?? 'sign_read_failed');
            wp_send_json_error([
                'message' => (string) ($error['message'] ?? 'No se pudo obtener la imagen.'),
                'code' => $code,
            ], self::http_status_for_code($code));
            return;
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        wp_send_json_success([
            'url' => (string) ($data['url'] ?? ''),
            'expires_in' => (int) ($data['expires_in'] ?? 0),
            'variant' => (string) ($data['variant'] ?? ''),
        ], 200);
    }

    private static function authorize(): bool {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.'], 403);
            return false;
        }

        if (!check_ajax_referer(self::NONCE_ACTION, '_wpnonce', false)) {
            wp_send_json_error(['message' => 'Nonce inválido.', 'code' => 'bad_nonce'], 403);
            return false;
        }

        return ExpedienteRegistrosAjax::require_expediente_shell_access();
    }

    /**
     * @return mixed int|string|null (sin absint; no-escalares → null)
     */
    private static function read_positive_id(string $key) {
        if (!isset($_POST[$key])) {
            return null;
        }

        $raw = wp_unslash($_POST[$key]);
        if (is_int($raw) || is_string($raw)) {
            return $raw;
        }

        return null;
    }

    /**
     * @return mixed string|null (no-escalares → null; sin default)
     */
    private static function read_variant() {
        if (!array_key_exists('variant', $_POST)) {
            return null;
        }

        $raw = wp_unslash($_POST['variant']);
        if (is_string($raw)) {
            return $raw;
        }

        return null;
    }

    private static function http_status_for_code(string $code): int {
        switch ($code) {
            case 'forbidden':
            case 'storage_not_included':
                return 403;
            case 'not_found':
            case 'client_not_found':
            case 'record_not_found':
            case 'attachment_not_found':
            case 'object_missing':
                return 404;
            case 'attachments_unavailable':
            case 'installation_missing':
            case 'object_mismatch':
            case 'adjunto_meta_conflict':
            case 'adjunto_identity_conflict':
            case 'finalize_mismatch':
            case 'path_mismatch':
            case 'path_forbidden':
            case 'adjunto_inconsistent':
            case 'storage_quota_exceeded':
                return 409;
            case 'storage_origin_not_configured':
            case 'expediente_attachments_unreachable':
            case 'upload_transport_error':
            case 'sign_failed':
            case 'sign_read_invalid':
            case 'signed_url_invalid':
            case 'delete_failed':
            case 'storage_delete_failed':
                return 502;
            case 'lookup_failed':
            case 'local_delete_failed':
            case 'storage_usage_unavailable':
            case 'variant_generation_failed':
                return 500;
            case 'invalid_id':
            case 'invalid_context':
            case 'variant_invalid':
                return 400;
            default:
                return 400;
        }
    }
}
