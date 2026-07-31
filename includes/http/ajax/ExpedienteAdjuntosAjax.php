<?php
/**
 * Expediente Adjuntos AJAX — attach image to an existing registro (MC4b).
 *
 * Capability/nonce alineados con ExpedienteRegistrosAjax.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/http/ajax/ExpedienteRegistrosAjax.php';
require_once dirname(__DIR__, 2) . '/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php';

final class ExpedienteAdjuntosAjax {

    public const ACTION_ATTACH = 'aa_attach_expediente_registro';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_ATTACH, [__CLASS__, 'handle_attach']);
    }

    public static function handle_attach(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.', 'code' => 'forbidden'], 403);
        }

        check_ajax_referer(ExpedienteRegistrosAjax::NONCE_ACTION, '_wpnonce');

        $client_id = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;
        $record_id = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;
        $operation_id = isset($_POST['upload_operation_id'])
            ? sanitize_text_field(wp_unslash((string) $_POST['upload_operation_id']))
            : '';

        if ($client_id < 1 || $record_id < 1) {
            wp_send_json_error(['message' => 'Cliente o registro no válido.', 'code' => 'invalid_context'], 400);
        }

        if ($operation_id === '') {
            wp_send_json_error(['message' => 'Identificador de operación no válido.', 'code' => 'invalid_operation_id'], 400);
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error(['message' => 'No se recibió ninguna imagen.', 'code' => 'file_missing'], 400);
        }

        $use_case = new UploadExpedienteRegistroAdjuntoUseCase();
        $result = $use_case->execute([
            'client_id' => $client_id,
            'record_id' => $record_id,
            'upload_operation_id' => $operation_id,
            'file' => $_FILES['file'],
        ]);

        if (empty($result['ok'])) {
            $code = (string) ($result['code'] ?? 'attach_failed');
            $message = (string) ($result['message'] ?? 'No se pudo subir la imagen.');
            $status = self::http_status_for_code($code);
            wp_send_json_error(['message' => $message, 'code' => $code], $status);
        }

        wp_send_json_success([
            'record_id' => $record_id,
            'attachment' => $result['attachment'],
        ]);
    }

    private static function http_status_for_code(string $code): int {
        switch ($code) {
            case 'forbidden':
                return 403;
            case 'client_not_found':
            case 'record_not_found':
                return 404;
            case 'installation_missing':
            case 'object_mismatch':
            case 'adjunto_meta_conflict':
            case 'adjunto_identity_conflict':
            case 'finalize_mismatch':
            case 'path_mismatch':
                return 409;
            case 'storage_origin_not_configured':
            case 'expediente_attachments_unreachable':
            case 'upload_transport_error':
            case 'sign_failed':
                return 502;
            default:
                return 400;
        }
    }
}
