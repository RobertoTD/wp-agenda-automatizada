<?php
/**
 * Executive Proposal Mapper — enriquece selección de policy con datos del board (MC1).
 *
 * Application: sin reglas de selección; traduce snapshot a payload ejecutivo.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-actions-policy.php';
require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-contract.php';
require_once dirname(__DIR__, 2) . '/domain/executable/class-aa-executable-contract.php';
require_once dirname(__DIR__, 2) . '/domain/executable/class-aa-executable-visible-actions-policy.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task.php';
require_once dirname(__DIR__, 2) . '/domain/tasks/class-aa-task-active-view-projection-policy.php';
require_once __DIR__ . '/../executable/ExecutableNavigationUrlResolver.php';

final class ExecutiveProposalMapper {

    private const SLOT_ORDER = [
        AA_Executive_Contract::SLOT_CURRENT,
        AA_Executive_Contract::SLOT_NEXT,
        AA_Executive_Contract::SLOT_THIRD,
    ];

    /**
     * @param array<string,mixed> $board
     * @param array{
     *     status:string,
     *     focus_list_id:int|null,
     *     task_ids:list<int>,
     *     eligible_count_in_focus_list:int
     * } $selection
     */
    public static function map(array $board, array $selection, string $now): array {
        if (($selection['status'] ?? '') !== AA_Executive_Contract::STATUS_READY) {
            return self::empty_payload();
        }

        $focus_list_id = (int) ($selection['focus_list_id'] ?? 0);

        if ($focus_list_id < 1) {
            return self::empty_payload();
        }

        $lists_by_id = self::index_rows_by_id($board['lists'] ?? []);
        $tasks_by_id = self::index_rows_by_id($board['tasks'] ?? []);
        $organization = is_array($board['organization'] ?? null) ? $board['organization'] : [];
        $evaluations_by_id = is_array($organization['task_evaluations_by_id'] ?? null)
            ? $organization['task_evaluations_by_id']
            : [];
        $focus_list = $lists_by_id[$focus_list_id] ?? null;

        if (!is_array($focus_list)) {
            return self::empty_payload();
        }

        $mapped_tasks = [];
        $task_ids = is_array($selection['task_ids'] ?? null) ? $selection['task_ids'] : [];

        foreach ($task_ids as $index => $task_id) {
            $task_id = (int) $task_id;
            $task = $tasks_by_id[$task_id] ?? null;

            if (!is_array($task)) {
                continue;
            }

            $slot = self::SLOT_ORDER[$index] ?? null;

            if ($slot === null) {
                continue;
            }

            $is_current = $slot === AA_Executive_Contract::SLOT_CURRENT;
            $task_vo = AA_Task::from_array($task);
            $evaluation = is_array($evaluations_by_id[$task_id] ?? null)
                ? $evaluations_by_id[$task_id]
                : [];
            $projected_bucket = self::resolve_projected_bucket($evaluation, $task_vo);
            $primary_action = $is_current
                ? self::resolve_primary_action($task, $organization)
                : null;
            $executive_actions = [];

            if ($is_current) {
                $item = self::build_executable_item(
                    $task,
                    $evaluation,
                    $primary_action,
                    $projected_bucket,
                    $focus_list
                );
                $executive_actions = AA_Executive_Actions_Policy::resolve($item, [
                    'view' => AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE,
                    'bucket_key' => $projected_bucket,
                    'source' => self::resolve_source($task, $focus_list),
                ]);
            }

            $mapped_tasks[] = [
                'slot' => $slot,
                'task_id' => $task_id,
                'title' => (string) ($task['title'] ?? ''),
                'description' => isset($task['notes']) ? (string) $task['notes'] : null,
                'default_bucket' => $projected_bucket,
                'due_at' => isset($task['due_at']) && $task['due_at'] !== '' ? (string) $task['due_at'] : null,
                'is_overdue' => $task_vo->is_overdue($now),
                'actionable' => $is_current,
                'continuation' => !$is_current,
                'executive_actions' => $executive_actions,
                'primary_action' => $primary_action,
                'reason' => $is_current
                    ? AA_Executive_Contract::REASON_FOCUS_CURRENT
                    : AA_Executive_Contract::REASON_CONTINUITY,
            ];
        }

        if ($mapped_tasks === []) {
            return self::empty_payload();
        }

        return [
            'success' => true,
            'status' => AA_Executive_Contract::STATUS_READY,
            'focus_list' => [
                'id' => $focus_list_id,
                'title' => (string) ($focus_list['title'] ?? ''),
                'source_category' => self::resolve_source_category($focus_list),
                'importance' => (int) ($focus_list['importance'] ?? 0),
            ],
            'tasks' => $mapped_tasks,
            'meta' => [
                'version' => AA_Executive_Contract::META_VERSION,
                'eligible_count_in_focus_list' => (int) ($selection['eligible_count_in_focus_list'] ?? count($mapped_tasks)),
                'focus_reason' => AA_Executive_Contract::FOCUS_REASON_FIRST_LIST_WITH_ELIGIBLE,
                'empty_reason' => null,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_payload(): array {
        return [
            'success' => true,
            'status' => AA_Executive_Contract::STATUS_EMPTY,
            'focus_list' => null,
            'tasks' => [],
            'meta' => [
                'version' => AA_Executive_Contract::META_VERSION,
                'eligible_count_in_focus_list' => 0,
                'focus_reason' => null,
                'empty_reason' => AA_Executive_Contract::EMPTY_REASON_NO_ELIGIBLE_TASKS,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $task
     * @param array<string,mixed> $evaluation
     * @param array<string,mixed>|null $primary_action
     * @param array<string,mixed> $focus_list
     * @return array<string,mixed>
     */
    private static function build_executable_item(
        array $task,
        array $evaluation,
        ?array $primary_action,
        string $projected_bucket,
        array $focus_list
    ): array {
        $is_pending = strtolower(trim((string) ($task['status'] ?? 'pending'))) === 'pending';
        $capabilities = is_array($evaluation['capabilities'] ?? null) ? $evaluation['capabilities'] : [];

        return [
            'id' => (string) ((int) ($task['id'] ?? 0)),
            'source' => self::resolve_source($task, $focus_list),
            'status' => $is_pending
                ? AA_Executable_Contract::ITEM_STATUS_PENDING
                : AA_Executable_Contract::ITEM_STATUS_DONE,
            'primary_action' => $primary_action,
            'capabilities' => [
                'can_complete' => $is_pending && !self::is_system_completion_type($task),
                'can_dismiss' => !empty($capabilities['can_dismiss']),
                'can_defer' => false,
                'can_edit' => false,
                'can_archive' => false,
                'can_delete' => false,
            ],
            'default_bucket' => $projected_bucket,
        ];
    }

    /**
     * @param array<string,mixed> $evaluation
     */
    private static function resolve_projected_bucket(array $evaluation, AA_Task $task): string {
        $projection = is_array($evaluation['projection'] ?? null) ? $evaluation['projection'] : [];
        $bucket = isset($projection['projected_bucket'])
            ? strtolower(trim((string) $projection['projected_bucket']))
            : '';

        if ($bucket === AA_Task_Active_View_Projection_Policy::BUCKET_SECONDARY) {
            return AA_Task_Active_View_Projection_Policy::BUCKET_SECONDARY;
        }

        if ($bucket === AA_Task_Active_View_Projection_Policy::BUCKET_PRIMARY) {
            return AA_Task_Active_View_Projection_Policy::BUCKET_PRIMARY;
        }

        return $task->default_bucket();
    }

    /**
     * @param array<string,mixed> $task
     * @param array<string,mixed> $organization
     * @return array<string,mixed>|null
     */
    private static function resolve_primary_action(array $task, array $organization): ?array {
        $persisted = self::resolve_persisted_primary_action($task, $organization);

        if ($persisted !== null) {
            return $persisted;
        }

        $is_pending = strtolower(trim((string) ($task['status'] ?? 'pending'))) === 'pending';

        if ($is_pending && !self::is_system_completion_type($task)) {
            return [
                'type' => AA_Executable_Contract::ACTION_STATUS,
                'label' => 'Completar',
                'to' => AA_Executable_Contract::ITEM_STATUS_DONE,
            ];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $task
     * @param array<string,mixed> $organization
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
     * @param array<string,mixed> $task
     */
    private static function is_system_completion_type(array $task): bool {
        return strtolower(trim((string) ($task['completion_type'] ?? 'manual'))) === 'system';
    }

    /**
     * @param array<string,mixed> $task
     * @param array<string,mixed> $focus_list
     */
    private static function resolve_source(array $task, array $focus_list): string {
        $source_category = self::resolve_source_category($task);

        if ($source_category === AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP) {
            return AA_Executable_Contract::SOURCE_SYSTEM;
        }

        $list_category = self::resolve_source_category($focus_list);

        if ($list_category === AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP) {
            return AA_Executable_Contract::SOURCE_SYSTEM;
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

        return $category !== '' ? $category : AA_Executable_Contract::SOURCE_CATEGORY_USER;
    }

    /**
     * @param mixed $rows
     * @return array<int,array<string,mixed>>
     */
    private static function index_rows_by_id($rows): array {
        if (!is_array($rows)) {
            return [];
        }

        $indexed = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);

            if ($id > 0) {
                $indexed[$id] = $row;
            }
        }

        return $indexed;
    }
}
