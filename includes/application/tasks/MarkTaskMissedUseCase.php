<?php
/**
 * Mark Task Missed Use Case — resolución terminal negativa "No realizada" (MC4).
 *
 * Solo aplica a tareas pendientes y vencidas. No cuenta como completada
 * (completed_at queda NULL) y no toca la cita asociada ni archived_at.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-execution-timing-policy.php';

final class MarkTaskMissedUseCase {

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
            return TaskUseCaseSupport::fail('task_not_pending', 'Solo se pueden marcar como no realizadas las tareas pendientes.');
        }

        $list_id = (int) ($task['list_id'] ?? 0);
        $list = TaskUseCaseSupport::find_active_list($list_id);

        if ($list === null) {
            return TaskUseCaseSupport::fail('list_not_found', 'Lista no encontrada o no activa.');
        }

        $now = TaskUseCaseSupport::resolve_now();
        $timezone_name = (string) get_option('aa_timezone', 'America/Mexico_City');

        try {
            $timezone = new DateTimeZone($timezone_name !== '' ? $timezone_name : 'America/Mexico_City');
        } catch (Exception $e) {
            $timezone = new DateTimeZone('America/Mexico_City');
        }

        $timing = (new AA_Task_Execution_Timing_Policy($timezone))
            ->evaluate(AA_Task::from_array($task), $now);

        if ((int) ($timing['temporal_layer'] ?? 0) !== 4) {
            return TaskUseCaseSupport::fail('task_not_overdue', 'Solo se pueden marcar como no realizadas las tareas vencidas.');
        }

        $row = TaskRepository::mark_missed($task_id, $now);

        if ($row === null) {
            return TaskUseCaseSupport::fail('persistence_failed', 'No se pudo marcar la tarea como no realizada.');
        }

        return TaskUseCaseSupport::ok(['task' => $row]);
    }
}
