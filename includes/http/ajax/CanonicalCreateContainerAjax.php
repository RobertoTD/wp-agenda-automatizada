<?php
/**
 * Canonical Create Container AJAX — creación productiva de contenedores universales (SB1-5B1).
 *
 * Transporte + composition root de escritura. Sin SQL directo.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCreateContainerAjax {

    public const ACTION = 'aa_create_canonical_container';
    public const NONCE_ACTION = 'aa_create_canonical_container';

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
        $title_raw = isset($_POST['title']) ? wp_unslash($_POST['title']) : null;
        $details_raw = array_key_exists('details', $_POST) ? wp_unslash($_POST['details']) : null;

        if (is_array($family_key_raw) || is_object($family_key_raw)
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
            $command = new CanonicalCreateContainerCommand($title, $details);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '[title_too_long]') === 0) {
                self::error(
                    'title_too_long',
                    'El nombre de la lista no puede exceder los '
                    . CanonicalCreateContainerCommand::MAX_TITLE_LENGTH
                    . ' caracteres.',
                    400
                );
            }
            if (strpos($msg, '[invalid_title]') === 0) {
                self::error('invalid_title', 'El nombre de la lista no puede estar vacío.', 400);
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

        $use_case = new WriteCanonicalShellContainerUseCase($gateway);

        try {
            $result = $use_case->create($manifest, $command);
        } catch (\InvalidArgumentException $e) {
            self::error('persistence_failed', 'No se pudo crear la lista.', 500);
        } catch (\Throwable $e) {
            self::error('persistence_failed', 'No se pudo crear la lista.', 500);
        }

        $state = $result->state();

        if ($state === CanonicalShellMutationResult::STATE_WRITE_ADAPTER_PENDING) {
            self::error('write_adapter_pending', 'La escritura canónica aún no está disponible.', 409);
        }
        if ($state === CanonicalShellMutationResult::STATE_PERSISTENCE_FAILED) {
            self::error('persistence_failed', 'No se pudo crear la lista.', 500);
        }
        if ($state === CanonicalShellMutationResult::STATE_UNCERTAIN) {
            self::error(
                'uncertain',
                'No fue posible confirmar si la lista se creó. Revisa el listado antes de intentarlo nuevamente.',
                409
            );
        }
        if ($state !== CanonicalShellMutationResult::STATE_CONFIRMED) {
            self::error('persistence_failed', 'No se pudo crear la lista.', 500);
        }

        $receipt = $result->receipt();
        if (!$receipt instanceof CanonicalMutationReceipt) {
            self::error('persistence_failed', 'No se pudo crear la lista.', 500);
        }

        $redirect_url = AA_Canonical_Shell_Base_Url_Policy::build_url(
            $resolved_family_key,
            null
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
        if (!class_exists('CanonicalCreateContainerCommand')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalCreateContainerCommand.php';
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
