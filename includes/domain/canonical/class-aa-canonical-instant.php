<?php
/**
 * Canonical Instant — Instant UTC canónico (segundos, sufijo Z).
 *
 * Dominio puro: sin WordPress.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Instant {

    /** @var \DateTimeImmutable */
    private $utc;

    private function __construct(\DateTimeImmutable $utc) {
        $this->utc = $utc;
    }

    /**
     * @param \DateTimeImmutable|string $value
     */
    public static function from($value): self {
        if ($value instanceof \DateTimeImmutable) {
            $utc = $value->setTimezone(new \DateTimeZone('UTC'));
            return new self(self::truncate_to_seconds($utc));
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('[invalid_updated_at] updated_at must be DateTimeImmutable or string.');
        }

        $raw = trim($value);
        if ($raw === '') {
            throw new \InvalidArgumentException('[invalid_updated_at] updated_at cannot be empty.');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw) === 1) {
            throw new \InvalidArgumentException('[invalid_updated_at] Naive MySQL datetime is not canonical.');
        }
        if (substr($raw, -1) !== 'Z') {
            throw new \InvalidArgumentException('[invalid_updated_at] Canonical updated_at must use Z suffix.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $raw) !== 1) {
            throw new \InvalidArgumentException('[invalid_updated_at] Canonical form is Y-m-d\\TH:i:s\\Z.');
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $raw, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false) {
            throw new \InvalidArgumentException('[invalid_updated_at] Invalid UTC instant.');
        }
        if (
            is_array($errors)
            && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)
        ) {
            throw new \InvalidArgumentException('[invalid_updated_at] Invalid UTC instant.');
        }

        return new self($parsed);
    }

    private static function truncate_to_seconds(\DateTimeImmutable $utc): \DateTimeImmutable {
        $truncated = \DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i:s\Z',
            $utc->format('Y-m-d\TH:i:s\Z'),
            new \DateTimeZone('UTC')
        );
        if ($truncated === false) {
            throw new \InvalidArgumentException('[invalid_updated_at] Unable to normalize UTC instant.');
        }
        return $truncated;
    }

    public function to_datetime(): \DateTimeImmutable {
        return $this->utc;
    }

    public function to_canonical_string(): string {
        return $this->utc->format('Y-m-d\TH:i:s\Z');
    }
}
