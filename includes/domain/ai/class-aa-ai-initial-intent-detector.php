<?php
/**
 * AI Initial Intent Detector
 *
 * Detector puro para refuerzos deterministas de clasificación inicial.
 *
 * Invariantes:
 * - Sin WordPress APIs.
 * - Sin SQL.
 * - Sin UI.
 * - Sin ejecución de acciones de negocio.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\AI
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Initial_Intent_Detector {

    /**
     * Detecta solicitudes explícitas de creación/alta/registro de cliente.
     *
     * Es deliberadamente conservador: exige la palabra "cliente" junto a
     * una acción de creación/registro/alta, y rechaza menciones de cita o
     * disponibilidad para no colisionar con `create_booking` ni
     * `check_availability`.
     */
    public static function is_clear_create_client_request(string $message): bool {
        $m = self::normalize_message($message);
        if ($m === '') {
            return false;
        }

        if (preg_match('/[?¿]/u', $message)) {
            return false;
        }

        if (preg_match('/\b(?:cita|citas|reserva|reservas|turno|turnos|disponibilidad|horario|horarios|libre|libres|espacio|espacios)\b/u', $m)) {
            return false;
        }

        if (preg_match('/\b(?:como\s+(?:crear|agregar|registrar|dar\s+de\s+alta)|ayuda|tutorial|explica|explicame|funciona|funcionan)\b/u', $m)) {
            return false;
        }

        $allow_patterns = [
            '/\b(?:crea|crear|creame|haz|hacer)\s+(?:un\s+|una\s+)?(?:nuevo\s+|nueva\s+)?cliente\b/u',
            '/\b(?:agrega|agregar|anade|anadir)\s+(?:un\s+|una\s+)?(?:nuevo\s+|nueva\s+)?cliente\b/u',
            '/\b(?:registra|registrar)\s+(?:un\s+|una\s+)?(?:nuevo\s+|nueva\s+)?cliente\b/u',
            '/\b(?:nuevo|nueva)\s+cliente\b/u',
            '/\bdar\s+de\s+alta\s+(?:a\s+)?(?:un\s+|una\s+)?(?:nuevo\s+|nueva\s+)?cliente\b/u',
            '/\b(?:alta|alta\s+de)\s+(?:un\s+|una\s+)?(?:nuevo\s+|nueva\s+)?cliente\b/u',
            '/\b(?:agrega|agregar|anade|anadir|registra|registrar|da\s+de\s+alta|dar\s+de\s+alta)\s+a\s+.+\s+como\s+cliente\b/u',
        ];

        foreach ($allow_patterns as $pattern) {
            if (preg_match($pattern, $m)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize_message(string $message): string {
        $normalized = mb_strtolower(trim($message), 'UTF-8');
        if ($normalized === '') {
            return '';
        }

        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ]);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);

        return is_string($normalized) ? trim($normalized) : '';
    }
}
