<?php
/**
 * Canonical Capability Write Bag — aportaciones de capacidades presentes en la petición.
 *
 * Solo claves presentes. Ausencia de clave = omitir esa capacidad.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityWriteBag {

    /** @var array<string, mixed> */
    private $present;

    /**
     * @param array<string, mixed> $present_key_to_raw
     */
    private function __construct(array $present_key_to_raw) {
        $this->present = $present_key_to_raw;
    }

    public static function empty(): self {
        return new self([]);
    }

    /**
     * @param array<string, mixed> $present_key_to_raw
     */
    public static function from_present_fields(array $present_key_to_raw): self {
        $normalized = [];
        foreach ($present_key_to_raw as $key => $raw) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $normalized[$key] = $raw;
        }

        return new self($normalized);
    }

    public function is_empty(): bool {
        return $this->present === [];
    }

    /**
     * @return list<string>
     */
    public function keys(): array {
        return array_keys($this->present);
    }

    public function has(string $capability_key): bool {
        return array_key_exists($capability_key, $this->present);
    }

    /**
     * @return mixed
     * @throws \OutOfBoundsException
     */
    public function raw(string $capability_key) {
        if (!array_key_exists($capability_key, $this->present)) {
            throw new \OutOfBoundsException('Capability write key not present: ' . $capability_key);
        }

        return $this->present[$capability_key];
    }
}
