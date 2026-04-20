<?php
/**
 * Text folder — normalización para comparación insensible a mayúsculas,
 * diacríticos y espacios.
 *
 * Capa: `includes/domain/text/` (dominio puro).
 *
 * Una sola entrada pública estática: `fold()`. Sin WordPress, sin SQL,
 * sin LLM. Determinista e idempotente: `fold(fold($x)) === fold($x)`.
 *
 * Algoritmo:
 *   1. `null` o cadena vacía (tras trim) → `''`.
 *   2. `trim` + colapso de espacios internos a un solo espacio
 *      (`preg_replace('/\s+/u', ' ', ...)`).
 *   3. `mb_strtolower(..., 'UTF-8')`.
 *   4. Eliminación de diacríticos:
 *      - **Preferido:** si la extensión `intl` está cargada y existe
 *        `Normalizer`, `Normalizer::normalize($s, FORM_D)` y luego
 *        `preg_replace('/\p{Mn}+/u', '', ...)` para quitar marcas
 *        combinantes (cubre la mayoría de idiomas latinos).
 *      - **Fallback determinista** (sin `intl`): `strtr()` con un
 *        mapeo mínimo para español estándar (á→a, é→e, í→i, ó→o,
 *        ú→u, ü→u, ñ→n y mayúsculas equivalentes; se aplica tras el
 *        lowercase, por lo que en la práctica predominan las claves
 *        minúsculas). Documentado como respaldo; en producción se
 *        prefiere `intl` cuando esté disponible.
 *
 * No altera dígitos ni puntuación (alcance acotado a nombres de
 * catálogo para lookup).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Text
 */

defined('ABSPATH') or die('No direct access');

final class AA_Text_Folder {

    /**
     * Mapeo fallback (post-lowercase); claves mayúsculas por si el motor
     * no bajara algún carácter raro antes del strtr.
     */
    private const FALLBACK_DIACRITICS = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
        'À' => 'a', 'È' => 'e', 'Ì' => 'i', 'Ò' => 'o', 'Ù' => 'u',
        'Ä' => 'a', 'Ë' => 'e', 'Ï' => 'i', 'Ö' => 'o', 'Ü' => 'u',
        'Â' => 'a', 'Ê' => 'e', 'Î' => 'i', 'Ô' => 'o', 'Û' => 'u',
        'Ñ' => 'n',
    ];

    /**
     * @param string|null $input Texto crudo o null.
     * @return string Cadena normalizada para comparación; `''` si null/vacío.
     */
    public static function fold(?string $input): string {
        if ($input === null) {
            return '';
        }

        $s = trim($input);
        if ($s === '') {
            return '';
        }

        $s = preg_replace('/\s+/u', ' ', $s);
        if ($s === '') {
            return '';
        }

        $s = mb_strtolower($s, 'UTF-8');

        if (class_exists('Normalizer', false) && extension_loaded('intl')) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if ($n !== false) {
                $s = preg_replace('/\p{Mn}+/u', '', $n);
            }
        } else {
            $s = strtr($s, self::FALLBACK_DIACRITICS);
        }

        return $s;
    }
}
