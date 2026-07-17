<?php
/**
 * Task Board → Executable projection.
 *
 * Mapea la salida de GetTaskBoardUseCase al contrato común.
 * No reevalúa policies de Tasks ni altera organization.
 * El feed activo proyecta solo tareas pending; done queda fuera hasta vista completadas.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executable/class-aa-executable-contract.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-execution-timing-policy.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-governance-policy.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-list-governance-policy.php';
require_once dirname(__DIR__, 2) . '/application/tasks/TaskUseCaseSupport.php';
require_once __DIR__ . '/ExecutableNavigationUrlResolver.php';

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
        $now = TaskUseCaseSupport::resolve_now();

        $mapped = [];

        foreach ($list_order as $list_id) {
            $list = self::find_list_by_id($lists, $list_id);

            if ($list === null) {
                continue;
            }

            $mapped[] = self::map_list($list, $tasks_by_id, $organization, $executive_candidates, $now);
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
        array $executive_candidates,
        string $now
    ): array {
        $list_id = (int) ($list['id'] ?? 0);
        $list_status = (string) ($list['status'] ?? AA_Executable_Contract::LIST_STATUS_ACTIVE);
        $is_archived = strtolower(trim($list_status)) === AA_Executable_Contract::LIST_STATUS_ARCHIVED;
        $source_category = self::resolve_source_category($list);
        $source = self::resolve_source($list);
        $list_governance = new AA_Task_List_Governance_Policy();

        return AA_Executable_Contract::normalize_list([
            'id' => (string) $list_id,
            'source' => $source,
            'source_category' => $source_category,
            'source_label' => AA_Executable_Contract::default_source_label($source_category),
            'origin_key' => self::normalize_nullable_string($list['origin_key'] ?? null),
            'title' => (string) ($list['title'] ?? ''),
            'description' => isset($list['description']) ? (string) $list['description'] : null,
            'importance' => (int) ($list['importance'] ?? 0),
            'position' => (int) ($list['position'] ?? 0),
            'status' => $is_archived
                ? AA_Executable_Contract::LIST_STATUS_ARCHIVED
                : AA_Executable_Contract::LIST_STATUS_ACTIVE,
            'capabilities' => [
                'can_archive' => $list_governance->can_archive_list($list),
                'can_edit' => $list_governance->can_edit_list($list),
                'can_restore_archived_tasks' => $list_governance->can_restore_archived_tasks($list),
                'can_delete' => $list_governance->can_delete_list($list),
            ],
            'buckets' => self::map_list_buckets($list_id, $tasks_by_id, $organization, $executive_candidates, $now),
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $tasks_by_id
     * @param array<string,mixed>            $organization
     * @param array<int,bool>                $executive_candidates
     * @return list<array<string,mixed>>
     */
    private static function map_list_buckets(
        int $list_id,
        array $tasks_by_id,
        array $organization,
        array $executive_candidates,
        string $now
    ): array {
        $projected_buckets = self::map_projected_task_buckets(
            $list_id,
            $tasks_by_id,
            $organization,
            $executive_candidates,
            $now
        );

        if ($projected_buckets !== null) {
            return $projected_buckets;
        }

        return [
            [
                'key' => AA_Executable_Contract::BUCKET_DEFAULT,
                'label' => '',
                'items' => self::map_list_tasks($list_id, $tasks_by_id, $organization, $executive_candidates, $now),
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $tasks_by_id
     * @param array<string,mixed>            $organization
     * @param array<int,bool>                $executive_candidates
     * @return list<array<string,mixed>>|null
     */
    private static function map_projected_task_buckets(
        int $list_id,
        array $tasks_by_id,
        array $organization,
        array $executive_candidates,
        string $now
    ): ?array {
        $raw_buckets_by_list = $organization['task_bucket_order_by_list'] ?? null;

        if (!is_array($raw_buckets_by_list)) {
            return null;
        }

        $raw_list_buckets = $raw_buckets_by_list[$list_id] ?? null;

        if (!is_array($raw_list_buckets)) {
            return null;
        }

        $bucket_defs = [
            AA_Executable_Contract::BUCKET_PRIMARY,
            AA_Executable_Contract::BUCKET_SECONDARY,
        ];
        $mapped = [];

        foreach ($bucket_defs as $bucket_key) {
            $label = AA_Executable_Contract::bucket_label($bucket_key);
            $items = self::map_tasks_by_order(
                $raw_list_buckets[$bucket_key] ?? [],
                $tasks_by_id,
                $executive_candidates,
                $organization,
                $now
            );

            if ($items === []) {
                continue;
            }

            $mapped[] = [
                'key' => $bucket_key,
                'label' => $label,
                'items' => $items,
            ];
        }

        return $mapped;
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
        array $executive_candidates,
        string $now
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

        return self::map_tasks_by_order($ordered_ids, $tasks_by_id, $executive_candidates, $organization, $now);
    }

    /**
     * @param mixed                         $raw_order
     * @param array<int,array<string,mixed>> $tasks_by_id
     * @param array<int,bool>                $executive_candidates
     * @param array<string,mixed>            $organization
     * @return list<array<string,mixed>>
     */
    private static function map_tasks_by_order(
        $raw_order,
        array $tasks_by_id,
        array $executive_candidates,
        array $organization,
        string $now
    ): array {
        if (!is_array($raw_order)) {
            return [];
        }

        $mapped = [];

        foreach ($raw_order as $task_id) {
            $task_id = (int) $task_id;

            if (!isset($tasks_by_id[$task_id])) {
                continue;
            }

            $task = $tasks_by_id[$task_id];

            if (self::is_done_task($task)) {
                continue;
            }

            $mapped[] = self::map_task($task, $executive_candidates, $organization, $now);
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
     * @param array<string,mixed> $organization
     * @return array<string,mixed>
     */
    private static function map_task(
        array $task,
        array $executive_candidates,
        array $organization,
        string $now
    ): array {
        $task_id = (int) ($task['id'] ?? 0);
        $status = strtolower(trim((string) ($task['status'] ?? AA_Executable_Contract::ITEM_STATUS_PENDING)));
        $is_done = $status === AA_Executable_Contract::ITEM_STATUS_DONE;
        $is_pending = !$is_done;
        $evaluation = self::resolve_task_evaluation($organization, $task_id);
        $signal_state = self::resolve_executable_signal_state($evaluation);
        $signal_capabilities = self::resolve_task_signal_capabilities($evaluation, $is_pending);
        $is_system_completion_type = self::is_system_completion_type($task);
        $is_system_completed = self::is_system_completed_evaluation($evaluation);
        $source_category = self::resolve_source_category($task);
        $source = self::resolve_source($task);
        $primary_action = self::resolve_primary_action($task, $organization, $is_pending, $is_done);
        $governance = new AA_Task_Governance_Policy();
        $timing_flags = AA_Task_Execution_Timing_Policy::project_executable_flags($evaluation);

        return AA_Executable_Contract::normalize_item([
            'id' => (string) $task_id,
            'source' => $source,
            'source_category' => $source_category,
            'origin_key' => self::normalize_nullable_string($task['origin_key'] ?? null),
            'title' => (string) ($task['title'] ?? ''),
            'description' => isset($task['notes']) ? (string) $task['notes'] : null,
            'importance' => (int) ($task['importance'] ?? 0),
            'due_at' => isset($task['due_at']) && $task['due_at'] !== '' ? (string) $task['due_at'] : null,
            'execution_available_at' => isset($task['execution_available_at']) && $task['execution_available_at'] !== ''
                ? (string) $task['execution_available_at']
                : null,
            'is_pertinent' => $timing_flags['is_pertinent'],
            'is_overdue' => $timing_flags['is_overdue'],
            'default_bucket' => isset($task['default_bucket']) && $task['default_bucket'] !== ''
                ? (string) $task['default_bucket']
                : AA_Executable_Contract::BUCKET_PRIMARY,
            'status' => $is_done
                ? AA_Executable_Contract::ITEM_STATUS_DONE
                : AA_Executable_Contract::ITEM_STATUS_PENDING,
            'state' => [
                'completed' => $is_done,
                'ignored' => $signal_state['ignored'],
                'dismissed' => $signal_state['dismissed'],
                'dismiss_active' => $signal_state['dismiss_active'],
                'auto_completed' => $is_system_completed,
            ],
            'capabilities' => [
                'can_complete' => $is_pending && !$is_system_completion_type,
                'can_reopen' => $is_done && !$is_system_completion_type,
                'can_defer' => $signal_capabilities['can_defer'],
                'can_dismiss' => $signal_capabilities['can_dismiss'],
                'can_reactivate' => false,
                'can_edit' => $governance->can_edit_task($task),
                'can_archive' => $governance->can_archive_task($task),
                'can_restore' => $governance->can_restore_task($task),
                'can_delete' => $governance->can_delete_task($task),
            ],
            'primary_action' => $primary_action,
            'is_executive_candidate' => isset($executive_candidates[$task_id]),
        ]);
    }

    /**
     * @param array<string,mixed> $task
     */
    private static function resolve_primary_action(array $task, array $organization, bool $is_pending, bool $is_done): ?array {
        $persisted_action = self::resolve_persisted_primary_action($task, $organization);

        if ($persisted_action !== null) {
            return $persisted_action;
        }

        if (!$is_pending && !$is_done) {
            return null;
        }

        if ($is_pending && !self::is_system_completion_type($task)) {
            return [
                'type' => AA_Executable_Contract::ACTION_STATUS,
                'label' => 'Completar',
                'to' => AA_Executable_Contract::ITEM_STATUS_DONE,
            ];
        }

        return [
            'type' => AA_Executable_Contract::ACTION_STATUS,
            'label' => 'Reabrir',
            'to' => AA_Executable_Contract::ITEM_STATUS_PENDING,
        ];
    }

    /**
     * @param array<string,mixed> $task
     * @return array<string,mixed>|null
     */
    private static function resolve_persisted_primary_action(array $task, array $organization): ?array {
        $task_id = (int) ($task['id'] ?? 0);

        if ($task_id < 1) {
            return null;
        }

        $actions_by_id = $organization['task_actions_by_id'] ?? null;

        if (!is_array($actions_by_id)) {
            return null;
        }

        $actions = $actions_by_id[$task_id] ?? null;

        if (!is_array($actions)) {
            return null;
        }

        $candidates = [];

        foreach ($actions as $action) {
            if (!is_array($action) || !self::is_primary_mechanical_action($action)) {
                continue;
            }

            $candidates[] = $action;
        }

        usort($candidates, static function (array $left, array $right): int {
            $position_compare = (int) ($left['position'] ?? 0) <=> (int) ($right['position'] ?? 0);

            if ($position_compare !== 0) {
                return $position_compare;
            }

            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });

        foreach ($candidates as $action) {
            $mapped = self::map_persisted_action($action);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $action
     */
    private static function is_primary_mechanical_action(array $action): bool {
        return (int) ($action['enabled'] ?? 0) === 1
            && strtolower(trim((string) ($action['placement'] ?? ''))) === AA_Executable_Contract::VISIBLE_PLACEMENT_PRIMARY
            && strtolower(trim((string) ($action['category'] ?? ''))) === AA_Executable_Contract::VISIBLE_CATEGORY_MECHANICAL;
    }

    /**
     * @param array<string,mixed> $action
     * @return array<string,mixed>|null
     */
    private static function map_persisted_action(array $action): ?array {
        $type = strtolower(trim((string) ($action['type'] ?? '')));
        $label = trim((string) ($action['label'] ?? ''));
        $action_key = trim((string) ($action['action_key'] ?? ''));

        if ($action_key === '') {
            return null;
        }

        if ($type === AA_Executable_Contract::ACTION_NAVIGATE) {
            $url = ExecutableNavigationUrlResolver::resolve([
                'module' => $action['target_module'] ?? null,
                'setup_focus' => $action['target_setup_focus'] ?? null,
                'fragment' => $action['target_fragment'] ?? null,
            ]);

            if ($url === null) {
                return null;
            }

            return [
                'key' => $action_key,
                'type' => AA_Executable_Contract::ACTION_NAVIGATE,
                'label' => $label !== '' ? $label : 'Ir',
                'url' => $url,
            ];
        }

        if ($type === AA_Executable_Contract::ACTION_HANDLER) {
            $handler = trim((string) ($action['handler'] ?? ''));

            if ($handler === '' || $label === '') {
                return null;
            }

            return [
                'key' => $action_key,
                'type' => AA_Executable_Contract::ACTION_HANDLER,
                'label' => $label,
                'handler' => $handler,
            ];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function resolve_source(array $row): string {
        $source_category = self::resolve_source_category($row);

        if ($source_category === AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP) {
            return AA_Executable_Contract::SOURCE_SYSTEM;
        }

        if ($source_category === AA_Executable_Contract::SOURCE_CATEGORY_AI) {
            return AA_Executable_Contract::SOURCE_AI;
        }

        $source = is_string($row['source'] ?? null) ? strtolower(trim((string) $row['source'])) : '';

        if ($source === AA_Executable_Contract::SOURCE_SYSTEM || $source === AA_Executable_Contract::SOURCE_AI) {
            return $source;
        }

        return AA_Executable_Contract::SOURCE_USER;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function resolve_source_category(array $row): string {
        $category = is_string($row['source_category'] ?? null)
            ? strtolower(trim((string) $row['source_category']))
            : '';

        if ($category !== '') {
            return $category;
        }

        return AA_Executable_Contract::SOURCE_CATEGORY_USER;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function resolve_managed_by(array $row): string {
        $managed_by = is_string($row['managed_by'] ?? null)
            ? strtolower(trim((string) $row['managed_by']))
            : '';

        return $managed_by !== '' ? $managed_by : 'user';
    }

    /**
     * @param mixed $value
     */
    private static function normalize_nullable_string($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string,mixed> $organization
     * @return array<string,mixed>|null
     */
    private static function resolve_task_evaluation(array $organization, int $task_id): ?array {
        $evaluations = $organization['task_evaluations_by_id'] ?? null;

        if (!is_array($evaluations)) {
            return null;
        }

        $evaluation = $evaluations[$task_id] ?? null;

        return is_array($evaluation) ? $evaluation : null;
    }

    /**
     * @param array<string,mixed>|null $evaluation
     * @return array{can_defer:bool,can_dismiss:bool}
     */
    private static function resolve_task_signal_capabilities(?array $evaluation, bool $is_pending): array {
        if (!$is_pending || $evaluation === null) {
            return [
                'can_defer' => false,
                'can_dismiss' => false,
            ];
        }

        $capabilities = is_array($evaluation['capabilities'] ?? null) ? $evaluation['capabilities'] : [];

        return [
            'can_defer' => !empty($capabilities['can_defer']),
            'can_dismiss' => !empty($capabilities['can_dismiss']),
        ];
    }

    /**
     * @param array<string,mixed> $task
     */
    private static function is_system_completion_type(array $task): bool {
        return strtolower(trim((string) ($task['completion_type'] ?? 'manual'))) === 'system';
    }

    /**
     * @param array<string,mixed>|null $evaluation
     */
    private static function is_system_completed_evaluation(?array $evaluation): bool {
        if ($evaluation === null) {
            return false;
        }

        $state = is_array($evaluation['state'] ?? null) ? $evaluation['state'] : [];

        return !empty($state['is_system_completed']);
    }

    /**
     * @param array<string,mixed>|null $evaluation
     * @return array{ignored:bool,dismissed:bool,dismiss_active:bool}
     */
    private static function resolve_executable_signal_state(?array $evaluation): array {
        if ($evaluation === null) {
            return [
                'ignored' => false,
                'dismissed' => false,
                'dismiss_active' => false,
            ];
        }

        $signals = is_array($evaluation['signals'] ?? null) ? $evaluation['signals'] : [];
        $state = is_array($evaluation['state'] ?? null) ? $evaluation['state'] : [];

        $has_dismiss = !empty($signals['has_dismiss']);
        $is_dismiss_active = !empty($state['is_dismiss_active']);

        return [
            'ignored' => $is_dismiss_active,
            'dismissed' => $has_dismiss,
            'dismiss_active' => $is_dismiss_active,
        ];
    }
}
