<?php
/**
 * Task Governance Policy — editabilidad y gobernanza de tareas (dominio puro).
 *
 * Sin WordPress ni SQL. Extensible para overrides explícitos futuros (p. ej. user_editable).
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__) . '/executable/class-aa-executable-contract.php';

final class AA_Task_Governance_Policy {

    public const MANAGED_BY_USER = 'user';

    /**
     * @param array<string,mixed> $task Fila o payload con al menos managed_by.
     */
    public function can_edit_task(array $task): bool {
        $managed_by = $this->resolve_managed_by($task);

        return $managed_by === self::MANAGED_BY_USER;
    }

    /**
     * @param array<string,mixed> $task
     */
    public function can_archive_task(array $task): bool {
        if (!$this->is_user_task($task)) {
            return false;
        }

        return !$this->is_archived_task($task);
    }

    /**
     * @param array<string,mixed> $task
     */
    public function can_restore_task(array $task): bool {
        if (!$this->is_user_task($task)) {
            return false;
        }

        return $this->is_archived_task($task);
    }

    /**
     * @param array<string,mixed> $task
     */
    private function is_user_task(array $task): bool {
        return $this->resolve_source_category($task) === AA_Executable_Contract::SOURCE_CATEGORY_USER
            && $this->resolve_managed_by($task) === self::MANAGED_BY_USER;
    }

    /**
     * @param array<string,mixed> $task
     */
    private function is_archived_task(array $task): bool {
        $archived_at = $task['archived_at'] ?? null;

        if ($archived_at === null || $archived_at === '') {
            return false;
        }

        return trim((string) $archived_at) !== '';
    }

    /**
     * @param array<string,mixed> $task
     */
    private function resolve_source_category(array $task): string {
        $category = is_string($task['source_category'] ?? null)
            ? strtolower(trim((string) $task['source_category']))
            : '';

        if ($category !== '') {
            return $category;
        }

        return AA_Executable_Contract::SOURCE_CATEGORY_USER;
    }

    /**
     * @param array<string,mixed> $task
     */
    private function resolve_managed_by(array $task): string {
        $managed_by = is_string($task['managed_by'] ?? null)
            ? strtolower(trim((string) $task['managed_by']))
            : '';

        if ($managed_by === '') {
            return self::MANAGED_BY_USER;
        }

        return $managed_by;
    }
}
