<?php
/**
 * Normalizador canónico de amount (A1a).
 *
 * Independiente de Finance legacy. Duplicación temporal hasta retirada del legacy
 * tras amount operativo en A1b (antes de imágenes).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Amount_Normalizer {

    public const MAX_RAW_AMOUNT_LENGTH = 60;
    public const MAX_AMOUNT_INTEGER_DIGITS = 17;

    /**
     * @param mixed $value
     * @return array{ok:true,value:?string}|array{ok:false,error:array{code:string,message:string}}
     */
    public static function normalize($value): array {
        if ($value === null) {
            return [
                'ok' => true,
                'value' => null,
            ];
        }

        if (!is_string($value)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_amount',
                    'message' => 'El importe debe ser una cadena de texto o null.',
                ],
            ];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [
                'ok' => true,
                'value' => null,
            ];
        }

        if (strlen($trimmed) > self::MAX_RAW_AMOUNT_LENGTH) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_amount',
                    'message' => 'El formato del importe no es válido.',
                ],
            ];
        }

        if (!preg_match('/^(-)?([0-9]+)(?:\.([0-9]+))?$/', $trimmed, $matches)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_amount',
                    'message' => 'El formato del importe no es válido.',
                ],
            ];
        }

        $is_negative = ($matches[1] === '-');
        $int_raw = $matches[2];
        $dec_raw = isset($matches[3]) ? $matches[3] : null;

        if ($dec_raw !== null && strlen($dec_raw) > 2) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'amount_too_many_decimals',
                    'message' => 'El importe no puede tener más de 2 decimales.',
                ],
            ];
        }

        $int_clean = ltrim($int_raw, '0');
        if ($int_clean === '') {
            $int_clean = '0';
        }

        if (strlen($int_clean) > self::MAX_AMOUNT_INTEGER_DIGITS) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'amount_out_of_range',
                    'message' => 'El importe excede el rango máximo permitido.',
                ],
            ];
        }

        if ($dec_raw === null || $dec_raw === '') {
            $dec_clean = '00';
        } elseif (strlen($dec_raw) === 1) {
            $dec_clean = $dec_raw . '0';
        } else {
            $dec_clean = $dec_raw;
        }

        if ($int_clean === '0' && $dec_clean === '00') {
            return [
                'ok' => true,
                'value' => '0.00',
            ];
        }

        $canonical = ($is_negative ? '-' : '') . $int_clean . '.' . $dec_clean;

        return [
            'ok' => true,
            'value' => $canonical,
        ];
    }
}
