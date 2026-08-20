<?php
/**
 * Expediente Id Policy — normalización canónica de expediente_id.
 *
 * Solo enteros positivos o strings decimales canónicos ("7").
 * Rechaza 0, negativos, "01", "+1", decimales, arrays y objetos.
 * Sin WordPress ni SQL.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Expediente_Id_Policy {

    /**
     * @param mixed $value
     */
    public static function normalize($value): ?int {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        if (!preg_match('/^[1-9][0-9]{0,18}$/', $value)) {
            return null;
        }

        $id = (int) $value;
        if ($id < 1 || (string) $id !== $value) {
            return null;
        }

        return $id;
    }
}
