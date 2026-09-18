<?php
/**
 * Efecto phone: eliminar fila de valor del registro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Phone_Clear_Effect implements CanonicalRecordCapabilityEffect {

    /** @var CanonicalRecordPhoneRepository */
    private $repository;

    public function __construct(CanonicalRecordPhoneRepository $repository) {
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
