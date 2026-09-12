<?php
/**
 * Canonical Attach Record Image AJAX — aa_attach_canonical_record_image (IMG-3b).
 *
 * Transporte sobre registro existente. No crea el registro ni escribe amount.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalAttachRecordImageAjax {

    public const ACTION = 'aa_attach_canonical_record_image';
    public const NONCE_ACTION = 'aa_attach_canonical_record_image';

    public static function register(): void {
        add_action('wp_ajax_' . self::ACTION, [__CLASS__, 'handle']);
    }

    public static function handle(): void {
        self::require_dependencies();

        if (!is_user_logged_in()) {
            self::error('unauthorized', 'Debes iniciar sesión.', 401);
        }

        $nonce_raw = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
        if (!is_string($nonce_raw) || !wp_verify_nonce($nonce_raw, self::NONCE_ACTION)) {
            self::error('invalid_nonce', 'Nonce de seguridad inválido o expirado.', 403);
        }

        $family_key_raw = isset($_POST['family_key']) ? wp_unslash($_POST['family_key']) : null;
        $container_id_raw = isset($_POST['container_id']) ? wp_unslash($_POST['container_id']) : null;
        $record_id_raw = isset($_POST['record_id']) ? wp_unslash($_POST['record_id']) : null;
        $operation_raw = isset($_POST['upload_operation_id']) ? wp_unslash($_POST['upload_operation_id']) : null;

        if (
            is_array($family_key_raw) || is_object($family_key_raw)
            || is_array($container_id_raw) || is_object($container_id_raw)
            || is_array($record_id_raw) || is_object($record_id_raw)
            || is_array($operation_raw) || is_object($operation_raw)
        ) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        if (!is_string($family_key_raw) || $family_key_raw === '' || !is_string($operation_raw)) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $container_id = CanonicalShellWriteAjaxSupport::parse_positive_int($container_id_raw);
        $record_id = CanonicalShellWriteAjaxSupport::parse_positive_int($record_id_raw);
        if ($container_id === null) {
            self::error('invalid_container_id', 'El contenedor no es válido.', 400);
        }
        if ($record_id === null) {
            self::error('invalid_record_id', 'El registro no es válido.', 400);
        }

        $family_key = sanitize_key($family_key_raw);
        $upload_operation_id = sanitize_text_field($operation_raw);

        try {
            CanonicalShellWriteAjaxSupport::authorize_identity($family_key);
        } catch (CanonicalShellWriteAjaxRejection $e) {
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            self::error('invalid_file', 'Archivo no válido.', 400);
        }

        /** @var array<string,mixed> $file */
        $file = $_FILES['file'];

        try {
            $use_case = new UploadCanonicalRecordImageUseCase();
            $result = $use_case->execute(
                $family_key,
                $container_id,
                $record_id,
                $upload_operation_id,
                $file
            );
        } catch (\Throwable $e) {
            self::error('persistence_failed', 'No se pudo adjuntar la imagen.', 500);
        }

        if (!is_array($result) || empty($result['ok'])) {
            $code = is_array($result) ? (string) ($result['code'] ?? 'persistence_failed') : 'persistence_failed';
            $message = is_array($result) ? (string) ($result['message'] ?? 'No se pudo adjuntar la imagen.') : 'No se pudo adjuntar la imagen.';
            $status = is_array($result) && isset($result['http_status']) ? (int) $result['http_status'] : 500;
            self::error($code, $message, $status > 0 ? $status : 500);
        }

        $image = $result['image'] ?? null;
        if (!is_array($image)) {
            self::error('persistence_failed', 'No se pudo adjuntar la imagen.', 500);
        }

        wp_send_json_success([
            'image' => [
                'id' => (int) ($image['id'] ?? 0),
                'width' => (int) ($image['width'] ?? 0),
                'height' => (int) ($image['height'] ?? 0),
                'byte_size' => (int) ($image['byte_size'] ?? 0),
                'created_at' => (string) ($image['created_at'] ?? ''),
            ],
        ]);
    }

    /**
     * @return never
     */
    private static function error(string $code, string $message, int $status): void {
        status_header($status);
        wp_send_json_error([
            'code' => $code,
            'message' => $message,
        ], $status);
    }

    private static function require_dependencies(): void {
        if (!class_exists('CanonicalShellWriteAjaxSupport')) {
            require_once __DIR__ . '/CanonicalShellWriteAjaxSupport.php';
        }
        if (!class_exists('CanonicalShellWriteAjaxRejection')) {
            require_once __DIR__ . '/CanonicalShellWriteAjaxRejection.php';
        }
        if (!class_exists('UploadCanonicalRecordImageUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/images/UploadCanonicalRecordImageUseCase.php';
        }
        if (!class_exists('CanonicalRecordImagesRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalRecordImagesRepository.php';
        }
        if (!class_exists('CanonicalImageUploadOperationsRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalImageUploadOperationsRepository.php';
        }
        if (!class_exists('CanonicalPurgeRunsRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalPurgeRunsRepository.php';
        }
        if (!class_exists('CanonicalRelationalRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalRelationalRepository.php';
        }
        if (!class_exists('CanonicalCapabilityConfigRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalCapabilityConfigRepository.php';
        }
        if (!class_exists('AA_Canonical_Record_Image_Confirmation_Store')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/canonical/images/class-aa-canonical-record-image-confirmation-store.php';
        }
        if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
        }
        if (!class_exists('AA_Expediente_Attachment_Signed_Uploader')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachment-signed-uploader.php';
        }
        if (!class_exists('AA_Expediente_Adjunto_Variant_Generator')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-expediente-adjunto-variant-generator.php';
        }
        if (!class_exists('AA_Canonical_Capability_Registry_Bootstrap')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
        }
    }
}
