<?php
/**
 * Canonical Family Enablement Nav — construye items de navegación desde registry + snapshot.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Infrastructure\Canonical
 */

defined('ABSPATH') or die('No direct access');

final class AA_Canonical_Family_Enablement_Nav {

    /**
     * Familias declaradas, presentes en snapshot y habilitadas (sin filtro de acceso).
     *
     * @return list<AA_Canonical_Family_Definition>
     */
    public static function enabled_families(
        AA_Canonical_Registry $registry,
        CanonicalFamilyEnablementSnapshot $snapshot
    ): array {
        $families = [];

        foreach ($registry->families() as $family) {
            $key = $family->key();
            if (!$snapshot->has($key) || !$snapshot->is_enabled($key)) {
                continue;
            }
            $families[] = $family;
        }

        return $families;
    }

    /**
     * Familias habilitadas y autorizadas para el usuario actual (criterio único del shell).
     *
     * Incluye familias sin contenedores. No altera roles ni políticas de acceso.
     *
     * @return list<AA_Canonical_Family_Definition>
     */
    public static function available_families(
        AA_Canonical_Registry $registry,
        CanonicalFamilyEnablementSnapshot $snapshot
    ): array {
        $families = [];

        foreach (self::enabled_families($registry, $snapshot) as $family) {
            $access = AA_Canonical_Access_Policy::check_family_access($family->key());
            if (!empty($access['authorized'])) {
                $families[] = $family;
            }
        }

        return $families;
    }

    /**
     * @return list<array{family_key:string,label:string,url:string,icon_key:string}>
     */
    public static function build(
        AA_Canonical_Registry $registry,
        CanonicalFamilyEnablementSnapshot $snapshot
    ): array {
        $items = [];

        foreach (self::available_families($registry, $snapshot) as $family) {
            $items[] = [
                'family_key' => $family->key(),
                'label' => $family->label(),
                'url' => AA_Canonical_Shell_Base_Url_Policy::build_url($family->key()),
                'icon_key' => $family->icon_key(),
            ];
        }

        return $items;
    }
}
