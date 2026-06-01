<?php
/**
 * Learning Visibility Policy — reglas puras de visibilidad, lista efectiva y orden.
 *
 * No consulta BD, WordPress ni facts reales; recibe definición, estado, facts y now.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Learning_Visibility_Policy {

    public const LIST_PRIMARY = 1;

    public const LIST_SECONDARY = 2;

    public const DEFAULT_AGING_DAYS = 14;

    /**
     * Evalúa todas las definiciones del catálogo y devuelve listas agrupadas (solo visibles).
     *
     * @param array<string,array<string,mixed>> $definitions     Indexado por key.
     * @param array<string,array<string,mixed>> $states_by_key   Estado persistido por key (puede faltar).
     * @param array<string,mixed>               $facts           Hechos ya resueltos (p. ej. google_connected).
     * @param string                            $now             Fecha/hora de referencia (Y-m-d H:i:s).
     * @param array<string,mixed>               $options         aging_days (int).
     * @return array{list_1:list<array<string,mixed>>,list_2:list<array<string,mixed>>,all_visible:list<array<string,mixed>>}
     */
    public function evaluate_all(
        array $definitions,
        array $states_by_key,
        array $facts,
        string $now,
        array $options = []
    ): array {
        $visible_items = [];

        foreach ($definitions as $key => $definition) {
            $state = $states_by_key[$key] ?? null;
            $evaluated = $this->evaluate($definition, $state, $facts, $now, $options);

            if ($evaluated['visible']) {
                $visible_items[] = $evaluated;
            }
        }

        $sorted = $this->sort_items($visible_items);

        return [
            'list_1' => $this->filter_by_effective_list($sorted, self::LIST_PRIMARY),
            'list_2' => $this->filter_by_effective_list($sorted, self::LIST_SECONDARY),
            'all_visible' => $sorted,
        ];
    }

    /**
     * Evalúa una recomendación.
     *
     * @param array<string,mixed>      $definition
     * @param array<string,mixed>|null $state
     * @param array<string,mixed>      $facts
     * @param string                   $now
     * @param array<string,mixed>      $options
     * @return array<string,mixed>
     */
    public function evaluate(
        array $definition,
        ?array $state,
        array $facts,
        string $now,
        array $options = []
    ): array {
        $key = (string) ($definition['key'] ?? '');
        $active = !empty($definition['active']);
        $default_list = $this->normalize_list($definition['default_list'] ?? self::LIST_PRIMARY);
        $completion_type = (string) ($definition['completion_type'] ?? AA_Learning_Catalog::COMPLETION_MANUAL);

        $state_row = is_array($state) ? $state : [];

        $manual_completed = $this->bool_value($state_row['is_completed'] ?? false);
        $auto_completed = $this->is_auto_completed($definition, $facts);
        $is_completed = $manual_completed || $auto_completed;
        $is_ignored = $this->bool_value($state_row['is_ignored'] ?? false);

        $visible = $active && !$is_completed;

        $effective_list = $this->resolve_effective_list(
            $definition,
            $state_row,
            $is_ignored,
            $now,
            $options
        );

        return [
            'key' => $key,
            'title' => (string) ($definition['title'] ?? ''),
            'description' => (string) ($definition['description'] ?? ''),
            'importance' => (int) ($definition['importance'] ?? 0),
            'default_list' => $default_list,
            'effective_list' => $effective_list,
            'visible' => $visible,
            'is_completed' => $is_completed,
            'is_auto_completed' => $auto_completed,
            'is_ignored' => $is_ignored,
            'completion_type' => $completion_type,
            'navigation' => is_array($definition['navigation'] ?? null)
                ? $definition['navigation']
                : [],
        ];
    }

    /**
     * Ordena por effective_list, importance ASC, key ASC.
     *
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    public function sort_items(array $items): array {
        usort($items, function (array $a, array $b): int {
            $list_cmp = ((int) ($a['effective_list'] ?? 0)) <=> ((int) ($b['effective_list'] ?? 0));

            if ($list_cmp !== 0) {
                return $list_cmp;
            }

            $importance_cmp = ((int) ($a['importance'] ?? 0)) <=> ((int) ($b['importance'] ?? 0));

            if ($importance_cmp !== 0) {
                return $importance_cmp;
            }

            return strcmp((string) ($a['key'] ?? ''), (string) ($b['key'] ?? ''));
        });

        return $items;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @param int                       $list
     * @return list<array<string,mixed>>
     */
    private function filter_by_effective_list(array $items, int $list): array {
        $filtered = [];

        foreach ($items as $item) {
            if ((int) ($item['effective_list'] ?? 0) === $list) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $state
     * @param bool                $is_ignored
     * @param string              $now
     * @param array<string,mixed> $options
     */
    private function resolve_effective_list(
        array $definition,
        array $state,
        bool $is_ignored,
        string $now,
        array $options
    ): int {
        $default_list = $this->normalize_list($definition['default_list'] ?? self::LIST_PRIMARY);

        $override = $state['list_override'] ?? null;

        if ($override !== null && $override !== '') {
            $normalized_override = $this->normalize_list($override);

            if ($normalized_override !== null) {
                return $normalized_override;
            }
        }

        if ($is_ignored) {
            return self::LIST_SECONDARY;
        }

        if ($this->is_aged_to_secondary($state, $now, $options)) {
            return self::LIST_SECONDARY;
        }

        return $default_list;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $facts
     */
    private function is_auto_completed(array $definition, array $facts): bool {
        if (($definition['completion_type'] ?? '') !== AA_Learning_Catalog::COMPLETION_AUTO) {
            return false;
        }

        $fact_key = $definition['completion_fact'] ?? null;

        if (!is_string($fact_key) || $fact_key === '') {
            return false;
        }

        return $this->bool_value($facts[$fact_key] ?? false);
    }

    /**
     * @param array<string,mixed> $state
     * @param string              $now
     * @param array<string,mixed> $options
     */
    private function is_aged_to_secondary(array $state, string $now, array $options): bool {
        $last_suggested = $state['last_suggested_at'] ?? null;

        if (!is_string($last_suggested) || trim($last_suggested) === '') {
            return false;
        }

        $aging_days = isset($options['aging_days'])
            ? max(1, (int) $options['aging_days'])
            : self::DEFAULT_AGING_DAYS;

        $suggested_ts = strtotime($last_suggested);
        $now_ts = strtotime($now);

        if ($suggested_ts === false || $now_ts === false) {
            return false;
        }

        $threshold_ts = $now_ts - ($aging_days * 86400);

        return $suggested_ts <= $threshold_ts;
    }

    /**
     * @param mixed $list
     */
    private function normalize_list($list): ?int {
        $value = (int) $list;

        if ($value === self::LIST_PRIMARY || $value === self::LIST_SECONDARY) {
            return $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function bool_value($value): bool {
        return !empty($value);
    }
}
