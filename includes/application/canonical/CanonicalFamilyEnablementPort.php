<?php
/**
 * Canonical Family Enablement Port — lectura/escritura de is_enabled.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalFamilyEnablementPort {

    /**
     * Lee el estado de las claves declaradas (ya validadas por Application).
     *
     * @param list<string> $family_keys
     * @throws CanonicalFamilyEnablementSchemaNotReady
     * @throws CanonicalFamilyEnablementPersistenceFailed
     */
    public function read_for_declared_families(array $family_keys): CanonicalFamilyEnablementSnapshot;

    /**
     * Actualiza is_enabled de una sola familia provisionada.
     *
     * @throws CanonicalFamilyNotProvisioned
     * @throws CanonicalFamilyEnablementSchemaNotReady
     * @throws CanonicalFamilyEnablementPersistenceFailed
     */
    public function set_enabled(string $family_key, bool $enabled): CanonicalFamilyEnablementResult;
}
