<?php
/**
 * Canonical Read Adapter Resolver — Puerto de resolución de adaptadores de lectura.
 *
 * Application solo necesita require(); el registro queda fuera de este puerto.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalReadAdapterResolver {

    /**
     * @throws CanonicalReadBindingNotFound Si no hay adaptador para la identidad.
     */
    public function require(CanonicalReadIdentity $identity): CanonicalReadAdapter;
}
