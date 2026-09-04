<?php
/**
 * Canonical Write Binding Registry — Implementación del resolver de adaptadores de escritura.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!interface_exists('CanonicalWriteAdapterResolver')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalWriteAdapterResolver.php';
}
if (!interface_exists('CanonicalWriteAdapter')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalWriteAdapter.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadIdentity.php';
}
if (!class_exists('CanonicalWriteBindingNotFound')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalWriteBindingNotFound.php';
}

final class AA_Canonical_Write_Binding_Registry implements CanonicalWriteAdapterResolver {

    /** @var array<string, CanonicalWriteAdapter> */
    private $adapters = [];

    public function register(CanonicalReadIdentity $identity, CanonicalWriteAdapter $adapter): void {
        $key = $identity->qualified_key();
        if (isset($this->adapters[$key])) {
            throw new \LogicException(
                '[duplicate_write_binding] Write adapter already registered for identity: ' . $key
            );
        }

        $this->adapters[$key] = $adapter;
    }

    public function require(CanonicalReadIdentity $identity): CanonicalWriteAdapter {
        $key = $identity->qualified_key();
        if (!isset($this->adapters[$key])) {
            throw new CanonicalWriteBindingNotFound($key);
        }

        return $this->adapters[$key];
    }
}
