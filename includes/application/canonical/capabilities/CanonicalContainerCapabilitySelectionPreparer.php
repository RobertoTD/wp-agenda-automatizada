<?php
/**
 * Valida una modificación scope+selection y construye el effect de aplicación.
 *
 * Create: cada clave de scope ∈ repertorio actual, known + ready.
 * Update: cada clave ∈ repertorio actual O ya asignada a la lista; known + ready.
 * selection ⊆ scope. No amplía scope. No toca claves fuera de scope.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContainerCapabilitySelectionPreparer {

    /** @var CanonicalCapabilityConfigRepository */
    private $repository;

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    public function __construct(
        CanonicalCapabilityConfigRepository $repository,
        AA_Canonical_Capability_Registry $capability_registry
    ) {
        $this->repository = $repository;
        $this->capability_registry = $capability_registry;
    }

    /**
     * @throws CanonicalCapabilityWriteRejected
     * @throws CanonicalCapabilityUnknown
     * @throws CanonicalCapabilityNotReady
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    public function build_create_effect(
        string $family_key,
        CanonicalContainerCapabilitySelection $selection
    ): CanonicalContainerCapabilityEffect {
        $family_id = $this->require_family_id($family_key);
        $repertoire = $this->repertoire_key_set($family_id);
        $activations = $this->validate_and_map(
            $selection,
            static function (string $key) use ($repertoire): bool {
                return isset($repertoire[$key]);
            },
            'capability_not_in_repertoire'
        );

        return new AA_Canonical_Apply_Container_Capability_Selection_Effect(
            $this->repository,
            $activations
        );
    }

    /**
     * @throws CanonicalCapabilityWriteRejected
     * @throws CanonicalCapabilityUnknown
     * @throws CanonicalCapabilityNotReady
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalContainerNotFound
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    public function build_update_effect(
        string $family_key,
        int $container_id,
        CanonicalContainerCapabilitySelection $selection
    ): CanonicalContainerCapabilityEffect {
        $family_id = $this->require_family_id($family_key);
        if (!$this->repository->container_belongs_to_family($family_id, $container_id)) {
            throw new CanonicalContainerNotFound($family_key, $container_id);
        }

        $repertoire = $this->repertoire_key_set($family_id);
        $assigned = $this->assigned_key_set($container_id);
        $activations = $this->validate_and_map(
            $selection,
            static function (string $key) use ($repertoire, $assigned): bool {
                return isset($repertoire[$key]) || isset($assigned[$key]);
            },
            'capability_not_editable'
        );

        return new AA_Canonical_Apply_Container_Capability_Selection_Effect(
            $this->repository,
            $activations
        );
    }

    /**
     * @param callable(string):bool $scope_key_allowed
     * @return array<string, bool>
     * @throws CanonicalCapabilityWriteRejected
     * @throws CanonicalCapabilityUnknown
     * @throws CanonicalCapabilityNotReady
     */
    private function validate_and_map(
        CanonicalContainerCapabilitySelection $selection,
        callable $scope_key_allowed,
        string $scope_reject_code
    ): array {
        $scope = $selection->scope();
        $active = $selection->selection();

        $scope_set = [];
        foreach ($scope as $key) {
            if (!is_string($key) || $key === '') {
                throw new CanonicalCapabilityWriteRejected(
                    'invalid_capability_selection',
                    'El alcance de capacidades no es válido.',
                    400
                );
            }
            if (isset($scope_set[$key])) {
                throw new CanonicalCapabilityWriteRejected(
                    'invalid_capability_selection',
                    'El alcance de capacidades contiene claves duplicadas.',
                    400
                );
            }
            $this->assert_known_ready($key);
            if (!$scope_key_allowed($key)) {
                throw new CanonicalCapabilityWriteRejected(
                    $scope_reject_code,
                    'Una capacidad del alcance ya no es válida para esta lista.',
                    409
                );
            }
            $scope_set[$key] = true;
        }

        $active_set = [];
        foreach ($active as $key) {
            if (!is_string($key) || $key === '') {
                throw new CanonicalCapabilityWriteRejected(
                    'invalid_capability_selection',
                    'La selección de capacidades no es válida.',
                    400
                );
            }
            if (isset($active_set[$key])) {
                throw new CanonicalCapabilityWriteRejected(
                    'invalid_capability_selection',
                    'La selección de capacidades contiene claves duplicadas.',
                    400
                );
            }
            if (!isset($scope_set[$key])) {
                throw new CanonicalCapabilityWriteRejected(
                    'invalid_capability_selection',
                    'La selección incluye una capacidad fuera del alcance.',
                    400
                );
            }
            $active_set[$key] = true;
        }

        $activations = [];
        foreach ($scope_set as $key => $_true) {
            $activations[$key] = isset($active_set[$key]);
        }

        return $activations;
    }

    /**
     * @throws CanonicalCapabilityUnknown
     * @throws CanonicalCapabilityNotReady
     */
    private function assert_known_ready(string $key): void {
        try {
            $definition = $this->capability_registry->get($key);
        } catch (\OutOfBoundsException $e) {
            throw new CanonicalCapabilityUnknown($key);
        }
        if (!$definition->is_ready()) {
            throw new CanonicalCapabilityNotReady($key);
        }
    }

    /**
     * @return array<string, true>
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    private function repertoire_key_set(int $family_id): array {
        $set = [];
        foreach ($this->repository->list_family_capabilities($family_id) as $row) {
            $key = (string) ($row['capability_key'] ?? '');
            if ($key !== '') {
                $set[$key] = true;
            }
        }

        return $set;
    }

    /**
     * @return array<string, true>
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    private function assigned_key_set(int $container_id): array {
        $set = [];
        foreach ($this->repository->list_container_capabilities($container_id) as $row) {
            $key = (string) ($row['capability_key'] ?? '');
            if ($key !== '') {
                $set[$key] = true;
            }
        }

        return $set;
    }

    /**
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    private function require_family_id(string $family_key): int {
        $this->repository->assert_schema_ready();
        $family_id = $this->repository->resolve_family_id($family_key);
        if ($family_id === null) {
            throw new CanonicalFamilyNotProvisioned($family_key);
        }

        return $family_id;
    }
}
