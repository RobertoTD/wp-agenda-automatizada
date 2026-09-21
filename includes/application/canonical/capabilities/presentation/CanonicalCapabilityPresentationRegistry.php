<?php
/**
 * Registry sellado de metadata de presentación de capabilities.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities\Presentation
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityPresentationRegistry {

    /** @var array<string, CanonicalCapabilityPresentationDefinition> */
    private $definitions = [];

    /** @var bool */
    private $frozen = false;

    /** @throws \LogicException|\InvalidArgumentException */
    public function register(CanonicalCapabilityPresentationDefinition $definition): self {
        if ($this->frozen) {
            throw new \LogicException('[capability_presentation_registry_frozen] Cannot register on a frozen registry.');
        }

        $key = $definition->capability_key();
        if (isset($this->definitions[$key])) {
            throw new \InvalidArgumentException(
                sprintf('[duplicate_capability_presentation] Capability "%s" already has presentation metadata.', $key)
            );
        }

        $this->definitions[$key] = $definition;
        return $this;
    }

    public function freeze(): self {
        $this->frozen = true;
        return $this;
    }

    public function is_frozen(): bool {
        return $this->frozen;
    }

    /** @throws \LogicException */
    public function has(string $capability_key): bool {
        $this->assert_frozen();
        return isset($this->definitions[$capability_key]);
    }

    /** @throws \LogicException|\OutOfBoundsException */
    public function get(string $capability_key): CanonicalCapabilityPresentationDefinition {
        $this->assert_frozen();
        if (!isset($this->definitions[$capability_key])) {
            throw new \OutOfBoundsException(
                sprintf('[unknown_capability_presentation] Capability "%s" has no presentation metadata.', $capability_key)
            );
        }
        return $this->definitions[$capability_key];
    }

    /** @return list<CanonicalCapabilityPresentationDefinition> */
    public function all(): array {
        $this->assert_frozen();
        return array_values($this->definitions);
    }

    private function assert_frozen(): void {
        if (!$this->frozen) {
            throw new \LogicException('[capability_presentation_registry_not_frozen] Cannot query registry before freeze.');
        }
    }
}
