<?php
/**
 * Canonical Delete Container Command — Entrada tipada para eliminar contenedor.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalDeleteContainerCommand {

    /** @var int */
    private $container_id;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(int $container_id) {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_mutation_input] container_id must be positive.');
        }

        $this->container_id = $container_id;
    }

    public function container_id(): int {
        return $this->container_id;
    }
}
