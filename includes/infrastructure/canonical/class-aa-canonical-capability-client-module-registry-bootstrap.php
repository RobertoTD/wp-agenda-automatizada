<?php
/** Bootstrap de módulos cliente declarados por capabilities. */
defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Client_Module_Registry_Bootstrap {
    private static $instance = null;

    public static function bootstrap(): CanonicalCapabilityClientModuleRegistry {
        if (self::$instance === null) {
            $capabilities = AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
            if (!$capabilities->has('completed')) {
                throw new \LogicException('[unknown_capability_client_module] Client module requires a registered capability.');
            }
            $registry = new CanonicalCapabilityClientModuleRegistry();
            $registry->register(new CanonicalCapabilityClientModule(
                'completed',
                'includes/admin/ui/modules/canonical_shell/capabilities/canonical-shell-completed-action.js',
                CanonicalSetRecordCompletionAjax::ACTION,
                CanonicalSetRecordCompletionAjax::NONCE_ACTION
            ));
            self::$instance = $registry->freeze();
        }
        return self::$instance;
    }
}
