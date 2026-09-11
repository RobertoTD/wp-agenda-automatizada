<?php
/**
 * Efecto de capacidad sobre un registro, aplicado dentro de la TX del repositorio.
 *
 * Sin \wpdb ni excepciones SQL. Fallos de persistencia → CanonicalMutationPersistenceFailed.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalRecordCapabilityEffect {

    /**
     * @throws CanonicalMutationPersistenceFailed
     */
    public function apply(CanonicalRecordMutationContext $context): void;
}
