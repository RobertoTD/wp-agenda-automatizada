<?php
/**
 * Set Canonical Family Enabled Use Case — mutación individual de is_enabled.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class SetCanonicalFamilyEnabledUseCase {

    /** @var CanonicalFamilyEnablementPort */
    private $port;

    /** @var AA_Canonical_Registry */
    private $registry;

    public function __construct(CanonicalFamilyEnablementPort $port, AA_Canonical_Registry $registry) {
        $this->port = $port;
        $this->registry = $registry;
    }

    /**
     * @throws CanonicalFamilyUnknown
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalFamilyEnablementSchemaNotReady
     * @throws CanonicalFamilyEnablementPersistenceFailed
     */
    public function execute(SetCanonicalFamilyEnabledCommand $command): CanonicalFamilyEnablementResult {
        $family_key = $command->family_key();

        try {
            $this->registry->family($family_key);
        } catch (\OutOfBoundsException $e) {
            throw new CanonicalFamilyUnknown($family_key);
        }

        return $this->port->set_enabled($family_key, $command->enabled());
    }
}
