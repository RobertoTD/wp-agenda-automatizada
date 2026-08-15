<?php
/**
 * Expediente Adjuntos AJAX — attach (MC4b) + sign-read (MC4c/MC5b) + delete (MC5c1).
 *
 * Capability/nonce alineados con ExpedienteRegistrosAjax. Contrato público
 * de adjunto: ExpedienteAdjuntoPublicDto (sin storage_path ni internos).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/http/ajax/ExpedienteRegistrosAjax.php';
require_once dirname(__DIR__, 2) . '/application/expediente/UploadExpedienteRegistroAdjuntoUseCase.php';
require_once dirname(__DIR__, 2) . '/application/expediente/GetExpedienteAdjuntoReadUrlUseCase.php';
require_once dirname(__DIR__, 2) . '/application/expediente/DeleteExpedienteAdjuntoUseCase.php';
require_once dirname(__DIR__, 2) . '/application/expediente/GetExpedienteStorageUsageUseCase.php';
require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoPublicDto.php';

final class ExpedienteAdjuntosAjax {

    public const ACTION_ATTACH = 'aa_attach_expediente_registro';
    public const ACTION_SIGN_READ = 'aa_sign_expediente_adjunto_read';
    public const ACTION_DELETE = 'aa_delete_expediente_adjunto';
    public const ACTION_STORAGE_USAGE = 'aa_get_expediente_storage_usage';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION_ATTACH, [__CLASS__, 'handle_attach']);
        add_action('wp_ajax_' . self::ACTION_SIGN_READ, [__CLASS__, 'handle_sign_read']);
        add_action('wp_ajax_' . self::ACTION_DELETE, [__CLASS__, 'handle_delete']);
        add_action('wp_ajax_' . self::ACTION_STORAGE_USAGE, [__CLASS__, 'handle_storage_usage']);
    }

    public static function handle_attach(): void {
        if (!self::authorize()) {
            return;
        }

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
        if (!self::authorize()) {
            return;
        }

        $client_id = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;
        $record_id = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $variant = array_key_exists('variant', $_POST) ? wp_unslash($_POST['variant']) : null;

        if ($client_id < 1 || $record_id < 1 || $attachment_id < 1) {
            wp_send_json_error(['message' => 'Cliente, registro o imagen no válidos.', 'code' => 'invalid_context'], 400);
        }

        $use_case = new GetExpedienteAdjuntoReadUrlUseCase();
        $result = $use_case->execute([
            'client_id' => $client_id,
            'record_id' => $record_id,
            'attachment_id' => $attachment_id,
            'variant' => $variant,
        ]);

        if (empty($result['ok'])) {
            $code = (string) ($result['code'] ?? 'sign_read_failed');
            $message = (string) ($result['message'] ?? 'No se pudo obtener la imagen.');
            wp_send_json_error(['message' => $message, 'code' => $code], self::http_status_for_code($code));
        }

        wp_send_json_success([
            'url' => $result['url'],
            'expires_in' => $result['expires_in'],
            'variant' => $result['variant'],
        ]);
    }

    public static function handle_delete(): void {
        if (!self::authorize()) {
            return;
        }

        $client_id = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;
        $record_id = isset($_POST['record_id']) ? absint($_POST['record_id']) : 0;
        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

        if ($client_id < 1 || $record_id < 1 || $attachment_id < 1) {
            wp_send_json_error(['message' => 'Cliente, registro o imagen no válidos.', 'code' => 'invalid_context'], 400);
        }

        $use_case = new DeleteExpedienteAdjuntoUseCase();
        $result = $use_case->execute([
            'client_id' => $client_id,
            'record_id' => $record_id,
            'attachment_id' => $attachment_id,
        ]);

        if (empty($result['ok'])) {
            $code = (string) ($result['code'] ?? 'delete_failed');
            $message = (string) ($result['message'] ?? 'No se pudo eliminar la imagen.');
            wp_send_json_error(['message' => $message, 'code' => $code], self::http_status_for_code($code));
        }

        wp_send_json_success([
            'record_id' => $result['record_id'],
            'deleted_attachment_id' => $result['deleted_attachment_id'],
            'adjuntos' => $result['adjuntos'],
            'adjunto' => $result['adjunto'],
        ]);
    }

    /**
     * MC5d2: consumo de almacenamiento de la instalación actual. Solo
     * lectura; el alcance es el blog actual — no acepta installation_id,
     * client_id ni ningún otro scope del navegador. Contrato público
     * cerrado: { used_bytes } (bytes contabilizados por metadata local
     * finalizada, no auditoría física de Storage).
     */
    public static function handle_storage_usage(): void {
        if (!self::authorize()) {
            return;
        }

        $use_case = new GetExpedienteStorageUsageUseCase();
        $result = $use_case->execute();

        wp_send_json_success([
            'used_bytes' => (int) $result['used_bytes'],
        ]);
    }

    private static function authorize(): bool {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permisos insuficientes.', 'code' => 'forbidden'], 403);
            return false;
        }

        check_ajax_referer(ExpedienteRegistrosAjax::NONCE_ACTION, '_wpnonce');

        return ExpedienteRegistrosAjax::require_expediente_shell_access();
    }

    private static function http_status_for_code(string $code): int {
        switch ($code) {
            case 'forbidden':
            case 'storage_not_included':
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
            case 'local_delete_failed':
            case 'storage_usage_unavailable':
            case 'variant_generation_failed':
                return 500;
            case 'variant_invalid':
                return 400;
            default:
                return 400;
        }
    }
}
