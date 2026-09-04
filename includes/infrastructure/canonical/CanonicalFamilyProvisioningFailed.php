<?php
/**
 * Canonical Family Provisioning Failed — Fallo al provisionar filas de familias.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalFamilyProvisioningFailed extends \RuntimeException {

    public function __construct(string $message) {
        parent::__construct('[canonical_family_provisioning_failed] ' . $message);
    }
}
