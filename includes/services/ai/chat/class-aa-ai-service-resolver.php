<?php
/**
 * AI Service Resolver
 *
 * Resuelve el service_name extraído por el parser LLM contra
 * servicios reales del plugin via AssignmentsModel::get_services().
 *
 * No crea servicios, no modifica BD, no toca reservas.
 * Solo clasifica el resultado de búsqueda en:
 *   missing | no_match | resolved | ambiguous
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/../../../domain/text/class-aa-text-folder.php';

final class AA_AI_Service_Resolver {

    /**
     * @param string|null $service_name Texto crudo del parser.
     * @return array{status: string, source_text: ?string, ...}
     */
    public function resolve(?string $service_name): array {
        $trimmed = $service_name !== null ? trim($service_name) : '';

        if ($trimmed === '') {
            return [
                'status'      => 'missing',
                'source_text' => null,
            ];
        }

        $services = $this->get_active_services();

        if (empty($services)) {
            return [
                'status'        => 'no_match',
                'source_text'   => $trimmed,
                'total_matches' => 0,
            ];
        }

        $needle = AA_Text_Folder::fold($trimmed);

        $exact = array_filter($services, function (array $svc) use ($needle) {
            return AA_Text_Folder::fold($svc['name']) === $needle;
        });

        if (empty($exact)) {
            $exact = array_filter($services, function (array $svc) use ($needle) {
                return mb_strpos(AA_Text_Folder::fold($svc['name']), $needle, 0, 'UTF-8') !== false;
            });
        }

        $candidates = array_values(array_map([$this, 'normalize_service'], $exact));

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
                'status'           => 'resolved',
                'match_type'       => 'unique',
                'source_text'      => $trimmed,
                'id'               => $match['id'],
                'name'             => $match['name'],
                'duration_minutes' => $match['duration_minutes'],
                'price'            => $match['price'],
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
     * @return array[] Servicios activos y no ocultos.
     */
    private function get_active_services(): array {
        if (!class_exists('AssignmentsModel')) {
            return [];
        }

        return \AssignmentsModel::get_services(true);
    }

    /**
     * Extrae solo los campos relevantes para el contrato del resolver.
     */
    private function normalize_service(array $svc): array {
        return [
            'id'               => (int) $svc['id'],
            'name'             => $svc['name'],
            'duration_minutes' => isset($svc['duration_minutes']) && $svc['duration_minutes'] !== null && $svc['duration_minutes'] !== ''
                                    ? (int) $svc['duration_minutes']
                                    : null,
            'price'            => $svc['price'] ?? null,
        ];
    }
}
