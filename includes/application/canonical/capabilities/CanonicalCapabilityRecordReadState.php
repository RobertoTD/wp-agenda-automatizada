<?php
/**
 * Estado de lectura de una capacidad para un registro (shell).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalCapabilityRecordReadState {

    public const STATUS_KNOWN_VALUE = 'known_value';
    public const STATUS_KNOWN_ABSENT = 'known_absent';
    public const STATUS_READ_FAILED = 'read_failed';

    /** @var string */
    private $status;

    /** @var string|null */
    private $value;

    private function __construct(string $status, ?string $value = null) {
        $this->status = $status;
        $this->value = $value;
    }

    public static function known_value(string $value): self {
        return new self(self::STATUS_KNOWN_VALUE, $value);
    }

    public static function known_absent(): self {
        return new self(self::STATUS_KNOWN_ABSENT, null);
    }

    public static function read_failed(): self {
        return new self(self::STATUS_READ_FAILED, null);
    }

    public function status(): string {
        return $this->status;
    }

    public function value(): ?string {
        return $this->value;
    }

    /**
     * @return array{status:string,value?:string}
     */
    public function to_array(): array {
        $out = ['status' => $this->status];
        if ($this->status === self::STATUS_KNOWN_VALUE && $this->value !== null) {
            $out['value'] = $this->value;
        }

        return $out;
    }
}
