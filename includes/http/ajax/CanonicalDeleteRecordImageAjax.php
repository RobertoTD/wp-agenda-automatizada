<?php
/**
 * Canonical Delete Record Image AJAX — retiro de una imagen (IMG-5 inc. 5).
 *
 * Transporte + composition root. Delega en RetireCanonicalRecordImageUseCase.
 * Sin SQL directo. Sin mandate_id. Sin gate images.is_ready.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalDeleteRecordImageAjax {

    public const ACTION = 'aa_delete_canonical_record_image';
    public const NONCE_ACTION = 'aa_delete_canonical_record_image';

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
        $image_id_raw = isset($_POST['image_id']) ? wp_unslash($_POST['image_id']) : null;
        $retire_action_raw = array_key_exists('retire_action', $_POST) ? wp_unslash($_POST['retire_action']) : '';

        if (is_array($family_key_raw) || is_object($family_key_raw)
            || is_array($image_id_raw) || is_object($image_id_raw)
            || is_array($retire_action_raw) || is_object($retire_action_raw)
        ) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        if (!is_string($family_key_raw) || $family_key_raw === '') {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $image_id = CanonicalShellWriteAjaxSupport::parse_positive_int($image_id_raw);
        if ($image_id === null) {
            self::error('invalid_image_id', 'La imagen no es válida.', 400);
        }

        $intent = RetireCanonicalRecordImageCommand::INTENT_RETIRE;
        if (is_string($retire_action_raw) && $retire_action_raw !== '') {
            if ($retire_action_raw !== RetireCanonicalRecordImageCommand::INTENT_CANCEL) {
                self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
            }
            $intent = RetireCanonicalRecordImageCommand::INTENT_CANCEL;
        }

        $family_key = sanitize_key($family_key_raw);

        try {
            $authorized = CanonicalShellWriteAjaxSupport::authorize_identity($family_key);
        } catch (CanonicalShellWriteAjaxRejection $e) {
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        $family = $authorized['family'];
        $resolved_family_key = $family->key();

        try {
            $command = new RetireCanonicalRecordImageCommand(
                $resolved_family_key,
                $image_id,
                $intent
            );
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '[invalid_image_id]') === 0) {
                self::error('invalid_image_id', 'La imagen no es válida.', 400);
            }
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $use_case = new RetireCanonicalRecordImageUseCase();

        try {
            $result = $use_case->execute($command);
        } catch (\InvalidArgumentException $e) {
            self::error('persistence_failed', 'No se pudo eliminar la imagen.', 500);
        } catch (\Throwable $e) {
            self::error('persistence_failed', 'No se pudo eliminar la imagen.', 500);
        }

        $state = $result->state();
        $return_ctx_input = [];
        if (array_key_exists('lists_scope', $_POST)) {
            $return_ctx_input['lists_scope'] = wp_unslash($_POST['lists_scope']);
        }
        if (array_key_exists('page', $_POST)) {
            $return_ctx_input['page'] = wp_unslash($_POST['page']);
        }
        if (array_key_exists('containers_page', $_POST)) {
            $return_ctx_input['containers_page'] = wp_unslash($_POST['containers_page']);
        }
        $return_ctx = AA_Canonical_Shell_Base_Url_Policy::parse_mutation_return_context($return_ctx_input);
        if ($return_ctx === null) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $container_id = $result->container_id();
        $redirect_url = null;
        if ($container_id !== null && $container_id >= 1) {
            $containers_page = $return_ctx['containers_page'];
            $redirect_page = $return_ctx['page'];
            $redirect_url = AA_Canonical_Shell_Base_Url_Policy::build_records_url(
                $resolved_family_key,
                $container_id,
                ($redirect_page !== null && $redirect_page > 1) ? $redirect_page : null,
                ($containers_page !== null && $containers_page > 1) ? $containers_page : null,
                $return_ctx['lists_scope']
            );
        }

        if ($state === RetireCanonicalRecordImageResult::STATE_CONTAINER_NOT_FOUND) {
            self::error('container_not_found', 'El contenedor solicitado no existe o no está disponible.', 404);
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_IMAGE_NOT_FOUND) {
            self::error('image_not_found', 'La imagen solicitada no existe o no está disponible.', 404);
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_FORBIDDEN) {
            self::error('forbidden', 'No tienes permiso para esta operación.', 403);
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_PERSISTENCE_FAILED) {
            self::error('persistence_failed', 'No se pudo eliminar la imagen.', 500);
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_RESOURCE_BUSY) {
            self::error('resource_busy', 'El recurso está ocupado. Inténtalo de nuevo.', 409);
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_SCOPE_OVERLAP) {
            self::error('scope_overlap', 'Hay otra eliminación en curso sobre este recurso.', 409);
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_UNCERTAIN) {
            self::error(
                'uncertain',
                'No fue posible confirmar si la imagen se eliminó. Recarga la lista para verificarlo antes de intentarlo nuevamente.',
                409,
                $redirect_url !== null ? ['redirect_url' => $redirect_url] : []
            );
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_INCOMPLETE) {
            self::error(
                'incomplete',
                'La eliminación no terminó. Pulsa Continuar para seguir.',
                409,
                [
                    'can_continue' => true,
                    'can_cancel' => false,
                ]
            );
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_CONFLICT) {
            self::error(
                'conflict',
                'No se pudo preparar la eliminación. Puedes cancelarla para desbloquear el registro, o reintentar.',
                409,
                [
                    'can_continue' => true,
                    'can_cancel' => $result->can_cancel(),
                ]
            );
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_CANCEL_REJECTED) {
            self::error(
                'cancel_rejected',
                'Ya hubo comunicación remota. No se puede cancelar. Pulsa Continuar para recuperar el protocolo.',
                409,
                [
                    'can_continue' => true,
                    'can_cancel' => false,
                ]
            );
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_INTERVENTION_REQUIRED) {
            self::error(
                'intervention_required',
                'Esta eliminación no puede continuar sola. Recarga la lista. Si el problema persiste, hace falta una revisión.',
                409,
                array_merge(
                    [
                        'can_continue' => false,
                        'can_cancel' => false,
                    ],
                    $redirect_url !== null ? ['redirect_url' => $redirect_url] : []
                )
            );
        }
        if ($state === RetireCanonicalRecordImageResult::STATE_CANCELLED) {
            wp_send_json_success([
                'status' => 'cancelled',
                'resource_id' => $result->image_id(),
                'image_id' => $result->image_id(),
                'record_id' => $result->record_id(),
                'container_id' => $result->container_id(),
                'family_key' => $resolved_family_key,
            ]);
        }
        if ($state !== RetireCanonicalRecordImageResult::STATE_CONFIRMED) {
            self::error('persistence_failed', 'No se pudo eliminar la imagen.', 500);
        }

        $success = [
            'status' => 'confirmed',
            'resource_id' => $result->image_id(),
            'image_id' => $result->image_id(),
            'record_id' => $result->record_id(),
            'container_id' => $result->container_id(),
            'family_key' => $resolved_family_key,
        ];
        if ($redirect_url !== null) {
            $success['redirect_url'] = $redirect_url;
        }
        wp_send_json_success($success);
    }

    private static function require_dependencies(): void {
        if (!class_exists('CanonicalShellWriteAjaxRejection')) {
            require_once __DIR__ . '/CanonicalShellWriteAjaxRejection.php';
        }
        if (!class_exists('CanonicalShellWriteAjaxSupport')) {
            require_once __DIR__ . '/CanonicalShellWriteAjaxSupport.php';
        }
        if (!class_exists('RetireCanonicalRecordImageCommand')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/images/RetireCanonicalRecordImageCommand.php';
        }
        if (!class_exists('RetireCanonicalRecordImageResult')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/images/RetireCanonicalRecordImageResult.php';
        }
        if (!class_exists('RetireCanonicalRecordImageUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/images/RetireCanonicalRecordImageUseCase.php';
        }
        if (!class_exists('AA_Canonical_Shell_Base_Url_Policy')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
        }
    }

    /**
     * @param array<string,mixed> $extra
     * @return never
     */
    private static function error(string $code, string $message, int $status, array $extra = []): void {
        wp_send_json_error(array_merge([
            'code' => $code,
            'message' => $message,
        ], $extra), $status);
    }
}
