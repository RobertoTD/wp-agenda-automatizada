<?php
/**
 * Canonical Record Not Found — Registro ausente o fuera de contenedor.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalRecordNotFound extends \RuntimeException {

    /** @var int */
    private $container_id;

    /** @var int */
    private $record_id;

    public function __construct(int $container_id, int $record_id) {
        $this->container_id = $container_id;
        $this->record_id = $record_id;
        parent::__construct(
            sprintf(
                '[record_not_found] Record %d not found for container %d.',
                $record_id,
                $container_id
            )
        );
    }

    public function container_id(): int {
        return $this->container_id;
    }

    public function record_id(): int {
        return $this->record_id;
    }
}
