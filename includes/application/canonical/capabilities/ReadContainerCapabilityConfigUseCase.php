<?php
/**
 * Read Container Capability Config — configuración efectiva de una lista.
 *
 * Consulta permitida para capacidades conocidas aunque no estén ready.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class ReadContainerCapabilityConfigUseCase {

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
     * @throws CanonicalFamilyUnknown
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalContainerNotFound
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    public function execute(
        string $family_key,
        int $container_id
    ): CanonicalContainerCapabilityConfigSnapshot {
        $this->repository->assert_schema_ready();

        $family_key = AA_Canonical_Key::assert_valid($family_key, 'family_key');
        if ($container_id < 1) {
            throw new \InvalidArgumentException('[invalid_container_id] container_id must be positive.');
        }

        try {
            $this->family_registry->family($family_key);
        } catch (\OutOfBoundsException $e) {
            throw new CanonicalFamilyUnknown($family_key);
        }

        $family_id = $this->repository->resolve_family_id($family_key);
        if ($family_id === null) {
            throw new CanonicalFamilyNotProvisioned($family_key);
        }

        if (!$this->repository->container_belongs_to_family($family_id, $container_id)) {
            throw new CanonicalContainerNotFound($family_key, $container_id);
        }

        $rows = $this->repository->list_container_capabilities($container_id);
        $by_key = [];
        foreach ($rows as $row) {
            $by_key[$row['capability_key']] = $row;
        }

        $capabilities = [];
        foreach ($this->capability_registry->all() as $definition) {
            $key = $definition->key();
            $assigned = isset($by_key[$key]);
            $capabilities[$key] = [
                'assigned' => $assigned,
                'active' => $assigned && !empty($by_key[$key]['is_active']),
                'scope' => $definition->scope(),
            ];
        }

        return new CanonicalContainerCapabilityConfigSnapshot(
            $family_key,
            $container_id,
            $capabilities
        );
    }
}
