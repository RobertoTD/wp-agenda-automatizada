<?php
/**
 * Efecto de capacidad sobre un contenedor, aplicado dentro de la TX del repositorio.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalContainerMutationEffect')) {
    require_once dirname(__DIR__) . '/CanonicalContainerMutationEffect.php';
}

interface CanonicalContainerCapabilityEffect extends CanonicalContainerMutationEffect {
}
