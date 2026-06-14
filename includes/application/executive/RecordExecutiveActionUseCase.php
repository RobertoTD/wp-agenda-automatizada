<?php
/**
 * Record Executive Action Use Case — acciones ejecutivas desde Propuesta ejecutiva (MC3).
 *
 * Orquesta validación contra propuesta actual, mutaciones permitidas y recálculo.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/domain/executive/class-aa-executive-contract.php';
require_once dirname(__DIR__, 2) . '/domain/executable/class-aa-executable-contract.php';
require_once __DIR__ . '/GetExecutiveProposalUseCase.php';
require_once __DIR__ . '/../tasks/ChangeTaskStatusUseCase.php';
require_once __DIR__ . '/../tasks/RecordTaskDismissSignalUseCase.php';
require_once __DIR__ . '/../tasks/TaskUseCaseSupport.php';

final class RecordExecutiveActionUseCase {

    /** @var callable|null */
    private $proposal_reader;

    /** @var callable|null */
    private $change_status_executor;

    /** @var callable|null */
    private $dismiss_executor;

    /**
     * @param callable|null $proposal_reader Debe devolver payload de GetExecutiveProposalUseCase.
     * @param callable|null $change_status_executor Debe aceptar input y devolver resultado de ChangeTaskStatusUseCase.
     * @param callable|null $dismiss_executor Debe aceptar input y devolver resultado de RecordTaskDismissSignalUseCase.
     */
    public function __construct(
        ?callable $proposal_reader = null,
        ?callable $change_status_executor = null,
        ?callable $dismiss_executor = null
    ) {
        $this->proposal_reader = $proposal_reader;
        $this->change_status_executor = $change_status_executor;
        $this->dismiss_executor = $dismiss_executor;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $task_id = TaskUseCaseSupport::normalize_task_id($input['task_id'] ?? 0);
        $action_key = trim((string) ($input['action_key'] ?? ''));

        if ($task_id < 1 || $action_key === '') {
            return TaskUseCaseSupport::fail('invalid_request', 'Solicitud de acción ejecutiva inválida.');
        }

        try {
            $proposal = $this->read_proposal();

            if (($proposal['status'] ?? '') !== AA_Executive_Contract::STATUS_READY) {
                return TaskUseCaseSupport::fail('proposal_empty', 'No hay propuesta ejecutiva disponible.');
            }

            $current = $this->find_current_task($proposal);

            if ($current === null) {
                return TaskUseCaseSupport::fail('proposal_empty', 'No hay tarea actual en la propuesta ejecutiva.');
            }

            if ((int) ($current['task_id'] ?? 0) !== $task_id) {
                return TaskUseCaseSupport::fail('task_not_current', 'La tarea no es la acción actual del Ejecutor.');
            }

            $action = $this->find_action($current, $action_key);

            if ($action === null) {
                return TaskUseCaseSupport::fail('action_not_allowed', 'La acción no está permitida en la propuesta ejecutiva.');
            }

            $execution = $this->execute_action($action, $task_id, $current);

            if (empty($execution['success'])) {
                $error = is_array($execution['error'] ?? null) ? $execution['error'] : [];

                return TaskUseCaseSupport::fail(
                    (string) ($error['code'] ?? 'action_failed'),
                    (string) ($error['message'] ?? 'No se pudo ejecutar la acción ejecutiva.')
                );
            }

            $new_proposal = $this->read_proposal();

            return TaskUseCaseSupport::ok([
                'action' => [
                    'key' => $action_key,
                    'type' => (string) ($action['type'] ?? ''),
                    'task_id' => $task_id,
                    'mutated' => !empty($execution['mutated']),
                ],
                'proposal' => $new_proposal,
                'client_action' => $execution['client_action'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            return TaskUseCaseSupport::fail(
                'executive_action_unavailable',
                'No se pudo ejecutar la acción ejecutiva.'
            );
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function read_proposal(): array {
        if ($this->proposal_reader !== null) {
            $payload = call_user_func($this->proposal_reader);

            return is_array($payload) ? $payload : [];
        }

        return (new GetExecutiveProposalUseCase())->execute();
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>|null
     */
    private function find_current_task(array $proposal): ?array {
        $tasks = is_array($proposal['tasks'] ?? null) ? $proposal['tasks'] : [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            if (($task['slot'] ?? '') === AA_Executive_Contract::SLOT_CURRENT) {
                return $task;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $current
     * @return array<string,mixed>|null
     */
    private function find_action(array $current, string $action_key): ?array {
        $actions = is_array($current['executive_actions'] ?? null) ? $current['executive_actions'] : [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            if ((string) ($action['key'] ?? '') === $action_key) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $action
     * @param array<string,mixed> $current
     * @return array{success:bool,mutated?:bool,client_action?:array<string,mixed>|null,error?:array{code:string,message:string}}
     */
    private function execute_action(array $action, int $task_id, array $current): array {
        $type = strtolower(trim((string) ($action['type'] ?? '')));
        $key = strtolower(trim((string) ($action['key'] ?? '')));

        if ($type === AA_Executable_Contract::ACTION_STATUS && $key === 'complete') {
            return $this->execute_complete($task_id);
        }

        if ($type === AA_Executable_Contract::ACTION_INTENT && $key === 'dismiss') {
            return $this->execute_dismiss($task_id);
        }

        if ($type === AA_Executable_Contract::ACTION_NAVIGATE) {
            $url = trim((string) ($action['url'] ?? ''));

            if ($url === '') {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'action_failed',
                        'message' => 'La acción de navegación no tiene URL.',
                    ],
                ];
            }

            return [
                'success' => true,
                'mutated' => false,
                'client_action' => [
                    'type' => 'navigate',
                    'url' => $url,
                ],
            ];
        }

        if ($type === AA_Executable_Contract::ACTION_HANDLER) {
            $handler = trim((string) ($action['handler'] ?? ''));

            if ($handler === '') {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'action_failed',
                        'message' => 'La acción handler no está configurada.',
                    ],
                ];
            }

            return [
                'success' => true,
                'mutated' => false,
                'client_action' => [
                    'type' => 'handler',
                    'handler' => $handler,
                    'origin_key' => isset($current['origin_key']) ? (string) $current['origin_key'] : null,
                    'task_id' => $task_id,
                    'source' => isset($current['source']) ? (string) $current['source'] : null,
                    'label' => (string) ($action['label'] ?? ''),
                ],
            ];
        }

        return [
            'success' => false,
            'error' => [
                'code' => 'action_not_allowed',
                'message' => 'Tipo de acción ejecutiva no soportado.',
            ],
        ];
    }

    /**
     * @return array{success:bool,mutated?:bool,client_action?:null,error?:array{code:string,message:string}}
     */
    private function execute_complete(int $task_id): array {
        $result = $this->change_status_executor !== null
            ? call_user_func($this->change_status_executor, [
                'task_id' => $task_id,
                'status' => 'done',
            ])
            : (new ChangeTaskStatusUseCase())->execute([
                'task_id' => $task_id,
                'status' => 'done',
            ]);

        if (empty($result['success'])) {
            return [
                'success' => false,
                'error' => is_array($result['error'] ?? null) ? $result['error'] : [
                    'code' => 'action_failed',
                    'message' => 'No se pudo completar la tarea.',
                ],
            ];
        }

        return [
            'success' => true,
            'mutated' => true,
            'client_action' => null,
        ];
    }

    /**
     * @return array{success:bool,mutated?:bool,client_action?:null,error?:array{code:string,message:string}}
     */
    private function execute_dismiss(int $task_id): array {
        $result = $this->dismiss_executor !== null
            ? call_user_func($this->dismiss_executor, ['task_id' => $task_id])
            : (new RecordTaskDismissSignalUseCase())->execute(['task_id' => $task_id]);

        if (empty($result['success'])) {
            return [
                'success' => false,
                'error' => is_array($result['error'] ?? null) ? $result['error'] : [
                    'code' => 'action_failed',
                    'message' => 'No se pudo registrar la señal de ignorar.',
                ],
            ];
        }

        return [
            'success' => true,
            'mutated' => true,
            'client_action' => null,
        ];
    }
}
