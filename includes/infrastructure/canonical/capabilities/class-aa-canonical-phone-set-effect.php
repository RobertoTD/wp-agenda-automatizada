<?php
/**
 * Efecto phone: upsert valor E.164 en el registro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Phone_Set_Effect implements CanonicalRecordCapabilityEffect {

    /** @var CanonicalRecordPhoneRepository */
    private $repository;

    /** @var string */
    private $phone;

    public function __construct(CanonicalRecordPhoneRepository $repository, string $phone) {
        $this->repository = $repository;
        $this->phone = $phone;
    }

    public function apply(CanonicalRecordMutationContext $context): void {
        try {
            $this->repository->upsert($context->record_id(), $this->phone, $context->utc_now());
        } catch (CanonicalCapabilityPersistenceFailed $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        }
    }
}
