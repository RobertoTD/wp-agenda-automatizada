<?php
/**
 * Task List — Value Object normalizado para listas (Listas/Tareas).
 *
 * Sin WordPress, SQL ni reglas de priorización.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Task_List {

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    /** @var int */
    private $id;

    /** @var string */
    private $title;

    /** @var string|null */
    private $description;

    /** @var string */
    private $owner_type;

    /** @var int */
    private $importance;

    /** @var string */
    private $status;

    /** @var int */
    private $position;

    /**
     * @param array<string,mixed> $data
     */
    private function __construct(array $data) {
        $this->id = (int) ($data['id'] ?? 0);
        $this->title = (string) ($data['title'] ?? '');
        $this->description = self::nullable_string($data['description'] ?? null);
        $this->owner_type = self::normalize_string($data['owner_type'] ?? 'user', 'user');
        $this->importance = (int) ($data['importance'] ?? 0);
        $this->status = self::normalize_status($data['status'] ?? self::STATUS_ACTIVE);
        $this->position = (int) ($data['position'] ?? 0);
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
            'title' => $this->title,
            'description' => $this->description,
            'owner_type' => $this->owner_type,
            'importance' => $this->importance,
            'status' => $this->status,
            'position' => $this->position,
        ];
    }

    public function id(): int {
        return $this->id;
    }

    public function title(): string {
        return $this->title;
    }

    public function importance(): int {
        return $this->importance;
    }

    public function position(): int {
        return $this->position;
    }

    public function status(): string {
        return $this->status;
    }

    public function is_active(): bool {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function is_archived(): bool {
        return $this->status === self::STATUS_ARCHIVED;
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

        if ($status === self::STATUS_ARCHIVED) {
            return self::STATUS_ARCHIVED;
        }

        return self::STATUS_ACTIVE;
    }
}
