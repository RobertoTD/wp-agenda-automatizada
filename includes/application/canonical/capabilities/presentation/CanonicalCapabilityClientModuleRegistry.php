<?php
/** Registry sellado de módulos cliente de capabilities. */
defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityClientModuleRegistry {
    /** @var array<string, CanonicalCapabilityClientModule> */
    private $modules = [];
    /** @var bool */
    private $frozen = false;

    public function register(CanonicalCapabilityClientModule $module): self {
        if ($this->frozen) {
            throw new \LogicException('[capability_client_module_registry_frozen] Cannot register on a frozen registry.');
        }
        $key = $module->capability_key();
        if (isset($this->modules[$key])) {
            throw new \InvalidArgumentException('[duplicate_capability_client_module] Capability already has a client module.');
        }
        $this->modules[$key] = $module;
        return $this;
    }

    public function freeze(): self { $this->frozen = true; return $this; }

    /** @return list<CanonicalCapabilityClientModule> */
    public function offered(array $capability_contributions): array {
        if (!$this->frozen) {
            throw new \LogicException('[capability_client_module_registry_not_frozen] Cannot query registry before freeze.');
        }
        $offered = [];
        foreach ($this->modules as $key => $module) {
            if (isset($capability_contributions[$key]) && is_array($capability_contributions[$key]) && !empty($capability_contributions[$key]['offered'])) {
                $offered[] = $module;
            }
        }
        return $offered;
    }
}
