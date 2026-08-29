<?php
/**
 * Variant Definition — Definición inmutable de una variante canónica.
 *
 * Dominio puro: sin WordPress ni dependencias externas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Variant_Definition {

    /** @var string */
    private $family_key;

    /** @var string */
    private $key;

    /** @var string */
    private $label;

    public function __construct(string $family_key, string $key, string $label) {
        $this->family_key = AA_Canonical_Key::assert_valid($family_key, 'family_key');
        $this->key = AA_Canonical_Key::assert_valid($key, 'variant_key');

        $trimmed_label = trim($label);
        if ($trimmed_label === '') {
            throw new \InvalidArgumentException('[invalid_label] Variant label cannot be empty.');
        }
        $this->label = $trimmed_label;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function key(): string {
        return $this->key;
    }

    public function label(): string {
        return $this->label;
    }

    /**
     * Representación derivada "family.variant".
     */
    public function qualified_key(): string {
        return AA_Canonical_Key::qualified($this->family_key, $this->key);
    }
}
