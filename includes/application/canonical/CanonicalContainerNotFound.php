<?php
/**
 * Canonical Container Not Found — Contenedor ausente o fuera de familia.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContainerNotFound extends \RuntimeException {

    /** @var string */
    private $family_key;

    /** @var int */
    private $container_id;

    public function __construct(string $family_key, int $container_id) {
        $this->family_key = $family_key;
        $this->container_id = $container_id;
        parent::__construct(
            sprintf(
                '[container_not_found] Container %d not found for family "%s".',
                $container_id,
                $family_key
            )
        );
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function container_id(): int {
        return $this->container_id;
    }
}
