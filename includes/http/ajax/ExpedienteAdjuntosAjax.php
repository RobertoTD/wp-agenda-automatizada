<?php
/**
 * Expediente Adjuntos AJAX — attach (MC4b) + sign-read dirigido (MC4c/MC5b).
 *
 * Capability/nonce alineados con ExpedienteRegistrosAjax. Contrato público
 * de adjunto: ExpedienteAdjuntoPublicDto (sin storage_path ni internos).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/http/ajax/ExpedienteRegistrosAjax.php';
require_once dirname(__DIR__, 2) . '/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php';
require_once dirname(__DIR__, 2) . '/application/expediente/GetExpedienteAdjuntoReadUrlUseCase.php';
require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoPublicDto.php';

final class ExpedienteAdjuntosAjax {

    public const ACTION_ATTACH = 'aa_attach_expediente_registro';
    public const ACTION_SIGN_READ = 'aa_sign_expediente_adjunto_read';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_ATTACH, [__CLASS__, 'handle_attach']);
        add_action('wp_ajax_' . self::ACTION_SIGN_READ, [__CLASS__, 'handle_sign_read']);
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
            'adjunto' => ExpedienteAdjuntoPublicDto::from($result['attachment']),
        ]);
    }

    public static function handle_sign_read(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.', 'code' => 'forbidden'], 403);
        }

        check_ajax_referer(ExpedienteRegistrosAjax::NONCE_ACTION, '_wpnonce');

        $client_id = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;
        $record_id = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;
        // MC5b: lectura siempre dirigida; attachment_id es obligatorio
        // (el fallback MC4c sin attachment_id quedó retirado).
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

        if ($client_id < 1 || $record_id < 1 || $attachment_id < 1) {
            wp_send_json_error(['message' => 'Cliente, registro o imagen no válidos.', 'code' => 'invalid_context'], 400);
        }

        $use_case = new GetExpedienteAdjuntoReadUrlUseCase();
        $result = $use_case->execute([
            'client_id' => $client_id,
            'record_id' => $record_id,
            'attachment_id' => $attachment_id,
        ]);

        if (empty($result['ok'])) {
            $code = (string) ($result['code'] ?? 'sign_read_failed');
            $message = (string) ($result['message'] ?? 'No se pudo obtener la imagen.');
            wp_send_json_error(['message' => $message, 'code' => $code], self::http_status_for_code($code));
        }

        wp_send_json_success([
            'url' => $result['url'],
            'expires_in' => $result['expires_in'],
            'adjunto' => $result['adjunto'],
        ]);
    }

    private static function http_status_for_code(string $code): int {
        switch ($code) {
            case 'forbidden':
                return 403;
            case 'client_not_found':
            case 'record_not_found':
            case 'attachment_not_found':
            case 'object_missing':
                return 404;
            case 'installation_missing':
            case 'object_mismatch':
            case 'adjunto_meta_conflict':
            case 'adjunto_identity_conflict':
            case 'finalize_mismatch':
            case 'path_mismatch':
            case 'path_forbidden':
            case 'adjunto_inconsistent':
                return 409;
            case 'storage_origin_not_configured':
            case 'expediente_attachments_unreachable':
            case 'upload_transport_error':
            case 'sign_failed':
            case 'sign_read_invalid':
            case 'signed_url_invalid':
                return 502;
            default:
                return 400;
        }
    }
}
