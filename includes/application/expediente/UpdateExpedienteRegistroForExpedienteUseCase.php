<?php
/**
 * Update Expediente Registro For Expediente — edición textual canónica.
 *
 * Ownership derivado en servidor (expediente_id + record_id). Ignora client_id
 * y fechas del caller. Reutiliza AA_Expediente_Registro_Create_Policy para
 * título/cuerpo (reglas compartidas; nombre Create intencional).
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

final class UpdateExpedienteRegistroForExpedienteUseCase {

    /** @var AA_Expediente_Registro_Create_Policy */
    private $policy;

    public function __construct(?AA_Expediente_Registro_Create_Policy $policy = null) {
        $this->policy = $policy ?: new AA_Expediente_Registro_Create_Policy();
    }

    /**
     * @param array{expediente_id?:mixed,record_id?:mixed,title?:mixed,body?:mixed} $input
     * @return array{success:true,data:array{record:array<string,mixed>}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $expediente_id = AA_Expediente_Id_Policy::normalize($input['expediente_id'] ?? null);
        $record_id = AA_Expediente_Id_Policy::normalize($input['record_id'] ?? null);
        if ($expediente_id === null || $record_id === null) {
            return $this->fail('invalid_id', 'Identificador no válido.');
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

        $record = ExpedienteRegistrosRepository::find_by_id_for_expediente($record_id, $expediente_id);
        if ($record === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el registro.');
        }
        if ($record === false) {
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        if (!$this->owners_consistent($owner, $record, $expediente_id)) {
            error_log('[UpdateExpedienteRegistroForExpedienteUseCase] record owner mismatch');
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        $now = current_time('mysql');

        $updated = ExpedienteRegistrosRepository::update_title_body_for_expediente(
            $record_id,
            $expediente_id,
            $title,
            $body,
            $now
        );

        if (is_wp_error($updated)) {
            return $this->fail('persistence_failed', $updated->get_error_message());
        }

        $fresh = ExpedienteRegistrosRepository::find_by_id_for_expediente($record_id, $expediente_id);
        if ($fresh === null) {
            return $this->fail('lookup_failed', 'No se pudo verificar el registro.');
        }
        if ($fresh === false) {
            return $this->fail('not_found', 'Registro no encontrado.');
        }

        return $this->ok([
            'record' => $this->to_public_dto($fresh),
        ]);
    }

    /**
     * Normaliza client_id almacenado (int|string|null de MySQL) a ?int canónico.
     *
     * @param mixed $raw
     * @return array{ok:true,id:?int}|array{ok:false}
     */
    private function normalize_stored_client_id($raw): array {
        // General válido solo con NULL real de schema (client_id IS NULL).
        // '' / espacios / 0 / formatos no canónicos → malformado (fail-closed).
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
     * @param array{id:int,client_id:?int|string|null} $owner
     * @param array{id:int,expediente_id:int|string,client_id:?int|string|null} $record
     */
    private function owners_consistent(array $owner, array $record, int $expediente_id): bool {
        $parent = $this->normalize_stored_client_id($owner['client_id'] ?? null);
        $child = $this->normalize_stored_client_id($record['client_id'] ?? null);
        if (!$parent['ok'] || !$child['ok']) {
            return false;
        }

        if ($parent['id'] !== $child['id']) {
            return false;
        }

        $record_expediente = AA_Expediente_Id_Policy::normalize($record['expediente_id'] ?? null);
        if ($record_expediente === null || $record_expediente !== $expediente_id) {
            return false;
        }

        $owner_id = AA_Expediente_Id_Policy::normalize($owner['id'] ?? null);
        if ($owner_id === null || $owner_id !== $expediente_id) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,title:string,body:string,recorded_at:string,created_at:string,updated_at:?string}
     */
    private function to_public_dto(array $row): array {
        $updated = $row['updated_at'] ?? null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'recorded_at' => (string) ($row['recorded_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => ($updated === null || $updated === '') ? null : (string) $updated,
        ];
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
