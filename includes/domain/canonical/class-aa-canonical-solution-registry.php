<?php
/**
 * Canonical Solution Registry — catálogo sellado de definitions de solution.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Solution_Registry {

    /** @var array<string, AA_Canonical_Solution_Definition> */
    private $solutions = [];

    /** @var bool */
    private $frozen = false;

    public function register(AA_Canonical_Solution_Definition $solution): self {
        if ($this->frozen) {
            throw new \LogicException('[solution_registry_frozen] Cannot register on a frozen solution registry.');
        }
        $key = $solution->key();
        if (isset($this->solutions[$key])) {
            throw new \InvalidArgumentException(
                sprintf('[duplicate_solution] Solution "%s" is already registered.', $key)
            );
        }
        $this->solutions[$key] = $solution;

        return $this;
    }

    public function freeze(): self {
        $this->frozen = true;
        return $this;
    }

    public function is_frozen(): bool { return $this->frozen; }

    public function has(string $key): bool {
        $this->assert_frozen();
        return isset($this->solutions[$key]);
    }

    public function get(string $key): AA_Canonical_Solution_Definition {
        $this->assert_frozen();
        if (!isset($this->solutions[$key])) {
            throw new \OutOfBoundsException(
                sprintf('[unknown_solution] Solution "%s" is not registered.', $key)
            );
        }
        return $this->solutions[$key];
    }

    /** @return list<AA_Canonical_Solution_Definition> */
    public function all(): array {
        $this->assert_frozen();
        return array_values($this->solutions);
    }

    private function assert_frozen(): void {
        if (!$this->frozen) {
            throw new \LogicException('[solution_registry_not_frozen] Cannot query solution registry before freeze.');
        }
    }
}
