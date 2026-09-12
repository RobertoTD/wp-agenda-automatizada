<?php
/**
 * Markup SVG reutilizable de iconos de familia canónica (presentación).
 *
 * @package WP_Agenda_Automatizada
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

final class AA_Canonical_Family_Icon_Markup {

    /**
     * Devuelve el SVG decorativo para un icon_key, o cadena vacía si es desconocido.
     */
    public static function svg(string $icon_key): string {
        $key = trim($icon_key);
        if ($key === '') {
            return '';
        }

        switch ($key) {
            case 'folder':
                // Carpeta con hoja.
                return '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">'
                    . '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5A2.5 2.5 0 015.5 5H9l1.5 2H18.5A2.5 2.5 0 0121 9.5v7A2.5 2.5 0 0118.5 19h-13A2.5 2.5 0 013 16.5v-9z"/>'
                    . '<path stroke-linecap="round" stroke-linejoin="round" d="M10 11.5h4.5v6H10v-6z"/>'
                    . '</svg>';

            case 'currency':
                // Moneda.
                return '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">'
                    . '<circle cx="12" cy="12" r="8.25"/>'
                    . '<path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5v9M9.75 9.5c.5-.75 1.35-1.15 2.25-1.15 1.35 0 2.4.8 2.4 2.05 0 1.1-.7 1.75-2.15 2.15l-1.5.4c-1.55.4-2.35 1.15-2.35 2.4 0 1.35 1.15 2.3 2.7 2.3.95 0 1.85-.4 2.4-1.15"/>'
                    . '</svg>';

            case 'grid':
                // Cuadrícula de artículos.
                return '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">'
                    . '<rect x="3.75" y="3.75" width="6.5" height="6.5" rx="1.25"/>'
                    . '<rect x="13.75" y="3.75" width="6.5" height="6.5" rx="1.25"/>'
                    . '<rect x="3.75" y="13.75" width="6.5" height="6.5" rx="1.25"/>'
                    . '<rect x="13.75" y="13.75" width="6.5" height="6.5" rx="1.25"/>'
                    . '</svg>';

            case 'contact_card':
                // Silueta con tarjeta.
                return '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">'
                    . '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/>'
                    . '<circle cx="9" cy="11" r="2.25"/>'
                    . '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 15.25c.55-1.1 1.55-1.75 2.25-1.75s1.7.65 2.25 1.75"/>'
                    . '<path stroke-linecap="round" stroke-linejoin="round" d="M14 10.25h4M14 13.25h4"/>'
                    . '</svg>';

            default:
                return '';
        }
    }
}
