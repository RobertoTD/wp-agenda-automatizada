<?php
/**
 * Record Task Dismiss Signal Use Case — registra señal "Ignorar" (ocultamiento temporal).
 *
 * Write-only MC13G-A: persiste señal; no altera buckets, feed ni visible_actions.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-work-cycle-policy.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskStateRepository.php';

final class RecordTaskDismissSignalUseCase {

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $task_id = TaskUseCaseSupport::normalize_task_id($input['task_id'] ?? $input['id'] ?? 0);
        $task = TaskUseCaseSupport::find_task($task_id);

        if ($task === null) {
            return TaskUseCaseSupport::fail('task_not_found', 'Tarea no encontrada.');
        }

        if (($task['status'] ?? '') !== 'pending') {
            return TaskUseCaseSupport::fail('task_not_pending', 'Solo se pueden registrar señales en tareas pendientes.');
        }

        $list_id = (int) ($task['list_id'] ?? 0);
        $list = TaskUseCaseSupport::find_active_list($list_id);

        if ($list === null) {
            return TaskUseCaseSupport::fail('list_not_found', 'Lista no encontrada o no activa.');
        }

        $now = TaskUseCaseSupport::resolve_now();
        $cycles = $this->resolve_ignore_cycles($input);
        $dismiss_until = (new AA_Task_Work_Cycle_Policy())->resolve_ignore_until($now, $cycles);
        $task_state = TaskStateRepository::record_dismiss($task_id, $now, $dismiss_until);

        if ($task_state === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo registrar la señal de ocultamiento.');
        }

        return TaskUseCaseSupport::ok(['task_state' => $task_state]);
    }

    /**
     * @param array<string,mixed> $input
     */
    private function resolve_ignore_cycles(array $input): int {
        $raw_cycles = $input['ignore_cycles'] ?? $input['cycles'] ?? AA_Task_Work_Cycle_Policy::DEFAULT_IGNORE_CYCLES;

        return max(1, (int) $raw_cycles);
    }
}
