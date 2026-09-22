<?php
/**
 * Canonical Capability Registry Bootstrap — Catálogo sellado de capacidades.
 *
 * `amount` queda ready tras A1b (lectura/UI + escritura).
 * `images` ready tras Paso 5 (flip de disponibilidad + DEFAULTS_VERSION=3).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Capability_Package_Registry_Bootstrap')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-capability-package-definition.php';
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-capability-package-registry.php';
    require_once __DIR__ . '/class-aa-canonical-capability-package-registry-bootstrap.php';
}

final class AA_Canonical_Capability_Registry_Bootstrap {

    /** @var AA_Canonical_Capability_Registry|null */
    private static $instance = null;

    public static function build_registry(): AA_Canonical_Capability_Registry {
        $registry = new AA_Canonical_Capability_Registry();

        $registry->register(
            new AA_Canonical_Capability_Definition(
                'amount',
                AA_Canonical_Capability_Definition::SCOPE_RECORD,
                true
            )
        );

        $registry->register(
            new AA_Canonical_Capability_Definition(
                'phone',
                AA_Canonical_Capability_Definition::SCOPE_RECORD,
                true
            )
        );

        $registry->register(
            new AA_Canonical_Capability_Definition(
                'whatsapp',
                AA_Canonical_Capability_Definition::SCOPE_RECORD,
                true
            )
        );

        $registry->register(
            new AA_Canonical_Capability_Definition(
                'email',
                AA_Canonical_Capability_Definition::SCOPE_RECORD,
                true
            )
        );

        $registry->register(
            new AA_Canonical_Capability_Definition(
                'images',
                AA_Canonical_Capability_Definition::SCOPE_RECORD,
                true
            )
        );
        foreach (AA_Canonical_Capability_Package_Registry_Bootstrap::bootstrap()->all() as $package) {
            $registry->register($package->capability());
        }

        $registry->freeze();

        return $registry;
    }

    public static function bootstrap(): AA_Canonical_Capability_Registry {
        if (self::$instance === null) {
            self::$instance = self::build_registry();
        }

        return self::$instance;
    }

    /**
     * @throws \LogicException
     */
    public static function instance(): AA_Canonical_Capability_Registry {
        if (self::$instance === null) {
            throw new \LogicException('[not_bootstrapped] Capability registry has not been bootstrapped.');
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
