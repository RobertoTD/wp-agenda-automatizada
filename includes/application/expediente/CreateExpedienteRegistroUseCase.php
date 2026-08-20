<?php
/**
 * Create Expediente Registro Use Case — alta de registro hijo bajo padre real.
 *
 * Persistencia vía insert_for_expediente (client_id NULL). Sin HTTP.
 * Ignora client_id, blog_id, recorded_at y created_at del input.
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

final class CreateExpedienteRegistroUseCase {

    /** @var AA_Expediente_Registro_Create_Policy */
    private $policy;

    public function __construct(?AA_Expediente_Registro_Create_Policy $policy = null) {
        $this->policy = $policy ?: new AA_Expediente_Registro_Create_Policy();
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

        $now = current_time('mysql');
        $record = ExpedienteRegistrosRepository::insert_for_expediente([
            'expediente_id' => $expediente_id,
            'title' => $title,
            'body' => $body,
            'recorded_at' => $now,
            'created_at' => $now,
        ]);

        if (is_wp_error($record)) {
            return $this->fail('persistence_failed', $record->get_error_message());
        }

        return $this->ok([
            'record' => $record,
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
