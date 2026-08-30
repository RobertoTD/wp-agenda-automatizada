<?php
/**
 * List Finance Containers Use Case — Listado paginado fijo de 15 contenedores en la familia Finanzas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Finance
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('FinanceUseCaseSupport')) {
    require_once __DIR__ . '/FinanceUseCaseSupport.php';
}
if (!class_exists('FinanceContainerRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/FinanceContainerRepository.php';
}

final class ListFinanceContainersUseCase {

    /** @var AA_Canonical_Registry */
    private $registry;

    public function __construct(AA_Canonical_Registry $registry) {
        $this->registry = $registry;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:true,data:array{items:list<array{id:int,family_key:string,variant_key:string,title:string,details:?string,created_at:string}>,page:int,per_page:int,total:int,total_pages:int,has_previous:bool,has_next:bool}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $var_res = FinanceUseCaseSupport::resolve_variant($this->registry, $input);
        if (!$var_res['ok']) {
            return FinanceUseCaseSupport::fail($var_res['error']['code'], $var_res['error']['message']);
        }
        $variant_key = $var_res['variant_key'];

        try {
            $total = FinanceContainerRepository::count_by_variant($variant_key);
            if ($total <= 0) {
                return FinanceUseCaseSupport::ok([
                    'items' => [],
                    'page' => 1,
                    'per_page' => FinanceContainerRepository::PAGE_SIZE,
                    'total' => 0,
                    'total_pages' => 0,
                    'has_previous' => false,
                    'has_next' => false,
                ]);
            }

            $total_pages = (int) ceil($total / FinanceContainerRepository::PAGE_SIZE);
            $page = FinanceUseCaseSupport::normalize_page($input['page'] ?? 1);
            if ($page > $total_pages) {
                $page = $total_pages;
            }

            $rows = FinanceContainerRepository::list_by_variant($variant_key, $page);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudieron listar los contenedores financieros.');
        }

        if (empty($rows)) {
            return FinanceUseCaseSupport::ok([
                'items' => [],
                'page' => $page,
                'per_page' => FinanceContainerRepository::PAGE_SIZE,
                'total' => $total,
                'total_pages' => $total_pages,
                'has_previous' => $page > 1,
                'has_next' => $page < $total_pages,
            ]);
        }

        $container_ids = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id']) || !is_int($row['id']) || $row['id'] < 1) {
                return FinanceUseCaseSupport::fail('persistence_failed', 'Estructura de contenedor inválida.');
            }
            $container_ids[] = $row['id'];
        }

        if (!class_exists('FinanceRecordRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/FinanceRecordRepository.php';
        }

        try {
            $amounts_map = FinanceRecordRepository::sum_amounts_by_container_ids($container_ids);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudieron calcular los totales de los contenedores.');
        }

        $items = [];
        foreach ($rows as $row) {
            $cid = $row['id'];
            if (!array_key_exists($cid, $amounts_map)) {
                return FinanceUseCaseSupport::fail('persistence_failed', 'Total de contenedor no encontrado en el resultado agregado.');
            }

            $items[] = array_merge($row, [
                'family_key'   => 'finance',
                'amount_total' => $amounts_map[$cid],
            ]);
        }

        return FinanceUseCaseSupport::ok([
            'items' => $items,
            'page' => $page,
            'per_page' => FinanceContainerRepository::PAGE_SIZE,
            'total' => $total,
            'total_pages' => $total_pages,
            'has_previous' => $page > 1,
            'has_next' => $page < $total_pages,
        ]);
    }
}
