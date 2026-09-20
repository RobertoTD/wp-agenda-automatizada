<?php
/**
 * Markup SVG reutilizable de iconos de familia canónica (presentación).
 *
 * Geometrías alineadas con iconos existentes del admin (sidebar/dashboard),
 * sin depender de esos módulos.
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
                // Carpeta sencilla (misma geometría que Expedientes).
                return self::wrap(
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>'
                );

            case 'currency':
                // Moneda (misma geometría que dashboard Ingresos).
                return self::wrap(
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                );

            case 'grid':
                // Cuadrícula (misma geometría que Listas en sidebar).
                return self::wrap(
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>'
                );

            case 'contact_card':
                // Grupo de personas (misma geometría que Clientes).
                return self::wrap(
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>'
                );

            case 'checklist':
                return self::wrap(
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5h10M9 12h10M9 19h10M5 5l1.5 1.5L8 4M5 12l1.5 1.5L8 11M5 19l1.5 1.5L8 18"/>'
                );

            default:
                return '';
        }
    }

    private static function wrap(string $inner): string {
        return '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">'
            . $inner
            . '</svg>';
    }
}
