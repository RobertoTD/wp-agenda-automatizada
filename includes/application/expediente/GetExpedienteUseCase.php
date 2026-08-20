<?php
/**
 * Get Expediente Use Case — lectura de un expediente padre por id.
 *
 * Valida una representación decimal positiva estricta (sin coercer signos).
 * Ignora cualquier id de instalación o sitio que el consumidor pueda enviar.
 * No toca clientes, registros ni adjuntos.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}

final class GetExpedienteUseCase {

    /**
     * @param array{expediente_id?:mixed} $input
     * @return array{success:true,data:array<string,mixed>}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $id = AA_Expediente_Id_Policy::normalize($input['expediente_id'] ?? null);
        if ($id === null) {
            return $this->fail('invalid_id', 'Expediente no válido.');
        }

        $row = ExpedientesRepository::find_by_id($id);
        if ($row === null) {
            return $this->fail('not_found', 'Expediente no encontrado.');
        }

        return $this->ok($row);
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
