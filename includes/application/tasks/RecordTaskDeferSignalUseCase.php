<?php
/**
 * Record Task Defer Signal Use Case — registra señal "Ahora no" (postergación).
 *
 * Write-only MC13G-A: persiste señal; no altera buckets, feed ni visible_actions.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskStateRepository.php';

final class RecordTaskDeferSignalUseCase {

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
        $task_state = TaskStateRepository::record_defer($task_id, $now);

        if ($task_state === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo registrar la señal de postergación.');
        }

        return TaskUseCaseSupport::ok(['task_state' => $task_state]);
    }
}
