<?php
/**
 * Canonical Family Not Provisioned — familia declarada sin fila en BD.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalFamilyNotProvisioned extends \RuntimeException {

    /** @var string */
    private $family_key;

    public function __construct(string $family_key) {
        $this->family_key = $family_key;
        parent::__construct('[family_not_provisioned] Family row is missing: ' . $family_key);
    }

    public function family_key(): string {
        return $this->family_key;
    }
}
