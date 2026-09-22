<?php
/** Registry sellado de manifiestos Capability Package v0. */
defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Package_Registry {
    /** @var array<string,AA_Canonical_Capability_Package_Definition> */
    private $packages = [];
    /** @var bool */
    private $frozen = false;

    public function register(AA_Canonical_Capability_Package_Definition $package): self {
        if ($this->frozen) {
            throw new \LogicException('[capability_package_registry_frozen] Cannot register on a frozen package registry.');
        }
        $key = $package->key();
        if (isset($this->packages[$key])) {
            throw new \InvalidArgumentException('[duplicate_capability_package] Capability package is already registered.');
        }
        $this->packages[$key] = $package;
        return $this;
    }

    public function freeze(): self { $this->frozen = true; return $this; }
    public function is_frozen(): bool { return $this->frozen; }

    public function has(string $key): bool {
        $this->assert_frozen();
        return isset($this->packages[$key]);
    }

    public function get(string $key): AA_Canonical_Capability_Package_Definition {
        $this->assert_frozen();
        if (!isset($this->packages[$key])) {
            throw new \OutOfBoundsException('[unknown_capability_package] Capability package is not registered.');
        }
        return $this->packages[$key];
    }

    /** @return list<AA_Canonical_Capability_Package_Definition> */
    public function all(): array {
        $this->assert_frozen();
        return array_values($this->packages);
    }

    private function assert_frozen(): void {
        if (!$this->frozen) {
            throw new \LogicException('[capability_package_registry_not_frozen] Cannot query package registry before freeze.');
        }
    }
}
