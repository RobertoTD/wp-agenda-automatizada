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

if (!class_exists('AA_Canonical_Instant')) {
    require_once __DIR__ . '/class-aa-canonical-instant.php';
}

final class AA_Canonical_Container {

    /** @var int */
    private $id;

    /** @var string */
    private $title;

    /** @var string|null */
    private $details;

    /** @var \DateTimeImmutable */
    private $updated_at;

    /**
     * @param int                      $id
     * @param string                   $title
     * @param string|null              $details
     * @param \DateTimeImmutable|string $updated_at Instant UTC or canonical string ending in Z.
     */
    public function __construct(
        int $id,
        string $title,
        ?string $details,
        $updated_at
    ) {
        if ($id < 1) {
            throw new \InvalidArgumentException('[invalid_id] Container id must be a positive integer.');
        }
        $this->id = $id;

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
        return AA_Canonical_Instant::from($updated_at)->to_datetime();
    }

    public function id(): int {
        return $this->id;
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
     * @return array{id:int,title:string,details:?string,updated_at:string}
     */
    public function to_canonical_array(): array {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'details' => $this->details,
            'updated_at' => $this->updated_at_canonical(),
        ];
    }
}
