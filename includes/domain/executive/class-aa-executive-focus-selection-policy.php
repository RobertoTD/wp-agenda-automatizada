<?php
/**
 * Executive Focus Selection Policy — selección aleatoria de lista foco (MC5).
 *
 * Dominio puro: sin WordPress ni SQL.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Executive_Focus_Selection_Policy {

    /**
     * @param list<int> $eligible_list_ids
     */
    public static function select_random_focus(array $eligible_list_ids, ?callable $randomizer = null): ?int {
        $ids = [];

        foreach ($eligible_list_ids as $raw_id) {
            $id = (int) $raw_id;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return null;
        }

        if (count($ids) === 1) {
            return $ids[0];
        }

        $index = $randomizer !== null
            ? (int) call_user_func($randomizer, count($ids))
            : random_int(0, count($ids) - 1);

        if ($index < 0) {
            $index = 0;
        }

        if ($index >= count($ids)) {
            $index = count($ids) - 1;
        }

        return $ids[$index];
    }
}
