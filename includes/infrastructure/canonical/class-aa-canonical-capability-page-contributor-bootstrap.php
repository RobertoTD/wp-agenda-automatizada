<?php
/**
 * Bootstrap del registry de contributors de página de registros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Page_Contributor_Bootstrap {

    /** @var CanonicalCapabilityRecordPageContributorRegistry|null */
    private static $instance = null;

    public static function build_registry(): CanonicalCapabilityRecordPageContributorRegistry {
        global $wpdb;

        $registry = new CanonicalCapabilityRecordPageContributorRegistry();
        $capability_registry = AA_Canonical_Capability_Registry_Bootstrap::bootstrap();

        $registry->register(
            new CanonicalAmountRecordsPageContributor(
                new CanonicalCapabilityConfigRepository($wpdb),
                new CanonicalRecordAmountRepository($wpdb),
                $capability_registry
            )
        );

        $registry->freeze();

        return $registry;
    }

    public static function bootstrap(): CanonicalCapabilityRecordPageContributorRegistry {
        if (self::$instance === null) {
            self::$instance = self::build_registry();
        }

        return self::$instance;
    }

    /**
     * @throws \LogicException
     */
    public static function instance(): CanonicalCapabilityRecordPageContributorRegistry {
        if (self::$instance === null) {
            throw new \LogicException('[not_bootstrapped] Capability page contributor registry has not been bootstrapped.');
        }

        return self::$instance;
    }

    /**
     * @internal Solo tests.
     */
    public static function reset_for_tests(): void {
        self::$instance = null;
    }
}
