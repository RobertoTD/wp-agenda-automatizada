<?php
/**
 * Canonical Registry — Registro en memoria de familias y variantes canónicas.
 *
 * Objeto de dominio instanciable e independiente del producto concreto.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Domain\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Registry {

    /** @var array<string, AA_Family_Definition> */
    private $families = [];

    /** @var array<string, array<string, AA_Variant_Definition>> */
    private $variants = [];

    /** @var bool */
    private $frozen = false;

    /**
     * Registra una definición de familia.
     *
     * @throws \LogicException Si el registro ya fue sellado.
     * @throws \InvalidArgumentException Si la familia ya existe.
     */
    public function register_family(AA_Family_Definition $family): self {
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
        if (!isset($this->variants[$key])) {
            $this->variants[$key] = [];
        }

        return $this;
    }

    /**
     * Registra una definición de variante dentro de una familia existente.
     *
     * @throws \LogicException Si el registro ya fue sellado.
     * @throws \InvalidArgumentException Si la familia no existe o la variante ya está registrada.
     */
    public function register_variant(AA_Variant_Definition $variant): self {
        if ($this->frozen) {
            throw new \LogicException('[registry_frozen] Cannot register variant on a frozen registry.');
        }

        $family_key = $variant->family_key();
        if (!isset($this->families[$family_key])) {
            throw new \InvalidArgumentException(
                sprintf('[unknown_family] Cannot register variant for unknown family "%s".', $family_key)
            );
        }

        $variant_key = $variant->key();
        if (isset($this->variants[$family_key][$variant_key])) {
            throw new \InvalidArgumentException(
                sprintf('[duplicate_variant] Variant "%s" is already registered for family "%s".', $variant_key, $family_key)
            );
        }

        $this->variants[$family_key][$variant_key] = $variant;

        return $this;
    }

    /**
     * Sella el registro verificando las invariantes globales.
     *
     * @throws \LogicException Si alguna familia no tiene registrada su variante predeterminada.
     */
    public function freeze(): self {
        if ($this->frozen) {
            return $this;
        }

        foreach ($this->families as $family_key => $family) {
            $default_variant_key = $family->default_variant_key();
            if (!isset($this->variants[$family_key][$default_variant_key])) {
                throw new \LogicException(
                    sprintf(
                        '[missing_default_variant] Family "%s" requires default variant "%s" to be registered before freeze.',
                        $family_key,
                        $default_variant_key
                    )
                );
            }
        }

        $this->frozen = true;

        return $this;
    }

    public function is_frozen(): bool {
        return $this->frozen;
    }

    public function has_family(string $key): bool {
        return isset($this->families[$key]);
    }

    public function family(string $key): ?AA_Family_Definition {
        return $this->families[$key] ?? null;
    }

    public function has_variant(string $family_key, string $variant_key): bool {
        return isset($this->variants[$family_key][$variant_key]);
    }

    public function variant(string $family_key, string $variant_key): ?AA_Variant_Definition {
        return $this->variants[$family_key][$variant_key] ?? null;
    }

    /**
     * @return array<int, AA_Variant_Definition>
     */
    public function variants_for(string $family_key): array {
        if (!isset($this->variants[$family_key])) {
            return [];
        }

        return array_values($this->variants[$family_key]);
    }

    /**
     * @return array<int, AA_Family_Definition>
     */
    public function families(): array {
        return array_values($this->families);
    }
}
