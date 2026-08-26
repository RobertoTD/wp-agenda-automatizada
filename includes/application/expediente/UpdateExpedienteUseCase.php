<?php
/**
 * Update Expediente Use Case — edición canónica del título del contenedor.
 *
 * Misma operación para cualquier categoría (general, clientes u otras).
 * No acepta client_id/category_id/description. Sin aggregate lock.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Id_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-id-policy.php';
}
if (!class_exists('AA_Expediente_Create_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-create-policy.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}

final class UpdateExpedienteUseCase {

    /** @var AA_Expediente_Create_Policy */
    private $policy;

    public function __construct(?AA_Expediente_Create_Policy $policy = null) {
        $this->policy = $policy ?: new AA_Expediente_Create_Policy();
    }

    /**
     * @param array{expediente_id?:mixed,title?:mixed} $input
     * @return array{success:true,data:array{expediente:array{id:int,title:string}}}|array{success:false,error:array{code:string,message:string}}
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
            return $this->fail('title_too_long', 'El título supera el máximo permitido.');
        }

        $context = ExpedientesRepository::find_title_context_by_id($expediente_id);
        if (is_wp_error($context)) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }
        if ($context === false) {
            return $this->fail('not_found', 'Expediente no encontrado.');
        }

        $stored_id = AA_Expediente_Id_Policy::normalize($context['id'] ?? null);
        if ($stored_id === null || $stored_id !== $expediente_id) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }

        $stored_title = $context['title'] ?? null;
        if (!is_string($stored_title)) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }

        if ($title === $stored_title) {
            return $this->ok([
                'expediente' => [
                    'id' => $stored_id,
                    'title' => $stored_title,
                ],
            ]);
        }

        $updated_at = current_time('mysql');
        $updated = ExpedientesRepository::update_title_by_id($expediente_id, $title, $updated_at);

        if (is_wp_error($updated)) {
            return $this->fail('persistence_failed', 'No se pudo actualizar el expediente.');
        }

        $fresh = ExpedientesRepository::find_title_context_by_id($expediente_id);
        if (is_wp_error($fresh)) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }
        if ($fresh === false) {
            return $this->fail('not_found', 'Expediente no encontrado.');
        }

        $fresh_id = AA_Expediente_Id_Policy::normalize($fresh['id'] ?? null);
        $fresh_title = $fresh['title'] ?? null;
        if ($fresh_id === null || $fresh_id !== $expediente_id || !is_string($fresh_title)) {
            return $this->fail('lookup_failed', 'No se pudo verificar el expediente.');
        }

        // true = UPDATE afectó; false = 0 filas (carrera/no-op MySQL). Siempre DTO de relectura.
        return $this->ok([
            'expediente' => [
                'id' => $fresh_id,
                'title' => $fresh_title,
            ],
        ]);
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
     * @param array{expediente:array{id:int,title:string}} $data
     * @return array{success:true,data:array{expediente:array{id:int,title:string}}}
     */
    private function ok(array $data): array {
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}
