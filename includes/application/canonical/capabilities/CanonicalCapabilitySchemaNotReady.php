<?php
/**
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilitySchemaNotReady extends \RuntimeException {
    public function __construct(string $message = 'Capability schema is not ready.') {
        parent::__construct('[capability_schema_not_ready] ' . $message);
    }

    public function error_code(): string {
        return 'capability_schema_not_ready';
    }
}
