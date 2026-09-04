<?php
/**
 * Canonical Family Enablement Schema Not Ready — tabla o schema ausente.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalFamilyEnablementSchemaNotReady extends \RuntimeException {

    public function __construct(string $detail = '') {
        $suffix = $detail !== '' ? ': ' . $detail : '';
        parent::__construct('[schema_not_ready] Canonical family enablement schema is not ready' . $suffix);
    }
}
