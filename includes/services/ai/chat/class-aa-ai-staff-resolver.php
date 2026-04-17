<?php
/**
 * AI Staff Resolver
 *
 * Resuelve el staff_name extraído por el parser LLM contra
 * personal real del plugin via AssignmentsModel::get_staff().
 *
 * No valida compatibilidad staff/servicio, no consulta disponibilidad,
 * no toca assignments ni reservas.
 * Solo clasifica el resultado de búsqueda en:
 *   missing | no_match | resolved | ambiguous
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Staff_Resolver {

    /**
     * @param string|null $staff_name Texto crudo del parser.
     * @return array{status: string, source_text: ?string, ...}
     */
    public function resolve(?string $staff_name): array {
        $trimmed = $staff_name !== null ? trim($staff_name) : '';

        if ($trimmed === '') {
            return [
                'status'      => 'missing',
                'source_text' => null,
            ];
        }

        $staff_list = $this->get_active_staff();

        if (empty($staff_list)) {
            return [
                'status'        => 'no_match',
                'source_text'   => $trimmed,
                'total_matches' => 0,
            ];
        }

        $needle = mb_strtolower($trimmed, 'UTF-8');

        $exact = array_filter($staff_list, function (array $member) use ($needle) {
            return mb_strtolower(trim($member['name']), 'UTF-8') === $needle;
        });

        if (empty($exact)) {
            $exact = array_filter($staff_list, function (array $member) use ($needle) {
                return mb_strpos(mb_strtolower(trim($member['name']), 'UTF-8'), $needle) !== false;
            });
        }

        $candidates = array_values(array_map([$this, 'normalize_staff'], $exact));

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
     * @return array[] Staff activo.
     */
    private function get_active_staff(): array {
        if (!class_exists('AssignmentsModel')) {
            return [];
        }

        return \AssignmentsModel::get_staff(true);
    }

    /**
     * Extrae solo los campos relevantes para el contrato del resolver.
     */
    private function normalize_staff(array $member): array {
        return [
            'id'   => (int) $member['id'],
            'name' => $member['name'],
        ];
    }
}
