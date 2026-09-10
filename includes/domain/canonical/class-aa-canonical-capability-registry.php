<?php
/**
 * Canonical Capability Registry — Catálogo en memoria de capacidades implementadas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Registry {

    /** @var array<string, AA_Canonical_Capability_Definition> */
    private $capabilities = [];

    /** @var bool */
    private $frozen = false;

    /**
     * @throws \LogicException
     * @throws \InvalidArgumentException
     */
    public function register(AA_Canonical_Capability_Definition $capability): self {
        if ($this->frozen) {
            throw new \LogicException('[capability_registry_frozen] Cannot register on a frozen capability registry.');
        }

        $key = $capability->key();
        if (isset($this->capabilities[$key])) {
            throw new \InvalidArgumentException(
                sprintf('[duplicate_capability] Capability "%s" is already registered.', $key)
            );
        }

        $this->capabilities[$key] = $capability;

        return $this;
    }

    public function freeze(): self {
        $this->frozen = true;

        return $this;
    }

    public function is_frozen(): bool {
        return $this->frozen;
    }

    /**
     * @throws \LogicException
     */
    public function has(string $key): bool {
        $this->assert_frozen();

        return isset($this->capabilities[$key]);
    }

    /**
     * @throws \LogicException
     * @throws \OutOfBoundsException
     */
    public function get(string $key): AA_Canonical_Capability_Definition {
        $this->assert_frozen();

        if (!isset($this->capabilities[$key])) {
            throw new \OutOfBoundsException(
                sprintf('[unknown_capability] Capability "%s" is not registered.', $key)
            );
        }

        return $this->capabilities[$key];
    }

    /**
     * @return list<AA_Canonical_Capability_Definition>
     * @throws \LogicException
     */
    public function all(): array {
        $this->assert_frozen();

        return array_values($this->capabilities);
    }

    private function assert_frozen(): void {
        if (!$this->frozen) {
            throw new \LogicException('[capability_registry_not_frozen] Cannot query capability registry before freeze.');
        }
    }
}
