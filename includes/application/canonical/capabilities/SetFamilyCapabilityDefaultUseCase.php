<?php
/**
 * Set Family Capability Default — modificación explícita de defaults (puede actualizar).
 *
 * Habilitar (enabled=true) exige is_ready. Desactivar siempre permitido para capacidad conocida.
 * No modifica asignaciones de listas existentes.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class SetFamilyCapabilityDefaultUseCase {

    /** @var CanonicalCapabilityConfigRepository */
    private $repository;

    /** @var AA_Canonical_Registry */
    private $family_registry;

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    public function __construct(
        CanonicalCapabilityConfigRepository $repository,
        AA_Canonical_Registry $family_registry,
        AA_Canonical_Capability_Registry $capability_registry
    ) {
        $this->repository = $repository;
        $this->family_registry = $family_registry;
        $this->capability_registry = $capability_registry;
    }

    /**
     * @return array{id:int,family_id:int,capability_key:string,is_default:bool,created_at:string,updated_at:string}
     * @throws CanonicalCapabilityUnknown
     * @throws CanonicalCapabilityNotReady
     * @throws CanonicalFamilyUnknown
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    public function execute(SetFamilyCapabilityDefaultCommand $command): array {
        $this->repository->assert_schema_ready();

        try {
            $this->family_registry->family($command->family_key());
        } catch (\OutOfBoundsException $e) {
            throw new CanonicalFamilyUnknown($command->family_key());
        }

        try {
            $capability = $this->capability_registry->get($command->capability_key());
        } catch (\OutOfBoundsException $e) {
            throw new CanonicalCapabilityUnknown($command->capability_key());
        }

        if ($command->enabled() && !$capability->is_ready()) {
            throw new CanonicalCapabilityNotReady($command->capability_key());
        }

        $family_id = $this->repository->resolve_family_id($command->family_key());
        if ($family_id === null) {
            throw new CanonicalFamilyNotProvisioned($command->family_key());
        }

        return $this->repository->upsert_family_capability(
            $family_id,
            $command->capability_key(),
            $command->enabled()
        );
    }
}
