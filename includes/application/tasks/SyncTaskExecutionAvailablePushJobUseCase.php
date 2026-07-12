<?php
/**
 * Sync Task Execution Available Push Job Use Case — best-effort sync tras persistir tarea.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/infrastructure/backend/class-aa-push-backend-client.php';
require_once __DIR__ . '/TaskUseCaseSupport.php';

final class SyncTaskExecutionAvailablePushJobUseCase {

    /** @var callable|null */
    private $push_client_sync;

    /** @var callable|null */
    private $timezone_reader;

    /**
     * @param callable|null $push_client_sync (array $payload): array
     * @param callable|null $timezone_reader (): string
     */
    public function __construct(
        ?callable $push_client_sync = null,
        ?callable $timezone_reader = null
    ) {
        $this->push_client_sync = $push_client_sync;
        $this->timezone_reader = $timezone_reader;
    }

    /**
     * Best-effort tras persistir aa_tasks.execution_available_at.
     *
     * @param array<string,mixed> $task
     */
    public static function sync_after_task_persisted_best_effort(array $task): void {
        $result = (new self())->execute(['task' => $task]);

        if (!empty($result['success'])) {
            return;
        }

        $task_id = (int) ($task['id'] ?? 0);
        $code = (string) ($result['error']['code'] ?? 'unknown');

        error_log(
            '⚠️ [SyncTaskExecutionAvailablePushJob] Sync no completado para tarea '
            . $task_id
            . ': '
            . $code
        );
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $task = $input['task'] ?? null;

        if (!is_array($task)) {
            return TaskUseCaseSupport::fail('missing_task', 'La tarea persistida es obligatoria.');
        }

        $task_id = (int) ($task['id'] ?? 0);

        if ($task_id < 1) {
            return TaskUseCaseSupport::fail('missing_task_id', 'El identificador de la tarea es obligatorio.');
        }

        $raw_execution_at = isset($task['execution_available_at'])
            ? trim((string) $task['execution_available_at'])
            : '';

        $payload = [
            'task_id' => $task_id,
            'execution_available_at' => null,
        ];

        if ($raw_execution_at !== '') {
            $execution_iso = $this->format_execution_available_at($raw_execution_at);

            if ($execution_iso === null) {
                return TaskUseCaseSupport::fail(
                    'invalid_execution_available_at',
                    'La fecha de ejecución disponible no es válida.'
                );
            }

            $payload['execution_available_at'] = $execution_iso;
        }

        $sync_result = $this->sync_with_backend($payload);

        return $this->map_sync_result($sync_result);
    }

    /**
     * @param array<string,mixed> $sync_result
     * @return array{success:bool,data?:array<string,mixed>,error?:array{code:string,message:string}}
     */
    private function map_sync_result(array $sync_result): array {
        if (!empty($sync_result['ok'])) {
            return TaskUseCaseSupport::ok([
                'sync' => isset($sync_result['sync']) && is_string($sync_result['sync'])
                    ? $sync_result['sync']
                    : 'unknown',
            ]);
        }

        $code = isset($sync_result['code']) && is_string($sync_result['code'])
            ? trim($sync_result['code'])
            : 'push_sync_failed';

        return TaskUseCaseSupport::fail($code, 'No se pudo sincronizar el job Push de tarea.');
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function sync_with_backend(array $payload): array {
        if ($this->push_client_sync !== null) {
            return call_user_func($this->push_client_sync, $payload);
        }

        $client = new AA_Push_Backend_Client();

        return $client->syncTaskExecutionAvailableJob($payload);
    }

    private function read_timezone(): string {
        if ($this->timezone_reader !== null) {
            return (string) call_user_func($this->timezone_reader);
        }

        return (string) get_option('aa_timezone', 'America/Mexico_City');
    }

    private function format_execution_available_at(string $local_datetime): ?string {
        try {
            $datetime = new DateTime($local_datetime, new DateTimeZone($this->read_timezone()));
        } catch (Exception $e) {
            return null;
        }

        return $datetime->format('c');
    }
}
