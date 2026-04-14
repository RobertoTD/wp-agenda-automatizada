<?php
/**
 * AI Client Resolver
 *
 * Resuelve el client_name extraído por el parser LLM contra
 * clientes reales del plugin via aa_search_clientes().
 *
 * No crea clientes, no modifica BD, no toca reservas.
 * Solo clasifica el resultado de búsqueda en:
 *   missing | no_match | resolved | ambiguous
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Client_Resolver {

    private const SEARCH_LIMIT = 10;

    /**
     * @param string|null $client_name Texto crudo del parser.
     * @return array{status: string, source_text: ?string, ...}
     */
    public function resolve(?string $client_name): array {
        $trimmed = $client_name !== null ? trim($client_name) : '';

        if ($trimmed === '') {
            return [
                'status'      => 'missing',
                'source_text' => null,
            ];
        }

        $results = aa_search_clientes($trimmed, self::SEARCH_LIMIT, 0);

        if (empty($results)) {
            return [
                'status'        => 'no_match',
                'source_text'   => $trimmed,
                'total_matches' => 0,
            ];
        }

        $candidates = array_map(function ($row) {
            return [
                'id'       => (int) $row->id,
                'nombre'   => $row->nombre,
                'telefono' => $row->telefono,
                'correo'   => $row->correo,
            ];
        }, $results);

        if (count($candidates) === 1) {
            $match = $candidates[0];
            return [
                'status'      => 'resolved',
                'match_type'  => 'unique',
                'source_text' => $trimmed,
                'id'          => $match['id'],
                'nombre'      => $match['nombre'],
                'telefono'    => $match['telefono'],
                'correo'      => $match['correo'],
            ];
        }

        return [
            'status'        => 'ambiguous',
            'source_text'   => $trimmed,
            'total_matches' => count($candidates),
            'candidates'    => $candidates,
        ];
    }
}
