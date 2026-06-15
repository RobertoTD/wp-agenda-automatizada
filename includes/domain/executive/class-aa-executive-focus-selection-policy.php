<?php
/**
 * Executive Focus Selection Policy — selección aleatoria de lista foco (MC5/MC5.1).
 *
 * Dominio puro: sin WordPress ni SQL.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Executive_Focus_Selection_Policy {

    /**
     * @param list<int> $eligible_list_ids
     */
    public static function select_random_focus(
        array $eligible_list_ids,
        ?int $current_focus_list_id = null,
        ?callable $randomizer = null
    ): ?int {
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

        $pool = $ids;

        if ($current_focus_list_id !== null && $current_focus_list_id > 0) {
            $current = $current_focus_list_id;
            $without_current = array_values(array_filter(
                $ids,
                static function (int $id) use ($current): bool {
                    return $id !== $current;
                }
            ));

            if ($without_current !== []) {
                $pool = $without_current;
            }
        }

        if (count($pool) === 1) {
            return $pool[0];
        }

        $index = $randomizer !== null
            ? (int) call_user_func($randomizer, count($pool))
            : random_int(0, count($pool) - 1);

        if ($index < 0) {
            $index = 0;
        }

        if ($index >= count($pool)) {
            $index = count($pool) - 1;
        }

        return $pool[$index];
    }
}
