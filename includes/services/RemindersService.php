<?php
/**
 * Service: Reminders
 *
 * Construye el payload de respuesta para el endpoint reminders-bulk.
 * Solo incluye en "confirmed" las reservas con estado confirmed + correo válido.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Services
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '../models/ReservationsModel.php';

class RemindersService {

    /**
     * Construye el payload bulk a partir de un array de IDs de reserva.
     *
     * @param array $ids Array de IDs (int) solicitados por el worker.
     * @return array ['success' => true, 'confirmed' => [...], 'skipped' => [...]]
     */
    public static function build_bulk_payload(array $ids) {
        $confirmed = [];
        $skipped   = [];

        $rows = ReservationsModel::get_by_ids($ids);

        // Indexar filas por ID para búsqueda rápida
        $rows_by_id = [];
        foreach ($rows as $row) {
            $rows_by_id[intval($row->id)] = $row;
        }

        foreach ($ids as $id) {
            $id = intval($id);
            $key = (string) $id;

            // No encontrada en DB
            if (!isset($rows_by_id[$id])) {
                $skipped[$key] = 'not_found';
                continue;
            }

            $row = $rows_by_id[$id];

            // No confirmada
            if ($row->estado !== 'confirmed') {
                $skipped[$key] = 'not_confirmed';
                continue;
            }

            // Sin email
            $email = isset($row->correo) ? trim($row->correo) : '';
            if (empty($email)) {
                $skipped[$key] = 'missing_email';
                continue;
            }

            // Válida → agregar a confirmed
            $confirmed[$key] = [
                'id'            => intval($row->id),
                'nombre'        => sanitize_text_field($row->nombre ?? ''),
                'email'         => sanitize_email($email),
                'telefono'      => sanitize_text_field($row->telefono ?? ''),
                'servicio'      => sanitize_text_field($row->servicio ?? ''),
                'fecha'         => $row->fecha,
                'duracion'      => intval($row->duracion ?? 60),
                'assignment_id' => $row->assignment_id !== null ? intval($row->assignment_id) : null,
            ];
        }

        return [
            'success'   => true,
            'confirmed' => $confirmed,
            'skipped'   => $skipped,
        ];
    }
}
