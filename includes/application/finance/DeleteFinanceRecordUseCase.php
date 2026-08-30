<?php
/**
 * Delete Finance Record Use Case — Eliminación contextual de un registro de Finanzas.
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

final class DeleteFinanceRecordUseCase {

    /** @var AA_Canonical_Registry */
    private $registry;

    public function __construct(AA_Canonical_Registry $registry) {
        $this->registry = $registry;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:true,data:array{deleted:true,id:int,container_id:int}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $context_res = FinanceUseCaseSupport::verify_container_context($this->registry, $input);
        if (!$context_res['ok']) {
            return FinanceUseCaseSupport::fail($context_res['error']['code'], $context_res['error']['message']);
        }

        $container_id = $context_res['container_id'];

        $record_id = FinanceUseCaseSupport::normalize_id(isset($input['record_id']) ? $input['record_id'] : null);
        if ($record_id === null) {
            return FinanceUseCaseSupport::fail('invalid_record_id', 'Identificador de registro no válido.');
        }

        try {
            $deleted = FinanceRecordRepository::delete($record_id, $container_id);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar el registro financiero.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar el registro financiero.');
        }

        if (!$deleted) {
            return FinanceUseCaseSupport::fail('record_not_found', 'Registro financiero no encontrado.');
        }

        return FinanceUseCaseSupport::ok([
            'deleted' => true,
            'id' => $record_id,
            'container_id' => $container_id,
        ]);
    }
}
