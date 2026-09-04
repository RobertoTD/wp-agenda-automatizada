<?php
/**
 * Canonical Write Binding Bootstrap — Bindings productivos universales de escritura (PCU-5B).
 *
 * Registra Relational Write Adapter compartido solo para identidades habilitadas.
 * Listo y testeable; no lo invoca el compositor de lectura.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

if (!class_exists('AA_Canonical_Write_Binding_Registry')) {
    require_once __DIR__ . '/class-aa-canonical-write-binding-registry.php';
}
if (!class_exists('CanonicalReadIdentity')) {
    require_once dirname(__DIR__, 2) . '/application/canonical/CanonicalReadIdentity.php';
}

final class AA_Canonical_Write_Binding_Bootstrap {

    /**
     * @param CanonicalRelationalRepository|null $repository Repo request-local opcional (tests / composition root futuro).
     *
     * @throws CanonicalFamilyEnablementSchemaNotReady
     * @throws CanonicalFamilyEnablementPersistenceFailed
     * @throws \LogicException
     */
    public static function register_productive(
        AA_Canonical_Write_Binding_Registry $registry,
        $repository = null
    ): void {
        self::require_dependencies();

        $canonical = AA_Canonical_Core_Bootstrap::instance();
        $snapshot = (new ReadCanonicalFamilyEnablementUseCase(
            new AA_Canonical_Family_Enablement_Store()
        ))->execute($canonical);

        if ($repository === null) {
            $repository = new CanonicalRelationalRepository();
        }
        if (!($repository instanceof CanonicalRelationalRepository)) {
            throw new \InvalidArgumentException(
                '[invalid_write_bootstrap] repository must be CanonicalRelationalRepository.'
            );
        }

        $write_adapter = new AA_Canonical_Relational_Write_Adapter($repository);

        foreach ($canonical->families() as $family) {
            $family_key = $family->key();
            if (!$snapshot->is_enabled($family_key)) {
                continue;
            }

            foreach ($canonical->variants_for($family_key) as $variant) {
                $identity = new CanonicalReadIdentity($family_key, $variant->key());
                $registry->register($identity, $write_adapter);
            }
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
        if (!class_exists('AA_Canonical_Relational_Write_Adapter')) {
            require_once __DIR__ . '/relational/class-aa-canonical-relational-write-adapter.php';
        }
    }
}
