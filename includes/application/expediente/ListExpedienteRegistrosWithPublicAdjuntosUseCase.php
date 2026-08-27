<?php
/**
 * List Expediente Registros With Public Adjuntos Use Case — lectura AJAX enriquecida.
 *
 * Orquesta: existencia del padre + owner + listado textual paginado + bulk de
 * adjuntos públicos (record-scoped, dual v1/v2). Sin gate/nonce (handler HTTP).
 * No altera ListExpedienteRegistrosUseCase (SSR textual).
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('AA_Expediente_Adjunto_Identity_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-adjunto-identity-policy.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}
if (!class_exists('ListExpedienteRegistrosUseCase')) {
    require_once __DIR__ . '/ListExpedienteRegistrosUseCase.php';
}
if (!class_exists('ExpedienteAdjuntosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteAdjuntosRepository.php';
}
if (!class_exists('ExpedienteAdjuntoPublicDto')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/ExpedienteAdjuntoPublicDto.php';
}

final class ListExpedienteRegistrosWithPublicAdjuntosUseCase {

    /** @var ListExpedienteRegistrosUseCase */
    private $list_use_case;

    public function __construct(?ListExpedienteRegistrosUseCase $list_use_case = null) {
        $this->list_use_case = $list_use_case ?: new ListExpedienteRegistrosUseCase();
    }

    /**
     * @param array{expediente_id?:mixed,page?:mixed} $input
     * @return array{success:true,data:array<string,mixed>}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $expediente_id = AA_Expediente_Id_Policy::normalize($input['expediente_id'] ?? null);
        if ($expediente_id === null) {
            return $this->fail('invalid_id', 'Expediente no válido.');
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

        $parent_client = AA_Expediente_Adjunto_Identity_Policy::normalize_client_id($owner['client_id'] ?? null);
        if (!$parent_client['ok']) {
            return $this->fail('adjunto_inconsistent', 'El expediente no es coherente.');
        }

        $list_result = $this->list_use_case->execute([
            'expediente_id' => $expediente_id,
            'page' => $input['page'] ?? 1,
        ]);
        if (empty($list_result['success'])) {
            $error = $list_result['error'] ?? [];
            return $this->fail(
                (string) ($error['code'] ?? 'unknown_error'),
                (string) ($error['message'] ?? 'No se pudo completar la acción.')
            );
        }

        $data = is_array($list_result['data'] ?? null) ? $list_result['data'] : [];
        $records = is_array($data['records'] ?? null) ? $data['records'] : [];

        $record_ids = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $rid = (int) ($record['id'] ?? 0);
            if ($rid > 0) {
                $record_ids[] = $rid;
            }
        }

        $adjuntos_by_record = [];
        if ($record_ids !== []) {
            $bulk = ExpedienteAdjuntosRepository::list_by_record_ids_for_records($record_ids);
            if ($bulk === null) {
                return $this->fail('lookup_failed', 'No se pudo verificar los adjuntos.');
            }
            $adjuntos_by_record = $bulk;
        }

        $parent_for_policy = [
            'id' => $expediente_id,
            'client_id' => $parent_client['id'],
        ];

        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                continue;
            }

            $rid = (int) ($record['id'] ?? 0);
            $rows = ($rid > 0) ? ($adjuntos_by_record[$rid] ?? []) : [];
            if (!is_array($rows)) {
                $rows = [];
            }

            $record_for_policy = [
                'id' => $rid,
                'expediente_id' => $expediente_id,
                'client_id' => $parent_client['id'],
            ];

            $dtos = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    return $this->fail('adjunto_inconsistent', 'Un adjunto local es inconsistente.');
                }

                $check = AA_Expediente_Adjunto_Identity_Policy::validate(
                    $parent_for_policy,
                    $record_for_policy,
                    $row
                );
                if (empty($check['ok'])) {
                    return $this->fail('adjunto_inconsistent', 'Un adjunto local es inconsistente.');
                }

                $dto = ExpedienteAdjuntoPublicDto::from($row);
                if ($dto !== null) {
                    $dtos[] = $dto;
                }
            }

            $records[$index]['adjuntos'] = $dtos;
            $records[$index]['adjunto'] = $dtos[0] ?? null;
        }

        $data['records'] = $records;

        return $this->ok($data);
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
     * @param array<string,mixed> $data
     * @return array{success:true,data:array<string,mixed>}
     */
    private function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}
