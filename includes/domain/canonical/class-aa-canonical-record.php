<?php
/**
 * Canonical Record — Value object del recurso Registro canónico.
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

final class AA_Canonical_Record {

    /** @var int */
    private $id;

    /** @var int */
    private $container_id;

    /** @var string */
    private $title;

    /** @var string|null */
    private $details;

    /** @var \DateTimeImmutable */
    private $updated_at;

    /**
     * @param int                       $id
     * @param int                       $container_id
     * @param string                    $title
     * @param string|null               $details
     * @param \DateTimeImmutable|string $updated_at
     */
    public function __construct(
        int $id,
        int $container_id,
        string $title,
        ?string $details,
        $updated_at
    ) {
        if ($id < 1) {
            throw new \InvalidArgumentException('[invalid_id] Record id must be a positive integer.');
        }
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_container_id] Record container_id must be a positive integer.');
        }
        $this->id = $id;
        $this->container_id = $container_id;

        $trimmed_title = trim($title);
        if ($trimmed_title === '') {
            throw new \InvalidArgumentException('[invalid_title] Record title cannot be empty.');
        }
        $this->title = $trimmed_title;

        if ($details !== null) {
            if (!is_string($details)) {
                throw new \InvalidArgumentException('[invalid_details] Record details must be string or null.');
            }
            $this->details = $details;
        } else {
            $this->details = null;
        }

        $this->updated_at = AA_Canonical_Instant::from($updated_at)->to_datetime();
    }

    public function id(): int {
        return $this->id;
    }

    public function container_id(): int {
        return $this->container_id;
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
     * @return array{id:int,container_id:int,title:string,details:?string,updated_at:string}
     */
    public function to_canonical_array(): array {
        return [
            'id' => $this->id,
            'container_id' => $this->container_id,
            'title' => $this->title,
            'details' => $this->details,
            'updated_at' => $this->updated_at_canonical(),
        ];
    }
}
