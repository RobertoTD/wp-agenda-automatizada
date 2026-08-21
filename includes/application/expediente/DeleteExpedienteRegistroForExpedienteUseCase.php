<?php
/**
 * Delete Expediente Registro For Expediente — eliminación canónica completa.
 *
 * Strategy A:
 * - padre cliente → una delegación a DeleteExpedienteRegistroUseCase (Storage→SQL)
 * - padre general → SQL canónico sin Storage; fail-closed si hay adjuntos
 *
 * Ownership derivado en servidor (expediente_id + record_id).
 * Deuda: tras delete, la paginación live/SSR puede quedar stale (sin relist).
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}
if (!class_exists('ExpedienteRegistrosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';
}
if (!class_exists('ExpedienteAdjuntosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteAdjuntosRepository.php';
}
if (!class_exists('DeleteExpedienteRegistroUseCase')) {
    require_once __DIR__ . '/DeleteExpedienteRegistroUseCase.php';
}

final class DeleteExpedienteRegistroForExpedienteUseCase {

    /** @var object DeleteExpedienteRegistroUseCase o doble de prueba */
    private $legacy_delete;

    /**
     * @param object|null $legacy_delete DeleteExpedienteRegistroUseCase o doble
     */
    public function __construct($legacy_delete = null) {
        $this->legacy_delete = $legacy_delete ?: new DeleteExpedienteRegistroUseCase();
    }

    /**
     * @param array{expediente_id?:mixed,record_id?:mixed} $input
     * @return array{success:true,data:array{deleted:true,record_id:int}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $expediente_id = AA_Expediente_Id_Policy::normalize($input['expediente_id'] ?? null);
        $record_id = AA_Expediente_Id_Policy::normalize($input['record_id'] ?? null);
        if ($expediente_id === null || $record_id === null) {
            return $this->fail('invalid_id', 'Identificador no válido.');
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

        $record = ExpedienteRegistrosRepository::find_by_id_for_expediente($record_id, $expediente_id);
        if ($record === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el registro.');
        }
        if ($record === false) {
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        $parent = $this->normalize_stored_client_id($owner['client_id'] ?? null);
        $child = $this->normalize_stored_client_id($record['client_id'] ?? null);
        if (!$parent['ok'] || !$child['ok'] || $parent['id'] !== $child['id']) {
            error_log('[DeleteExpedienteRegistroForExpedienteUseCase] record owner mismatch');
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        $record_expediente = AA_Expediente_Id_Policy::normalize($record['expediente_id'] ?? null);
        $owner_id = AA_Expediente_Id_Policy::normalize($owner['id'] ?? null);
        if (
            $record_expediente === null
            || $record_expediente !== $expediente_id
            || $owner_id === null
            || $owner_id !== $expediente_id
        ) {
            error_log('[DeleteExpedienteRegistroForExpedienteUseCase] record owner mismatch');
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        if ($parent['id'] === null) {
            return $this->delete_general($record_id, $expediente_id);
        }

        return $this->delete_for_client($parent['id'], $record_id);
    }

    /**
     * @return array{success:true,data:array{deleted:true,record_id:int}}|array{success:false,error:array{code:string,message:string}}
     */
    private function delete_for_client(int $client_id, int $record_id): array {
        $result = $this->legacy_delete->execute([
            'client_id' => $client_id,
            'record_id' => $record_id,
        ]);

        if (!empty($result['ok'])) {
            return $this->ok([
                'deleted' => true,
                'record_id' => $record_id,
            ]);
        }

        $code = (string) ($result['code'] ?? 'delete_failed');
        if ($code === 'record_not_found') {
            $code = 'not_found';
        }

        $message = (string) ($result['message'] ?? 'No se pudo eliminar el registro.');
        // Mensajes legacy ya son genéricos; no reinyectar paths/owners.
        return $this->fail($code, $message);
    }

    /**
     * @return array{success:true,data:array{deleted:true,record_id:int}}|array{success:false,error:array{code:string,message:string}}
     */
    private function delete_general(int $record_id, int $expediente_id): array {
        $has_adjuntos = ExpedienteAdjuntosRepository::has_any_by_record_id($record_id);
        if ($has_adjuntos === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el registro.');
        }
        if ($has_adjuntos === true) {
            return $this->fail('adjunto_inconsistent', 'Un adjunto local es inconsistente.');
        }

        $deleted = ExpedienteRegistrosRepository::delete_by_id_for_expediente($record_id, $expediente_id);
        if ($deleted === null) {
            return $this->fail('local_delete_failed', 'No se pudo eliminar el registro.');
        }
        if ($deleted === false) {
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        return $this->ok([
            'deleted' => true,
            'record_id' => $record_id,
        ]);
    }

    /**
     * @param mixed $raw
     * @return array{ok:true,id:?int}|array{ok:false}
     */
    private function normalize_stored_client_id($raw): array {
        if ($raw === null) {
            return ['ok' => true, 'id' => null];
        }

        if (is_int($raw)) {
            if ($raw < 1) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $raw];
        }

        if (is_string($raw)) {
            $normalized = AA_Expediente_Id_Policy::normalize($raw);
            if ($normalized === null) {
                return ['ok' => false];
            }

            return ['ok' => true, 'id' => $normalized];
        }

        return ['ok' => false];
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
     * @param array{deleted:true,record_id:int} $data
     * @return array{success:true,data:array{deleted:true,record_id:int}}
     */
    private function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}
