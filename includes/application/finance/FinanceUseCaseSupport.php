<?php
/**
 * Finance Use Case Support — Funciones auxiliares y normalización de la capa Application de Finanzas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Finance
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Key')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-key.php';
}
if (!class_exists('AA_Canonical_Registry')) {
    require_once dirname(__DIR__, 2) . '/domain/canonical/class-aa-canonical-registry.php';
}

final class FinanceUseCaseSupport {

    public const MAX_TITLE_LENGTH = 200;
    public const MAX_DETAILS_BYTES = 65000;
    public const MAX_RAW_AMOUNT_LENGTH = 60;
    public const MAX_AMOUNT_INTEGER_DIGITS = 17;

    /**
     * Valida y resuelve el contexto canónico de Finanzas y su variante.
     *
     * @param AA_Canonical_Registry $registry
     * @param array<string,mixed> $input
     * @return array{ok:true,variant_key:string}|array{ok:false,error:array{code:string,message:string}}
     */
    public static function resolve_variant(AA_Canonical_Registry $registry, array $input): array {
        if (!$registry->is_frozen() || !$registry->has_family('finance')) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'canonical_unavailable',
                    'message' => 'El núcleo canónico no está disponible.',
                ],
            ];
        }

        if (!array_key_exists('variant_key', $input) || $input['variant_key'] === null) {
            $family = $registry->family('finance');
            return [
                'ok' => true,
                'variant_key' => $family->default_variant_key(),
            ];
        }

        $raw_variant = $input['variant_key'];
        if (!is_string($raw_variant) || $raw_variant === '' || !AA_Canonical_Key::is_valid($raw_variant)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_variant_key',
                    'message' => 'Clave de variante no válida.',
                ],
            ];
        }

        if (!$registry->has_variant('finance', $raw_variant)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'unknown_variant',
                    'message' => sprintf('Variante canónica "%s" no encontrada.', $raw_variant),
                ],
            ];
        }

        return [
            'ok' => true,
            'variant_key' => $raw_variant,
        ];
    }

    /**
     * Valida el contexto canónico, resuelve la variante y verifica la existencia y pertenencia del contenedor padre.
     *
     * @param AA_Canonical_Registry $registry
     * @param array<string,mixed> $input
     * @return array{ok:true,variant_key:string,container_id:int,container:array{id:int,family_key:string,variant_key:string,title:string,details:?string,created_at:string}}|array{ok:false,error:array{code:string,message:string}}
     */
    public static function verify_container_context(AA_Canonical_Registry $registry, array $input): array {
        $var_res = self::resolve_variant($registry, $input);
        if (!$var_res['ok']) {
            return $var_res;
        }
        $variant_key = $var_res['variant_key'];

        $container_id = self::normalize_id($input['container_id'] ?? null);
        if ($container_id === null) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_container_id',
                    'message' => 'Identificador de contenedor no válido.',
                ],
            ];
        }

        if (!class_exists('FinanceContainerRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/FinanceContainerRepository.php';
        }

        try {
            $container = FinanceContainerRepository::find_by_id($container_id);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'persistence_failed',
                    'message' => 'No se pudo consultar el contenedor financiero.',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'persistence_failed',
                    'message' => 'No se pudo consultar el contenedor financiero.',
                ],
            ];
        }

        if ($container === null || ($container['variant_key'] ?? '') !== $variant_key) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'container_not_found',
                    'message' => 'Contenedor financiero no encontrado.',
                ],
            ];
        }

        return [
            'ok' => true,
            'variant_key' => $variant_key,
            'container_id' => $container_id,
            'container' => array_merge(['family_key' => 'finance'], $container),
        ];
    }

    /**
     * Valida y normaliza el campo amount a una cadena decimal canónica de 2 decimales o null.
     *
     * @param mixed $value
     * @return array{ok:true,value:?string}|array{ok:false,error:array{code:string,message:string}}
     */
    public static function normalize_amount($value): array {
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

    /**
     * Valida y normaliza el campo title.
     *
     * @param mixed $value
     * @return array{ok:true,value:string}|array{ok:false,error:array{code:string,message:string}}
     */
    public static function normalize_title($value): array {
        if ($value === null) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'missing_title',
                    'message' => 'El título es obligatorio.',
                ],
            ];
        }

        if (!is_string($value)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_title',
                    'message' => 'El título debe ser una cadena de texto.',
                ],
            ];
        }

        if (!self::is_valid_utf8($value)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_title',
                    'message' => 'El título contiene caracteres UTF-8 no válidos.',
                ],
            ];
        }

        $normalized = preg_replace('/\s+/u', ' ', $value);
        if (!is_string($normalized)) {
            $normalized = preg_replace('/\s+/', ' ', $value);
        }

        $trimmed = trim((string) $normalized);

        if ($trimmed === '') {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'missing_title',
                    'message' => 'El título no puede estar vacío.',
                ],
            ];
        }

        if (self::utf8_length($trimmed) > self::MAX_TITLE_LENGTH) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'title_too_long',
                    'message' => sprintf('El título no puede exceder los %d caracteres.', self::MAX_TITLE_LENGTH),
                ],
            ];
        }

        return [
            'ok' => true,
            'value' => $trimmed,
        ];
    }

    /**
     * Valida y normaliza el campo details.
     *
     * @param mixed $value
     * @return array{ok:true,value:?string}|array{ok:false,error:array{code:string,message:string}}
     */
    public static function normalize_details($value): array {
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
                    'code' => 'invalid_details',
                    'message' => 'Los detalles deben ser una cadena de texto o null.',
                ],
            ];
        }

        if (!self::is_valid_utf8($value)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'invalid_details',
                    'message' => 'Los detalles contienen caracteres UTF-8 no válidos.',
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

        if (strlen($trimmed) > self::MAX_DETAILS_BYTES) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'details_too_long',
                    'message' => sprintf('Los detalles no pueden exceder los %d bytes.', self::MAX_DETAILS_BYTES),
                ],
            ];
        }

        return [
            'ok' => true,
            'value' => $trimmed,
        ];
    }

    /**
     * Valida y normaliza un ID entero positivo.
     *
     * @param mixed $value
     * @return int|null
     */
    public static function normalize_id($value): ?int {
        if (is_int($value) && $value >= 1) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value)) {
            $int_val = (int) $value;
            if ($int_val >= 1 && (string) $int_val === $value) {
                return $int_val;
            }
        }

        return null;
    }

    /**
     * Normaliza el número de página de forma segura sin casts peligrosos.
     *
     * @param mixed $value
     * @return int
     */
    public static function normalize_page($value): int {
        if (!isset($value) || $value === null || $value === '') {
            return 1;
        }

        if (is_int($value) && $value >= 1) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value)) {
            $int_val = (int) $value;
            if ($int_val >= 1) {
                return $int_val;
            }
        }

        return 1;
    }

    /**
     * Comprueba si una cadena es UTF-8 válida.
     *
     * @param string $string
     * @return bool
     */
    public static function is_valid_utf8(string $string): bool {
        return (bool) preg_match('//u', $string);
    }

    /**
     * Mide la longitud en caracteres de una cadena UTF-8 de forma portable.
     *
     * @param string $string
     * @return int
     */
    public static function utf8_length(string $string): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($string, 'UTF-8');
        }

        $matched = preg_match_all('/./us', $string);
        return $matched === false ? strlen($string) : (int) $matched;
    }

    /**
     * Construye un envelope exitoso.
     *
     * @param array<string,mixed> $data
     * @return array{success:true,data:array<string,mixed>}
     */
    public static function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Construye un envelope de error.
     *
     * @param string $code
     * @param string $message
     * @return array{success:false,error:array{code:string,message:string}}
     */
    public static function fail(string $code, string $message): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
