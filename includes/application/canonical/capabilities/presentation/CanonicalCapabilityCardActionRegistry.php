<?php
/** Registry sellado de providers para el slot común de acciones de card. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityCardActionRegistry {
    /** @var array<string, CanonicalCapabilityCardActionProvider> */
    private $providers = [];
    /** @var bool */
    private $frozen = false;

    public function register(CanonicalCapabilityCardActionProvider $provider): self {
        if ($this->frozen) {
            throw new \LogicException('[capability_card_action_registry_frozen] Cannot register on a frozen registry.');
        }
        $key = $provider->capability_key();
        if (isset($this->providers[$key])) {
            throw new \InvalidArgumentException('[duplicate_capability_card_action_provider] Capability already has a card action provider.');
        }
        $this->providers[$key] = $provider;
        return $this;
    }

    public function freeze(): self { $this->frozen = true; return $this; }

    /** @return list<CanonicalCapabilityCardActionProvider> */
    public function all(): array {
        if (!$this->frozen) {
            throw new \LogicException('[capability_card_action_registry_not_frozen] Cannot query registry before freeze.');
        }
        return array_values($this->providers);
    }
}
