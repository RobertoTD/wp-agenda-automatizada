<?php
/**
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class SetFamilyCapabilityDefaultCommand {

    /** @var string */
    private $family_key;

    /** @var string */
    private $capability_key;

    /** @var bool */
    private $enabled;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(string $family_key, string $capability_key, bool $enabled) {
        $this->family_key = AA_Canonical_Key::assert_valid($family_key, 'family_key');
        $this->capability_key = AA_Canonical_Key::assert_valid($capability_key, 'capability_key');
        $this->enabled = $enabled;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function capability_key(): string {
        return $this->capability_key;
    }

    public function enabled(): bool {
        return $this->enabled;
    }
}
