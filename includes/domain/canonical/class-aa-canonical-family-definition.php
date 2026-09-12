<?php
/**
 * Canonical Family Definition — Definición inmutable de una familia canónica.
 *
 * Dominio puro: sin WordPress ni dependencias externas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Family_Definition {

    /** @var string */
    private $key;

    /** @var string */
    private $label;

    /** @var string */
    private $icon_key;

    /**
     * @param string $icon_key Clave estable de icono de presentación; vacía = sin icono.
     */
    public function __construct(string $key, string $label, string $icon_key = '') {
        $this->key = AA_Canonical_Key::assert_valid($key, 'family_key');

        $trimmed_label = trim($label);
        if ($trimmed_label === '') {
            throw new \InvalidArgumentException('[invalid_label] Family label cannot be empty.');
        }
        $this->label = $trimmed_label;

        $trimmed_icon = trim($icon_key);
        if ($trimmed_icon !== '') {
            $this->icon_key = AA_Canonical_Key::assert_valid($trimmed_icon, 'icon_key');
        } else {
            $this->icon_key = '';
        }
    }

    public function key(): string {
        return $this->key;
    }

    public function label(): string {
        return $this->label;
    }

    public function icon_key(): string {
        return $this->icon_key;
    }
}
