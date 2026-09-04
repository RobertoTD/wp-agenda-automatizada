<?php
/**
 * Canonical Family Enablement Status — estado tipado de una familia declarada.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalFamilyEnablementStatus {

    /** @var string */
    private $family_key;

    /** @var bool */
    private $provisioned;

    /** @var bool */
    private $is_enabled;

    public function __construct(string $family_key, bool $provisioned, bool $is_enabled) {
        $this->family_key = $family_key;
        $this->provisioned = $provisioned;
        $this->is_enabled = $provisioned ? $is_enabled : false;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function is_provisioned(): bool {
        return $this->provisioned;
    }

    public function is_enabled(): bool {
        return $this->is_enabled;
    }
}
