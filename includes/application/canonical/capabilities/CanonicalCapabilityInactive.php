<?php
/**
 * Capacidad conocida pero inactiva en la lista (rechazo de escritura).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityInactive extends \RuntimeException {

    /** @var string */
    private $capability_key;

    public function __construct(string $capability_key) {
        $this->capability_key = $capability_key;
        parent::__construct('[capability_inactive] Capability is inactive on container: ' . $capability_key);
    }

    public function capability_key(): string {
        return $this->capability_key;
    }

    public function error_code(): string {
        return 'capability_inactive';
    }

    public function http_status(): int {
        return 409;
    }
}
