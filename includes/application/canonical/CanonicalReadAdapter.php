<?php
/**
 * Canonical Read Adapter — Puerto de lectura canónica de contenedores y registros.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalReadAdapter {

    /**
     * Lista contenedores de la familia ligada al adaptador.
     *
     * Debe ordenar el conjunto completo por updated_at DESC, id DESC antes de cortar.
     */
    public function list_containers(int $page, int $per_page): CanonicalPage;

    /**
     * Devuelve el contenedor autoritativo de la familia.
     *
     * @throws CanonicalContainerNotFound
     */
    public function get_container(int $container_id): AA_Canonical_Container;

    /**
     * Lista registros de un contenedor existente.
     *
     * Debe ordenar el conjunto completo por updated_at DESC, id DESC antes de cortar.
     * Un contenedor inexistente debe lanzar CanonicalContainerNotFound (nunca página vacía).
     *
     * @throws CanonicalContainerNotFound
     */
    public function list_records(
        int $container_id,
        int $page,
        int $per_page
    ): CanonicalRecordsPage;
}
