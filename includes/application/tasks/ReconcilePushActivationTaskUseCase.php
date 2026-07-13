<?php
/**
 * Reconcile Push Activation Task Use Case — ocurrencias enable_push:* por dispositivo.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/TaskUseCaseSupport.php';
require_once __DIR__ . '/ChangeTaskStatusUseCase.php';
require_once dirname(__DIR__, 2) . '/repositories/PushActivationTaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/SeededTaskRepository.php';
require_once dirname(__DIR__, 2) . '/repositories/TaskActionRepository.php';

final class ReconcilePushActivationTaskUseCase {

    private const SOURCE_CATEGORY = 'agenda_app';

    private const LIST_ORIGIN_KEY = 'learning.recommendations';

    private const HANDLER = 'push.activate';

    private const ACTION_LABEL = 'Activar notificaciones';

    private const TASK_TITLE = 'Activa las notificaciones en este dispositivo';

    private const TASK_DESCRIPTION = 'Permite que DEOIA te avise en este dispositivo cuando una cita confirmada esté próxima, cuando una tarea llegue a su momento de realización y ante otros avisos importantes.';

    /** @var callable|null */
    private $list_resolver;

    /** @var callable|null */
    private $lock_runner;

    /** @var callable|null */
    private $occurrences_lister;

    /** @var callable|null */
    private $task_creator;

    /** @var callable|null */
    private $status_changer;

    /** @var callable|null */
    private $action_ensurer;

    /**
     * @param callable|null $list_resolver       () => ?array
     * @param callable|null $lock_runner         (string $lock_name, callable $callback) => mixed
     * @param callable|null $occurrences_lister  (string $source_category, string $device_key) => list<array>
     * @param callable|null $task_creator        (int $list_id, string $origin_key) => ?array
     * @param callable|null $status_changer      (int $task_id) => array use-case result
     * @param callable|null $action_ensurer      (int $task_id) => ?array
     */
    public function __construct(
        ?callable $list_resolver = null,
        ?callable $lock_runner = null,
        ?callable $occurrences_lister = null,
        ?callable $task_creator = null,
        ?callable $status_changer = null,
        ?callable $action_ensurer = null
    ) {
        $this->list_resolver = $list_resolver;
        $this->lock_runner = $lock_runner;
        $this->occurrences_lister = $occurrences_lister;
        $this->task_creator = $task_creator;
        $this->status_changer = $status_changer;
        $this->action_ensurer = $action_ensurer;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{
     *     success:bool,
     *     data?:array<string,mixed>,
     *     error?:array{code:string,message:string,retryable?:bool}
     * }
     */
    public function execute(array $input): array {
        $device_key = self::normalize_device_key($input['device_key'] ?? null);

        if ($device_key === null) {
            return self::fail('invalid_device_key', 'La clave del dispositivo no es válida.');
        }

        $readiness = self::normalize_readiness($input['readiness'] ?? null);

        if ($readiness === null) {
            return self::fail('invalid_readiness', 'El estado de preparación no es válido.');
        }

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

        $lock_name = PushActivationTaskRepository::build_lock_name(
            (string) get_current_blog_id(),
            $device_key
        );

        $locked = $this->run_with_lock($lock_name, function () use ($readiness, $device_key, $list_id): array {
            $non_done = $this->list_non_done_occurrences(self::SOURCE_CATEGORY, $device_key);

            if ($readiness === 'prepared') {
                return $this->reconcile_prepared($non_done);
            }

            return $this->reconcile_unprepared($non_done, $list_id, $device_key);
        });

        if (!empty($locked['lock_unavailable'])) {
            return self::fail(
                'push_task_lock_unavailable',
                'No se pudo adquirir el bloqueo de reconciliación.',
                true
            );
        }

        if (!empty($locked['error'])) {
            $error = $locked['error'];

            return self::fail(
                (string) ($error['code'] ?? 'unknown_error'),
                (string) ($error['message'] ?? 'No se pudo reconciliar la tarea de activación push.')
            );
        }

        return TaskUseCaseSupport::ok($locked['data'] ?? []);
    }

    /**
     * @param list<array<string,mixed>> $non_done
     * @return array{data?:array<string,mixed>,error?:array{code:string,message:string,retryable?:bool}}
     */
    private function reconcile_prepared(array $non_done): array {
        $completed_task_ids = [];

        foreach ($non_done as $task) {
            $task_id = (int) ($task['id'] ?? 0);

            if ($task_id < 1) {
                continue;
            }

            $result = $this->complete_task($task_id);

            if (empty($result['success'])) {
                $error = $result['error'] ?? [];

                return [
                    'error' => [
                        'code' => (string) ($error['code'] ?? 'task_completion_failed'),
                        'message' => (string) ($error['message'] ?? 'No se pudo completar la tarea de activación push.'),
                    ],
                ];
            }

            $completed_task_ids[] = $task_id;
        }

        return [
            'data' => [
                'readiness' => 'prepared',
                'task' => null,
                'created' => false,
                'completed_task_ids' => $completed_task_ids,
                'retryable' => false,
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $non_done
     * @return array{data?:array<string,mixed>,error?:array{code:string,message:string,retryable?:bool}}
     */
    private function reconcile_unprepared(array $non_done, int $list_id, string $device_key): array {
        if ($non_done !== []) {
            $existing = $non_done[0];
            $task_id = (int) ($existing['id'] ?? 0);

            if ($task_id < 1) {
                return [
                    'error' => [
                        'code' => 'task_persistence_failed',
                        'message' => 'No se pudo localizar la tarea de activación push.',
                    ],
                ];
            }

            $action = $this->ensure_push_action($task_id);

            if ($action === null) {
                return [
                    'error' => [
                        'code' => 'action_persistence_failed',
                        'message' => 'No se pudo persistir la acción de activación push.',
                    ],
                ];
            }

            return [
                'data' => [
                    'readiness' => 'unprepared',
                    'task' => $existing,
                    'created' => false,
                    'completed_task_ids' => [],
                    'retryable' => false,
                ],
            ];
        }

        $task = $this->create_occurrence($list_id, $device_key);

        if ($task === null) {
            $recovered = $this->list_non_done_occurrences(self::SOURCE_CATEGORY, $device_key);

            if ($recovered !== []) {
                $existing = $recovered[0];
                $task_id = (int) ($existing['id'] ?? 0);

                if ($task_id < 1) {
                    return [
                        'error' => [
                            'code' => 'task_persistence_failed',
                            'message' => 'No se pudo localizar la tarea de activación push.',
                        ],
                    ];
                }

                $action = $this->ensure_push_action($task_id);

                if ($action === null) {
                    return [
                        'error' => [
                            'code' => 'action_persistence_failed',
                            'message' => 'No se pudo persistir la acción de activación push.',
                        ],
                    ];
                }

                return [
                    'data' => [
                        'readiness' => 'unprepared',
                        'task' => $existing,
                        'created' => false,
                        'completed_task_ids' => [],
                        'retryable' => false,
                    ],
                ];
            }

            return [
                'error' => [
                    'code' => 'task_persistence_failed',
                    'message' => 'No se pudo crear la tarea de activación push.',
                ],
            ];
        }

        return [
            'data' => [
                'readiness' => 'unprepared',
                'task' => $task,
                'created' => true,
                'completed_task_ids' => [],
                'retryable' => false,
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function create_occurrence(int $list_id, string $device_key): ?array {
        if ($this->task_creator !== null) {
            $occurrence_id = PushActivationTaskRepository::generate_occurrence_id();
            $origin_key = PushActivationTaskRepository::build_origin_key($device_key, $occurrence_id);

            if ($origin_key === null) {
                return null;
            }

            $task = call_user_func($this->task_creator, $list_id, $origin_key);

            if (!is_array($task)) {
                return null;
            }

            $task_id = (int) ($task['id'] ?? 0);

            if ($task_id < 1 || $this->ensure_push_action($task_id) === null) {
                return null;
            }

            return $task;
        }

        $occurrence_id = PushActivationTaskRepository::generate_occurrence_id();
        $origin_key = PushActivationTaskRepository::build_origin_key($device_key, $occurrence_id);

        if ($origin_key === null) {
            return null;
        }

        $task = SeededTaskRepository::upsert_seeded_task($this->build_task_payload($list_id, $origin_key));

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

        return SeededTaskRepository::find_task_by_origin(self::SOURCE_CATEGORY, $origin_key);
    }

    /**
     * @return array<string,mixed>
     */
    private function build_task_payload(int $list_id, string $origin_key): array {
        return [
            'list_id' => $list_id,
            'title' => self::TASK_TITLE,
            'notes' => self::TASK_DESCRIPTION,
            'status' => 'pending',
            'source' => 'system',
            'source_category' => self::SOURCE_CATEGORY,
            'origin_key' => $origin_key,
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
    private function complete_task(int $task_id): array {
        if ($this->status_changer !== null) {
            $result = call_user_func($this->status_changer, $task_id);

            return is_array($result) ? $result : TaskUseCaseSupport::fail('task_completion_failed', 'No se pudo completar la tarea.');
        }

        return (new ChangeTaskStatusUseCase())->execute([
            'task_id' => $task_id,
            'status' => 'done',
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function list_non_done_occurrences(string $source_category, string $device_key): array {
        if ($this->occurrences_lister !== null) {
            $rows = call_user_func($this->occurrences_lister, $source_category, $device_key);

            return is_array($rows) ? array_values($rows) : [];
        }

        return PushActivationTaskRepository::list_non_done_occurrences($source_category, $device_key);
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
     * @param callable():array{data?:array<string,mixed>,error?:array{code:string,message:string,retryable?:bool},lock_unavailable?:bool} $callback
     * @return array{data?:array<string,mixed>,error?:array{code:string,message:string,retryable?:bool},lock_unavailable?:bool}
     */
    private function run_with_lock(string $lock_name, callable $callback): array {
        if ($this->lock_runner !== null) {
            $result = call_user_func($this->lock_runner, $lock_name, $callback);

            return is_array($result) ? $result : ['lock_unavailable' => true];
        }

        if (!PushActivationTaskRepository::try_acquire_lock($lock_name)) {
            return ['lock_unavailable' => true];
        }

        try {
            $result = $callback();

            return is_array($result) ? $result : [];
        } finally {
            PushActivationTaskRepository::release_lock($lock_name);
        }
    }

    /**
     * @param mixed $value
     */
    private static function normalize_device_key($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $device_key = strtolower(trim($value));

        return PushActivationTaskRepository::is_valid_device_key($device_key) ? $device_key : null;
    }

    /**
     * @param mixed $value
     */
    private static function normalize_readiness($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $readiness = strtolower(trim($value));

        if ($readiness === 'prepared' || $readiness === 'unprepared') {
            return $readiness;
        }

        return null;
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
