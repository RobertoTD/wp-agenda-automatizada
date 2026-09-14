<?php
/**
 * Canonical Delete Container AJAX — retiro productivo de una lista (IMG-5 inc. 4).
 *
 * Transporte + composition root. Delega en RetireCanonicalContainerUseCase.
 * Sin SQL directo. Sin mandate_id ni batch_seq en JSON.
 * WriteCanonicalShellContainerUseCase::delete permanece como red de seguridad
 * (RESTRICT + purge guard) y no es el camino productivo.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalDeleteContainerAjax {

    public const ACTION = 'aa_delete_canonical_container';
    public const NONCE_ACTION = 'aa_delete_canonical_container';

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
        $retire_action_raw = array_key_exists('retire_action', $_POST) ? wp_unslash($_POST['retire_action']) : '';

        if (is_array($family_key_raw) || is_object($family_key_raw)
            || is_array($container_id_raw) || is_object($container_id_raw)
            || is_array($retire_action_raw) || is_object($retire_action_raw)
        ) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        if (!is_string($family_key_raw) || $family_key_raw === '') {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $container_id = CanonicalShellWriteAjaxSupport::parse_positive_int($container_id_raw);
        if ($container_id === null) {
            self::error('invalid_container_id', 'La lista no es válida.', 400);
        }

        $intent = RetireCanonicalContainerCommand::INTENT_RETIRE;
        if (is_string($retire_action_raw) && $retire_action_raw !== '') {
            if ($retire_action_raw !== RetireCanonicalContainerCommand::INTENT_CANCEL) {
                self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
            }
            $intent = RetireCanonicalContainerCommand::INTENT_CANCEL;
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
            $command = new RetireCanonicalContainerCommand(
                $resolved_family_key,
                $container_id,
                $intent
            );
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '[invalid_container_id]') === 0) {
                self::error('invalid_container_id', 'La lista no es válida.', 400);
            }
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $use_case = new RetireCanonicalContainerUseCase();

        try {
            $result = $use_case->execute($command);
        } catch (\InvalidArgumentException $e) {
            self::error('persistence_failed', 'No se pudo eliminar la lista.', 500);
        } catch (\Throwable $e) {
            self::error('persistence_failed', 'No se pudo eliminar la lista.', 500);
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
        $redirect_page = $return_ctx['page'];
        $redirect_url = AA_Canonical_Shell_Base_Url_Policy::build_containers_return_url(
            $return_ctx['lists_scope'],
            $resolved_family_key,
            ($redirect_page !== null && $redirect_page > 1) ? $redirect_page : null
        );

        if ($state === RetireCanonicalContainerResult::STATE_CONTAINER_NOT_FOUND) {
            self::error('container_not_found', 'La lista solicitada no existe o no está disponible.', 404);
        }
        if ($state === RetireCanonicalContainerResult::STATE_FORBIDDEN) {
            self::error('forbidden', 'No tienes permiso para esta operación.', 403);
        }
        if ($state === RetireCanonicalContainerResult::STATE_PERSISTENCE_FAILED) {
            self::error('persistence_failed', 'No se pudo eliminar la lista.', 500);
        }
        if ($state === RetireCanonicalContainerResult::STATE_RESOURCE_BUSY) {
            self::error('resource_busy', 'El recurso está ocupado. Inténtalo de nuevo.', 409);
        }
        if ($state === RetireCanonicalContainerResult::STATE_SCOPE_OVERLAP) {
            self::error('scope_overlap', 'Hay otra eliminación en curso sobre este recurso.', 409);
        }
        if ($state === RetireCanonicalContainerResult::STATE_UNCERTAIN) {
            self::error(
                'uncertain',
                'No fue posible confirmar si la lista se eliminó. Recarga el listado para verificarlo antes de intentarlo nuevamente.',
                409,
                ['redirect_url' => $redirect_url]
            );
        }
        if ($state === RetireCanonicalContainerResult::STATE_INCOMPLETE) {
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
        if ($state === RetireCanonicalContainerResult::STATE_CONFLICT) {
            self::error(
                'conflict',
                'No se pudo preparar la eliminación. Puedes cancelarla para desbloquear la lista, o reintentar.',
                409,
                [
                    'can_continue' => true,
                    'can_cancel' => $result->can_cancel(),
                ]
            );
        }
        if ($state === RetireCanonicalContainerResult::STATE_CANCEL_REJECTED) {
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
        if ($state === RetireCanonicalContainerResult::STATE_INTERVENTION_REQUIRED) {
            self::error(
                'intervention_required',
                'Esta eliminación no puede continuar sola. Recarga el listado. Si el problema persiste, hace falta una revisión.',
                409,
                [
                    'can_continue' => false,
                    'can_cancel' => false,
                    'redirect_url' => $redirect_url,
                ]
            );
        }
        if ($state === RetireCanonicalContainerResult::STATE_CANCELLED) {
            wp_send_json_success([
                'status' => 'cancelled',
                'resource_id' => $result->container_id(),
                'container_id' => $result->container_id(),
                'family_key' => $resolved_family_key,
            ]);
        }
        if ($state !== RetireCanonicalContainerResult::STATE_CONFIRMED) {
            self::error('persistence_failed', 'No se pudo eliminar la lista.', 500);
        }

        wp_send_json_success([
            'status' => 'confirmed',
            'resource_id' => $result->container_id(),
            'container_id' => $result->container_id(),
            'family_key' => $resolved_family_key,
            'redirect_url' => $redirect_url,
        ]);
    }

    private static function require_dependencies(): void {
        if (!class_exists('CanonicalShellWriteAjaxRejection')) {
            require_once __DIR__ . '/CanonicalShellWriteAjaxRejection.php';
        }
        if (!class_exists('CanonicalShellWriteAjaxSupport')) {
            require_once __DIR__ . '/CanonicalShellWriteAjaxSupport.php';
        }
        if (!class_exists('RetireCanonicalContainerCommand')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/images/RetireCanonicalContainerCommand.php';
        }
        if (!class_exists('RetireCanonicalContainerResult')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/images/RetireCanonicalContainerResult.php';
        }
        if (!class_exists('RetireCanonicalContainerUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/images/RetireCanonicalContainerUseCase.php';
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
