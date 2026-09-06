<?php
/**
 * Canonical Registry — Registro en memoria de familias canónicas.
 *
 * Objeto de dominio instanciable e independiente del producto concreto.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Registry {

    /** @var array<string, AA_Canonical_Family_Definition> */
    private $families = [];

    /** @var bool */
    private $frozen = false;

    /**
     * Registra una definición de familia.
     *
     * @throws \LogicException Si el registro ya fue sellado.
     * @throws \InvalidArgumentException Si la familia ya existe.
     */
    public function register_family(AA_Canonical_Family_Definition $family): self {
        if ($this->frozen) {
            throw new \LogicException('[registry_frozen] Cannot register family on a frozen registry.');
        }

        $key = $family->key();
        if (isset($this->families[$key])) {
            throw new \InvalidArgumentException(
                sprintf('[duplicate_family] Family "%s" is already registered.', $key)
            );
        }

        $this->families[$key] = $family;

        return $this;
    }

    /**
     * Sella el registro.
     */
    public function freeze(): self {
        $this->frozen = true;

        return $this;
    }

    public function is_frozen(): bool {
        return $this->frozen;
    }

    /**
     * @throws \LogicException Si el registro no ha sido sellado.
     */
    public function has_family(string $key): bool {
        $this->assert_frozen();

        return isset($this->families[$key]);
    }

    /**
     * @throws \LogicException Si el registro no ha sido sellado.
     * @throws \OutOfBoundsException Si la familia no existe.
     */
    public function family(string $key): AA_Canonical_Family_Definition {
        $this->assert_frozen();

        if (!isset($this->families[$key])) {
            throw new \OutOfBoundsException(
                sprintf('[unknown_family] Family "%s" is not registered.', $key)
            );
        }

        return $this->families[$key];
    }

    /**
     * @return array<int, AA_Canonical_Family_Definition>
     * @throws \LogicException Si el registro no ha sido sellado.
     */
    public function families(): array {
        $this->assert_frozen();

        return array_values($this->families);
    }

    private function assert_frozen(): void {
        if (!$this->frozen) {
            throw new \LogicException('[registry_not_frozen] Cannot resolve or query registry before freeze.');
        }
    }
}
