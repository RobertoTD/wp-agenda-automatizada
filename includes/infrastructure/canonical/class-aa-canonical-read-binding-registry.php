<?php
/**
 * Canonical Read Binding Registry — Implementación del resolver de adaptadores.
 *
 * Infrastructure. Application solo ve CanonicalReadAdapterResolver.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalReadAdapterResolver')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadAdapterResolver.php';
}
if (!interface_exists('CanonicalReadAdapter')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadAdapter.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadIdentity.php';
}
if (!class_exists('CanonicalReadBindingNotFound')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadBindingNotFound.php';
}

final class AA_Canonical_Read_Binding_Registry implements CanonicalReadAdapterResolver {

    /** @var array<string, CanonicalReadAdapter> */
    private $adapters = [];

    public function register(CanonicalReadIdentity $identity, CanonicalReadAdapter $adapter): void {
        $key = $identity->qualified_key();
        if (isset($this->adapters[$key])) {
            throw new \LogicException(
                '[duplicate_read_binding] Read adapter already registered for identity: ' . $key
            );
        }

        $this->adapters[$key] = $adapter;
    }

    public function require(CanonicalReadIdentity $identity): CanonicalReadAdapter {
        $key = $identity->qualified_key();
        if (!isset($this->adapters[$key])) {
            throw new CanonicalReadBindingNotFound($key);
        }

        return $this->adapters[$key];
    }
}
