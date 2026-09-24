<?php
/** Campos universales compartidos por una lista y un registro canónicos. */
defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Base_Fields {

    public const MAX_TITLE_LENGTH = 200;

    /** @var string */
    private $title;

    /** @var string|null */
    private $details;

    public function __construct(string $title, ?string $details) {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('[invalid_title] El título es obligatorio.');
        }
        if (self::utf8_length($title) > self::MAX_TITLE_LENGTH) {
            throw new \InvalidArgumentException('[title_too_long] El título excede 200 caracteres.');
        }

        $this->title = $title;
        $details = $details === null ? null : trim($details);
        $this->details = $details === '' ? null : $details;
    }

    public function title(): string { return $this->title; }
    public function details(): ?string { return $this->details; }

    private static function utf8_length(string $value): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }
        $count = preg_match_all('/./us', $value);
        return $count === false ? strlen($value) : (int) $count;
    }
}
