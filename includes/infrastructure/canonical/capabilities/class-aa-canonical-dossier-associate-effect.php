<?php
/**
 * Efecto: asociar el contenedor Archivo recién creado al contacto (misma TX).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Dossier_Associate_Effect implements CanonicalContainerCapabilityEffect {

    /** @var CanonicalContactDossierRepository */
    private $dossier_repository;

    /** @var int */
    private $contact_record_id;

    public function __construct(
        CanonicalContactDossierRepository $dossier_repository,
        int $contact_record_id
    ) {
        if ($contact_record_id < 1) {
            throw new \InvalidArgumentException(
                '[invalid_dossier_association] contact_record_id must be positive.'
            );
        }
        $this->dossier_repository = $dossier_repository;
        $this->contact_record_id = $contact_record_id;
    }

    public function apply(CanonicalContainerMutationContext $context): void {
        try {
            $this->dossier_repository->insert_association(
                $this->contact_record_id,
                $context->container_id(),
                $context->utc_now()
            );
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        } catch (CanonicalCapabilityPersistenceFailed $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        }
    }
}
