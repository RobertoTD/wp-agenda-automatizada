<?php
/**
 * Bootstrap del stack de escritura de capacidades (misma conexión que el write repo).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Write_Bootstrap {

    /**
     * @param object|null $wpdb Misma conexión que CanonicalRelationalRepository de la TX.
     * @return array{
     *   preparer: CanonicalCapabilityRecordWritePreparer,
     *   materializer: AA_Canonical_Capability_Defaults_Materializer,
     *   selection_preparer: CanonicalContainerCapabilitySelectionPreparer,
     *   handlers: CanonicalCapabilityWriteHandlerRegistry
     * }
     */
    public static function build_stack($wpdb = null): array {
        self::require_dependencies();

        if ($wpdb === null) {
            global $wpdb;
        }

        AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
        $capability_registry = AA_Canonical_Capability_Registry_Bootstrap::instance();
        $config_repository = new CanonicalCapabilityConfigRepository($wpdb);
        $amount_repository = new CanonicalRecordAmountRepository($wpdb);
        $phone_repository = new CanonicalRecordPhoneRepository($wpdb);
        $whatsapp_repository = new CanonicalRecordWhatsappRepository($wpdb);
        $email_repository = new CanonicalRecordEmailRepository($wpdb);

        $handlers = new CanonicalCapabilityWriteHandlerRegistry();
        $handlers->register(new AA_Canonical_Amount_Write_Handler(
            $capability_registry,
            $config_repository,
            $amount_repository
        ));
        $handlers->register(new AA_Canonical_Phone_Write_Handler(
            $capability_registry,
            $config_repository,
            $phone_repository
        ));
        $handlers->register(new AA_Canonical_Whatsapp_Write_Handler(
            $capability_registry,
            $config_repository,
            $whatsapp_repository
        ));
        $handlers->register(new AA_Canonical_Email_Write_Handler(
            $capability_registry,
            $config_repository,
            $email_repository
        ));
        $handlers->freeze();

        return [
            'preparer' => new CanonicalCapabilityRecordWritePreparer($handlers),
            'materializer' => new AA_Canonical_Capability_Defaults_Materializer(
                $capability_registry,
                $config_repository
            ),
            'selection_preparer' => new CanonicalContainerCapabilitySelectionPreparer(
                $config_repository,
                $capability_registry
            ),
            'handlers' => $handlers,
        ];
    }

    private static function require_dependencies(): void {
        $app = dirname(__DIR__, 3) . '/application/canonical/capabilities';
        $infra = __DIR__;
        $repos = dirname(__DIR__, 3) . '/repositories';

        $domain = dirname(__DIR__, 3) . '/domain/canonical';
        $canonical_infra = dirname(__DIR__);

        $files = [
            $domain . '/class-aa-canonical-capability-definition.php',
            $domain . '/class-aa-canonical-capability-registry.php',
            $canonical_infra . '/class-aa-canonical-capability-registry-bootstrap.php',
            $app . '/CanonicalCapabilityWriteBag.php',
            $app . '/CanonicalContainerCapabilitySelection.php',
            $app . '/CanonicalContainerCapabilitySelectionPreparer.php',
            $app . '/CanonicalRecordMutationContext.php',
            $app . '/CanonicalContainerMutationContext.php',
            $app . '/CanonicalRecordCapabilityEffect.php',
            $app . '/CanonicalContainerCapabilityEffect.php',
            $app . '/CanonicalCapabilityWriteHandler.php',
            $app . '/CanonicalCapabilityWriteHandlerRegistry.php',
            $app . '/CanonicalCapabilityRecordWritePreparer.php',
            $app . '/CanonicalCapabilityWriteRejected.php',
            $app . '/CanonicalCapabilityInactive.php',
            $app . '/AA_Canonical_Amount_Normalizer.php',
            $app . '/AA_Canonical_Phone_Normalizer.php',
            $app . '/AA_Canonical_Email_Normalizer.php',
            $repos . '/CanonicalCapabilityConfigRepository.php',
            $repos . '/CanonicalRecordAmountRepository.php',
            $repos . '/CanonicalRecordPhoneRepository.php',
            $repos . '/CanonicalRecordWhatsappRepository.php',
            $repos . '/CanonicalRecordEmailRepository.php',
            $infra . '/class-aa-canonical-amount-set-effect.php',
            $infra . '/class-aa-canonical-amount-clear-effect.php',
            $infra . '/class-aa-canonical-amount-write-handler.php',
            $infra . '/class-aa-canonical-phone-set-effect.php',
            $infra . '/class-aa-canonical-phone-clear-effect.php',
            $infra . '/class-aa-canonical-phone-write-handler.php',
            $infra . '/class-aa-canonical-whatsapp-set-effect.php',
            $infra . '/class-aa-canonical-whatsapp-clear-effect.php',
            $infra . '/class-aa-canonical-whatsapp-write-handler.php',
            $infra . '/class-aa-canonical-email-set-effect.php',
            $infra . '/class-aa-canonical-email-clear-effect.php',
            $infra . '/class-aa-canonical-email-write-handler.php',
            $infra . '/class-aa-canonical-capability-defaults-materializer.php',
            $infra . '/class-aa-canonical-materialize-family-defaults-effect.php',
            $infra . '/class-aa-canonical-apply-container-capability-selection-effect.php',
        ];

        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            require_once $file;
        }
    }
}
