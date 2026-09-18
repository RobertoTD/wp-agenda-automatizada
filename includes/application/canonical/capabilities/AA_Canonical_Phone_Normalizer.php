<?php
/**
 * Normalizador canónico de phone (E.164 con +).
 *
 * Independiente de aa_normalize_telefono / WhatsAppService legacy.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Phone_Normalizer {

    public const MAX_RAW_LENGTH = 40;

    /**
     * Códigos admitidos, ordenados de mayor a menor longitud (longest-prefix).
     *
     * @var list<string>
     */
    private const COUNTRY_CODES = [
        '593', '502',
        '52', '54', '57', '56', '51', '58', '34',
        '1',
    ];

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
                    'code' => 'invalid_phone',
                    'message' => 'El teléfono debe ser una cadena de texto o null.',
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

        if (strlen($trimmed) > self::MAX_RAW_LENGTH) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_phone',
                    'message' => 'El teléfono no es válido.',
                ],
            ];
        }

        if ($trimmed[0] !== '+') {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_phone',
                    'message' => 'El teléfono debe incluir el código internacional con +.',
                ],
            ];
        }

        $body = substr($trimmed, 1);
        if ($body === '' || !preg_match('/^[0-9\s\-\(\)]+$/', $body)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_phone',
                    'message' => 'El teléfono solo puede incluir dígitos y separadores (espacio, guion, paréntesis).',
                ],
            ];
        }

        $digits = preg_replace('/[^0-9]/', '', $body);
        if (!is_string($digits) || $digits === '') {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_phone',
                    'message' => 'El teléfono no es válido.',
                ],
            ];
        }

        $matched = self::match_country_code($digits);
        if ($matched === null) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'phone_unsupported_country',
                    'message' => 'El código de país no está admitido.',
                ],
            ];
        }

        $country = $matched['country'];
        $national = $matched['national'];
        $national_error = self::validate_national($country, $national);
        if ($national_error !== null) {
            return [
                'ok' => false,
                'error' => $national_error,
            ];
        }

        return [
            'ok' => true,
            'value' => '+' . $country . $national,
        ];
    }

    /**
     * Presentación: "+{código} {resto}" sin agrupación por país.
     */
    public static function format_display(string $e164): ?string {
        $parsed = self::parse_stored($e164);
        if ($parsed === null) {
            return null;
        }

        return '+' . $parsed['country'] . ' ' . $parsed['national'];
    }

    /**
     * @return array{country:string,national:string}|null
     */
    public static function parse_stored(string $e164): ?array {
        $trimmed = trim($e164);
        if ($trimmed === '' || $trimmed[0] !== '+') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', substr($trimmed, 1));
        if (!is_string($digits) || $digits === '') {
            return null;
        }

        $matched = self::match_country_code($digits);
        if ($matched === null) {
            return null;
        }

        if (self::validate_national($matched['country'], $matched['national']) !== null) {
            return null;
        }

        return $matched;
    }

    /**
     * @return list<array{code:string,label:string}>
     */
    public static function country_options(): array {
        return [
            ['code' => '52', 'label' => 'México (+52)'],
            ['code' => '1', 'label' => 'Estados Unidos (+1)'],
            ['code' => '34', 'label' => 'España (+34)'],
            ['code' => '57', 'label' => 'Colombia (+57)'],
            ['code' => '54', 'label' => 'Argentina (+54)'],
            ['code' => '56', 'label' => 'Chile (+56)'],
            ['code' => '51', 'label' => 'Perú (+51)'],
            ['code' => '593', 'label' => 'Ecuador (+593)'],
            ['code' => '58', 'label' => 'Venezuela (+58)'],
            ['code' => '502', 'label' => 'Guatemala (+502)'],
        ];
    }

    /**
     * @return array{country:string,national:string}|null
     */
    private static function match_country_code(string $digits): ?array {
        foreach (self::COUNTRY_CODES as $code) {
            $len = strlen($code);
            if (strlen($digits) > $len && substr($digits, 0, $len) === $code) {
                return [
                    'country' => $code,
                    'national' => substr($digits, $len),
                ];
            }
        }

        return null;
    }

    /**
     * @return array{code:string,message:string}|null
     */
    private static function validate_national(string $country, string $national): ?array {
        $len = strlen($national);
        $invalid_length = [
            'code' => 'phone_invalid_length',
            'message' => 'La longitud del teléfono no es válida para el país seleccionado.',
        ];
        $invalid_phone = [
            'code' => 'invalid_phone',
            'message' => 'El teléfono no es válido para el país seleccionado.',
        ];

        switch ($country) {
            case '52':
            case '1':
            case '57':
            case '58':
                return $len === 10 ? null : $invalid_length;
            case '34':
            case '56':
                return $len === 9 ? null : $invalid_length;
            case '502':
                return $len === 8 ? null : $invalid_length;
            case '54':
                if ($len === 10) {
                    $first = $national[0];
                    return ($first === '1' || $first === '2' || $first === '3')
                        ? null
                        : $invalid_phone;
                }
                if ($len === 11) {
                    if ($national[0] !== '9') {
                        return $invalid_phone;
                    }
                    $rest = substr($national, 1);
                    $first = $rest[0];
                    return ($first === '1' || $first === '2' || $first === '3')
                        ? null
                        : $invalid_phone;
                }
                return $invalid_length;
            case '51':
            case '593':
                if ($len === 8) {
                    return null;
                }
                if ($len === 9) {
                    return $national[0] === '9' ? null : $invalid_phone;
                }
                return $invalid_length;
            default:
                return [
                    'code' => 'phone_unsupported_country',
                    'message' => 'El código de país no está admitido.',
                ];
        }
    }
}
