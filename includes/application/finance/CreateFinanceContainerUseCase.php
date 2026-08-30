<?php
/**
 * Create Finance Container Use Case — Creación de contenedor en la familia Finanzas.
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

final class CreateFinanceContainerUseCase {

    /** @var AA_Canonical_Registry */
    private $registry;

    public function __construct(AA_Canonical_Registry $registry) {
        $this->registry = $registry;
    }

    /**
     * @param array<string,mixed> $input
     * @return array{success:true,data:array{container:array{id:int,family_key:string,variant_key:string,title:string,details:?string,created_at:string}}}|array{success:false,error:array{code:string,message:string}}
     */
    public function execute(array $input): array {
        $var_res = FinanceUseCaseSupport::resolve_variant($this->registry, $input);
        if (!$var_res['ok']) {
            return FinanceUseCaseSupport::fail($var_res['error']['code'], $var_res['error']['message']);
        }
        $variant_key = $var_res['variant_key'];

        $title_res = FinanceUseCaseSupport::normalize_title($input['title'] ?? null);
        if (!$title_res['ok']) {
            return FinanceUseCaseSupport::fail($title_res['error']['code'], $title_res['error']['message']);
        }
        $title = $title_res['value'];

        $details_res = FinanceUseCaseSupport::normalize_details($input['details'] ?? null);
        if (!$details_res['ok']) {
            return FinanceUseCaseSupport::fail($details_res['error']['code'], $details_res['error']['message']);
        }
        $details = $details_res['value'];

        try {
            $row = FinanceContainerRepository::create($variant_key, $title, $details);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo crear el contenedor financiero.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo crear el contenedor financiero.');
        }

        return FinanceUseCaseSupport::ok([
            'container' => array_merge(['family_key' => 'finance'], $row),
        ]);
    }
}
