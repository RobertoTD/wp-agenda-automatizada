<?php
/**
 * Canonical Container Not Found — Contenedor ausente o fuera de variante.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContainerNotFound extends \RuntimeException {

    /** @var string */
    private $variant_key;

    /** @var int */
    private $container_id;

    public function __construct(string $variant_key, int $container_id) {
        $this->variant_key = $variant_key;
        $this->container_id = $container_id;
        parent::__construct(
            sprintf(
                '[container_not_found] Container %d not found for variant "%s".',
                $container_id,
                $variant_key
            )
        );
    }

    public function variant_key(): string {
        return $this->variant_key;
    }

    public function container_id(): int {
        return $this->container_id;
    }
}
