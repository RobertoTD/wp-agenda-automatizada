<?php
/**
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityNotReady extends \RuntimeException {
    /** @var string */
    private $capability_key;

    public function __construct(string $capability_key) {
        $this->capability_key = $capability_key;
        parent::__construct('[capability_not_ready] Capability is not ready for activation: ' . $capability_key);
    }

    public function capability_key(): string {
        return $this->capability_key;
    }

    public function error_code(): string {
        return 'capability_not_ready';
    }
}
