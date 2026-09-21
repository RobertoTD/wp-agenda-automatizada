<?php
defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Records_View_Bootstrap {
    /** @var CanonicalCapabilityRecordsViewRegistry|null */
    private static $instance = null;
    public static function bootstrap(): CanonicalCapabilityRecordsViewRegistry {
        if (self::$instance === null) {
            global $wpdb;
            self::$instance = new CanonicalCapabilityRecordsViewRegistry();
            self::$instance->register(new CanonicalCompletedRecordsViewProvider(
                new CanonicalCapabilityConfigRepository($wpdb),
                AA_Canonical_Capability_Registry_Bootstrap::bootstrap()
            ));
        }
        return self::$instance;
    }
}
