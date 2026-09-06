<?php
/**
 * Canonical Create Record AJAX — creación productiva de registros universales (SB1-5B2).
 *
 * Transporte + composition root de escritura. Sin SQL directo.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCreateRecordAjax {

    public const ACTION = 'aa_create_canonical_record';
    public const NONCE_ACTION = 'aa_create_canonical_record';

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
            self::error('invalid_container_id', 'El contenedor no es válido.', 400);
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
            $command = new CanonicalCreateRecordCommand($container_id, $title, $details);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '[invalid_container_id]') === 0) {
                self::error('invalid_container_id', 'El contenedor no es válido.', 400);
            }
            if (strpos($msg, '[title_too_long]') === 0) {
                self::error(
                    'title_too_long',
                    'El título del registro no puede exceder los '
                    . CanonicalCreateRecordCommand::MAX_TITLE_LENGTH
                    . ' caracteres.',
                    400
                );
            }
            if (strpos($msg, '[invalid_title]') === 0) {
                self::error('invalid_title', 'El título del registro no puede estar vacío.', 400);
            }
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $identity = new CanonicalReadIdentity($resolved_family_key);
        $manifest = new CanonicalShellManifest($identity, $family);

        try {
            $gateway = CanonicalShellWriteAjaxSupport::build_write_gateway();
        } catch (CanonicalShellWriteAjaxRejection $e) {
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        $use_case = new WriteCanonicalShellRecordUseCase($gateway);

        try {
            $result = $use_case->create($manifest, $command);
        } catch (\InvalidArgumentException $e) {
            self::error('persistence_failed', 'No se pudo crear el registro.', 500);
        } catch (\Throwable $e) {
            self::error('persistence_failed', 'No se pudo crear el registro.', 500);
        }

        $state = $result->state();

        if ($state === CanonicalShellMutationResult::STATE_WRITE_ADAPTER_PENDING) {
            self::error('write_adapter_pending', 'La escritura canónica aún no está disponible.', 409);
        }
        if ($state === CanonicalShellMutationResult::STATE_CONTAINER_NOT_FOUND) {
            self::error('container_not_found', 'El contenedor solicitado no existe o no está disponible.', 404);
        }
        if ($state === CanonicalShellMutationResult::STATE_PERSISTENCE_FAILED) {
            self::error('persistence_failed', 'No se pudo crear el registro.', 500);
        }
        if ($state === CanonicalShellMutationResult::STATE_UNCERTAIN) {
            self::error(
                'uncertain',
                'No fue posible confirmar si el registro se creó. Revisa la lista antes de intentarlo nuevamente.',
                409
            );
        }
        if ($state !== CanonicalShellMutationResult::STATE_CONFIRMED) {
            self::error('persistence_failed', 'No se pudo crear el registro.', 500);
        }

        $receipt = $result->receipt();
        if (!$receipt instanceof CanonicalMutationReceipt) {
            self::error('persistence_failed', 'No se pudo crear el registro.', 500);
        }

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

        $containers_page = $return_ctx['containers_page'];
        $redirect_url = AA_Canonical_Shell_Base_Url_Policy::build_records_url(
            $resolved_family_key,
            $container_id,
            null,
            ($containers_page !== null && $containers_page > 1) ? $containers_page : null,
            $return_ctx['lists_scope']
        );

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
        if (!class_exists('CanonicalCreateRecordCommand')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalCreateRecordCommand.php';
        }
        if (!class_exists('CanonicalReadIdentity')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadIdentity.php';
        }
        if (!class_exists('CanonicalShellManifest')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalShellManifest.php';
        }
        if (!class_exists('WriteCanonicalShellRecordUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/WriteCanonicalShellRecordUseCase.php';
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
