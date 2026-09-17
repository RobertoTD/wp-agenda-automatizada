<?php
/**
 * Agregado de amount a nivel de lista: canonización SQL y formato de presentación.
 *
 * No emplea float ni el normalizador de importes individuales (un total puede
 * exceder el rango de un solo amount).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Amount_List_Sum {

    /**
     * Convierte el resultado de SUM/infra a cadena canónica con exactamente 2 decimales.
     *
     * @param mixed $sql_value
     */
    public static function canonicalize_aggregate($sql_value): string {
        if ($sql_value === null || $sql_value === false) {
            return '0.00';
        }

        if (is_int($sql_value)) {
            return ((string) $sql_value) . '.00';
        }

        if (!is_string($sql_value) && !is_numeric($sql_value)) {
            throw new \InvalidArgumentException('[invalid_list_sum] Aggregate must be a decimal string.');
        }

        $trimmed = trim((string) $sql_value);
        if ($trimmed === '') {
            return '0.00';
        }

        if (!preg_match('/^(-)?([0-9]+)(?:\.([0-9]+))?$/', $trimmed, $matches)) {
            throw new \InvalidArgumentException('[invalid_list_sum] Aggregate format is invalid.');
        }

        $is_negative = ($matches[1] === '-');
        $int_raw = $matches[2];
        $dec_raw = isset($matches[3]) ? $matches[3] : '';

        $int_clean = ltrim($int_raw, '0');
        if ($int_clean === '') {
            $int_clean = '0';
        }

        if ($dec_raw === '') {
            $dec_clean = '00';
        } elseif (strlen($dec_raw) === 1) {
            $dec_clean = $dec_raw . '0';
        } elseif (strlen($dec_raw) === 2) {
            $dec_clean = $dec_raw;
        } else {
            // Un agregado de amounts decimal(19,2) debe conservar esta escala.
            // Rechazar una escala inesperada evita truncar un total silenciosamente.
            throw new \InvalidArgumentException('[invalid_list_sum] Aggregate scale is invalid.');
        }

        if ($int_clean === '0' && $dec_clean === '00') {
            return '0.00';
        }

        return ($is_negative ? '-' : '') . $int_clean . '.' . $dec_clean;
    }

    /**
     * Formato de presentación con separador de miles (sin float).
     */
    public static function format_display(string $canonical): string {
        if (!preg_match('/^(-)?([0-9]+)\.([0-9]{2})$/', $canonical, $matches)) {
            throw new \InvalidArgumentException('[invalid_list_sum] Display requires canonical aggregate.');
        }

        $sign = $matches[1] === '-' ? '-' : '';
        $int_part = $matches[2];
        $dec_part = $matches[3];
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $int_part);

        return $sign . $grouped . '.' . $dec_part;
    }

    public static function is_negative(string $canonical): bool {
        return str_starts_with($canonical, '-');
    }
}
