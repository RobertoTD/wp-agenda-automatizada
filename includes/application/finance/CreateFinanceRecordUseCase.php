<?php
/**
 * Create Finance Record Use Case — Creación de registro en un contenedor de Finanzas.
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

final class CreateFinanceRecordUseCase {

    /** @var AA_Canonical_Registry */
    private $registry;

    public function __construct(AA_Canonical_Registry $registry) {
        $this->registry = $registry;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:true,data:array{record:array{id:int,family_key:string,variant_key:string,container_id:int,title:string,details:?string,amount:?string,created_at:string}}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $context_res = FinanceUseCaseSupport::verify_container_context($this->registry, $input);
        if (!$context_res['ok']) {
            return FinanceUseCaseSupport::fail($context_res['error']['code'], $context_res['error']['message']);
        }

        $variant_key = $context_res['variant_key'];
        $container_id = $context_res['container_id'];

        $title_res = FinanceUseCaseSupport::normalize_title(isset($input['title']) ? $input['title'] : null);
        if (!$title_res['ok']) {
            return FinanceUseCaseSupport::fail($title_res['error']['code'], $title_res['error']['message']);
        }
        $title = $title_res['value'];

        $details_res = FinanceUseCaseSupport::normalize_details(isset($input['details']) ? $input['details'] : null);
        if (!$details_res['ok']) {
            return FinanceUseCaseSupport::fail($details_res['error']['code'], $details_res['error']['message']);
        }
        $details = $details_res['value'];

        $amount_res = FinanceUseCaseSupport::normalize_amount(isset($input['amount']) ? $input['amount'] : null);
        if (!$amount_res['ok']) {
            return FinanceUseCaseSupport::fail($amount_res['error']['code'], $amount_res['error']['message']);
        }
        $amount = $amount_res['value'];

        try {
            $row = FinanceRecordRepository::create($container_id, $title, $details, $amount);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo crear el registro financiero.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo crear el registro financiero.');
        }

        return FinanceUseCaseSupport::ok([
            'record' => array_merge([
                'family_key' => 'finance',
                'variant_key' => $variant_key,
            ], $row),
        ]);
    }
}
