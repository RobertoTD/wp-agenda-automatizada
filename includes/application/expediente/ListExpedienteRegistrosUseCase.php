<?php
/**
 * List Expediente Registros Use Case — lectura paginada por expediente_id.
 *
 * Validación estricta de id decimal positivo (sin absint). No aplica gate
 * ni valida existencia del padre; solo lista hijos por scope.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedienteRegistrosRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedienteRegistrosRepository.php';
}

final class ListExpedienteRegistrosUseCase {

    public const PER_PAGE = 15;

    /**
     * @param array{expediente_id?:mixed,page?:mixed} $input
     * @return array{success:true,data:array<string,mixed>}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $expediente_id = $this->normalize_id($input['expediente_id'] ?? null);
        if ($expediente_id === null) {
            return $this->fail('invalid_id', 'Expediente no válido.');
        }

        $page = $this->normalize_page($input['page'] ?? 1);
        $total = ExpedienteRegistrosRepository::count_by_expediente_id($expediente_id);
        if ($total < 0) {
            $total = 0;
        }

        if ($total === 0) {
            return $this->ok([
                'records' => [],
                'page' => 1,
                'per_page' => self::PER_PAGE,
                'total' => 0,
                'total_pages' => 0,
                'has_previous' => false,
                'has_next' => false,
            ]);
        }

        $total_pages = (int) ceil($total / self::PER_PAGE);
        if ($page > $total_pages) {
            $page = $total_pages;
        }

        $offset = ($page - 1) * self::PER_PAGE;
        $records = ExpedienteRegistrosRepository::list_by_expediente_id($expediente_id, self::PER_PAGE, $offset);

        return $this->ok([
            'records' => $records,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'total' => $total,
            'total_pages' => $total_pages,
            'has_previous' => $page > 1,
            'has_next' => $page < $total_pages,
        ]);
    }

    /**
     * @param mixed $value
     */
    private function normalize_id($value): ?int {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        if (!preg_match('/^[1-9][0-9]{0,18}$/', $value)) {
            return null;
        }

        $id = (int) $value;
        if ($id < 1 || (string) $id !== $value) {
            return null;
        }

        return $id;
    }

    /**
     * @param mixed $value
     */
    private function normalize_page($value): int {
        $page = (int) $value;

        return $page < 1 ? 1 : $page;
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
