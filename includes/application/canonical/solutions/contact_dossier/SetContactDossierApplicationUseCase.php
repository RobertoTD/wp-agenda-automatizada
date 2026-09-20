<?php
/**
 * Activa/desactiva contact_dossier. Activar exige disponibilidad; desactivar
 * sigue permitido si un requisito deja de estar disponible.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Solutions\ContactDossier
 */

defined('ABSPATH') or die('No direct access');

final class SetContactDossierApplicationUseCase {

    /** @var ReadContactDossierApplicationUseCase */ private $reader;
    /** @var CanonicalContactDossierApplicationRepository */ private $applications;
    /** @var CanonicalContactDossierApplicationPolicy */ private $policy;

    public function __construct(
        ReadContactDossierApplicationUseCase $reader,
        CanonicalContactDossierApplicationRepository $applications,
        ?CanonicalContactDossierApplicationPolicy $policy = null
    ) {
        $this->reader = $reader;
        $this->applications = $applications;
        $this->policy = $policy ?: new CanonicalContactDossierApplicationPolicy();
    }

    public function execute(
        SetContactDossierApplicationCommand $command
    ): CanonicalContactDossierApplicationSnapshot {
        $before = $this->reader->execute($command->contact_container_id());
        $this->policy->assert_transition_allowed($before, $command->active());

        $this->applications->upsert(
            $command->contact_container_id(),
            $command->active(),
            gmdate('Y-m-d H:i:s')
        );

        return $this->reader->execute($command->contact_container_id());
    }
}
