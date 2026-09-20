<?php
/**
 * Canonical Core Bootstrap — Composición e instancia compartida del núcleo canónico.
 *
 * Capa de infraestructura responsable de inicializar el registro canónico y
 * componer las definiciones del producto (familias productivas).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-key.php';
require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-family-definition.php';
require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-registry.php';

final class AA_Canonical_Core_Bootstrap {

    /** @var AA_Canonical_Registry|null */
    private static $instance = null;

    /**
     * Construye y devuelve un registro canónico nuevo, completo y sellado.
     */
    public static function build_registry(): AA_Canonical_Registry {
        $registry = new AA_Canonical_Registry();

        $registry->register_family(
            new AA_Canonical_Family_Definition('finance', 'Finanzas', 'currency')
        );
        $registry->register_family(
            new AA_Canonical_Family_Definition('archive', 'Archivo', 'folder')
        );
        $registry->register_family(
            new AA_Canonical_Family_Definition('catalog', 'Catálogos', 'grid')
        );
        $registry->register_family(
            new AA_Canonical_Family_Definition('contact', 'Contactos', 'contact_card')
        );
        $registry->register_family(
            new AA_Canonical_Family_Definition('action', 'Acciones', 'checklist')
        );

        $registry->freeze();

        return $registry;
    }

    /**
     * Publica de forma idempotente la instancia compartida del registro canónico.
     */
    public static function bootstrap(): AA_Canonical_Registry {
        if (self::$instance === null) {
            self::$instance = self::build_registry();
        }

        return self::$instance;
    }

    /**
     * Devuelve la instancia compartida publicada.
     *
     * @throws \LogicException Si se llama antes de ejecutar bootstrap().
     */
    public static function instance(): AA_Canonical_Registry {
        if (self::$instance === null) {
            throw new \LogicException('[not_bootstrapped] Canonical core has not been bootstrapped.');
        }

        return self::$instance;
    }
}
