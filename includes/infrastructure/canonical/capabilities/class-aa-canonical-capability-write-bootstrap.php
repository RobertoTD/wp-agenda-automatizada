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
     *   handlers: CanonicalCapabilityWriteHandlerRegistry
     * }
     */
    public static function build_stack($wpdb = null): array {
        self::require_dependencies();

        if ($wpdb === null) {
            global $wpdb;
        }

        $capability_registry = AA_Canonical_Capability_Registry_Bootstrap::instance();
        $config_repository = new CanonicalCapabilityConfigRepository($wpdb);
        $amount_repository = new CanonicalRecordAmountRepository($wpdb);

        $handlers = new CanonicalCapabilityWriteHandlerRegistry();
        $handlers->register(new AA_Canonical_Amount_Write_Handler(
            $capability_registry,
            $config_repository,
            $amount_repository
        ));
        $handlers->freeze();

        return [
            'preparer' => new CanonicalCapabilityRecordWritePreparer($handlers),
            'materializer' => new AA_Canonical_Capability_Defaults_Materializer(
                $capability_registry,
                $config_repository
            ),
            'handlers' => $handlers,
        ];
    }

    private static function require_dependencies(): void {
        $app = dirname(__DIR__, 3) . '/application/canonical/capabilities';
        $infra = __DIR__;
        $repos = dirname(__DIR__, 3) . '/repositories';

        $files = [
            $app . '/CanonicalCapabilityWriteBag.php',
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
            $repos . '/CanonicalRecordAmountRepository.php',
            $infra . '/class-aa-canonical-amount-set-effect.php',
            $infra . '/class-aa-canonical-amount-clear-effect.php',
            $infra . '/class-aa-canonical-amount-write-handler.php',
            $infra . '/class-aa-canonical-capability-defaults-materializer.php',
            $infra . '/class-aa-canonical-materialize-family-defaults-effect.php',
        ];

        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }
            require_once $file;
        }
    }
}
