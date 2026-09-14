<?php
/**
 * Resultado de retiro de una imagen canónica (IMG-5 inc. 5).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

final class RetireCanonicalRecordImageResult {

    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_INCOMPLETE = 'incomplete';
    public const STATE_UNCERTAIN = 'uncertain';
    public const STATE_CONFLICT = 'conflict';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_CANCEL_REJECTED = 'cancel_rejected';
    public const STATE_RESOURCE_BUSY = 'resource_busy';
    public const STATE_IMAGE_NOT_FOUND = 'image_not_found';
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

    public static function confirmed(int $image_id, int $record_id, int $container_id): self {
        return new self(self::STATE_CONFIRMED, [
            'image_id' => $image_id,
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function incomplete(int $image_id, int $record_id, int $container_id, array $payload = []): self {
        return new self(self::STATE_INCOMPLETE, array_merge([
            'image_id' => $image_id,
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => true,
        ], $payload));
    }

    public static function uncertain(int $image_id, int $record_id, int $container_id): self {
        return new self(self::STATE_UNCERTAIN, [
            'image_id' => $image_id,
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function conflict(int $image_id, int $record_id, int $container_id, array $payload = []): self {
        $can_cancel = array_key_exists('can_cancel', $payload) ? (bool) $payload['can_cancel'] : true;

        return new self(self::STATE_CONFLICT, array_merge([
            'image_id' => $image_id,
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => $can_cancel,
            'can_continue' => true,
        ], $payload));
    }

    public static function cancelled(int $image_id, int $record_id, int $container_id): self {
        return new self(self::STATE_CANCELLED, [
            'image_id' => $image_id,
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ]);
    }

    public static function cancel_rejected(int $image_id, int $record_id, int $container_id): self {
        return new self(self::STATE_CANCEL_REJECTED, [
            'image_id' => $image_id,
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

    public static function image_not_found(?int $image_id = null, ?int $record_id = null, ?int $container_id = null): self {
        $payload = [
            'can_cancel' => false,
            'can_continue' => false,
        ];
        if ($image_id !== null && $image_id >= 1) {
            $payload['image_id'] = $image_id;
        }
        if ($record_id !== null && $record_id >= 1) {
            $payload['record_id'] = $record_id;
        }
        if ($container_id !== null && $container_id >= 1) {
            $payload['container_id'] = $container_id;
        }

        return new self(self::STATE_IMAGE_NOT_FOUND, $payload);
    }

    public static function container_not_found(?int $container_id = null): self {
        $payload = [
            'can_cancel' => false,
            'can_continue' => false,
        ];
        if ($container_id !== null && $container_id >= 1) {
            $payload['container_id'] = $container_id;
        }

        return new self(self::STATE_CONTAINER_NOT_FOUND, $payload);
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
    public static function intervention_required(int $image_id, int $record_id, int $container_id, array $payload = []): self {
        return new self(self::STATE_INTERVENTION_REQUIRED, array_merge([
            'image_id' => $image_id,
            'record_id' => $record_id,
            'container_id' => $container_id,
            'can_cancel' => false,
            'can_continue' => false,
        ], $payload));
    }

    public static function scope_overlap(int $image_id, int $record_id, int $container_id): self {
        return new self(self::STATE_SCOPE_OVERLAP, [
            'image_id' => $image_id,
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

    public function image_id(): ?int {
        $value = (int) ($this->payload['image_id'] ?? 0);

        return $value >= 1 ? $value : null;
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
