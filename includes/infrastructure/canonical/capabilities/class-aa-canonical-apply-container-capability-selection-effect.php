<?php
/**
 * Efecto: aplicar selección explícita scope→active sobre container_capabilities.
 *
 * Solo toca claves del mapa (scope). Validación ocurre antes de la TX.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Apply_Container_Capability_Selection_Effect implements CanonicalContainerCapabilityEffect {

    /** @var CanonicalCapabilityConfigRepository */
    private $config_repository;

    /** @var array<string, bool> capability_key => is_active */
    private $activations;

    /**
     * @param array<string, bool> $activations
     */
    public function __construct(
        CanonicalCapabilityConfigRepository $config_repository,
        array $activations
    ) {
        $this->config_repository = $config_repository;
        $this->activations = $activations;
    }

    public function apply(CanonicalContainerMutationContext $context): void {
        try {
            $this->config_repository->assert_schema_ready();
            foreach ($this->activations as $key => $is_active) {
                $this->config_repository->upsert_container_capability(
                    $context->container_id(),
                    (string) $key,
                    (bool) $is_active
                );
            }
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        } catch (CanonicalCapabilityPersistenceFailed $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        }
    }
}
