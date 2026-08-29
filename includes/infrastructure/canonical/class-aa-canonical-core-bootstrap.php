<?php
/**
 * Canonical Core Bootstrap — Composición e instancia compartida del núcleo canónico.
 *
 * Capa de infraestructura responsable de inicializar el registro canónico y
 * componer las definiciones del producto (familia finance y variante general).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-key.php';
require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-family-definition.php';
require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-variant-definition.php';
require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-registry.php';

final class AA_Canonical_Core_Bootstrap {

    /** @var AA_Canonical_Registry|null */
    private static $instance = null;

    /**
     * Obtiene la instancia compartida del registro canónico compuesta y sellada.
     */
    public static function instance(): AA_Canonical_Registry {
        if (self::$instance === null) {
            self::$instance = self::build_default_registry();
        }

        return self::$instance;
    }

    /**
     * Punto de entrada para inicialización temprana o explícita.
     */
    public static function bootstrap(): AA_Canonical_Registry {
        return self::instance();
    }

    /**
     * Construye y sella el registro canónico base del producto.
     */
    private static function build_default_registry(): AA_Canonical_Registry {
        $registry = new AA_Canonical_Registry();

        $registry->register_family(
            new AA_Family_Definition('finance', 'Finanzas', 'general')
        );

        $registry->register_variant(
            new AA_Variant_Definition('finance', 'general', 'General')
        );

        $registry->freeze();

        return $registry;
    }

    /**
     * @internal Solo para pruebas de aceptación / tests.
     */
    public static function reset_for_tests(): void {
        self::$instance = null;
    }

    /**
     * @internal Solo para pruebas de aceptación / tests.
     */
    public static function set_instance_for_tests(?AA_Canonical_Registry $registry): void {
        self::$instance = $registry;
    }
}
