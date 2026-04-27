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

require_once __DIR__ . '/../../../domain/text/class-aa-text-folder.php';

final class AA_AI_Staff_Resolver {

    /**
     * @param string|null $staff_name Texto crudo del parser.
     * @return array{status: string, source_text: ?string, ...}
     */
    public function resolve(?string $staff_name): array {
        $trimmed = $staff_name !== null ? trim($staff_name) : '';

        if ($trimmed === '') {
            $active_staff = $this->get_active_staff();
            $eligible = array_values(array_filter($active_staff, function (array $member): bool {
                if (!class_exists('AssignmentsModel')) {
                    return false;
                }
                $staff_id = isset($member['id']) ? (int) $member['id'] : 0;
                if ($staff_id <= 0) {
                    return false;
                }
                $service_ids = \AssignmentsModel::get_staff_service_ids($staff_id);
                return is_array($service_ids) && count($service_ids) > 0;
            }));

            if (count($eligible) === 1) {
                $match = $this->normalize_staff($eligible[0]);
                return [
                    'status'      => 'resolved',
                    'match_type'  => 'unique',
                    'matched_by'  => 'single_active_staff',
                    'source_text' => null,
                    'id'          => $match['id'],
                    'name'        => $match['name'],
                ];
            }

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

        $needle = AA_Text_Folder::fold($trimmed);

        $exact = array_filter($staff_list, function (array $member) use ($needle) {
            return AA_Text_Folder::fold($member['name']) === $needle;
        });

        if (empty($exact)) {
            $exact = array_filter($staff_list, function (array $member) use ($needle) {
                return mb_strpos(AA_Text_Folder::fold($member['name']), $needle, 0, 'UTF-8') !== false;
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
