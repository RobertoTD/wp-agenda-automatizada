<?php
/**
 * Política pura de disponibilidad y aplicación de contact_dossier.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical\Solutions\ContactDossier
 */

defined('ABSPATH') or die('No direct access');

final class CanonicalContactDossierApplicationPolicy {

    public const BLOCK_SOLUTION_NOT_READY = 'solution_not_ready';
    public const BLOCK_INAPPLICABLE_CONTEXT = 'inapplicable_context';
    public const BLOCK_REQUIRED_FAMILY_NOT_PROVISIONED = 'required_family_not_provisioned';
    public const BLOCK_REQUIRED_FAMILY_DISABLED = 'required_family_disabled';

    /**
     * Activar exige disponibilidad actual. Desactivar solo exige que el contexto
     * sea aplicable, para no atrapar una aplicación activa si cambia un requisito.
     *
     * @throws CanonicalSolutionApplicationRejected
     */
    public function assert_transition_allowed(
        CanonicalContactDossierApplicationSnapshot $snapshot,
        bool $desired_active
    ): void {
        if (!$snapshot->is_applicable()) {
            throw new CanonicalSolutionApplicationRejected(
                self::BLOCK_INAPPLICABLE_CONTEXT,
                'contact_dossier does not apply to this container.'
            );
        }
        if ($desired_active && !$snapshot->is_available()) {
            $blockers = $snapshot->blockers();
            $reason = $blockers !== [] ? $blockers[0] : 'solution_unavailable';
            throw new CanonicalSolutionApplicationRejected(
                $reason,
                'contact_dossier requirements are not satisfied.'
            );
        }
    }

    /**
     * @param array{contact_container_id:int,is_active:bool,created_at:string,updated_at:string}|null $persisted
     */
    public function evaluate(
        AA_Canonical_Solution_Definition $definition,
        string $family_key,
        int $container_id,
        ?array $persisted,
        CanonicalFamilyEnablementSnapshot $enablement
    ): CanonicalContactDossierApplicationSnapshot {
        $applicable = $definition->applies_to_family($family_key);
        $ready = $definition->is_ready();
        $active = $persisted !== null && !empty($persisted['is_active']);
        $blockers = [];

        if (!$ready) {
            $blockers[] = self::BLOCK_SOLUTION_NOT_READY;
        }
        if (!$applicable) {
            $blockers[] = self::BLOCK_INAPPLICABLE_CONTEXT;
        }
        if ($applicable) {
            foreach ($definition->required_enabled_family_keys() as $required_family_key) {
                if (!$enablement->has($required_family_key)
                    || !$enablement->is_provisioned($required_family_key)
                ) {
                    $blockers[] = self::BLOCK_REQUIRED_FAMILY_NOT_PROVISIONED . ':' . $required_family_key;
                    continue;
                }
                if (!$enablement->is_enabled($required_family_key)) {
                    $blockers[] = self::BLOCK_REQUIRED_FAMILY_DISABLED . ':' . $required_family_key;
                }
            }
        }

        return new CanonicalContactDossierApplicationSnapshot(
            $definition->key(),
            $family_key,
            $container_id,
            $applicable,
            $ready,
            $blockers === [],
            $active,
            $blockers
        );
    }
}
