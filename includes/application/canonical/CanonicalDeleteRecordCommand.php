<?php
/**
 * Canonical Delete Record Command — Entrada tipada para eliminar registro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalDeleteRecordCommand {

    /** @var int */
    private $container_id;

    /** @var int */
    private $record_id;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(int $container_id, int $record_id) {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_container_id] container_id must be positive.');
        }
        if ($record_id < 1) {
            throw new \InvalidArgumentException('[invalid_record_id] record_id must be positive.');
        }

        $this->container_id = $container_id;
        $this->record_id = $record_id;
    }

    public function container_id(): int {
        return $this->container_id;
    }

    public function record_id(): int {
        return $this->record_id;
    }
}
