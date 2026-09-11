<?php
/**
 * Efecto amount: eliminar fila de valor del registro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Amount_Clear_Effect implements CanonicalRecordCapabilityEffect {

    /** @var CanonicalRecordAmountRepository */
    private $repository;

    public function __construct(CanonicalRecordAmountRepository $repository) {
        $this->repository = $repository;
    }

    public function apply(CanonicalRecordMutationContext $context): void {
        try {
            $this->repository->delete($context->record_id());
        } catch (CanonicalCapabilityPersistenceFailed $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        }
    }
}
