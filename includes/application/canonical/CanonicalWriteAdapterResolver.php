<?php
/**
 * Canonical Write Adapter Resolver — Puerto de resolución de adaptadores de escritura.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('CanonicalReadIdentity')) {
    require_once __DIR__ . '/CanonicalReadIdentity.php';
}
if (!interface_exists('CanonicalWriteAdapter')) {
    require_once __DIR__ . '/CanonicalWriteAdapter.php';
}

interface CanonicalWriteAdapterResolver {

    /**
     * @throws CanonicalWriteBindingNotFound
     */
    public function require(CanonicalReadIdentity $identity): CanonicalWriteAdapter;
}
