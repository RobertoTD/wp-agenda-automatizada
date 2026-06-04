<?php
/**
 * Prioritization Provider — puerto de organización para Listas/Tareas.
 *
 * Recibe un snapshot de listas/tareas/contexto y devuelve solo datos de
 * organización (orden). No persiste scores ni expone criterios premium.
 *
 * Implementaciones:
 * - Local/free: AA_Task_Prioritization_Policy (dominio puro).
 * - Premium (futuro): adaptador backend en infrastructure/.
 */

defined('ABSPATH') or die('No direct access');

interface AA_Prioritization_Provider_Interface {

    /**
     * @param array<string,mixed> $snapshot lists, tasks, now (Y-m-d H:i:s).
     * @return array{
     *     list_order:list<int>,
     *     task_order_by_list:array<int,list<int>>,
     *     executive_candidates:list<int>
     * }
     */
    public function prioritize(array $snapshot): array;
}
