<?php
/**
 * Efecto email: upsert valor en el registro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Email_Set_Effect implements CanonicalRecordCapabilityEffect {

    /** @var CanonicalRecordEmailRepository */
    private $repository;

    /** @var string */
    private $email;

    public function __construct(CanonicalRecordEmailRepository $repository, string $email) {
        $this->repository = $repository;
        $this->email = $email;
    }

    public function apply(CanonicalRecordMutationContext $context): void {
        try {
            $this->repository->upsert($context->record_id(), $this->email, $context->utc_now());
        } catch (CanonicalCapabilityPersistenceFailed $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        }
    }
}
