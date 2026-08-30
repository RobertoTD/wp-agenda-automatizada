<?php
/**
 * List Finance Records Use Case — Listado paginado de registros con suma monetaria del contenedor.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Finance
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('FinanceUseCaseSupport')) {
    require_once __DIR__ . '/FinanceUseCaseSupport.php';
}
if (!class_exists('FinanceRecordRepository')) {
    require_once dirname(__DIR__, 2) . '/repositories/FinanceRecordRepository.php';
}

final class ListFinanceRecordsUseCase {

    /** @var AA_Canonical_Registry */
    private $registry;

    public function __construct(AA_Canonical_Registry $registry) {
        $this->registry = $registry;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{
     *     success: true,
     *     data: array{
     *         container: array{id:int,family_key:string,variant_key:string,title:string,details:?string,created_at:string},
     *         items: list<array{id:int,family_key:string,variant_key:string,container_id:int,title:string,details:?string,amount:?string,created_at:string}>,
     *         page: int,
     *         per_page: int,
     *         total: int,
     *         total_pages: int,
     *         has_previous: bool,
     *         has_next: bool,
     *         amount_total: ?string
     *     }
     * }|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $context_res = FinanceUseCaseSupport::verify_container_context($this->registry, $input);
        if (!$context_res['ok']) {
            return FinanceUseCaseSupport::fail($context_res['error']['code'], $context_res['error']['message']);
        }

        $variant_key = $context_res['variant_key'];
        $container_id = $context_res['container_id'];
        $container_dto = $context_res['container'];

        try {
            $total = FinanceRecordRepository::count_by_container($container_id);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudieron consultar los registros financieros.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudieron consultar los registros financieros.');
        }

        if ($total === 0) {
            return FinanceUseCaseSupport::ok([
                'container'    => $container_dto,
                'items'        => [],
                'page'         => 1,
                'per_page'     => FinanceRecordRepository::PAGE_SIZE,
                'total'        => 0,
                'total_pages'  => 0,
                'has_previous' => false,
                'has_next'     => false,
                'amount_total' => null,
            ]);
        }

        try {
            $amount_total = FinanceRecordRepository::sum_amounts_by_container($container_id);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudieron consultar los registros financieros.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudieron consultar los registros financieros.');
        }

        $total_pages = (int) ceil($total / FinanceRecordRepository::PAGE_SIZE);
        $page = FinanceUseCaseSupport::normalize_page(isset($input['page']) ? $input['page'] : 1);
        if ($page > $total_pages) {
            $page = $total_pages;
        }

        try {
            $rows = FinanceRecordRepository::list_by_container($container_id, $page);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudieron consultar los registros financieros.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudieron consultar los registros financieros.');
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = array_merge([
                'family_key'  => 'finance',
                'variant_key' => $variant_key,
            ], $row);
        }

        return FinanceUseCaseSupport::ok([
            'container'    => $container_dto,
            'items'        => $items,
            'page'         => $page,
            'per_page'     => FinanceRecordRepository::PAGE_SIZE,
            'total'        => $total,
            'total_pages'  => $total_pages,
            'has_previous' => $page > 1,
            'has_next'     => $page < $total_pages,
            'amount_total' => $amount_total,
        ]);
    }
}
