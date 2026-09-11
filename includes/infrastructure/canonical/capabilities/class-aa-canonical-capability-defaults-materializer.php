<?php
/**
 * Materializa defaults de familia ready+enabled en la configuración de una lista nueva.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Capability_Defaults_Materializer {

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    /** @var CanonicalCapabilityConfigRepository */
    private $config_repository;

    public function __construct(
        AA_Canonical_Capability_Registry $capability_registry,
        CanonicalCapabilityConfigRepository $config_repository
    ) {
        $this->capability_registry = $capability_registry;
        $this->config_repository = $config_repository;
    }

    public function build_effect(): CanonicalContainerCapabilityEffect {
        return new AA_Canonical_Materialize_Family_Defaults_Effect(
            $this->capability_registry,
            $this->config_repository
        );
    }
}
