<?php
/**
 * Resultado de retiro de un registro canónico (IMG-5 inc. 3).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

final class RetireCanonicalRecordResult {

    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_INCOMPLETE = 'incomplete';
    public const STATE_UNCERTAIN = 'uncertain';
    public const STATE_CONFLICT = 'conflict';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_CANCEL_REJECTED = 'cancel_rejected';
    public const STATE_RESOURCE_BUSY = 'resource_busy';
    public const STATE_RECORD_NOT_FOUND = 'record_not_found';
    public const STATE_CONTAINER_NOT_FOUND = 'container_not_found';
    public const STATE_PERSISTENCE_FAILED = 'persistence_failed';
    public const STATE_INTERVENTION_REQUIRED = 'intervention_required';
    public const STATE_SCOPE_OVERLAP = 'scope_overlap';
    public const STATE_FORBIDDEN = 'forbidden';

    /** @var string */
    private $state;

    /** @var array<string, mixed> */
    private $payload;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(string $state, array $payload) {
        $this->state = $state;
        $this->payload = $payload;
    }

    public static function confirmed(int $record_id, int $container_id): self {
        return new self(self::STATE_CONFIRMED, [
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function incomplete(int $record_id, int $container_id, array $payload = []): self {
        return new self(self::STATE_INCOMPLETE, array_merge([
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => true,
        ], $payload));
    }

    public static function uncertain(int $record_id, int $container_id): self {
        return new self(self::STATE_UNCERTAIN, [
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function conflict(int $record_id, int $container_id, array $payload = []): self {
        $can_cancel = array_key_exists('can_cancel', $payload) ? (bool) $payload['can_cancel'] : true;

        return new self(self::STATE_CONFLICT, array_merge([
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => $can_cancel,
            'can_continue' => true,
        ], $payload));
    }

    public static function cancelled(int $record_id, int $container_id): self {
        return new self(self::STATE_CANCELLED, [
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    public static function cancel_rejected(int $record_id, int $container_id): self {
        return new self(self::STATE_CANCEL_REJECTED, [
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => true,
        ]);
    }

    public static function resource_busy(): self {
        return new self(self::STATE_RESOURCE_BUSY, [
            'can_cancel' => false,
            'can_continue' => true,
        ]);
    }

    public static function record_not_found(int $container_id, int $record_id): self {
        return new self(self::STATE_RECORD_NOT_FOUND, [
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    public static function container_not_found(int $container_id): self {
        return new self(self::STATE_CONTAINER_NOT_FOUND, [
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    public static function persistence_failed(): self {
        return new self(self::STATE_PERSISTENCE_FAILED, [
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function intervention_required(int $record_id, int $container_id, array $payload = []): self {
        return new self(self::STATE_INTERVENTION_REQUIRED, array_merge([
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ], $payload));
    }

    public static function scope_overlap(int $record_id, int $container_id): self {
        return new self(self::STATE_SCOPE_OVERLAP, [
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    public static function forbidden(): self {
        return new self(self::STATE_FORBIDDEN, [
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    public function state(): string {
        return $this->state;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array {
        return $this->payload;
    }

    public function record_id(): ?int {
        $value = (int) ($this->payload['record_id'] ?? 0);

        return $value >= 1 ? $value : null;
    }

    public function container_id(): ?int {
        $value = (int) ($this->payload['container_id'] ?? 0);

        return $value >= 1 ? $value : null;
    }

    public function can_cancel(): bool {
        return !empty($this->payload['can_cancel']);
    }

    public function can_continue(): bool {
        return !empty($this->payload['can_continue']);
    }

    public function conflict_code(): ?string {
        $value = $this->payload['conflict_code'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
