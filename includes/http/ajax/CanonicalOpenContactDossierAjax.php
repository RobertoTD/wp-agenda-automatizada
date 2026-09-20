<?php
/**
 * Canonical Open Contact Dossier AJAX — abrir o crear expediente Archivo de un contacto.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage HTTP\AJAX
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalOpenContactDossierAjax {

    public const ACTION = 'aa_open_canonical_contact_dossier';
    public const NONCE_ACTION = 'aa_open_canonical_contact_dossier';

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

        if (is_array($family_key_raw) || is_object($family_key_raw)
            || is_array($container_id_raw) || is_object($container_id_raw)
            || is_array($record_id_raw) || is_object($record_id_raw)
        ) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        if (!is_string($family_key_raw) || $family_key_raw === '') {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        $family_key = sanitize_key($family_key_raw);
        if ($family_key !== OpenOrCreateCanonicalContactDossierUseCase::CONTACT_FAMILY) {
            self::error('forbidden', 'Esta operación solo aplica a Contactos.', 403);
        }

        $container_id = CanonicalShellWriteAjaxSupport::parse_positive_int($container_id_raw);
        if ($container_id === null) {
            self::error('invalid_container_id', 'La lista no es válida.', 400);
        }

        $record_id = CanonicalShellWriteAjaxSupport::parse_positive_int($record_id_raw);
        if ($record_id === null) {
            self::error('invalid_record_id', 'El registro no es válido.', 400);
        }

        try {
            CanonicalShellWriteAjaxSupport::authorize_identity($family_key);
        } catch (CanonicalShellWriteAjaxRejection $e) {
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        try {
            $authorized_archive = CanonicalShellWriteAjaxSupport::authorize_identity(
                OpenOrCreateCanonicalContactDossierUseCase::ARCHIVE_FAMILY
            );
        } catch (CanonicalShellWriteAjaxRejection $e) {
            if ($e->error_code() === 'family_disabled'
                || $e->error_code() === 'family_not_provisioned'
            ) {
                self::error(
                    'archive_disabled',
                    'Archivo está desactivado. Actívalo en Ajustes, en “Tipos de registros”.',
                    409
                );
            }
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        $archive_family = $authorized_archive['family'];

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

        try {
            $command = new OpenOrCreateCanonicalContactDossierCommand(
                $container_id,
                $record_id,
                $return_ctx['lists_scope'],
                $return_ctx['page'],
                $return_ctx['containers_page']
            );
        } catch (\InvalidArgumentException $e) {
            self::error('invalid_payload', 'La solicitud contiene campos no válidos.', 400);
        }

        try {
            $composition = CanonicalShellWriteAjaxSupport::build_write_composition();
        } catch (CanonicalShellWriteAjaxRejection $e) {
            self::error($e->error_code(), $e->error_message(), $e->http_status());
        }

        $repository = new CanonicalRelationalRepository();
        $dossier_repo = new CanonicalContactDossierRepository($repository->connection());
        $application_reader = new ReadContactDossierApplicationUseCase(
            $repository,
            new CanonicalContactDossierApplicationRepository($repository->connection()),
            AA_Canonical_Solution_Registry_Bootstrap::bootstrap(),
            AA_Canonical_Core_Bootstrap::bootstrap(),
            new AA_Canonical_Family_Enablement_Store($repository->connection())
        );
        $purge_runs = new CanonicalPurgeRunsRepository($repository->connection());
        $lock = AA_Expediente_Aggregate_Lock::create_default();

        $use_case = new OpenOrCreateCanonicalContactDossierUseCase(
            $repository,
            $dossier_repo,
            $application_reader,
            $composition['gateway'],
            $composition['materializer'],
            $purge_runs,
            $lock,
            $archive_family
        );

        try {
            $result = $use_case->execute($command);
        } catch (\Throwable $e) {
            self::error('persistence_failed', 'No se pudo abrir el expediente.', 500);
        }

        self::respond($result);
    }

    private static function respond(OpenOrCreateCanonicalContactDossierResult $result): void {
        $state = $result->state();

        if ($state === OpenOrCreateCanonicalContactDossierResult::STATE_OPENED
            || $state === OpenOrCreateCanonicalContactDossierResult::STATE_CREATED
        ) {
            $url = $result->redirect_url();
            $archive_id = $result->archive_container_id();
            if (!is_string($url) || $url === '' || $archive_id === null || $archive_id < 1) {
                self::error('persistence_failed', 'No se pudo abrir el expediente.', 500);
            }
            wp_send_json_success([
                'status' => $state,
                'redirect_url' => $url,
                'archive_container_id' => $archive_id,
                'family_key' => OpenOrCreateCanonicalContactDossierUseCase::ARCHIVE_FAMILY,
            ]);
        }

        $map = [
            OpenOrCreateCanonicalContactDossierResult::STATE_CONTAINER_NOT_FOUND => [
                'container_not_found', 'La lista solicitada no existe o no está disponible.', 404,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_RECORD_NOT_FOUND => [
                'record_not_found', 'El contacto no existe o no está disponible.', 404,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_SOLUTION_INACTIVE => [
                'solution_inactive', 'Expediente no está activo en esta lista.', 409,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_ORIGIN_RETIRING => [
                'origin_retiring', 'Este contacto se está eliminando. Inténtalo más tarde.', 409,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_DOSSIER_RETIRING => [
                'dossier_retiring', 'El expediente se está eliminando. No se puede abrir ni crear otro todavía.', 409,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_TARGET_INVALID => [
                'dossier_target_invalid', 'La asociación del expediente no es válida.', 409,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_ARCHIVE_DISABLED => [
                'archive_disabled', 'Archivo está desactivado. Actívalo en Ajustes, en “Tipos de registros”.', 409,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_RESOURCE_BUSY => [
                'resource_busy', 'El recurso está ocupado. Inténtalo de nuevo.', 409,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_UNCERTAIN => [
                'uncertain',
                'No fue posible confirmar si el expediente se creó. Recarga antes de intentarlo de nuevo.',
                409,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_FORBIDDEN => [
                'forbidden', 'No tienes permiso para esta operación.', 403,
            ],
            OpenOrCreateCanonicalContactDossierResult::STATE_PERSISTENCE_FAILED => [
                'persistence_failed', 'No se pudo abrir el expediente.', 500,
            ],
        ];

        if (isset($map[$state])) {
            self::error($map[$state][0], $map[$state][1], $map[$state][2]);
        }

        self::error('persistence_failed', 'No se pudo abrir el expediente.', 500);
    }

    /**
     * @param array<string,mixed> $extra
     */
    private static function error(string $code, string $message, int $status, array $extra = []): void {
        wp_send_json_error(array_merge(['code' => $code, 'message' => $message], $extra), $status);
    }

    private static function require_dependencies(): void {
        if (!class_exists('CanonicalShellWriteAjaxRejection')) {
            require_once __DIR__ . '/CanonicalShellWriteAjaxRejection.php';
        }
        if (!class_exists('CanonicalShellWriteAjaxSupport')) {
            require_once __DIR__ . '/CanonicalShellWriteAjaxSupport.php';
        }
        if (!class_exists('OpenOrCreateCanonicalContactDossierCommand')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierCommand.php';
        }
        if (!class_exists('OpenOrCreateCanonicalContactDossierResult')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierResult.php';
        }
        if (!class_exists('OpenOrCreateCanonicalContactDossierUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/dossier/OpenOrCreateCanonicalContactDossierUseCase.php';
        }
        if (!class_exists('CanonicalContactDossierRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalContactDossierRepository.php';
        }
        if (!class_exists('CanonicalRelationalRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalRelationalRepository.php';
        }
        if (!class_exists('CanonicalPurgeRunsRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalPurgeRunsRepository.php';
        }
        if (!class_exists('AA_Expediente_Aggregate_Lock')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
        }
        if (!class_exists('AA_Canonical_Dossier_Associate_Effect')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/canonical/capabilities/class-aa-canonical-dossier-associate-effect.php';
        }
        if (!class_exists('AA_Canonical_Shell_Base_Url_Policy')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
        }
        if (!class_exists('CanonicalCreateContainerCommand')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalCreateContainerCommand.php';
        }
        if (!class_exists('CanonicalReadIdentity')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadIdentity.php';
        }
        if (!class_exists('CanonicalMutationReceipt')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalMutationReceipt.php';
        }
        if (!class_exists('CanonicalWriteBindingNotFound')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalWriteBindingNotFound.php';
        }
        if (!class_exists('ReadContactDossierApplicationUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/solutions/contact_dossier/CanonicalContactDossierApplicationSnapshot.php';
            require_once dirname(__DIR__, 2) . '/application/canonical/solutions/contact_dossier/CanonicalContactDossierApplicationPolicy.php';
            require_once dirname(__DIR__, 2) . '/application/canonical/solutions/contact_dossier/ReadContactDossierApplicationUseCase.php';
        }
        if (!class_exists('CanonicalContactDossierApplicationRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalContactDossierApplicationRepository.php';
        }
        if (!class_exists('AA_Canonical_Solution_Registry_Bootstrap')) {
            require_once dirname(__DIR__, 2) . '/infrastructure/canonical/class-aa-canonical-solution-registry-bootstrap.php';
        }
    }
}
