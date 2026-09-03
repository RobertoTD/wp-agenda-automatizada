<?php
/**
 * Canonical Update Container Command — Entrada tipada para editar contenedor.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalUpdateContainerCommand {

    /** @var int */
    private $container_id;

    /** @var string */
    private $title;

    /** @var string|null */
    private $details;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(int $container_id, string $title, ?string $details) {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_mutation_input] container_id must be positive.');
        }

        $trimmed = trim($title);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('[invalid_mutation_input] Container title cannot be empty.');
        }

        $this->container_id = $container_id;
        $this->title = $trimmed;
        $this->details = $details;
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
}
