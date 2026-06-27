<?php
/**
 * Sync Learning Catalog To Tasks Use Case.
 *
 * Siembra manualmente definiciones del catálogo Learning en la DB común de Tasks.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/domain/learning/class-aa-learning-catalog.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskActionRepository.php';

final class SyncLearningCatalogToTasksUseCase {

    private const SOURCE_CATEGORY = 'agenda_app';

    private const LIST_ORIGIN_KEY = 'learning.recommendations';

    /**
     * @var callable|null
     */
    private $catalog_provider;

    /**
     * @param callable|null $catalog_provider Optional provider returning Learning definitions.
     */
    public function __construct(?callable $catalog_provider = null) {
        $this->catalog_provider = $catalog_provider;
    }

    /**
     * @return array{
     *     lists_created:int,
     *     lists_updated:int,
     *     tasks_created:int,
     *     tasks_updated:int,
     *     actions_created:int,
     *     actions_updated:int,
     *     skipped_inactive:int,
     *     list_id:int,
     *     task_ids:array<string,int>
     * }
     */
    public function execute(): array {
        $counts = [
            'lists_created' => 0,
            'lists_updated' => 0,
            'tasks_created' => 0,
            'tasks_updated' => 0,
            'actions_created' => 0,
            'actions_updated' => 0,
            'skipped_inactive' => 0,
            'list_id' => 0,
            'task_ids' => [],
        ];

        $existing_list = SeededTaskRepository::find_list_by_origin(self::SOURCE_CATEGORY, self::LIST_ORIGIN_KEY);
        $list = SeededTaskRepository::upsert_seeded_list($this->list_payload());

        if ($list === null) {
            return $counts;
        }

        $counts[$existing_list === null ? 'lists_created' : 'lists_updated']++;
        $list_id = (int) $list['id'];
        $counts['list_id'] = $list_id;

        $position = 0;

        foreach ($this->definitions() as $key => $definition) {
            if (empty($definition['active'])) {
                $counts['skipped_inactive']++;
                continue;
            }

            $origin_key = isset($definition['key']) ? (string) $definition['key'] : (string) $key;
            $existing_task = SeededTaskRepository::find_task_by_origin(self::SOURCE_CATEGORY, $origin_key);
            $task = SeededTaskRepository::upsert_seeded_task($this->task_payload($list_id, $definition, $origin_key, $position));
            $position++;

            if ($task === null) {
                continue;
            }

            $counts[$existing_task === null ? 'tasks_created' : 'tasks_updated']++;
            $task_id = (int) $task['id'];
            $counts['task_ids'][$origin_key] = $task_id;

            $active_action_keys = [];

            foreach ($this->action_payloads($definition) as $action_payload) {
                $existing_action = TaskActionRepository::find_by_task_and_key($task_id, (string) $action_payload['action_key']);
                $action = TaskActionRepository::upsert($task_id, $action_payload);

                if ($action === null) {
                    continue;
                }

                $counts[$existing_action === null ? 'actions_created' : 'actions_updated']++;
                $active_action_keys[] = (string) $action_payload['action_key'];
            }

            if ($active_action_keys !== []) {
                TaskActionRepository::disable_missing_for_task($task_id, $active_action_keys);
            }
        }

        return $counts;
    }

    /**
     * @return array<string,mixed>
     */
    private function list_payload(): array {
        return [
            'title' => 'Activación de tu agenda',
            'description' => 'Sugerencias para configurar y usar tu agenda.',
            'owner_type' => 'developer',
            'source_category' => self::SOURCE_CATEGORY,
            'origin_key' => self::LIST_ORIGIN_KEY,
            'managed_by' => 'developer',
            'status' => 'archived',
            'importance' => 0,
            'position' => 0,
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function definitions(): array {
        if ($this->catalog_provider !== null) {
            $definitions = call_user_func($this->catalog_provider);

            return is_array($definitions) ? $definitions : [];
        }

        return AA_Learning_Catalog::definitions();
    }

    /**
     * @param array<string,mixed> $definition
     * @return array<string,mixed>
     */
    private function task_payload(int $list_id, array $definition, string $origin_key, int $position): array {
        return [
            'list_id' => $list_id,
            'title' => (string) ($definition['title'] ?? ''),
            'notes' => (string) ($definition['description'] ?? ''),
            'status' => 'pending',
            'source' => 'system',
            'source_category' => self::SOURCE_CATEGORY,
            'origin_key' => $origin_key,
            'managed_by' => 'developer',
            'importance' => (int) ($definition['importance'] ?? 0),
            'position' => $position,
            'default_bucket' => $this->default_bucket($definition),
            'completion_type' => $this->completion_type($definition),
            'completion_fact_key' => $definition['completion_fact'] ?? null,
            'due_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * @param array<string,mixed> $definition
     * @return list<array<string,mixed>>
     */
    private function action_payloads(array $definition): array {
        $action = array_key_exists('action', $definition) ? $definition['action'] : null;

        if (is_array($action) && ($action['type'] ?? '') === 'handler') {
            $handler = isset($action['handler']) ? trim((string) $action['handler']) : '';

            if ($handler === '') {
                return [];
            }

            return [[
                'action_key' => $handler,
                'type' => 'handler',
                'label' => isset($action['label']) && trim((string) $action['label']) !== '' ? trim((string) $action['label']) : 'Ir',
                'placement' => 'primary',
                'category' => 'mechanical',
                'handler' => $handler,
                'enabled' => 1,
                'position' => 0,
            ]];
        }

        $navigation = is_array($definition['navigation'] ?? null) ? $definition['navigation'] : [];
        $module = isset($navigation['module']) ? trim((string) $navigation['module']) : '';

        if ($module === '') {
            return [];
        }

        $setup_focus = isset($navigation['setup_focus']) ? trim((string) $navigation['setup_focus']) : '';

        return [[
            'action_key' => $setup_focus !== '' ? "navigate.{$module}.{$setup_focus}" : "navigate.{$module}",
            'type' => 'navigate',
            'label' => 'Ir',
            'placement' => 'primary',
            'category' => 'mechanical',
            'target_module' => $module,
            'target_setup_focus' => $setup_focus !== '' ? $setup_focus : null,
            'target_fragment' => isset($navigation['fragment']) && trim((string) $navigation['fragment']) !== '' ? trim((string) $navigation['fragment']) : null,
            'url' => null,
            'handler' => null,
            'enabled' => 1,
            'position' => 0,
        ]];
    }

    /**
     * @param array<string,mixed> $definition
     */
    private function default_bucket(array $definition): string {
        return (int) ($definition['default_list'] ?? 2) === 1 ? 'primary' : 'secondary';
    }

    /**
     * @param array<string,mixed> $definition
     */
    private function completion_type(array $definition): string {
        return ($definition['completion_type'] ?? '') === AA_Learning_Catalog::COMPLETION_AUTO ? 'system' : 'manual';
    }
}
