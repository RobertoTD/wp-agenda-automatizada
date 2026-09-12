<?php
/**
 * Canonical Sign Record Image Read AJAX — aa_sign_canonical_record_image_read (IMG-4).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalSignRecordImageReadAjax {

    public const ACTION = 'aa_sign_canonical_record_image_read';
    public const NONCE_ACTION = 'aa_sign_canonical_record_image_read';

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
        $image_id_raw = isset($_POST['image_id']) ? wp_unslash($_POST['image_id']) : null;
        $variant_raw = isset($_POST['variant']) ? wp_unslash($_POST['variant']) : null;

        if (
            is_array($family_key_raw) || is_object($family_key_raw)
            || is_array($container_id_raw) || is_object($container_id_raw)
            || is_array($record_id_raw) || is_object($record_id_raw)
            || is_array($image_id_raw) || is_object($image_id_raw)
            || is_array($variant_raw) || is_object($variant_raw)
        ) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        if (!is_string($family_key_raw) || $family_key_raw === '' || !is_string($variant_raw)) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $container_id = CanonicalShellWriteAjaxSupport::parse_positive_int($container_id_raw);
        $record_id = CanonicalShellWriteAjaxSupport::parse_positive_int($record_id_raw);
        $image_id = CanonicalShellWriteAjaxSupport::parse_positive_int($image_id_raw);
        if ($container_id === null) {
            self::error('invalid_container_id', 'El contenedor no es válido.', 400);
        }
        if ($record_id === null) {
            self::error('invalid_record_id', 'El registro no es válido.', 400);
        }
        if ($image_id === null) {
            self::error('invalid_image_id', 'La imagen no es válida.', 400);
        }

        $family_key = sanitize_key($family_key_raw);
        $variant = sanitize_key($variant_raw);

        try {
            $authorized = CanonicalShellWriteAjaxSupport::authorize_identity($family_key);
        } catch (CanonicalShellWriteAjaxRejection $e) {
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        $resolved_family_key = $authorized['family']->key();

        try {
            $use_case = new GetCanonicalRecordImageReadUrlUseCase();
            $result = $use_case->execute(
                $resolved_family_key,
                $container_id,
                $record_id,
                $image_id,
                $variant
            );
        } catch (\Throwable $e) {
            self::error('persistence_failed', 'No se pudo obtener la imagen.', 500);
        }

        if (!is_array($result) || empty($result['ok'])) {
            $code = is_array($result) ? (string) ($result['code'] ?? 'sign_read_failed') : 'sign_read_failed';
            $message = is_array($result)
                ? (string) ($result['message'] ?? 'No se pudo obtener la imagen.')
                : 'No se pudo obtener la imagen.';
            $status = is_array($result) && isset($result['http_status']) ? (int) $result['http_status'] : 500;
            self::error($code, $message, $status > 0 ? $status : 500);
        }

        wp_send_json_success([
            'url' => (string) $result['url'],
            'expires_in' => (int) $result['expires_in'],
            'variant' => (string) $result['variant'],
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
        if (!class_exists('GetCanonicalRecordImageReadUrlUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/images/GetCanonicalRecordImageReadUrlUseCase.php';
        }
        if (!class_exists('CanonicalRecordImagesRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalRecordImagesRepository.php';
        }
        if (!class_exists('CanonicalRelationalRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalRelationalRepository.php';
        }
        if (!class_exists('CanonicalCapabilityConfigRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalCapabilityConfigRepository.php';
        }
        if (!class_exists('AA_Expediente_Attachments_Backend_Client')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachments-backend-client.php';
        }
        if (!class_exists('AA_Expediente_Attachment_Read_Url_Validator')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-expediente-attachment-read-url-validator.php';
        }
        if (!class_exists('AA_Canonical_Capability_Registry_Bootstrap')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-capability-registry-bootstrap.php';
        }
    }
}
