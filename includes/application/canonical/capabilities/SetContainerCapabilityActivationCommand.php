<?php
/**
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class SetContainerCapabilityActivationCommand {

    /** @var string */
    private $family_key;

    /** @var int */
    private $container_id;

    /** @var string */
    private $capability_key;

    /** @var bool */
    private $active;

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        string $family_key,
        int $container_id,
        string $capability_key,
        bool $active
    ) {
        $this->family_key = AA_Canonical_Key::assert_valid($family_key, 'family_key');
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_container_id] container_id must be positive.');
        }
        $this->container_id = $container_id;
        $this->capability_key = AA_Canonical_Key::assert_valid($capability_key, 'capability_key');
        $this->active = $active;
    }

    public function family_key(): string {
        return $this->family_key;
    }

    public function container_id(): int {
        return $this->container_id;
    }

    public function capability_key(): string {
        return $this->capability_key;
    }

    public function active(): bool {
        return $this->active;
    }
}
