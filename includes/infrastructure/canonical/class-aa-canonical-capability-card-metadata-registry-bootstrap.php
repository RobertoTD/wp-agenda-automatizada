<?php
/** Bootstrap del slot común de metadata temporal de card. */
defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Card_Metadata_Registry_Bootstrap {
    private static $instance = null;

    public static function bootstrap(): CanonicalCapabilityCardMetadataRegistry {
        if (self::$instance === null) {
            $capabilities = AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
            if (!$capabilities->has(CanonicalCompletedCardMetadataProvider::KEY)) {
                throw new \LogicException('[unknown_capability_card_metadata_provider] Card metadata provider requires a registered capability.');
            }
            $registry = new CanonicalCapabilityCardMetadataRegistry();
            $registry->register(new CanonicalCompletedCardMetadataProvider());
            self::$instance = $registry->freeze();
        }
        return self::$instance;
    }
}
