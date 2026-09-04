<?php
/**
 * Canonical Family Enablement Persistence Failed — fallo SQL de enablement.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalFamilyEnablementPersistenceFailed extends \RuntimeException {

    public function __construct(string $detail = '') {
        $suffix = $detail !== '' ? ': ' . $detail : '';
        parent::__construct('[persistence_failed] Canonical family enablement persistence failed' . $suffix);
    }
}
