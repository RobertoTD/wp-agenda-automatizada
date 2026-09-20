<?php
/**
 * Canonical Solution Registry Bootstrap — catálogo sellado de Solutions.
 *
 * contact_dossier: solution lista para el producto; default desactivado por lista.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Solution_Registry_Bootstrap {

    public const CONTACT_DOSSIER = 'contact_dossier';

    /** @var AA_Canonical_Solution_Registry|null */
    private static $instance = null;

    public static function build_registry(): AA_Canonical_Solution_Registry {
        $registry = new AA_Canonical_Solution_Registry();
        $registry->register(new AA_Canonical_Solution_Definition(
            self::CONTACT_DOSSIER,
            1,
            'Expediente',
            true,
            AA_Canonical_Solution_Definition::APPLICATION_SCOPE_CONTAINER,
            ['contact'],
            ['archive'],
            [],
            AA_Canonical_Solution_Definition::ACTIVATION_EXPLICIT,
            AA_Canonical_Solution_Definition::DEACTIVATION_PRESERVE_RESOURCES,
            AA_Canonical_Solution_Definition::LIFECYCLE_LAZY_CREATE_PRESERVE
        ));
        $registry->freeze();

        return $registry;
    }

    public static function bootstrap(): AA_Canonical_Solution_Registry {
        if (self::$instance === null) {
            self::$instance = self::build_registry();
        }
        return self::$instance;
    }

    public static function instance(): AA_Canonical_Solution_Registry {
        if (self::$instance === null) {
            throw new \LogicException('[not_bootstrapped] Solution registry has not been bootstrapped.');
        }
        return self::$instance;
    }

    /** @internal Solo tests. */
    public static function reset_for_tests(): void { self::$instance = null; }
}
