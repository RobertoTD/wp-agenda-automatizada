<?php
/**
 * Set Canonical Family Enabled Command — entrada tipada de mutación.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class SetCanonicalFamilyEnabledCommand {

    /** @var string */
    private $family_key;

    /** @var bool */
    private $enabled;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $family_key, bool $enabled) {
        if (!class_exists('AA_Canonical_Key')) {
            throw new \LogicException('[canonical_unavailable] AA_Canonical_Key is required.');
        }

        $this->family_key = AA_Canonical_Key::assert_valid($family_key, 'family_key');
        $this->enabled = $enabled;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function enabled(): bool {
        return $this->enabled;
    }
}
