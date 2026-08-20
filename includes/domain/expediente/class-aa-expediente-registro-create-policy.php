<?php
/**
 * Expediente Registro Create Policy — reglas puras de alta de registro hijo.
 *
 * Título y cuerpo obligatorios tras trim. Longitud multibyte con fallback.
 * Sin WordPress ni SQL.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Expediente_Registro_Create_Policy {

    public const TITLE_MAX_LENGTH = 200;

    public const BODY_MAX_LENGTH = 10000;

    /**
     * @param mixed $value
     */
    public function normalize_title($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $title = trim($value);

        return $title !== '' ? $title : null;
    }

    /**
     * @param mixed $value
     */
    public function normalize_body($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $body = trim($value);

        return $body !== '' ? $body : null;
    }

    public function title_exceeds_max(string $title): bool {
        return $this->length($title) > self::TITLE_MAX_LENGTH;
    }

    public function body_exceeds_max(string $body): bool {
        return $this->length($body) > self::BODY_MAX_LENGTH;
    }

    private function length(string $value): int {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
