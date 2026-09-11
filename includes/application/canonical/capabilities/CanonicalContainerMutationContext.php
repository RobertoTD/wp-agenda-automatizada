<?php
/**
 * Contexto de mutación de contenedor para efectos de capacidad (sin \wpdb).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContainerMutationContext {

    /** @var int */
    private $family_id;

    /** @var int */
    private $container_id;

    /** @var string */
    private $utc_now;

    public function __construct(int $family_id, int $container_id, string $utc_now) {
        $this->family_id = $family_id;
        $this->container_id = $container_id;
        $this->utc_now = $utc_now;
    }

    public function family_id(): int {
        return $this->family_id;
    }

    public function container_id(): int {
        return $this->container_id;
    }

    public function utc_now(): string {
        return $this->utc_now;
    }
}
