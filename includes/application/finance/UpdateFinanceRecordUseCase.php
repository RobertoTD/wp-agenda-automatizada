<?php
/**
 * Update Finance Record Use Case — Edición completa de registro en la familia Finanzas.
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

final class UpdateFinanceRecordUseCase {

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

        if (!array_key_exists('details', $input)) {
            return FinanceUseCaseSupport::fail('missing_details', 'Los detalles son obligatorios en la actualización.');
        }

        if (!array_key_exists('amount', $input)) {
            return FinanceUseCaseSupport::fail('missing_amount', 'El importe es obligatorio en la actualización.');
        }

        $title_res = FinanceUseCaseSupport::normalize_title($input['title'] ?? null);
        if (!$title_res['ok']) {
            return FinanceUseCaseSupport::fail($title_res['error']['code'], $title_res['error']['message']);
        }
        $title = $title_res['value'];

        $details_res = FinanceUseCaseSupport::normalize_details($input['details']);
        if (!$details_res['ok']) {
            return FinanceUseCaseSupport::fail($details_res['error']['code'], $details_res['error']['message']);
        }
        $details = $details_res['value'];

        $amount_res = FinanceUseCaseSupport::normalize_amount($input['amount']);
        if (!$amount_res['ok']) {
            return FinanceUseCaseSupport::fail($amount_res['error']['code'], $amount_res['error']['message']);
        }
        $amount = $amount_res['value'];

        try {
            $existing = FinanceRecordRepository::find_by_id_and_container($record_id, $container_id);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo verificar el registro financiero.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo verificar el registro financiero.');
        }

        if ($existing === null) {
            return FinanceUseCaseSupport::fail('record_not_found', 'Registro financiero no encontrado.');
        }

        try {
            $row = FinanceRecordRepository::update($record_id, $container_id, $title, $details, $amount);
        } catch (\RuntimeException $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo actualizar el registro financiero.');
        } catch (\Throwable $e) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo actualizar el registro financiero.');
        }

        if ($row === null) {
            return FinanceUseCaseSupport::fail('record_not_found', 'Registro financiero no encontrado.');
        }

        if (
            !isset($row['id'], $row['container_id'], $row['title'], $row['created_at'])
            || (int) $row['id'] !== $record_id
            || (int) $row['container_id'] !== $container_id
            || !is_string($row['title'])
            || ($row['details'] !== null && !is_string($row['details']))
            || ($row['amount'] !== null && !is_string($row['amount']))
            || (string) ($row['created_at'] ?? '') === ''
        ) {
            return FinanceUseCaseSupport::fail('persistence_failed', 'No se pudo actualizar el registro financiero.');
        }

        return FinanceUseCaseSupport::ok([
            'record' => array_merge([
                'family_key' => 'finance',
                'variant_key' => $variant_key,
            ], $row),
        ]);
    }
}
