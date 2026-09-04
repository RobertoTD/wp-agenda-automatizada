<?php
/**
 * Canonical Family Unknown — familia no declarada en el registry.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalFamilyUnknown extends \RuntimeException {

    /** @var string */
    private $family_key;

    public function __construct(string $family_key) {
        $this->family_key = $family_key;
        parent::__construct('[unknown_family] Family key is not declared: ' . $family_key);
    }

    public function family_key(): string {
        return $this->family_key;
    }
}
