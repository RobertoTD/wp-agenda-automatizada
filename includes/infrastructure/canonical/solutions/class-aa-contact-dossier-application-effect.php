<?php
/** Efecto transaccional de application de contact_dossier sobre una lista. */
defined('ABSPATH') or die('No direct access');

final class AA_Contact_Dossier_Application_Effect implements CanonicalContainerMutationEffect {
    /** @var CanonicalContactDossierApplicationRepository */ private $applications;
    /** @var bool */ private $active;
    public function __construct(CanonicalContactDossierApplicationRepository $applications, bool $active) {
        $this->applications = $applications;
        $this->active = $active;
    }
    public function apply(CanonicalContainerMutationContext $context): void {
        $this->applications->upsert($context->container_id(), $this->active, $context->utc_now());
    }
}
