<?php
/**
 * Family Definition — Definición inmutable de una familia canónica.
 *
 * Dominio puro: sin WordPress ni dependencias externas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Family_Definition {

    /** @var string */
    private $key;

    /** @var string */
    private $label;

    /** @var string */
    private $default_variant_key;

    public function __construct(string $key, string $label, string $default_variant_key) {
        $this->key = AA_Canonical_Key::assert_valid($key, 'family_key');

        $trimmed_label = trim($label);
        if ($trimmed_label === '') {
            throw new \InvalidArgumentException('[invalid_label] Family label cannot be empty.');
        }
        $this->label = $trimmed_label;

        $this->default_variant_key = AA_Canonical_Key::assert_valid($default_variant_key, 'default_variant_key');
    }

    public function key(): string {
        return $this->key;
    }

    public function label(): string {
        return $this->label;
    }

    public function default_variant_key(): string {
        return $this->default_variant_key;
    }
}
