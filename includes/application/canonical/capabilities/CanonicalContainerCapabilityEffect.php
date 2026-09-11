<?php
/**
 * Efecto de capacidad sobre un contenedor, aplicado dentro de la TX del repositorio.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalContainerCapabilityEffect {

    /**
     * @throws CanonicalMutationPersistenceFailed
     */
    public function apply(CanonicalContainerMutationContext $context): void;
}
