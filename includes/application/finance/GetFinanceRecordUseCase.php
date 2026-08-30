<?php
/**
 * Get Finance Record Use Case — Obtención contextual de un registro de Finanzas.
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

final class GetFinanceRecordUseCase {

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

        $record_id = FinanceUseCaseSupport::normalize_id(isset($input['record_id']) ? $input['record_id'] : null);
        if ($record_id === null) {
            return FinanceUseCaseSupport::fail('invalid_record_id', 'Identificador de registro no válido.');
        }

        try {
            $row = FinanceRecordRepository::find_by_id_and_container($record_id, $container_id);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo consultar el registro financiero.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo consultar el registro financiero.');
        }

        if ($row === null) {
            return FinanceUseCaseSupport::fail('record_not_found', 'Registro financiero no encontrado.');
        }

        return FinanceUseCaseSupport::ok([
            'record' => array_merge([
                'family_key' => 'finance',
                'variant_key' => $variant_key,
            ], $row),
        ]);
    }
}
