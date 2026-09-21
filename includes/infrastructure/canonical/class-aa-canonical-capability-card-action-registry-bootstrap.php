<?php
/** Bootstrap del slot común de acciones de card. */
defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Card_Action_Registry_Bootstrap {
    private static $instance = null;

    public static function bootstrap(): CanonicalCapabilityCardActionRegistry {
        if (self::$instance === null) {
            $capabilities = AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
            if (!$capabilities->has(CanonicalCompletedCardActionProvider::KEY)) {
                throw new \LogicException('[unknown_capability_card_action_provider] Card action provider requires a registered capability.');
            }
            $registry = new CanonicalCapabilityCardActionRegistry();
            $registry->register(new CanonicalCompletedCardActionProvider());
            self::$instance = $registry->freeze();
        }
        return self::$instance;
    }
}
