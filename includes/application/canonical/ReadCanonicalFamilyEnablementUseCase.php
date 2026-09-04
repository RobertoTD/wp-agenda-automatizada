<?php
/**
 * Read Canonical Family Enablement Use Case — snapshot de familias declaradas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class ReadCanonicalFamilyEnablementUseCase {

    /** @var CanonicalFamilyEnablementPort */
    private $port;

    public function __construct(CanonicalFamilyEnablementPort $port) {
        $this->port = $port;
    }

    /**
     * @throws CanonicalFamilyEnablementSchemaNotReady
     * @throws CanonicalFamilyEnablementPersistenceFailed
     */
    public function execute(AA_Canonical_Registry $registry): CanonicalFamilyEnablementSnapshot {
        $keys = [];
        foreach ($registry->families() as $family) {
            $keys[] = $family->key();
        }

        return $this->port->read_for_declared_families($keys);
    }
}
