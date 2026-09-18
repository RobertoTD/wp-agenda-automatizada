<?php
/**
 * Bootstrap del registry de contributors de página de registros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalPhoneRecordsPageContributor')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/phone/CanonicalPhoneRecordsPageContributor.php';
}
if (!class_exists('CanonicalRecordPhoneRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalRecordPhoneRepository.php';
}
if (!class_exists('CanonicalWhatsappRecordsPageContributor')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/whatsapp/CanonicalWhatsappRecordsPageContributor.php';
}
if (!class_exists('CanonicalRecordWhatsappRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalRecordWhatsappRepository.php';
}
if (!class_exists('CanonicalEmailRecordsPageContributor')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/email/CanonicalEmailRecordsPageContributor.php';
}
if (!class_exists('CanonicalRecordEmailRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalRecordEmailRepository.php';
}
if (!class_exists('CanonicalImagesRecordsPageContributor')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/images/CanonicalImagesRecordsPageContributor.php';
}
if (!class_exists('CanonicalRecordImagesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/CanonicalRecordImagesRepository.php';
}
if (!class_exists('CanonicalRecordImagePublicDto')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/images/CanonicalRecordImagePublicDto.php';
}

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

        $registry->register(
            new CanonicalPhoneRecordsPageContributor(
                new CanonicalCapabilityConfigRepository($wpdb),
                new CanonicalRecordPhoneRepository($wpdb),
                $capability_registry
            )
        );

        $registry->register(
            new CanonicalWhatsappRecordsPageContributor(
                new CanonicalCapabilityConfigRepository($wpdb),
                new CanonicalRecordWhatsappRepository($wpdb),
                $capability_registry
            )
        );

        $registry->register(
            new CanonicalEmailRecordsPageContributor(
                new CanonicalCapabilityConfigRepository($wpdb),
                new CanonicalRecordEmailRepository($wpdb),
                $capability_registry
            )
        );

        $registry->register(
            new CanonicalImagesRecordsPageContributor(
                new CanonicalCapabilityConfigRepository($wpdb),
                new CanonicalRecordImagesRepository($wpdb),
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
