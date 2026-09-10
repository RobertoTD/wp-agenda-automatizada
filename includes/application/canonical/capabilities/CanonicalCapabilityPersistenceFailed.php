<?php
/**
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityPersistenceFailed extends \RuntimeException {
    public function __construct(string $message = 'Capability persistence failed.') {
        parent::__construct('[capability_persistence_failed] ' . $message);
    }

    public function error_code(): string {
        return 'capability_persistence_failed';
    }
}
