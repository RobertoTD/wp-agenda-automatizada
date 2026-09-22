<?php
/** Registry sellado de providers para el slot común de metadata de card. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityCardMetadataRegistry {
    /** @var array<string,CanonicalCapabilityCardMetadataProvider> */
    private $providers = [];
    /** @var bool */
    private $frozen = false;

    public function register(CanonicalCapabilityCardMetadataProvider $provider): self {
        if ($this->frozen) {
            throw new \LogicException('[capability_card_metadata_registry_frozen] Cannot register on a frozen registry.');
        }
        $key = $provider->capability_key();
        if (isset($this->providers[$key])) {
            throw new \InvalidArgumentException('[duplicate_capability_card_metadata_provider] Capability already has a card metadata provider.');
        }
        $this->providers[$key] = $provider;
        return $this;
    }

    public function freeze(): self { $this->frozen = true; return $this; }

    /** @return list<CanonicalCapabilityCardMetadataProvider> */
    public function all(): array {
        if (!$this->frozen) {
            throw new \LogicException('[capability_card_metadata_registry_not_frozen] Cannot query registry before freeze.');
        }
        return array_values($this->providers);
    }
}
