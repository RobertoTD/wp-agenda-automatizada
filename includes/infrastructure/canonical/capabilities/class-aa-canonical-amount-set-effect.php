<?php
/**
 * Efecto amount: upsert valor tipado en el registro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Amount_Set_Effect implements CanonicalRecordCapabilityEffect {

    /** @var CanonicalRecordAmountRepository */
    private $repository;

    /** @var string */
    private $amount;

    public function __construct(CanonicalRecordAmountRepository $repository, string $amount) {
        $this->repository = $repository;
        $this->amount = $amount;
    }

    public function apply(CanonicalRecordMutationContext $context): void {
        try {
            $this->repository->upsert($context->record_id(), $this->amount, $context->utc_now());
        } catch (CanonicalCapabilityPersistenceFailed $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        }
    }
}
