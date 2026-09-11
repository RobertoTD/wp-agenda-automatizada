<?php
/**
 * Efecto: copiar defaults ready+enabled a container_capabilities.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Materialize_Family_Defaults_Effect implements CanonicalContainerCapabilityEffect {

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    /** @var CanonicalCapabilityConfigRepository */
    private $config_repository;

    public function __construct(
        AA_Canonical_Capability_Registry $capability_registry,
        CanonicalCapabilityConfigRepository $config_repository
    ) {
        $this->capability_registry = $capability_registry;
        $this->config_repository = $config_repository;
    }

    public function apply(CanonicalContainerMutationContext $context): void {
        try {
            $this->config_repository->assert_schema_ready();
            $defaults = $this->config_repository->list_family_defaults($context->family_id());
            foreach ($defaults as $row) {
                if (empty($row['is_enabled'])) {
                    continue;
                }
                $key = (string) $row['capability_key'];
                if (!$this->capability_registry->has($key)) {
                    continue;
                }
                $definition = $this->capability_registry->get($key);
                if (!$definition->is_ready()) {
                    continue;
                }
                $this->config_repository->upsert_container_capability(
                    $context->container_id(),
                    $key,
                    true
                );
            }
        } catch (CanonicalCapabilitySchemaNotReady $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        } catch (CanonicalCapabilityPersistenceFailed $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        } catch (\OutOfBoundsException $e) {
            throw new CanonicalMutationPersistenceFailed($e->getMessage());
        }
    }
}
