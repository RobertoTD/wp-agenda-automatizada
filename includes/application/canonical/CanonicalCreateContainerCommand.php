<?php
/**
 * Canonical Create Container Command — Entrada tipada para crear contenedor.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCreateContainerCommand {

    public const MAX_TITLE_LENGTH = 200;

    /** @var string */
    private $title;

    /** @var string|null */
    private $details;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $title, ?string $details) {
        $trimmed = trim($title);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('[invalid_title] Container title cannot be empty.');
        }

        if (self::utf8_length($trimmed) > self::MAX_TITLE_LENGTH) {
            throw new \InvalidArgumentException(
                '[title_too_long] Container title exceeds maximum length of '
                . self::MAX_TITLE_LENGTH . ' characters.'
            );
        }

        $this->title = $trimmed;

        if ($details === null) {
            $this->details = null;
        } else {
            $trimmed_details = trim($details);
            $this->details = ($trimmed_details === '') ? null : $trimmed_details;
        }
    }

    public function title(): string {
        return $this->title;
    }

    public function details(): ?string {
        return $this->details;
    }

    private static function utf8_length(string $string): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($string, 'UTF-8');
        }

        $matched = preg_match_all('/./us', $string);
        return $matched === false ? strlen($string) : (int) $matched;
    }
}
