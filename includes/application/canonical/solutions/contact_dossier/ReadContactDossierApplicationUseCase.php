<?php
/**
 * Lee application y disponibilidad de contact_dossier sin consultar capabilities.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Solutions\ContactDossier
 */

defined('ABSPATH') or die('No direct access');

final class ReadContactDossierApplicationUseCase {

    /** @var CanonicalRelationalRepository */ private $relational;
    /** @var CanonicalContactDossierApplicationRepository */ private $applications;
    /** @var AA_Canonical_Solution_Registry */ private $solutions;
    /** @var AA_Canonical_Registry */ private $families;
    /** @var CanonicalFamilyEnablementPort */ private $enablement_port;
    /** @var CanonicalContactDossierApplicationPolicy */ private $policy;

    public function __construct(
        CanonicalRelationalRepository $relational,
        CanonicalContactDossierApplicationRepository $applications,
        AA_Canonical_Solution_Registry $solutions,
        AA_Canonical_Registry $families,
        CanonicalFamilyEnablementPort $enablement_port,
        ?CanonicalContactDossierApplicationPolicy $policy = null
    ) {
        $this->relational = $relational;
        $this->applications = $applications;
        $this->solutions = $solutions;
        $this->families = $families;
        $this->enablement_port = $enablement_port;
        $this->policy = $policy ?: new CanonicalContactDossierApplicationPolicy();
    }

    public function execute(int $container_id): CanonicalContactDossierApplicationSnapshot {
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_container_id] Container id must be positive.');
        }

        $definition = $this->solutions->get(
            AA_Canonical_Solution_Registry_Bootstrap::CONTACT_DOSSIER
        );
        $container = $this->relational->find_container_by_id($container_id);
        if ($container === null) {
            throw new CanonicalContainerNotFound('contact', $container_id);
        }
        $family_key = (string) ($container['family_key'] ?? '');
        $persisted = $this->applications->find($container_id);
        $enablement = (new ReadCanonicalFamilyEnablementUseCase($this->enablement_port))
            ->execute($this->families);

        return $this->policy->evaluate(
            $definition,
            $family_key,
            $container_id,
            $persisted,
            $enablement
        );
    }
}
