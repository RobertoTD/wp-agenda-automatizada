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
     * Familias declaradas, presentes en snapshot y habilitadas (misma regla de disponibilidad).
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
     * @return list<array{family_key:string,label:string,url:string}>
     */
    public static function build(
        AA_Canonical_Registry $registry,
        CanonicalFamilyEnablementSnapshot $snapshot
    ): array {
        $items = [];

        foreach (self::enabled_families($registry, $snapshot) as $family) {
            $items[] = [
                'family_key' => $family->key(),
                'label' => $family->label(),
                'url' => AA_Canonical_Shell_Base_Url_Policy::build_url($family->key()),
            ];
        }

        return $items;
    }
}
