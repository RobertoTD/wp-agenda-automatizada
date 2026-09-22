<?php
defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-capability-definition.php';
require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-capability-registry.php';
require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/CanonicalCapabilityRecordsViewRegistry.php';
require_once dirname(__DIR__, 2) . '/application/canonical/capabilities/completed/CanonicalCompletedRecordsViewProvider.php';
require_once dirname(__DIR__, 2) . '/repositories/CanonicalCapabilityConfigRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/CanonicalRecordsQueryCompiler.php';
require_once dirname(__DIR__, 2) . '/repositories/capabilities/CanonicalCompletedCriterionCompiler.php';
require_once __DIR__ . '/class-aa-canonical-capability-registry-bootstrap.php';

final class AA_Canonical_Capability_Records_View_Bootstrap {
    /** @var CanonicalCapabilityRecordsViewRegistry|null */
    private static $instance = null;
    public static function query_compiler(): CanonicalRecordsQueryCompiler {
        return (new CanonicalRecordsQueryCompiler())
            ->register('completed', new CanonicalCompletedCriterionCompiler())
            ->freeze();
    }

    public static function bootstrap(): CanonicalCapabilityRecordsViewRegistry {
        if (self::$instance === null) {
            global $wpdb;
            self::$instance = new CanonicalCapabilityRecordsViewRegistry();
            self::$instance->register(new CanonicalCompletedRecordsViewProvider(
                new CanonicalCapabilityConfigRepository($wpdb),
                AA_Canonical_Capability_Registry_Bootstrap::bootstrap()
            ));
        }
        return self::$instance->freeze();
    }
}
