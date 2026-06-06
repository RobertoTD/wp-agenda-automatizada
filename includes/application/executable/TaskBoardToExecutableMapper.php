<?php
/**
 * Task Board → Executable projection.
 *
 * Mapea la salida de GetTaskBoardUseCase al contrato común.
 * No reevalúa AA_Task_Prioritization_Policy ni altera organization.
 * El feed activo/default proyecta solo tareas pending; done queda fuera hasta vista completadas.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executable/class-aa-executable-contract.php';

final class TaskBoardToExecutableMapper {

    /**
     * @param array{
     *     lists?:list<array<string,mixed>>,
     *     tasks?:list<array<string,mixed>>,
     *     organization?:array<string,mixed>
     * } $payload
     * @return list<array<string,mixed>>
     */
    public static function map(array $payload): array {
        $lists = is_array($payload['lists'] ?? null) ? $payload['lists'] : [];
        $tasks = is_array($payload['tasks'] ?? null) ? $payload['tasks'] : [];
        $organization = is_array($payload['organization'] ?? null) ? $payload['organization'] : [];

        $tasks_by_id = self::index_tasks_by_id($tasks);
        $list_order = self::resolve_list_order($lists, $organization);
        $executive_candidates = self::resolve_executive_candidate_ids($organization);

        $mapped = [];

        foreach ($list_order as $list_id) {
            $list = self::find_list_by_id($lists, $list_id);

            if ($list === null) {
                continue;
            }

            $mapped[] = self::map_list($list, $tasks_by_id, $organization, $executive_candidates);
        }

        return $mapped;
    }

    /**
     * @param list<array<string,mixed>> $tasks
     * @return array<int,array<string,mixed>>
     */
    private static function index_tasks_by_id(array $tasks): array {
        $indexed = [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $task_id = (int) ($task['id'] ?? 0);

            if ($task_id < 1) {
                continue;
            }

            $indexed[$task_id] = $task;
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $lists
     * @param array<string,mixed>       $organization
     * @return list<int>
     */
    private static function resolve_list_order(array $lists, array $organization): array {
        $ordered = [];
        $raw_order = $organization['list_order'] ?? [];

        if (is_array($raw_order)) {
            foreach ($raw_order as $list_id) {
                $normalized = (int) $list_id;

                if ($normalized > 0) {
                    $ordered[] = $normalized;
                }
            }
        }

        if ($ordered !== []) {
            return $ordered;
        }

        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }

            $list_id = (int) ($list['id'] ?? 0);

            if ($list_id > 0) {
                $ordered[] = $list_id;
            }
        }

        return $ordered;
    }

    /**
     * @param array<string,mixed> $organization
     * @return array<int,bool>
     */
    private static function resolve_executive_candidate_ids(array $organization): array {
        $indexed = [];
        $raw_candidates = $organization['executive_candidates'] ?? [];

        if (!is_array($raw_candidates)) {
            return $indexed;
        }

        foreach ($raw_candidates as $task_id) {
            $normalized = (int) $task_id;

            if ($normalized > 0) {
                $indexed[$normalized] = true;
            }
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $lists
     */
    private static function find_list_by_id(array $lists, int $list_id): ?array {
        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }

            if ((int) ($list['id'] ?? 0) === $list_id) {
                return $list;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed>       $list
     * @param array<int,array<string,mixed>> $tasks_by_id
     * @param array<string,mixed>       $organization
     * @param array<int,bool>           $executive_candidates
     * @return array<string,mixed>
     */
    private static function map_list(
        array $list,
        array $tasks_by_id,
        array $organization,
        array $executive_candidates
    ): array {
        $list_id = (int) ($list['id'] ?? 0);
        $list_status = (string) ($list['status'] ?? AA_Executable_Contract::LIST_STATUS_ACTIVE);
        $is_archived = strtolower(trim($list_status)) === AA_Executable_Contract::LIST_STATUS_ARCHIVED;

        return AA_Executable_Contract::normalize_list([
            'id' => (string) $list_id,
            'source' => AA_Executable_Contract::SOURCE_USER,
            'origin_key' => null,
            'title' => (string) ($list['title'] ?? ''),
            'description' => isset($list['description']) ? (string) $list['description'] : null,
            'importance' => (int) ($list['importance'] ?? 0),
            'position' => (int) ($list['position'] ?? 0),
            'status' => $is_archived
                ? AA_Executable_Contract::LIST_STATUS_ARCHIVED
                : AA_Executable_Contract::LIST_STATUS_ACTIVE,
            'capabilities' => [
                'can_archive' => !$is_archived,
            ],
            'buckets' => [
                [
                    'key' => AA_Executable_Contract::BUCKET_DEFAULT,
                    'label' => '',
                    'items' => self::map_list_tasks($list_id, $tasks_by_id, $organization, $executive_candidates),
                ],
            ],
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $tasks_by_id
     * @param array<string,mixed>            $organization
     * @param array<int,bool>                  $executive_candidates
     * @return list<array<string,mixed>>
     */
    private static function map_list_tasks(
        int $list_id,
        array $tasks_by_id,
        array $organization,
        array $executive_candidates
    ): array {
        $ordered_ids = [];
        $raw_order = $organization['task_order_by_list'][$list_id] ?? null;

        if (is_array($raw_order)) {
            foreach ($raw_order as $task_id) {
                $normalized = (int) $task_id;

                if ($normalized > 0) {
                    $ordered_ids[] = $normalized;
                }
            }
        }

        if ($ordered_ids === []) {
            foreach ($tasks_by_id as $task_id => $task) {
                if ((int) ($task['list_id'] ?? 0) === $list_id) {
                    $ordered_ids[] = (int) $task_id;
                }
            }
        }

        $mapped = [];

        foreach ($ordered_ids as $task_id) {
            if (!isset($tasks_by_id[$task_id])) {
                continue;
            }

            $task = $tasks_by_id[$task_id];

            if (self::is_done_task($task)) {
                continue;
            }

            $mapped[] = self::map_task($task, $executive_candidates);
        }

        return $mapped;
    }

    /**
     * @param array<string,mixed> $task
     */
    private static function is_done_task(array $task): bool {
        $status = strtolower(trim((string) ($task['status'] ?? AA_Executable_Contract::ITEM_STATUS_PENDING)));

        return $status === AA_Executable_Contract::ITEM_STATUS_DONE;
    }

    /**
     * @param array<string,mixed> $task
     * @param array<int,bool>     $executive_candidates
     * @return array<string,mixed>
     */
    private static function map_task(array $task, array $executive_candidates): array {
        $task_id = (int) ($task['id'] ?? 0);
        $status = strtolower(trim((string) ($task['status'] ?? AA_Executable_Contract::ITEM_STATUS_PENDING)));
        $is_done = $status === AA_Executable_Contract::ITEM_STATUS_DONE;
        $is_pending = !$is_done;

        return AA_Executable_Contract::normalize_item([
            'id' => (string) $task_id,
            'source' => AA_Executable_Contract::SOURCE_USER,
            'origin_key' => null,
            'title' => (string) ($task['title'] ?? ''),
            'description' => isset($task['notes']) ? (string) $task['notes'] : null,
            'importance' => (int) ($task['importance'] ?? 0),
            'due_at' => isset($task['due_at']) && $task['due_at'] !== '' ? (string) $task['due_at'] : null,
            'status' => $is_done
                ? AA_Executable_Contract::ITEM_STATUS_DONE
                : AA_Executable_Contract::ITEM_STATUS_PENDING,
            'state' => [
                'completed' => $is_done,
                'ignored' => false,
                'dismissed' => false,
                'dismiss_active' => false,
                'auto_completed' => false,
            ],
            'capabilities' => [
                'can_complete' => $is_pending,
                'can_reopen' => $is_done,
                'can_defer' => false,
                'can_dismiss' => false,
                'can_reactivate' => false,
            ],
            'primary_action' => $is_pending
                ? [
                    'type' => AA_Executable_Contract::ACTION_STATUS,
                    'label' => 'Completar',
                    'to' => AA_Executable_Contract::ITEM_STATUS_DONE,
                ]
                : [
                    'type' => AA_Executable_Contract::ACTION_STATUS,
                    'label' => 'Reabrir',
                    'to' => AA_Executable_Contract::ITEM_STATUS_PENDING,
                ],
            'is_executive_candidate' => isset($executive_candidates[$task_id]),
        ]);
    }
}
