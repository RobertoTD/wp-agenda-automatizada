<?php
/**
 * Handler de escritura de una capacidad (reglas + efecto).
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Capabilities
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalCapabilityWriteHandler {

    public function capability_key(): string;

    /**
     * @param mixed $raw
     * @return CanonicalRecordCapabilityEffect|null null = noop (p. ej. create con vacío)
     *
     * @throws CanonicalCapabilityUnknown
     * @throws CanonicalCapabilityNotReady
     * @throws CanonicalCapabilityInactive
     * @throws CanonicalCapabilityWriteRejected
     * @throws CanonicalCapabilitySchemaNotReady
     * @throws CanonicalCapabilityPersistenceFailed
     */
    public function prepare_record_write(
        string $family_key,
        int $container_id,
        $raw,
        bool $is_update
    ): ?CanonicalRecordCapabilityEffect;
}
