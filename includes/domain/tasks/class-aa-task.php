<?php
/**
 * Task — Value Object normalizado para tareas (Listas/Tareas).
 *
 * Sin WordPress, SQL ni reglas de priorización.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Task {

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_MISSED = 'missed';

    /** @var int */
    private $id;

    /** @var int */
    private $list_id;

    /** @var string */
    private $title;

    /** @var string|null */
    private $notes;

    /** @var string */
    private $status;

    /** @var string */
    private $source;

    /** @var string */
    private $default_bucket;

    /** @var int */
    private $importance;

    /** @var string|null */
    private $due_at;

    /** @var string|null */
    private $execution_available_at;

    /** @var int */
    private $position;

    /** @var string|null */
    private $completed_at;

    /** @var string|null */
    private $archived_at;

    /** @var string|null */
    private $origin_key;

    /**
     * @param array<string,mixed> $data
     */
    private function __construct(array $data) {
        $this->id = (int) ($data['id'] ?? 0);
        $this->list_id = (int) ($data['list_id'] ?? 0);
        $this->title = (string) ($data['title'] ?? '');
        $this->notes = self::nullable_string($data['notes'] ?? null);
        $this->status = self::normalize_status($data['status'] ?? self::STATUS_PENDING);
        $this->source = self::normalize_string($data['source'] ?? 'user', 'user');
        $this->default_bucket = self::normalize_default_bucket($data['default_bucket'] ?? 'primary');
        $this->importance = (int) ($data['importance'] ?? 0);
        $this->due_at = self::nullable_string($data['due_at'] ?? null);
        $this->execution_available_at = self::nullable_string($data['execution_available_at'] ?? null);
        $this->position = (int) ($data['position'] ?? 0);
        $this->completed_at = self::nullable_string($data['completed_at'] ?? null);
        $this->archived_at = self::nullable_string($data['archived_at'] ?? null);
        $this->origin_key = self::nullable_trimmed_string($data['origin_key'] ?? null);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function from_array(array $data): self {
        return new self($data);
    }

    /**
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'list_id' => $this->list_id,
            'title' => $this->title,
            'notes' => $this->notes,
            'status' => $this->status,
            'source' => $this->source,
            'default_bucket' => $this->default_bucket,
            'importance' => $this->importance,
            'due_at' => $this->due_at,
            'execution_available_at' => $this->execution_available_at,
            'position' => $this->position,
            'completed_at' => $this->completed_at,
            'archived_at' => $this->archived_at,
        ];
    }

    public function id(): int {
        return $this->id;
    }

    public function list_id(): int {
        return $this->list_id;
    }

    public function title(): string {
        return $this->title;
    }

    public function status(): string {
        return $this->status;
    }

    public function default_bucket(): string {
        return $this->default_bucket;
    }

    public function importance(): int {
        return $this->importance;
    }

    /**
     * @return string|null Y-m-d H:i:s
     */
    public function due_at(): ?string {
        return $this->due_at;
    }

    /**
     * @return string|null Y-m-d H:i:s
     */
    public function execution_available_at(): ?string {
        return $this->execution_available_at;
    }

    public function position(): int {
        return $this->position;
    }

    /**
     * @return string|null Y-m-d H:i:s
     */
    public function archived_at(): ?string {
        return $this->archived_at;
    }

    public function is_archived(): bool {
        return $this->archived_at !== null && trim($this->archived_at) !== '';
    }

    public function is_pending(): bool {
        return $this->status === self::STATUS_PENDING;
    }

    public function is_done(): bool {
        return $this->status === self::STATUS_DONE;
    }

    public function is_missed(): bool {
        return $this->status === self::STATUS_MISSED;
    }

    public function origin_key(): ?string {
        return $this->origin_key;
    }

    /**
     * Helper legacy/deprecado de compatibilidad.
     *
     * Fuente canónica de producto: AA_Task_Execution_Timing_Policy::is_overdue()
     * / evaluate() (capa 4, due_at <= now, con aa_timezone).
     * Este VO no instancia la policy (no tiene timezone de sitio); solo alinea
     * la frontera a <= para no contradecir la semántica canónica.
     *
     * @param string $now Y-m-d H:i:s
     * @deprecated Use AA_Task_Execution_Timing_Policy::is_overdue().
     */
    public function is_overdue(string $now): bool {
        if (!$this->is_pending() || $this->due_at === null || trim($this->due_at) === '') {
            return false;
        }

        $due_ts = strtotime($this->due_at);
        $now_ts = strtotime($now);

        if ($due_ts === false || $now_ts === false) {
            return false;
        }

        return $due_ts <= $now_ts;
    }

    /**
     * Complemento temporal legacy de is_overdue(): due estrictamente posterior a now.
     * due_at == now ya no es futura (es vencida en la frontera canónica).
     *
     * @param string $now Y-m-d H:i:s
     */
    public function has_upcoming_due(string $now): bool {
        if (!$this->is_pending() || $this->due_at === null || trim($this->due_at) === '') {
            return false;
        }

        $due_ts = strtotime($this->due_at);
        $now_ts = strtotime($now);

        if ($due_ts === false || $now_ts === false) {
            return false;
        }

        return $due_ts > $now_ts;
    }

    /**
     * @param mixed $value
     */
    private static function nullable_string($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @param mixed $value
     */
    private static function nullable_trimmed_string($value): ?string {
        if ($value === null || !is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_string($value, string $default): string {
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_status($value): string {
        $status = is_string($value) ? strtolower(trim($value)) : '';

        if ($status === self::STATUS_DONE) {
            return self::STATUS_DONE;
        }

        if ($status === self::STATUS_MISSED) {
            return self::STATUS_MISSED;
        }

        return self::STATUS_PENDING;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_default_bucket($value): string {
        $bucket = is_string($value) ? strtolower(trim($value)) : '';

        if ($bucket === 'secondary') {
            return 'secondary';
        }

        return 'primary';
    }
}
