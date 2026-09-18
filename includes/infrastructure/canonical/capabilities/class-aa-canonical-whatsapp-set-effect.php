<?php
/**
 * Efecto whatsapp: upsert valor E.164 en el registro.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Whatsapp_Set_Effect implements CanonicalRecordCapabilityEffect {

    /** @var CanonicalRecordWhatsappRepository */
    private $repository;

    /** @var string */
    private $whatsapp;

    public function __construct(CanonicalRecordWhatsappRepository $repository, string $whatsapp) {
        $this->repository = $repository;
        $this->whatsapp = $whatsapp;
    }

    public function apply(CanonicalRecordMutationContext $context): void {
        try {
            $this->repository->upsert($context->record_id(), $this->whatsapp, $context->utc_now());
        } catch (CanonicalCapabilityPersistenceFailed $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        }
    }
}
