<?php
/**
 * Bootstrap del catálogo de presentación administrativa de capabilities.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Presentation_Registry_Bootstrap {

    /** @var CanonicalCapabilityPresentationRegistry|null */
    private static $instance = null;

    public static function build_registry(
        ?AA_Canonical_Capability_Registry $capability_registry = null
    ): CanonicalCapabilityPresentationRegistry {
        $capability_registry = $capability_registry ?: AA_Canonical_Capability_Registry_Bootstrap::bootstrap();
        $registry = new CanonicalCapabilityPresentationRegistry();

        $labels = [
            'amount' => 'Importe',
            'phone' => 'Teléfono',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'images' => 'Imágenes',
            'completed' => 'Completar',
        ];

        foreach ($labels as $capability_key => $label) {
            if (!$capability_registry->has($capability_key)) {
                throw new \LogicException('[unknown_capability_presentation] Presentation metadata requires a registered capability.');
            }
            $registry->register(new CanonicalCapabilityPresentationDefinition($capability_key, $label));
        }

        return $registry->freeze();
    }

    public static function bootstrap(): CanonicalCapabilityPresentationRegistry {
        if (self::$instance === null) {
            self::$instance = self::build_registry();
        }
        return self::$instance;
    }

    /** @internal Solo tests. */
    public static function reset_for_tests(): void {
        self::$instance = null;
    }
}
