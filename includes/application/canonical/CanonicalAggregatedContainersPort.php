<?php
/**
 * Canonical Aggregated Containers Port — Lectura multi-familia de contenedores.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalAggregatedContainersPort {

    /**
     * Lista contenedores de las familias indicadas con orden y paginación globales.
     *
     * El conjunto $family_keys debe ser no vacío. Un conjunto vacío no debe invocarse:
     * produce página vacía en el use case sin consulta.
     *
     * @param list<string>              $family_keys
     * @param array<string, string>     $labels_by_key family_key => label
     * @throws CanonicalReadPersistenceFailed
     * @throws \InvalidArgumentException Contrato de página / keys
     */
    public function list_containers(
        array $family_keys,
        array $labels_by_key,
        int $page,
        int $per_page
    ): CanonicalAggregatedContainersPage;
}
