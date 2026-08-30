<?php
/**
 * Delete Finance Container Use Case — Eliminación contextual de contenedor en la familia Finanzas.
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

final class DeleteFinanceContainerUseCase {

    /** @var AA_Canonical_Registry */
    private $registry;

    public function __construct(AA_Canonical_Registry $registry) {
        $this->registry = $registry;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:true,data:array{deleted:true,id:int}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $id = FinanceUseCaseSupport::normalize_id($input['id'] ?? null);
        if ($id === null) {
            return FinanceUseCaseSupport::fail('invalid_id', 'Identificador de contenedor no válido.');
        }

        $var_res = FinanceUseCaseSupport::resolve_variant($this->registry, $input);
        if (!$var_res['ok']) {
            return FinanceUseCaseSupport::fail($var_res['error']['code'], $var_res['error']['message']);
        }
        $variant_key = $var_res['variant_key'];

        try {
            $row = FinanceContainerRepository::find_by_id($id);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo verificar el contenedor financiero.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo verificar el contenedor financiero.');
        }

        if ($row === null || ($row['variant_key'] ?? '') !== $variant_key) {
            return FinanceUseCaseSupport::fail('not_found', 'Contenedor financiero no encontrado.');
        }

        try {
            $deleted = FinanceContainerRepository::delete($id);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar el contenedor financiero.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo eliminar el contenedor financiero.');
        }

        if (!$deleted) {
            return FinanceUseCaseSupport::fail('not_found', 'Contenedor financiero no encontrado.');
        }

        return FinanceUseCaseSupport::ok([
            'deleted' => true,
            'id' => $id,
        ]);
    }
}
