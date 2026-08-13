<?php
/**
 * Update Service Area Use Case — actualización atómica de nombre y color.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';

final class UpdateServiceAreaUseCase {

    /** @var callable(int): ?array */
    private $find_by_id;

    /** @var callable(int, string, string): bool */
    private $update_name_and_color;

    /**
     * @param callable|null $find_by_id
     * @param callable|null $update_name_and_color
     */
    public function __construct(?callable $find_by_id = null, ?callable $update_name_and_color = null) {
        $this->find_by_id = $find_by_id ?? [AssignmentsRepository::class, 'find_service_area_by_id'];
        $this->update_name_and_color = $update_name_and_color
            ?? [AssignmentsRepository::class, 'update_service_area_name_and_color'];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array{area:array<string,mixed>},error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = isset($input['name']) ? trim((string) $input['name']) : '';
        $color = isset($input['color']) ? trim((string) $input['color']) : '';

        if ($id <= 0) {
            return $this->fail('invalid_id', 'ID inválido');
        }

        if ($name === '') {
            return $this->fail('invalid_name', 'El nombre no puede estar vacío');
        }

        if ($color === '' || !preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
            return $this->fail(
                'invalid_color',
                'Formato de color inválido. Debe ser hexadecimal (ej: #16225b)'
            );
        }

        $existing = ($this->find_by_id)($id);
        if (!is_array($existing)) {
            return $this->fail('not_found', 'Zona de atención no encontrada');
        }

        $updated = ($this->update_name_and_color)($id, $name, $color);
        if ($updated !== true) {
            return $this->fail('persistence_failed', 'Error al actualizar la zona de atención');
        }

        $area = ($this->find_by_id)($id);
        if (!is_array($area)) {
            return $this->fail('persistence_failed', 'Error al actualizar la zona de atención');
        }

        return [
            'success' => true,
            'data' => [
                'area' => $area,
            ],
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
}
