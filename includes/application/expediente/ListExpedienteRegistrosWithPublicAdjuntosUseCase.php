<?php
/**
 * List Expediente Registros With Public Adjuntos Use Case — lectura AJAX enriquecida.
 *
 * Orquesta: existencia del padre + owner + listado textual paginado + bulk de
 * adjuntos públicos. No aplica gate/nonce (viven en el handler HTTP).
 * No altera ListExpedienteRegistrosUseCase (SSR textual).
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
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

        $parent_client_id = $owner['client_id'] ?? null;
        $has_client = is_int($parent_client_id) && $parent_client_id > 0;

        $adjuntos_by_record = [];
        if ($has_client && $records !== []) {
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

            if ($record_ids !== []) {
                $adjuntos_by_record = ExpedienteAdjuntosRepository::list_by_record_ids(
                    $record_ids,
                    $parent_client_id
                );
                if (!is_array($adjuntos_by_record)) {
                    $adjuntos_by_record = [];
                }
            }
        }

        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                continue;
            }

            $rid = (int) ($record['id'] ?? 0);
            $rows = ($has_client && $rid > 0)
                ? ($adjuntos_by_record[$rid] ?? [])
                : [];
            if (!is_array($rows)) {
                $rows = [];
            }

            $dtos = [];
            foreach ($rows as $row) {
                $dto = ExpedienteAdjuntoPublicDto::from(is_array($row) ? $row : null);
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
