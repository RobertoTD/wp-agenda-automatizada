<?php
/**
 * Get Executable Lists Feed Use Case — ensambla feed común MC7 desde Learning + Tasks.
 *
 * Orquesta Use Cases existentes y mappers de proyección; no recalcula policies.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/ExecutableVisibleActionsEnricher.php';
require_once __DIR__ . '/LearningRecommendationsToExecutableMapper.php';
require_once __DIR__ . '/TaskBoardToExecutableMapper.php';

final class GetExecutableListsFeedUseCase {

    public const META_VERSION = 1;

    public const VIEW_ACTIVE = AA_Executable_Visible_Actions_Policy::VIEW_ACTIVE;

    /** @var callable|null */
    private $learning_reader;

    /** @var callable|null */
    private $tasks_reader;

    /**
     * @param callable|null $learning_reader Debe devolver payload de GetLearningRecommendationsUseCase.
     * @param callable|null $tasks_reader     Debe devolver payload de GetTaskBoardUseCase.
     */
    public function __construct(?callable $learning_reader = null, ?callable $tasks_reader = null) {
        $this->learning_reader = $learning_reader;
        $this->tasks_reader = $tasks_reader;
    }

    /**
     * @return array{
     *     success:bool,
     *     lists:list<array<string,mixed>>,
     *     meta:array<string,mixed>,
     *     error?:array{code:string,message:string}
     * }
     */
    public function execute(): array {
        $system_list = null;
        $user_lists = [];
        $learning_source = $this->build_source_error_meta('No se pudo cargar recomendaciones.');
        $tasks_source = $this->build_source_error_meta('No se pudo cargar listas de usuario.');
        $task_payload = [];

        try {
            $task_payload = $this->read_tasks();
            $user_lists = TaskBoardToExecutableMapper::map($task_payload);
            $tasks_source = $this->build_tasks_source_meta($user_lists);
        } catch (\Throwable $exception) {
            $tasks_source = $this->build_source_error_meta($exception->getMessage());
        }

        if (
            $this->payload_has_ready_seeded_agenda_app_list($task_payload)
            && $this->learning_state_is_safe_to_skip()
        ) {
            $learning_source = $this->build_learning_source_skipped_meta();
        } else {
            try {
                $learning_payload = $this->read_learning();
                $system_list = LearningRecommendationsToExecutableMapper::map($learning_payload);
                $learning_source = $this->build_learning_source_meta($system_list);
            } catch (\Throwable $exception) {
                $learning_source = $this->build_source_error_meta($exception->getMessage());
            }
        }

        if (($learning_source['status'] ?? '') === 'error' && ($tasks_source['status'] ?? '') === 'error') {
            return [
                'success' => false,
                'lists' => [],
                'meta' => $this->build_meta([], $learning_source, $tasks_source),
                'error' => [
                    'code' => 'feed_sources_unavailable',
                    'message' => 'No se pudo cargar el feed de listas.',
                ],
            ];
        }

        $lists = ExecutableVisibleActionsEnricher::enrich_lists(
            $this->assemble_lists($system_list, $user_lists),
            ['view' => self::VIEW_ACTIVE]
        );

        return [
            'success' => true,
            'lists' => $lists,
            'meta' => $this->build_meta($lists, $learning_source, $tasks_source),
        ];
    }

    /**
     * @return array{
     *     list_1?:list<array<string,mixed>>,
     *     list_2?:list<array<string,mixed>>,
     *     all_visible?:list<array<string,mixed>>
     * }
     */
    private function read_learning(): array {
        if ($this->learning_reader !== null) {
            $payload = ($this->learning_reader)();

            if (!is_array($payload)) {
                throw new \RuntimeException('Learning reader must return an array.');
            }

            return $payload;
        }

        require_once dirname(__DIR__) . '/learning/GetLearningRecommendationsUseCase.php';

        return (new GetLearningRecommendationsUseCase())->execute();
    }

    /**
     * @return array{
     *     lists?:list<array<string,mixed>>,
     *     tasks?:list<array<string,mixed>>,
     *     organization?:array<string,mixed>
     * }
     */
    private function read_tasks(): array {
        if ($this->tasks_reader !== null) {
            $payload = ($this->tasks_reader)();

            if (!is_array($payload)) {
                throw new \RuntimeException('Tasks reader must return an array.');
            }

            return $payload;
        }

        require_once dirname(__DIR__) . '/tasks/GetTaskBoardUseCase.php';

        return (new GetTaskBoardUseCase())->execute();
    }

    /**
     * @param array<string,mixed>|null $system_list
     * @param list<array<string,mixed>>  $user_lists
     * @return list<array<string,mixed>>
     */
    private function assemble_lists(?array $system_list, array $user_lists): array {
        $lists = [];

        if (is_array($system_list)) {
            $lists[] = $system_list;
        }

        foreach ($user_lists as $list) {
            if (is_array($list)) {
                $lists[] = $list;
            }
        }

        return $lists;
    }

    /**
     * @param list<array<string,mixed>> $lists
     * @param array<string,mixed>       $learning_source
     * @param array<string,mixed>       $tasks_source
     * @return array<string,mixed>
     */
    private function build_meta(array $lists, array $learning_source, array $tasks_source): array {
        return [
            'version' => self::META_VERSION,
            'sources' => [
                'learning' => $learning_source,
                'tasks' => $tasks_source,
            ],
            'order' => $this->build_order($lists),
        ];
    }

    /**
     * @param list<array<string,mixed>> $lists
     * @return list<string>
     */
    private function build_order(array $lists): array {
        $order = [];

        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }

            $order[] = (string) ($list['id'] ?? '');
        }

        return $order;
    }

    /**
     * @param array<string,mixed> $system_list
     * @return array{status:string,list_count:int,item_count:int}
     */
    private function build_learning_source_meta(array $system_list): array {
        return [
            'status' => 'ok',
            'list_count' => 1,
            'item_count' => self::count_items_in_list($system_list),
        ];
    }

    /**
     * @param list<array<string,mixed>> $user_lists
     * @return array{status:string,list_count:int,item_count:int}
     */
    private function build_tasks_source_meta(array $user_lists): array {
        $item_count = 0;

        foreach ($user_lists as $list) {
            if (!is_array($list)) {
                continue;
            }

            $item_count += self::count_items_in_list($list);
        }

        return [
            'status' => 'ok',
            'list_count' => count($user_lists),
            'item_count' => $item_count,
        ];
    }

    /**
     * @param array{
     *     lists?:list<array<string,mixed>>,
     *     tasks?:list<array<string,mixed>>,
     *     organization?:array<string,mixed>
     * } $task_payload
     */
    private function payload_has_ready_seeded_agenda_app_list(array $task_payload): bool {
        $lists = is_array($task_payload['lists'] ?? null) ? $task_payload['lists'] : [];

        foreach ($lists as $list) {
            if (!is_array($list)) {
                continue;
            }

            $source_category = is_string($list['source_category'] ?? null)
                ? trim((string) $list['source_category'])
                : '';
            $origin_key = is_string($list['origin_key'] ?? null)
                ? trim((string) $list['origin_key'])
                : '';
            $status = strtolower(trim((string) ($list['status'] ?? '')));

            if (
                $source_category !== AA_Executable_Contract::SOURCE_CATEGORY_AGENDA_APP
                || $origin_key !== LearningRecommendationsToExecutableMapper::LIST_ORIGIN_KEY
                || $status !== AA_Executable_Contract::LIST_STATUS_ACTIVE
            ) {
                continue;
            }

            $list_id = (int) ($list['id'] ?? 0);

            if ($list_id > 0 && $this->payload_list_has_tasks($task_payload, $list_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{
     *     lists?:list<array<string,mixed>>,
     *     tasks?:list<array<string,mixed>>,
     *     organization?:array<string,mixed>
     * } $task_payload
     */
    private function payload_list_has_tasks(array $task_payload, int $list_id): bool {
        $organization = is_array($task_payload['organization'] ?? null) ? $task_payload['organization'] : [];
        $task_order_by_list = is_array($organization['task_order_by_list'] ?? null)
            ? $organization['task_order_by_list']
            : [];
        $task_order = is_array($task_order_by_list[$list_id] ?? null)
            ? $task_order_by_list[$list_id]
            : [];

        if ($task_order !== []) {
            return true;
        }

        $tasks = is_array($task_payload['tasks'] ?? null) ? $task_payload['tasks'] : [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            if ((int) ($task['list_id'] ?? 0) === $list_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * True cuando es seguro omitir Learning legacy: migración al día o sin estado accionable.
     */
    private function learning_state_is_safe_to_skip(): bool {
        try {
            require_once dirname(__DIR__, 2) . '/infrastructure/wp/LearningStateMigrationLifecycle.php';

            $stored_migration_version = (string) get_option(
                AA_Learning_State_Migration_Lifecycle::OPTION_MIGRATION_VERSION,
                '0'
            );

            if (version_compare(
                $stored_migration_version,
                AA_Learning_State_Migration_Lifecycle::MIGRATION_VERSION,
                '>='
            )) {
                return true;
            }

            require_once dirname(__DIR__, 2) . '/repositories/LearningRecommendationStateRepository.php';

            return !LearningRecommendationStateRepository::has_actionable_state();
        } catch (\Throwable $exception) {
            error_log('[GetExecutableListsFeedUseCase] learning_state_is_safe_to_skip: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * @return array{status:string,list_count:int,item_count:int,reason:string}
     */
    private function build_learning_source_skipped_meta(): array {
        return [
            'status' => 'skipped',
            'list_count' => 0,
            'item_count' => 0,
            'reason' => 'seeded_agenda_app_available',
        ];
    }

    /**
     * @param string $message
     * @return array{status:string,list_count:int,item_count:int,message:string}
     */
    private function build_source_error_meta(string $message): array {
        return [
            'status' => 'error',
            'list_count' => 0,
            'item_count' => 0,
            'message' => $message,
        ];
    }

    /**
     * @param array<string,mixed> $list
     */
    private static function count_items_in_list(array $list): int {
        $count = 0;

        foreach ($list['buckets'] ?? [] as $bucket) {
            if (!is_array($bucket)) {
                continue;
            }

            $items = $bucket['items'] ?? [];

            if (is_array($items)) {
                $count += count($items);
            }
        }

        return $count;
    }
}
