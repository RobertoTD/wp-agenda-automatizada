<?php
/**
 * Estado de lectura de una capacidad para un registro (shell).
 *
 * known_value: escalar (amount).
 * known_fields: mapa tipado de campos de una capability (completed).
 * known_collection: lista no vacía de items públicos (images).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityRecordReadState {

    public const STATUS_KNOWN_VALUE = 'known_value';
    public const STATUS_KNOWN_FIELDS = 'known_fields';
    public const STATUS_KNOWN_COLLECTION = 'known_collection';
    public const STATUS_KNOWN_ABSENT = 'known_absent';
    public const STATUS_READ_FAILED = 'read_failed';

    /** @var string */
    private $status;

    /** @var string|null */
    private $value;

    /** @var list<array<string, mixed>>|null */
    private $items;

    /** @var array<string,scalar>|null */
    private $fields;

    /**
     * @param list<array<string, mixed>>|null $items
     */
    private function __construct(string $status, ?string $value = null, ?array $items = null, ?array $fields = null) {
        $this->status = $status;
        $this->value = $value;
        $this->items = $items;
        $this->fields = $fields;
    }

    public static function known_value(string $value): self {
        return new self(self::STATUS_KNOWN_VALUE, $value, null);
    }

    /**
     * @param array<string,scalar> $fields Campos tipados, no ejecutables, de una capability.
     */
    public static function known_fields(array $fields): self {
        if ($fields === []) {
            throw new \InvalidArgumentException(
                '[invalid_read_state] known_fields requires a non-empty field map; use known_absent.'
            );
        }
        $normalized = [];
        foreach ($fields as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/', $key) || !is_scalar($value)) {
                throw new \InvalidArgumentException(
                    '[invalid_read_state] known_fields requires a flat scalar map with canonical keys.'
                );
            }
            $normalized[$key] = $value;
        }
        ksort($normalized);
        return new self(self::STATUS_KNOWN_FIELDS, null, null, $normalized);
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

    /** @return array<string,scalar>|null */
    public function fields(): ?array {
        return $this->fields;
    }

    /**
     * @return array{status:string,value?:string,fields?:array<string,scalar>,items?:list<array<string,mixed>>}
     */
    public function to_array(): array {
        $out = ['status' => $this->status];
        if ($this->status === self::STATUS_KNOWN_VALUE && $this->value !== null) {
            $out['value'] = $this->value;
        }
        if ($this->status === self::STATUS_KNOWN_FIELDS && $this->fields !== null) {
            $out['fields'] = $this->fields;
        }
        if ($this->status === self::STATUS_KNOWN_COLLECTION && $this->items !== null) {
            $out['items'] = $this->items;
        }

        return $out;
    }
}
