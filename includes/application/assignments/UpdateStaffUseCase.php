<?php
/**
 * Update Staff Use Case — actualización atómica de nombre y servicios.
 */

defined('ABSPATH') or die('No direct access');

require_once dirname(__DIR__, 2) . '/repositories/AssignmentsRepository.php';

final class UpdateStaffUseCase {

    public const NAME_MAX_LENGTH = 191;

    /** @var callable(int): ?array */
    private $find_by_id;

    /** @var callable(int): array<int> */
    private $get_service_ids;

    /** @var callable(): array<int> */
    private $list_assignable_ids;

    /** @var callable(int): array */
    private $get_services;

    /** @var callable(int, string, array<int>, array<int>): bool */
    private $update_name_and_services;

    /**
     * @param callable|null $find_by_id
     * @param callable|null $get_service_ids
     * @param callable|null $list_assignable_ids
     * @param callable|null $get_services
     * @param callable|null $update_name_and_services
     */
    public function __construct(
        ?callable $find_by_id = null,
        ?callable $get_service_ids = null,
        ?callable $list_assignable_ids = null,
        ?callable $get_services = null,
        ?callable $update_name_and_services = null
    ) {
        $this->find_by_id = $find_by_id ?? [AssignmentsRepository::class, 'find_staff_by_id'];
        $this->get_service_ids = $get_service_ids ?? [AssignmentsRepository::class, 'get_staff_service_ids'];
        $this->list_assignable_ids = $list_assignable_ids ?? [AssignmentsRepository::class, 'list_assignable_service_ids'];
        $this->get_services = $get_services ?? [AssignmentsRepository::class, 'get_staff_services'];
        $this->update_name_and_services = $update_name_and_services
            ?? [AssignmentsRepository::class, 'update_staff_name_and_services'];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:bool,data?:array{staff:array<string,mixed>,added_count:int},error?:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        $name = isset($input['name']) ? trim((string) $input['name']) : '';
        $desired_ids = $this->normalize_service_ids($input['service_ids'] ?? []);

        if ($id <= 0) {
            return $this->fail('invalid_id', 'ID inválido');
        }

        if ($name === '') {
            return $this->fail('invalid_name', 'El nombre no puede estar vacío');
        }

        if ($this->name_length($name) > self::NAME_MAX_LENGTH) {
            return $this->fail(
                'invalid_name',
                'El nombre no puede superar ' . self::NAME_MAX_LENGTH . ' caracteres'
            );
        }

        $existing = ($this->find_by_id)($id);
        if (!is_array($existing)) {
            return $this->fail('not_found', 'Personal no encontrado');
        }

        $current_ids = $this->normalize_service_ids(($this->get_service_ids)($id));
        $assignable_ids = $this->normalize_service_ids(($this->list_assignable_ids)());
        $diff = $this->diff_service_ids($current_ids, $desired_ids);

        $assignable_set = array_fill_keys($assignable_ids, true);
        foreach ($diff['to_add'] as $service_id) {
            if (!isset($assignable_set[$service_id])) {
                return $this->fail(
                    'invalid_service_ids',
                    'Uno o más servicios no son asignables'
                );
            }
        }

        $name_unchanged = $name === trim((string) ($existing['name'] ?? ''));
        if ($name_unchanged && $diff['to_add'] === [] && $diff['to_remove'] === []) {
            return $this->ok($this->canonical_staff($id, $existing), 0);
        }

        $updated = ($this->update_name_and_services)($id, $name, $diff['to_add'], $diff['to_remove']);
        if ($updated !== true) {
            return $this->fail('persistence_failed', 'Error al actualizar el personal');
        }

        $reloaded = ($this->find_by_id)($id);
        $row = is_array($reloaded) ? $reloaded : [
            'id' => (int) ($existing['id'] ?? $id),
            'name' => $name,
            'active' => (int) ($existing['active'] ?? 0),
            'created_at' => (string) ($existing['created_at'] ?? ''),
        ];

        return $this->ok($this->canonical_staff($id, $row), count($diff['to_add']));
    }

    /**
     * @param mixed $raw
     * @return array<int>
     */
    private function normalize_service_ids($raw): array {
        if (!is_array($raw)) {
            $raw = $raw === null || $raw === '' ? [] : [$raw];
        }

        $ids = [];
        $seen = [];

        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param array<int> $current_ids
     * @param array<int> $desired_ids
     * @return array{to_add:array<int>,to_remove:array<int>}
     */
    private function diff_service_ids(array $current_ids, array $desired_ids): array {
        $current_set = array_fill_keys($current_ids, true);
        $desired_set = array_fill_keys($desired_ids, true);
        $to_add = [];
        $to_remove = [];

        foreach ($desired_ids as $service_id) {
            if (!isset($current_set[$service_id])) {
                $to_add[] = $service_id;
            }
        }

        foreach ($current_ids as $service_id) {
            if (!isset($desired_set[$service_id])) {
                $to_remove[] = $service_id;
            }
        }

        return [
            'to_add' => $to_add,
            'to_remove' => $to_remove,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int,name:string,active:int,created_at:string,services:array<int,array{id:int,name:string}>}
     */
    private function canonical_staff(int $id, array $row): array {
        $services = ($this->get_services)($id);
        if (!is_array($services)) {
            $services = [];
        }

        $normalized = [];
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }

            $service_id = (int) ($service['id'] ?? 0);
            if ($service_id <= 0) {
                continue;
            }

            $normalized[] = [
                'id' => $service_id,
                'name' => (string) ($service['name'] ?? ''),
            ];
        }

        return [
            'id' => (int) ($row['id'] ?? $id),
            'name' => (string) ($row['name'] ?? ''),
            'active' => (int) ($row['active'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'services' => $normalized,
        ];
    }

    private function name_length(string $name): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($name);
        }

        return strlen($name);
    }

    /**
     * @param array<string,mixed> $staff
     * @return array{success:true,data:array{staff:array<string,mixed>,added_count:int}}
     */
    private function ok(array $staff, int $added_count): array {
        return [
            'success' => true,
            'data' => [
                'staff' => $staff,
                'added_count' => $added_count,
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
