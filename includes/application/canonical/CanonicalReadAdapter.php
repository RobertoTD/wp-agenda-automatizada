<?php
/**
 * Canonical Read Adapter — Puerto de lectura paginada de contenedores.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Application\Canonical
 */

defined('ABSPATH') or die('No direct access');

interface CanonicalReadAdapter {

    /**
     * Lista contenedores de una variante ya validada.
     *
     * Debe ordenar el conjunto completo por updated_at DESC, id DESC antes de cortar.
     */
    public function list_containers(string $variant_key, int $page, int $per_page): CanonicalPage;
}
