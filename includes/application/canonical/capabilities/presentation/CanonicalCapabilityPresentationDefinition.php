<?php
/**
 * Metadata mínima de presentación administrativa de una capability.
 *
 * No contiene renderers, assets ni comportamiento. Esos contratos se añaden
 * por etapas sobre slots explícitos del Shell.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities\Presentation
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityPresentationDefinition {

    /** @var string */
    private $capability_key;

    /** @var string */
    private $label;

    /** @throws \InvalidArgumentException */
    public function __construct(string $capability_key, string $label) {
        $this->capability_key = AA_Canonical_Key::assert_valid($capability_key, 'capability_key');
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('[invalid_capability_presentation_label] Label must not be empty.');
        }
        if (strlen($label) > 100) {
            throw new \InvalidArgumentException('[invalid_capability_presentation_label] Label is too long.');
        }

        $this->label = $label;
    }

    public function capability_key(): string {
        return $this->capability_key;
    }

    public function label(): string {
        return $this->label;
    }
}
