<?php
/**
 * Create Expediente Registro Use Case — alta de registro hijo bajo padre real.
 *
 * Persistencia:
 * - padre general (client_id NULL) → insert_for_expediente (client_id NULL)
 * - padre vinculado a cliente → insert_for_client_expediente (ambos IDs, derivados en servidor)
 *
 * Ignora client_id, blog_id, recorded_at y created_at del input HTTP.
 *
 * Ciclo A: named lock por scope client|expediente antes de insertar.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('AA_Expediente_Registro_Create_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-registro-create-policy.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}
if (!class_exists('ExpedienteRegistrosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';
}
if (!class_exists('AA_Expediente_Aggregate_Lock')) {
    require_once dirname(__DIR__, 2) . '/infrastructure/wp/class-aa-expediente-aggregate-lock.php';
}

final class CreateExpedienteRegistroUseCase {

    /** @var AA_Expediente_Registro_Create_Policy */
    private $policy;

    /** @var AA_Expediente_Aggregate_Lock */
    private $lock;

    public function __construct(
        ?AA_Expediente_Registro_Create_Policy $policy = null,
        ?AA_Expediente_Aggregate_Lock $lock = null
    ) {
        $this->policy = $policy ?: new AA_Expediente_Registro_Create_Policy();
        $this->lock = $lock ?: AA_Expediente_Aggregate_Lock::create_default();
    }

    /**
     * @param array{expediente_id?:mixed,title?:mixed,body?:mixed} $input
     * @return array{success:true,data:array{record:array<string,mixed>}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $expediente_id = AA_Expediente_Id_Policy::normalize($input['expediente_id'] ?? null);
        if ($expediente_id === null) {
            return $this->fail('invalid_id', 'Expediente no válido.');
        }

        $title = $this->policy->normalize_title($input['title'] ?? null);
        if ($title === null) {
            return $this->fail('missing_title', 'El título es obligatorio.');
        }

        if ($this->policy->title_exceeds_max($title)) {
            return $this->fail('title_too_long', 'El título es demasiado largo.');
        }

        $body = $this->policy->normalize_body($input['body'] ?? null);
        if ($body === null) {
            return $this->fail('missing_body', 'El texto es obligatorio.');
        }

        if ($this->policy->body_exceeds_max($body)) {
            return $this->fail('body_too_long', 'El texto es demasiado largo.');
        }

        $exists = ExpedientesRepository::exists_by_id($expediente_id);
        if ($exists === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }
        if ($exists === false) {
            return $this->fail('not_found', 'Expediente no encontrado.');
        }

        $owner = ExpedientesRepository::find_owner_context_by_id($expediente_id);
        if ($owner === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }

        $scope = $this->resolve_scope($owner, $expediente_id);
        if ($scope === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }

        $lease = $this->lock->acquire(
            $scope['kind'],
            $scope['id'],
            AA_Expediente_Aggregate_Lock::DEFAULT_TIMEOUT_SECONDS
        );
        if (is_wp_error($lease)) {
            return $this->fail_from_lock($lease);
        }

        try {
            $exists_after = ExpedientesRepository::exists_by_id($expediente_id);
            if ($exists_after === null) {
                return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
            }
            if ($exists_after === false) {
                return $this->fail('not_found', 'Expediente no encontrado.');
            }

            $owner_after = ExpedientesRepository::find_owner_context_by_id($expediente_id);
            if ($owner_after === null) {
                return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
            }

            if (!$this->same_owner_context($owner, $owner_after, $expediente_id)) {
                return $this->fail(
                    'concurrent_change',
                    'El expediente cambió mientras se preparaba la operación.'
                );
            }

            $held = $this->lock->assert_held($lease);
            if (is_wp_error($held)) {
                return $this->fail_from_lock($held);
            }

            $now = current_time('mysql');
            $parent_client_id = $owner_after['client_id'] ?? null;

            if (is_int($parent_client_id) && $parent_client_id > 0) {
                $record = ExpedienteRegistrosRepository::insert_for_client_expediente([
                    'client_id' => $parent_client_id,
                    'expediente_id' => $expediente_id,
                    'title' => $title,
                    'body' => $body,
                    'recorded_at' => $now,
                    'created_at' => $now,
                ]);
            } else {
                $record = ExpedienteRegistrosRepository::insert_for_expediente([
                    'expediente_id' => $expediente_id,
                    'title' => $title,
                    'body' => $body,
                    'recorded_at' => $now,
                    'created_at' => $now,
                ]);
            }

            if (is_wp_error($record)) {
                return $this->fail('persistence_failed', $record->get_error_message());
            }

            unset($record['client_id'], $record['expediente_id'], $record['blog_id']);

            return $this->ok([
                'record' => $record,
            ]);
        } finally {
            $this->lock->release($lease);
        }
    }

    /**
     * @param array{id:int,client_id:?int} $owner
     * @return array{kind:string,id:int}|null
     */
    private function resolve_scope(array $owner, int $expediente_id): ?array {
        $owner_id = AA_Expediente_Id_Policy::normalize($owner['id'] ?? null);
        if ($owner_id === null || $owner_id !== $expediente_id) {
            return null;
        }

        $client_raw = $owner['client_id'] ?? null;
        if ($client_raw === null) {
            return [
                'kind' => AA_Expediente_Aggregate_Lock::SCOPE_EXPEDIENTE,
                'id' => $expediente_id,
            ];
        }

        if (!is_int($client_raw) || $client_raw < 1) {
            return null;
        }

        return [
            'kind' => AA_Expediente_Aggregate_Lock::SCOPE_CLIENT,
            'id' => $client_raw,
        ];
    }

    /**
     * @param array{id:int,client_id:?int} $before
     * @param array{id:int,client_id:?int} $after
     */
    private function same_owner_context(array $before, array $after, int $expediente_id): bool {
        $before_id = AA_Expediente_Id_Policy::normalize($before['id'] ?? null);
        $after_id = AA_Expediente_Id_Policy::normalize($after['id'] ?? null);
        if (
            $before_id === null
            || $after_id === null
            || $before_id !== $expediente_id
            || $after_id !== $expediente_id
        ) {
            return false;
        }

        $before_client = $before['client_id'] ?? null;
        $after_client = $after['client_id'] ?? null;

        if ($before_client === null && $after_client === null) {
            return true;
        }

        if (
            is_int($before_client)
            && $before_client > 0
            && is_int($after_client)
            && $after_client === $before_client
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param WP_Error $error
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function fail_from_lock($error): array {
        $code = (string) $error->get_error_code();
        $message = (string) $error->get_error_message();
        if ($code === '') {
            $code = AA_Expediente_Aggregate_Lock::ERROR_COORDINATION_FAILED;
        }
        if ($message === '') {
            $message = 'No se pudo coordinar la operación.';
        }

        return $this->fail($code, $message);
    }

    /**
     * @return array{success:false,error:array{code:string,message:string}}
     */
    private function fail(string $code, string $message): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param array{record:array<string,mixed>} $data
     * @return array{success:true,data:array{record:array<string,mixed>}}
     */
    private function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}
