<?php
/**
 * Resultado de retiro de un contenedor canónico (IMG-5 inc. 4).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

final class RetireCanonicalContainerResult {

    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_INCOMPLETE = 'incomplete';
    public const STATE_UNCERTAIN = 'uncertain';
    public const STATE_CONFLICT = 'conflict';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_CANCEL_REJECTED = 'cancel_rejected';
    public const STATE_RESOURCE_BUSY = 'resource_busy';
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

    public static function confirmed(int $container_id): self {
        return new self(self::STATE_CONFIRMED, [
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function incomplete(int $container_id, array $payload = []): self {
        return new self(self::STATE_INCOMPLETE, array_merge([
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => true,
        ], $payload));
    }

    public static function uncertain(int $container_id): self {
        return new self(self::STATE_UNCERTAIN, [
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function conflict(int $container_id, array $payload = []): self {
        $can_cancel = array_key_exists('can_cancel', $payload) ? (bool) $payload['can_cancel'] : true;

        return new self(self::STATE_CONFLICT, array_merge([
            'container_id' => $container_id,
            'can_cancel' => $can_cancel,
            'can_continue' => true,
        ], $payload));
    }

    public static function cancelled(int $container_id): self {
        return new self(self::STATE_CANCELLED, [
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    public static function cancel_rejected(int $container_id): self {
        return new self(self::STATE_CANCEL_REJECTED, [
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
    public static function intervention_required(int $container_id, array $payload = []): self {
        return new self(self::STATE_INTERVENTION_REQUIRED, array_merge([
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ], $payload));
    }

    public static function scope_overlap(int $container_id): self {
        return new self(self::STATE_SCOPE_OVERLAP, [
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
