<?php
/**
 * Expediente Create Policy — reglas puras de alta de expediente padre.
 *
 * Sin WordPress ni SQL. El slug de categoría por defecto es `general`.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Expediente_Create_Policy {

    public const GENERAL_SLUG = 'general';

    public const TITLE_MAX_LENGTH = 200;

    public const DESCRIPTION_MAX_LENGTH = 10000;

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
    public function normalize_description($value): ?string {
        if ($value === null || !is_string($value)) {
            return null;
        }

        $description = trim($value);

        return $description !== '' ? $description : null;
    }

    /**
     * @param mixed $value
     */
    public function normalize_category_slug($value): string {
        if (!is_string($value)) {
            return self::GENERAL_SLUG;
        }

        $slug = trim($value);

        return $slug !== '' ? $slug : self::GENERAL_SLUG;
    }

    public function title_exceeds_max(string $title): bool {
        return $this->length($title) > self::TITLE_MAX_LENGTH;
    }

    public function description_exceeds_max(string $description): bool {
        return $this->length($description) > self::DESCRIPTION_MAX_LENGTH;
    }

    private function length(string $value): int {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
