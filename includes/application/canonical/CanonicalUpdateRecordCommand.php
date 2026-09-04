<?php
/**
 * Canonical Update Record Command — Entrada tipada para editar registro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalUpdateRecordCommand {

    public const MAX_TITLE_LENGTH = 200;

    /** @var int */
    private $container_id;

    /** @var int */
    private $record_id;

    /** @var string */
    private $title;

    /** @var string|null */
    private $details;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(int $container_id, int $record_id, string $title, ?string $details) {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_container_id] container_id must be positive.');
        }
        if ($record_id < 1) {
            throw new \InvalidArgumentException('[invalid_record_id] record_id must be positive.');
        }

        $trimmed = trim($title);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('[invalid_title] Record title cannot be empty.');
        }

        if (self::utf8_length($trimmed) > self::MAX_TITLE_LENGTH) {
            throw new \InvalidArgumentException(
                '[title_too_long] Record title exceeds maximum length of '
                . self::MAX_TITLE_LENGTH . ' characters.'
            );
        }

        $this->container_id = $container_id;
        $this->record_id = $record_id;
        $this->title = $trimmed;

        if ($details === null) {
            $this->details = null;
        } else {
            $trimmed_details = trim($details);
            $this->details = ($trimmed_details === '') ? null : $trimmed_details;
        }
    }

    public function container_id(): int {
        return $this->container_id;
    }

    public function record_id(): int {
        return $this->record_id;
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
