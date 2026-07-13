<?php
/**
 * Ensure Push Activation Task Use Case — asegura agenda_app / enable_push pending.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';
require_once __DIR__ . '/ChangeTaskStatusUseCase.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskActionRepository.php';

final class ReconcilePushActivationTaskUseCase {

    public const SOURCE_CATEGORY = 'agenda_app';

    public const TASK_ORIGIN_KEY = 'enable_push';

    private const LIST_ORIGIN_KEY = 'learning.recommendations';

    private const HANDLER = 'push.activate';

    private const ACTION_LABEL = 'Activar notificaciones';

    private const TASK_TITLE = 'Activa las notificaciones en este dispositivo';

    private const TASK_DESCRIPTION = 'Permite que DEOIA te avise en este dispositivo cuando una cita confirmada esté próxima, cuando una tarea llegue a su momento de realización y ante otros avisos importantes.';

    /** @var callable|null */
    private $list_resolver;

    /** @var callable|null */
    private $task_finder;

    /** @var callable|null */
    private $task_creator;

    /** @var callable|null */
    private $pending_ensurer;

    /** @var callable|null */
    private $action_ensurer;

    /**
     * @param callable|null $list_resolver  () => ?array
     * @param callable|null $task_finder    (string $source_category, string $origin_key) => ?array
     * @param callable|null $task_creator   (int $list_id) => ?array
     * @param callable|null $pending_ensurer (int $task_id) => array use-case result
     * @param callable|null $action_ensurer (int $task_id) => ?array
     */
    public function __construct(
        ?callable $list_resolver = null,
        ?callable $task_finder = null,
        ?callable $task_creator = null,
        ?callable $pending_ensurer = null,
        ?callable $action_ensurer = null
    ) {
        $this->list_resolver = $list_resolver;
        $this->task_finder = $task_finder;
        $this->task_creator = $task_creator;
        $this->pending_ensurer = $pending_ensurer;
        $this->action_ensurer = $action_ensurer;
    }

    /**
     * @return array{
     *     success:bool,
     *     data?:array<string,mixed>,
     *     error?:array{code:string,message:string,retryable?:bool}
     * }
     */
    public function execute(): array {
        $list = $this->resolve_active_list();

        if ($list === null) {
            return self::fail(
                'activation_list_not_ready',
                'La lista de activación no está disponible.',
                true
            );
        }

        $list_id = (int) ($list['id'] ?? 0);

        if ($list_id < 1) {
            return self::fail(
                'activation_list_not_ready',
                'La lista de activación no está disponible.',
                true
            );
        }

        $result = $this->ensure_global_task($list_id);

        if (!empty($result['error'])) {
            $error = $result['error'];

            return self::fail(
                (string) ($error['code'] ?? 'unknown_error'),
                (string) ($error['message'] ?? 'No se pudo reconciliar la tarea de activación push.')
            );
        }

        return TaskUseCaseSupport::ok($result);
    }

    /**
     * @return array<string,mixed>
     */
    private function ensure_global_task(int $list_id): array {
        $existing = $this->find_global_task();

        if ($existing !== null) {
            $task_id = (int) ($existing['id'] ?? 0);

            if ($task_id < 1) {
                return [
                    'error' => [
                        'code' => 'task_persistence_failed',
                        'message' => 'No se pudo localizar la tarea de activación push.',
                    ],
                ];
            }

            if (strtolower(trim((string) ($existing['status'] ?? ''))) !== 'pending') {
                $pending_result = $this->ensure_pending($task_id);

                if (empty($pending_result['success'])) {
                    return [
                        'error' => [
                            'code' => 'task_persistence_failed',
                            'message' => 'No se pudo mantener pendiente la tarea de activación push.',
                        ],
                    ];
                }

                $refreshed = $this->find_global_task();
                if (is_array($refreshed)) {
                    $existing = $refreshed;
                }
            }

            if ($this->ensure_push_action($task_id) === null) {
                return [
                    'error' => [
                        'code' => 'action_persistence_failed',
                        'message' => 'No se pudo persistir la acción de activación push.',
                    ],
                ];
            }

            return $this->build_result($existing, false);
        }

        $created = $this->create_global_task($list_id);

        if ($created === null) {
            $recovered = $this->find_global_task();

            if ($recovered !== null) {
                $task_id = (int) ($recovered['id'] ?? 0);

                if ($task_id < 1) {
                    return [
                        'error' => [
                            'code' => 'task_persistence_failed',
                            'message' => 'No se pudo localizar la tarea de activación push.',
                        ],
                    ];
                }

                if ($this->ensure_push_action($task_id) === null) {
                    return [
                        'error' => [
                            'code' => 'action_persistence_failed',
                            'message' => 'No se pudo persistir la acción de activación push.',
                        ],
                    ];
                }

                return $this->build_result($recovered, false);
            }

            return [
                'error' => [
                    'code' => 'task_persistence_failed',
                    'message' => 'No se pudo crear la tarea de activación push.',
                ],
            ];
        }

        return $this->build_result($created, true);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function create_global_task(int $list_id): ?array {
        if ($this->task_creator !== null) {
            $task = call_user_func($this->task_creator, $list_id);

            if (!is_array($task)) {
                return null;
            }

            $task_id = (int) ($task['id'] ?? 0);

            if ($task_id < 1 || $this->ensure_push_action($task_id) === null) {
                return null;
            }

            return $task;
        }

        $task = SeededTaskRepository::upsert_seeded_task($this->build_task_payload($list_id));

        if ($task === null) {
            return null;
        }

        $task_id = (int) ($task['id'] ?? 0);

        if ($task_id < 1) {
            return null;
        }

        if ($this->ensure_push_action($task_id) === null) {
            return null;
        }

        return $this->find_global_task();
    }

    /**
     * @return array<string,mixed>
     */
    private function build_task_payload(int $list_id): array {
        return [
            'list_id' => $list_id,
            'title' => self::TASK_TITLE,
            'notes' => self::TASK_DESCRIPTION,
            'status' => 'pending',
            'source' => 'system',
            'source_category' => self::SOURCE_CATEGORY,
            'origin_key' => self::TASK_ORIGIN_KEY,
            'managed_by' => 'developer',
            'importance' => 110,
            'default_bucket' => 'primary',
            'completion_type' => 'system',
            'completion_fact_key' => null,
            'due_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function find_global_task(): ?array {
        if ($this->task_finder !== null) {
            $task = call_user_func($this->task_finder, self::SOURCE_CATEGORY, self::TASK_ORIGIN_KEY);

            return is_array($task) ? $task : null;
        }

        return SeededTaskRepository::find_task_by_origin(self::SOURCE_CATEGORY, self::TASK_ORIGIN_KEY);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function ensure_push_action(int $task_id): ?array {
        if ($this->action_ensurer !== null) {
            $action = call_user_func($this->action_ensurer, $task_id);

            return is_array($action) ? $action : null;
        }

        return TaskActionRepository::upsert($task_id, [
            'action_key' => self::HANDLER,
            'type' => 'handler',
            'label' => self::ACTION_LABEL,
            'placement' => 'primary',
            'category' => 'mechanical',
            'handler' => self::HANDLER,
            'enabled' => 1,
            'position' => 0,
        ]);
    }

    /**
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    private function ensure_pending(int $task_id): array {
        if ($this->pending_ensurer !== null) {
            $result = call_user_func($this->pending_ensurer, $task_id);

            return is_array($result) ? $result : TaskUseCaseSupport::fail(
                'task_persistence_failed',
                'No se pudo mantener pendiente la tarea.'
            );
        }

        return (new ChangeTaskStatusUseCase())->execute([
            'task_id' => $task_id,
            'status' => 'pending',
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolve_active_list(): ?array {
        if ($this->list_resolver !== null) {
            $list = call_user_func($this->list_resolver);

            if (!is_array($list)) {
                return null;
            }

            if (strtolower(trim((string) ($list['status'] ?? ''))) !== 'active') {
                return null;
            }

            return $list;
        }

        $list = SeededTaskRepository::find_list_by_origin(self::SOURCE_CATEGORY, self::LIST_ORIGIN_KEY);

        if ($list === null) {
            return null;
        }

        if (strtolower(trim((string) ($list['status'] ?? ''))) !== 'active') {
            return null;
        }

        return $list;
    }

    /**
     * @return array<string,mixed>
     */
    private function build_result(?array $task, bool $created): array {
        return [
            'task' => $task,
            'created' => $created,
            'retryable' => false,
        ];
    }

    /**
     * @return array{success:false,error:array{code:string,message:string,retryable?:bool}}
     */
    private static function fail(string $code, string $message, bool $retryable = false): array {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($retryable) {
            $error['retryable'] = true;
        }

        return [
            'success' => false,
            'error' => $error,
        ];
    }
}
