<?php
/**
 * Handler de escritura phone (reglas + efecto).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Phone_Write_Handler implements CanonicalCapabilityWriteHandler {

    public const KEY = 'phone';

    /** @var AA_Canonical_Capability_Registry */
    private $capability_registry;

    /** @var CanonicalCapabilityConfigRepository */
    private $config_repository;

    /** @var CanonicalRecordPhoneRepository */
    private $phone_repository;

    public function __construct(
        AA_Canonical_Capability_Registry $capability_registry,
        CanonicalCapabilityConfigRepository $config_repository,
        CanonicalRecordPhoneRepository $phone_repository
    ) {
        $this->capability_registry = $capability_registry;
        $this->config_repository = $config_repository;
        $this->phone_repository = $phone_repository;
    }

    public function capability_key(): string {
        return self::KEY;
    }

    public function prepare_record_write(
        string $family_key,
        int $container_id,
        $raw,
        bool $is_update
    ): ?CanonicalRecordCapabilityEffect {
        $this->config_repository->assert_schema_ready();

        try {
            $definition = $this->capability_registry->get(self::KEY);
        } catch (\OutOfBoundsException $e) {
            throw new CanonicalCapabilityUnknown(self::KEY);
        }

        if (!$definition->is_ready()) {
            throw new CanonicalCapabilityNotReady(self::KEY);
        }

        $family_id = $this->config_repository->resolve_family_id($family_key);
        if ($family_id === null) {
            throw new CanonicalFamilyNotProvisioned($family_key);
        }

        if (!$this->config_repository->container_belongs_to_family($family_id, $container_id)) {
            throw new CanonicalContainerNotFound($family_key, $container_id);
        }

        $assignment = $this->config_repository->find_container_capability($container_id, self::KEY);
        if ($assignment === null || empty($assignment['is_active'])) {
            throw new CanonicalCapabilityInactive(self::KEY);
        }

        $normalized = AA_Canonical_Phone_Normalizer::normalize($raw);
        if (empty($normalized['ok'])) {
            $error = $normalized['error'];
            throw new CanonicalCapabilityWriteRejected(
                (string) $error['code'],
                (string) $error['message'],
                400
            );
        }

        $value = $normalized['value'];
        if ($value === null) {
            if (!$is_update) {
                return null;
            }

            return new AA_Canonical_Phone_Clear_Effect($this->phone_repository);
        }

        return new AA_Canonical_Phone_Set_Effect($this->phone_repository, $value);
    }
}
