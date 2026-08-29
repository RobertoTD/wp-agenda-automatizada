<?php
/**
 * Canonical Key — validación y formato de claves canónicas.
 *
 * Dominio puro: sin WordPress ni dependencias externas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Key {

    private const PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    /**
     * Comprueba si una clave cumple con la convención canónica.
     */
    public static function is_valid(string $key): bool {
        return (bool) preg_match(self::PATTERN, $key);
    }

    /**
     * Valida una clave y la devuelve, o lanza una excepción con tag estable.
     *
     * @throws \InvalidArgumentException Si la clave no es válida.
     */
    public static function assert_valid(string $key, string $context = 'key'): string {
        if (!self::is_valid($key)) {
            throw new \InvalidArgumentException(
                sprintf('[invalid_key] Invalid %s: "%s"', $context, $key)
            );
        }

        return $key;
    }

    /**
     * Devuelve la representación cualificada derivada "family.variant".
     */
    public static function qualified(string $family_key, string $variant_key): string {
        return $family_key . '.' . $variant_key;
    }
}
