<?php
/**
 * Canonical Container — Value object del recurso Contenedor canónico.
 *
 * Dominio puro: sin WordPress.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Key')) {
    require_once __DIR__ . '/class-aa-canonical-key.php';
}

final class AA_Canonical_Container {

    /** @var int */
    private $id;

    /** @var string */
    private $variant_key;

    /** @var string */
    private $title;

    /** @var string|null */
    private $details;

    /** @var \DateTimeImmutable */
    private $updated_at;

    /**
     * @param int                      $id
     * @param string                   $variant_key
     * @param string                   $title
     * @param string|null              $details
     * @param \DateTimeImmutable|string $updated_at Instant UTC or canonical string ending in Z.
     */
    public function __construct(
        int $id,
        string $variant_key,
        string $title,
        ?string $details,
        $updated_at
    ) {
        if ($id < 1) {
            throw new \InvalidArgumentException('[invalid_id] Container id must be a positive integer.');
        }
        $this->id = $id;

        $this->variant_key = AA_Canonical_Key::assert_valid($variant_key, 'variant_key');

        $trimmed_title = trim($title);
        if ($trimmed_title === '') {
            throw new \InvalidArgumentException('[invalid_title] Container title cannot be empty.');
        }
        $this->title = $trimmed_title;

        if ($details !== null) {
            if (!is_string($details)) {
                throw new \InvalidArgumentException('[invalid_details] Container details must be string or null.');
            }
            $this->details = $details;
        } else {
            $this->details = null;
        }

        $this->updated_at = self::normalize_updated_at($updated_at);
    }

    /**
     * @param \DateTimeImmutable|string $updated_at
     */
    public static function normalize_updated_at($updated_at): \DateTimeImmutable {
        if ($updated_at instanceof \DateTimeImmutable) {
            $utc = $updated_at->setTimezone(new \DateTimeZone('UTC'));
            return self::truncate_to_seconds($utc);
        }

        if (!is_string($updated_at)) {
            throw new \InvalidArgumentException('[invalid_updated_at] updated_at must be DateTimeImmutable or string.');
        }

        $raw = trim($updated_at);
        if ($raw === '') {
            throw new \InvalidArgumentException('[invalid_updated_at] updated_at cannot be empty.');
        }

        // Reject MySQL-naive and non-Z offsets (including +00:00).
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

        return $parsed;
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

    public function id(): int {
        return $this->id;
    }

    public function variant_key(): string {
        return $this->variant_key;
    }

    public function title(): string {
        return $this->title;
    }

    public function details(): ?string {
        return $this->details;
    }

    public function updated_at(): \DateTimeImmutable {
        return $this->updated_at;
    }

    public function updated_at_canonical(): string {
        return $this->updated_at->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @return array{id:int,variant_key:string,title:string,details:?string,updated_at:string}
     */
    public function to_canonical_array(): array {
        return [
            'id' => $this->id,
            'variant_key' => $this->variant_key,
            'title' => $this->title,
            'details' => $this->details,
            'updated_at' => $this->updated_at_canonical(),
        ];
    }
}
