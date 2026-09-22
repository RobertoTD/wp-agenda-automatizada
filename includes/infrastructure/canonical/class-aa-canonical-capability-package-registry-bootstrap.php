<?php
/** Bootstrap explícito de Capability Package v0. */
defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Package_Registry_Bootstrap {
    /** @var AA_Canonical_Capability_Package_Registry|null */
    private static $instance = null;

    public static function build_registry(): AA_Canonical_Capability_Package_Registry {
        $registry = new AA_Canonical_Capability_Package_Registry();
        $registry->register(new AA_Canonical_Capability_Package_Definition(
            new AA_Canonical_Capability_Definition(
                'completed',
                AA_Canonical_Capability_Definition::SCOPE_RECORD,
                true
            ),
            1,
            'Completar',
            ['action' => true],
            'canonical_record_completion',
            AA_Canonical_Capability_Package_Definition::PERSISTENCE_MANAGED_SCHEMA,
            AA_Canonical_Capability_Package_Definition::DEACTIVATION_PRESERVE,
            AA_Canonical_Capability_Package_Definition::UNINSTALL_PRESERVE,
            AA_Canonical_Capability_Package_Definition::PURGE_EXPLICIT,
            [AA_Canonical_Capability_Package_Definition::CONTRACT_RECORD_READ],
            [AA_Canonical_Capability_Package_Definition::CONTRACT_RECORD_WRITE],
            AA_Canonical_Capability_Package_Definition::WRITE_PERMISSION_CANONICAL_RECORD,
            true,
            ['completed'],
            [
                AA_Canonical_Capability_Package_Definition::PRESENTATION_CARD_ACTION,
                AA_Canonical_Capability_Package_Definition::PRESENTATION_CLIENT_MODULE,
                AA_Canonical_Capability_Package_Definition::PRESENTATION_CARD_METADATA,
                AA_Canonical_Capability_Package_Definition::PRESENTATION_RECORD_VIEWS,
            ]
        ));
        return $registry->freeze();
    }

    public static function bootstrap(): AA_Canonical_Capability_Package_Registry {
        if (self::$instance === null) {
            self::$instance = self::build_registry();
        }
        return self::$instance;
    }

    /** @internal Solo tests. */
    public static function reset_for_tests(): void { self::$instance = null; }
}
