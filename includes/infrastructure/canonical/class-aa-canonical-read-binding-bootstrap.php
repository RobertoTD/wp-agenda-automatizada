<?php
/**
 * Canonical Read Binding Bootstrap — Bindings productivos universales (PCU-5B).
 *
 * Registra Relational Read Adapter solo para familias habilitadas y provisionadas.
 * Sin adaptador Finance del shell, sin tablas legacy, sin familias hardcodeadas.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Read_Binding_Registry')) {
    require_once __DIR__ . '/class-aa-canonical-read-binding-registry.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadIdentity.php';
}

final class AA_Canonical_Read_Binding_Bootstrap {

    /**
     * @throws CanonicalFamilyEnablementSchemaNotReady
     * @throws CanonicalFamilyEnablementPersistenceFailed
     * @throws \LogicException
     */
    public static function register_productive(AA_Canonical_Read_Binding_Registry $registry, ?CanonicalRecordsFilter $records_filter = null): void {
        self::require_dependencies();

        $canonical = AA_Canonical_Core_Bootstrap::instance();
        $snapshot = (new ReadCanonicalFamilyEnablementUseCase(
            new AA_Canonical_Family_Enablement_Store()
        ))->execute($canonical);

        $repository = new CanonicalRelationalRepository();

        foreach ($canonical->families() as $family) {
            $family_key = $family->key();
            if (!$snapshot->is_enabled($family_key)) {
                continue;
            }

            $identity = new CanonicalReadIdentity($family_key);
            $registry->register(
                $identity,
                new AA_Canonical_Relational_Read_Adapter($repository, $identity, $records_filter)
            );
        }
    }

    private static function require_dependencies(): void {
        if (!class_exists('AA_Canonical_Core_Bootstrap')) {
            require_once __DIR__ . '/class-aa-canonical-core-bootstrap.php';
        }
        if (!class_exists('CanonicalFamilyEnablementSchemaNotReady')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalFamilyEnablementSchemaNotReady.php';
        }
        if (!class_exists('CanonicalFamilyEnablementPersistenceFailed')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalFamilyEnablementPersistenceFailed.php';
        }
        if (!class_exists('CanonicalFamilyEnablementStatus')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalFamilyEnablementStatus.php';
        }
        if (!class_exists('CanonicalFamilyEnablementSnapshot')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalFamilyEnablementSnapshot.php';
        }
        if (!class_exists('CanonicalFamilyEnablementResult')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalFamilyEnablementResult.php';
        }
        if (!class_exists('CanonicalFamilyNotProvisioned')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalFamilyNotProvisioned.php';
        }
        if (!interface_exists('CanonicalFamilyEnablementPort')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalFamilyEnablementPort.php';
        }
        if (!class_exists('ReadCanonicalFamilyEnablementUseCase')) {
            require_once dirname(__DIR__, 2) . '/application/canonical/ReadCanonicalFamilyEnablementUseCase.php';
        }
        if (!class_exists('AA_Canonical_Family_Enablement_Store')) {
            require_once __DIR__ . '/class-aa-canonical-family-enablement-store.php';
        }
        if (!class_exists('CanonicalRelationalQueryFailed')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalRelationalQueryFailed.php';
        }
        if (!class_exists('CanonicalRelationalAmbiguousOutcome')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalRelationalAmbiguousOutcome.php';
        }
        if (!class_exists('CanonicalRelationalRepository')) {
            require_once dirname(__DIR__, 2) . '/repositories/CanonicalRelationalRepository.php';
        }
        if (!class_exists('AA_Canonical_Relational_Read_Adapter')) {
            require_once __DIR__ . '/relational/class-aa-canonical-relational-read-adapter.php';
        }
    }
}
