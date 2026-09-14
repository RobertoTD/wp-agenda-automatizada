<?php
/**
 * Resultado de abrir/reanudar y capturar inventario de purge canónico (IMG-5 inc. 2).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Images
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalPurgeCaptureResult {

    public const STATE_CAPTURE_PROGRESS = 'capture_progress';
    public const STATE_CAPTURE_COMPLETE = 'capture_complete';
    public const STATE_CONFLICT = 'conflict';
    public const STATE_SCOPE_OVERLAP = 'scope_overlap';
    public const STATE_RESOURCE_BUSY = 'resource_busy';
    public const STATE_INVALID_SCOPE = 'invalid_scope';
    public const STATE_SCHEMA_NOT_READY = 'schema_not_ready';
    public const STATE_PERSISTENCE_FAILED = 'persistence_failed';

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

    /**
     * @param array<string, mixed> $payload
     */
    public static function capture_progress(array $payload): self {
        return new self(self::STATE_CAPTURE_PROGRESS, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function capture_complete(array $payload): self {
        return new self(self::STATE_CAPTURE_COMPLETE, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function conflict(array $payload): self {
        return new self(self::STATE_CONFLICT, $payload);
    }

    public static function scope_overlap(): self {
        return new self(self::STATE_SCOPE_OVERLAP, []);
    }

    public static function resource_busy(): self {
        return new self(self::STATE_RESOURCE_BUSY, []);
    }

    public static function invalid_scope(): self {
        return new self(self::STATE_INVALID_SCOPE, []);
    }

    public static function schema_not_ready(): self {
        return new self(self::STATE_SCHEMA_NOT_READY, []);
    }

    public static function persistence_failed(): self {
        return new self(self::STATE_PERSISTENCE_FAILED, []);
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

    public function mandate_id(): ?string {
        $value = $this->payload['mandate_id'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function purge_run_id(): ?int {
        $value = (int) ($this->payload['purge_run_id'] ?? 0);
        return $value >= 1 ? $value : null;
    }

    public function is_capture_complete(): bool {
        return !empty($this->payload['capture_complete']);
    }

    public function batches_prepared(): bool {
        return !empty($this->payload['batches_prepared']);
    }

    public function prepared_batch_count(): int {
        return (int) ($this->payload['prepared_batch_count'] ?? 0);
    }

    public function capture_conflict_code(): ?string {
        $value = $this->payload['capture_conflict_code'] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }
}
