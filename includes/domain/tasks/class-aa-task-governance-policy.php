<?php
/**
 * Task Governance Policy — editabilidad y gobernanza de tareas (dominio puro).
 *
 * Sin WordPress ni SQL. Extensible para overrides explícitos futuros (p. ej. user_editable).
 */

defined('ABSPATH') or die('No direct access');

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
