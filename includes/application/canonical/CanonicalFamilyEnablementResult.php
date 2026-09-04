<?php
/**
 * Canonical Family Enablement Result — resultado tipado de set_enabled.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalFamilyEnablementResult {

    /** @var string */
    private $family_key;

    /** @var bool */
    private $is_enabled;

    /** @var bool */
    private $changed;

    public function __construct(string $family_key, bool $is_enabled, bool $changed) {
        $this->family_key = $family_key;
        $this->is_enabled = $is_enabled;
        $this->changed = $changed;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function is_enabled(): bool {
        return $this->is_enabled;
    }

    public function changed(): bool {
        return $this->changed;
    }
}
