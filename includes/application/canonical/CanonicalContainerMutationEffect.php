<?php
/**
 * Efecto neutral aplicado dentro de una mutación transaccional de contenedor.
 *
 * Puede pertenecer al núcleo de capabilities, a una Solution u otra extensión tipada.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalContainerMutationEffect {
    /** @throws CanonicalMutationPersistenceFailed */
    public function apply(CanonicalContainerMutationContext $context): void;
}
