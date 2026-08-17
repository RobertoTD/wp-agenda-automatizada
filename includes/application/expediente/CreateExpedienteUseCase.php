<?php
/**
 * Create Expediente Use Case — alta de expediente padre.
 *
 * Título obligatorio. Descripción opcional (vacía → null). Categoría por slug
 * (default `general`). Sin client_id y sin tocar tablas legacy.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Expediente_Create_Policy')) {
    require_once dirname(__DIR__, 2) . '/domain/expediente/class-aa-expediente-create-policy.php';
}
if (!class_exists('ExpedienteCategoriesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteCategoriesRepository.php';
}
if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}

final class CreateExpedienteUseCase {

    /** @var AA_Expediente_Create_Policy */
    private $policy;

    public function __construct(?AA_Expediente_Create_Policy $policy = null) {
        $this->policy = $policy ?: new AA_Expediente_Create_Policy();
    }

    /**
     * @param array{title?:mixed,description?:mixed,category_slug?:mixed} $input
     * @return array{success:true,data:array<string,mixed>}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $title = $this->policy->normalize_title($input['title'] ?? null);
        if ($title === null) {
            return $this->fail('missing_title', 'El título es obligatorio.');
        }

        if ($this->policy->title_exceeds_max($title)) {
            return $this->fail('title_too_long', 'El título supera el máximo permitido.');
        }

        $description = $this->policy->normalize_description($input['description'] ?? null);
        if ($description !== null && $this->policy->description_exceeds_max($description)) {
            return $this->fail('description_too_long', 'La descripción supera el máximo permitido.');
        }

        $slug = $this->policy->normalize_category_slug($input['category_slug'] ?? null);
        $category = ExpedienteCategoriesRepository::find_by_slug($slug);
        if ($category === null) {
            return $this->fail('category_not_found', 'La categoría no existe.');
        }

        $created_at = current_time('mysql');
        $row = ExpedientesRepository::insert([
            'title' => $title,
            'description' => $description,
            'category_id' => (int) $category['id'],
            'created_at' => $created_at,
        ]);

        if ($row === null) {
            return $this->fail('persistence_failed', 'No se pudo crear el expediente.');
        }

        return $this->ok([
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'description' => $row['description'],
            'category' => [
                'slug' => (string) $category['slug'],
                'name' => (string) $category['name'],
            ],
            'created_at' => (string) $row['created_at'],
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
