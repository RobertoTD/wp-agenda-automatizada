<?php
/**
 * Canonical Update Container AJAX — edición productiva de contenedores universales (SB1-5B5).
 *
 * Transporte + composition root de escritura. Sin SQL directo.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalUpdateContainerAjax {

    public const ACTION = 'aa_update_canonical_container';
    public const NONCE_ACTION = 'aa_update_canonical_container';

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

        $return_ctx_input = [];
        if (array_key_exists('capability_views', $_POST)) {
            $return_ctx_input['capability_views'] = wp_unslash($_POST['capability_views']);
        }
        if (array_key_exists('lists_scope', $_POST)) {
            $return_ctx_input['lists_scope'] = wp_unslash($_POST['lists_scope']);
        }
        if (array_key_exists('page', $_POST)) {
            $return_ctx_input['page'] = wp_unslash($_POST['page']);
        }
        if (array_key_exists('containers_page', $_POST)) {
            $return_ctx_input['containers_page'] = wp_unslash($_POST['containers_page']);
        }
        if (array_key_exists('return_view', $_POST)) {
            $return_ctx_input['return_view'] = wp_unslash($_POST['return_view']);
        }
        $return_ctx = AA_Canonical_Shell_Base_Url_Policy::parse_mutation_return_context($return_ctx_input);
        if ($return_ctx === null) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $family_key_raw = isset($_POST['family_key']) ? wp_unslash($_POST['family_key']) : null;
        $container_id_raw = isset($_POST['container_id']) ? wp_unslash($_POST['container_id']) : null;
        $title_raw = isset($_POST['title']) ? wp_unslash($_POST['title']) : null;
        $details_raw = array_key_exists('details', $_POST) ? wp_unslash($_POST['details']) : null;

        if (is_array($family_key_raw) || is_object($family_key_raw)
            || is_array($container_id_raw) || is_object($container_id_raw)
            || is_array($title_raw) || is_object($title_raw)
            || is_array($details_raw) || is_object($details_raw)
        ) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        if (!is_string($family_key_raw) || $family_key_raw === ''
            || !is_string($title_raw)
        ) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        if ($details_raw !== null && !is_string($details_raw)) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $container_id = CanonicalShellWriteAjaxSupport::parse_positive_int($container_id_raw);
        if ($container_id === null) {
            self::error('invalid_container_id', 'La lista no es válida.', 400);
        }

        $family_key = sanitize_key($family_key_raw);
        $title = sanitize_text_field($title_raw);
        $details = ($details_raw === null)
            ? null
            : sanitize_textarea_field($details_raw);

        try {
            $authorized = CanonicalShellWriteAjaxSupport::authorize_identity($family_key);
        } catch (CanonicalShellWriteAjaxRejection $e) {
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        $family = $authorized['family'];
        $resolved_family_key = $family->key();

        try {
            $command = new CanonicalUpdateContainerCommand($container_id, $title, $details);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '[invalid_container_id]') === 0) {
                self::error('invalid_container_id', 'La lista no es válida.', 400);
            }
            if (strpos($msg, '[title_too_long]') === 0) {
                self::error(
                    'title_too_long',
                    'El nombre de la lista no puede exceder los '
                    . CanonicalUpdateContainerCommand::MAX_TITLE_LENGTH
                    . ' caracteres.',
                    400
                );
            }
            if (strpos($msg, '[invalid_title]') === 0) {
                self::error('invalid_title', 'El nombre de la lista no puede estar vacío.', 400);
            }
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        try {
            $selection = CanonicalShellWriteAjaxSupport::parse_capability_selection_from_source($_POST);
            $solution_selection = CanonicalShellWriteAjaxSupport::parse_solution_selection_from_source($_POST);
        } catch (CanonicalShellWriteAjaxRejection $e) {
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        $identity = new CanonicalReadIdentity($resolved_family_key);
        $manifest = new CanonicalShellManifest($identity, $family);

        try {
            $composition = CanonicalShellWriteAjaxSupport::build_write_composition();
        } catch (CanonicalShellWriteAjaxRejection $e) {
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        $use_case = new WriteCanonicalShellContainerUseCase(
            $composition['gateway'],
            $composition['materializer'],
            $composition['selection_preparer']
        );

        try {
            $solution_effects = CanonicalShellWriteAjaxSupport::contact_dossier_solution_effects(
                $resolved_family_key,
                $solution_selection
            );
            $result = $use_case->update($manifest, $command, $selection, $solution_effects);
        } catch (\InvalidArgumentException $e) {
            self::error('persistence_failed', 'No se pudo actualizar la lista.', 500);
        } catch (\Throwable $e) {
            $mapped = CanonicalShellWriteAjaxSupport::map_capability_write_exception($e);
            if ($mapped !== null) {
                self::error($mapped->error_code(), $mapped->error_message(), $mapped->http_status());
            }
            self::error('persistence_failed', 'No se pudo actualizar la lista.', 500);
        }

        $state = $result->state();

        if ($state === CanonicalShellMutationResult::STATE_WRITE_ADAPTER_PENDING) {
            self::error('write_adapter_pending', 'La escritura canónica aún no está disponible.', 409);
        }
        if ($state === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND) {
            self::error('container_not_found', 'La lista solicitada no existe o no está disponible.', 404);
        }
        if ($state === CanonicalShellMutationResult::STATE_PERSISTENCE_FAILED) {
            self::error('persistence_failed', 'No se pudo actualizar la lista.', 500);
        }
        if ($state === CanonicalShellMutationResult::STATE_UNCERTAIN) {
            self::error(
                'uncertain',
                'No fue posible confirmar si los cambios se guardaron. Revisa el listado antes de intentarlo nuevamente.',
                409
            );
        }
        if ($state !== CanonicalShellMutationResult::STATE_CONFIRMED) {
            self::error('persistence_failed', 'No se pudo actualizar la lista.', 500);
        }

        $receipt = $result->receipt();
        if (!$receipt instanceof CanonicalMutationReceipt) {
            self::error('persistence_failed', 'No se pudo actualizar la lista.', 500);
        }



        if ($return_ctx['return_view'] === 'records') {
            $records_page = $return_ctx['page'];
            $containers_page = $return_ctx['containers_page'];
            $redirect_url = AA_Canonical_Shell_Base_Url_Policy::build_records_url(
                $resolved_family_key,
                $command->container_id(),
                ($records_page !== null && $records_page > 1) ? $records_page : null,
                ($containers_page !== null && $containers_page > 1) ? $containers_page : null,
                $return_ctx['lists_scope'], 'simple', $return_ctx['capability_views']
            );
        } else {
            $redirect_page = $return_ctx['page'];
            $redirect_url = AA_Canonical_Shell_Base_Url_Policy::build_containers_return_url(
                $return_ctx['lists_scope'],
                $resolved_family_key,
                ($redirect_page !== null && $redirect_page > 1) ? $redirect_page : null
            );
        }

        wp_send_json_success([
            'status' => 'confirmed',
            'resource_id' => $receipt->resource_id(),
            'container_id' => $receipt->container_id(),
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
        if (!class_exists('CanonicalUpdateContainerCommand')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalUpdateContainerCommand.php';
        }
        if (!class_exists('CanonicalReadIdentity')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadIdentity.php';
        }
        if (!class_exists('CanonicalShellManifest')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalShellManifest.php';
        }
        if (!class_exists('WriteCanonicalShellContainerUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/WriteCanonicalShellContainerUseCase.php';
        }
        if (!class_exists('CanonicalShellMutationResult')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalShellMutationResult.php';
        }
        if (!class_exists('CanonicalMutationReceipt')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalMutationReceipt.php';
        }
        if (!class_exists('AA_Canonical_Shell_Base_Url_Policy')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
        }
    }

    /**
     * @return never
     */
    private static function error(string $code, string $message, int $status): void {
        wp_send_json_error([
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
