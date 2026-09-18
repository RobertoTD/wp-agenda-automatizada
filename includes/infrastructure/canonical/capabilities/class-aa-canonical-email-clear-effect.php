<?php
/**
 * Efecto email: eliminar fila de valor del registro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Email_Clear_Effect implements CanonicalRecordCapabilityEffect {

    /** @var CanonicalRecordEmailRepository */
    private $repository;

    public function __construct(CanonicalRecordEmailRepository $repository) {
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
