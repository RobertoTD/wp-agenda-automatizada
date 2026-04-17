<?php
/**
 * AI Zone Resolver
 *
 * Resuelve el zone_name extraído por el parser LLM contra
 * zonas de atención reales del plugin via AssignmentsModel::get_service_areas().
 *
 * No valida disponibilidad, compatibilidad zona/staff/servicio,
 * no toca assignments ni reservas.
 * Solo clasifica el resultado de búsqueda en:
 *   missing | no_match | resolved | ambiguous
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Zone_Resolver {

    /**
     * @param string|null $zone_name Texto crudo del parser.
     * @return array{status: string, source_text: ?string, ...}
     */
    public function resolve(?string $zone_name): array {
        $trimmed = $zone_name !== null ? trim($zone_name) : '';

        if ($trimmed === '') {
            return [
                'status'      => 'missing',
                'source_text' => null,
            ];
        }

        $areas = $this->get_active_areas();

        if (empty($areas)) {
            return [
                'status'        => 'no_match',
                'source_text'   => $trimmed,
                'total_matches' => 0,
            ];
        }

        $needle = mb_strtolower($trimmed, 'UTF-8');

        $exact = array_filter($areas, function (array $area) use ($needle) {
            return mb_strtolower(trim($area['name']), 'UTF-8') === $needle;
        });

        if (empty($exact)) {
            $exact = array_filter($areas, function (array $area) use ($needle) {
                return mb_strpos(mb_strtolower(trim($area['name']), 'UTF-8'), $needle) !== false;
            });
        }

        $candidates = array_values(array_map([$this, 'normalize_area'], $exact));

        if (empty($candidates)) {
            return [
                'status'        => 'no_match',
                'source_text'   => $trimmed,
                'total_matches' => 0,
            ];
        }

        if (count($candidates) === 1) {
            $match = $candidates[0];
            return [
                'status'      => 'resolved',
                'match_type'  => 'unique',
                'source_text' => $trimmed,
                'id'          => $match['id'],
                'name'        => $match['name'],
                'description' => $match['description'],
                'color'       => $match['color'],
            ];
        }

        return [
            'status'        => 'ambiguous',
            'source_text'   => $trimmed,
            'total_matches' => count($candidates),
            'candidates'    => $candidates,
        ];
    }

    /**
     * @return array[] Zonas de atención activas.
     */
    private function get_active_areas(): array {
        if (!class_exists('AssignmentsModel')) {
            return [];
        }

        return \AssignmentsModel::get_service_areas(true);
    }

    /**
     * Extrae solo los campos relevantes para el contrato del resolver.
     */
    private function normalize_area(array $area): array {
        return [
            'id'          => (int) $area['id'],
            'name'        => $area['name'],
            'description' => $area['description'] ?? null,
            'color'       => $area['color'] ?? null,
        ];
    }
}
