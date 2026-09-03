<?php
/**
 * Canonical Create Container Command — Entrada tipada para crear contenedor.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCreateContainerCommand {

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
            throw new \InvalidArgumentException('[invalid_mutation_input] Container title cannot be empty.');
        }

        $this->title = $trimmed;
        $this->details = $details;
    }

    public function title(): string {
        return $this->title;
    }

    public function details(): ?string {
        return $this->details;
    }
}
