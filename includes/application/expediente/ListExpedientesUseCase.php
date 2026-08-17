<?php
/**
 * List Expedientes Use Case — listado paginado de expedientes padre.
 *
 * Página interna fija de 15. Búsqueda solo por title. Query vacía o de
 * espacios = listado completo. Sin techo acumulado de 100. No acepta
 * per_page desde el consumidor.
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('ExpedientesRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/ExpedientesRepository.php';
}

final class ListExpedientesUseCase {

    public const PER_PAGE = 15;

    /**
     * @param array{query?:mixed,page?:mixed} $input
     * @return array{success:true,data:array<string,mixed>}
     */
    public function execute(array $input): array {
        $query = $this->normalize_query($input['query'] ?? '');
        $page = $this->normalize_page($input['page'] ?? 1);

        $total = ExpedientesRepository::count_matching($query);
        if ($total < 0) {
            $total = 0;
        }

        if ($total === 0) {
            return $this->ok([
                'expedientes' => [],
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
        $expedientes = ExpedientesRepository::list_page($query, self::PER_PAGE, $offset);

        return $this->ok([
            'expedientes' => $expedientes,
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
    private function normalize_query($value): string {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * @param mixed $value
     */
    private function normalize_page($value): int {
        $page = (int) $value;

        return $page < 1 ? 1 : $page;
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
