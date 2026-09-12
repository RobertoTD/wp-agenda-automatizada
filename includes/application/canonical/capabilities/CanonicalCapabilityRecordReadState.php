<?php
/**
 * Estado de lectura de una capacidad para un registro (shell).
 *
 * known_value: escalar (amount).
 * known_collection: lista no vacía de items públicos (images).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityRecordReadState {

    public const STATUS_KNOWN_VALUE = 'known_value';
    public const STATUS_KNOWN_COLLECTION = 'known_collection';
    public const STATUS_KNOWN_ABSENT = 'known_absent';
    public const STATUS_READ_FAILED = 'read_failed';

    /** @var string */
    private $status;

    /** @var string|null */
    private $value;

    /** @var list<array<string, mixed>>|null */
    private $items;

    /**
     * @param list<array<string, mixed>>|null $items
     */
    private function __construct(string $status, ?string $value = null, ?array $items = null) {
        $this->status = $status;
        $this->value = $value;
        $this->items = $items;
    }

    public static function known_value(string $value): self {
        return new self(self::STATUS_KNOWN_VALUE, $value, null);
    }

    /**
     * @param list<array<string, mixed>> $items Colección no vacía de DTOs públicos.
     */
    public static function known_collection(array $items): self {
        if ($items === []) {
            throw new \InvalidArgumentException(
                '[invalid_read_state] known_collection requires a non-empty items list; use known_absent.'
            );
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException(
                    '[invalid_read_state] known_collection items must be arrays.'
                );
            }
            $normalized[] = $item;
        }

        return new self(self::STATUS_KNOWN_COLLECTION, null, $normalized);
    }

    public static function known_absent(): self {
        return new self(self::STATUS_KNOWN_ABSENT, null, null);
    }

    public static function read_failed(): self {
        return new self(self::STATUS_READ_FAILED, null, null);
    }

    public function status(): string {
        return $this->status;
    }

    public function value(): ?string {
        return $this->value;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function items(): ?array {
        return $this->items;
    }

    /**
     * @return array{status:string,value?:string,items?:list<array<string,mixed>>}
     */
    public function to_array(): array {
        $out = ['status' => $this->status];
        if ($this->status === self::STATUS_KNOWN_VALUE && $this->value !== null) {
            $out['value'] = $this->value;
        }
        if ($this->status === self::STATUS_KNOWN_COLLECTION && $this->items !== null) {
            $out['items'] = $this->items;
        }

        return $out;
    }
}
