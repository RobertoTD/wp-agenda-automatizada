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
     * @return list<array{family_key:string,label:string,url:string}>
     */
    public static function build(
        AA_Canonical_Registry $registry,
        CanonicalFamilyEnablementSnapshot $snapshot
    ): array {
        $items = [];

        foreach ($registry->families() as $family) {
            $key = $family->key();
            if (!$snapshot->has($key) || !$snapshot->is_enabled($key)) {
                continue;
            }

            $variant_key = $family->default_variant_key();
            $items[] = [
                'family_key' => $key,
                'label' => $family->label(),
                'url' => AA_Canonical_Shell_Base_Url_Policy::build_url($key, $variant_key),
            ];
        }

        return $items;
    }
}
