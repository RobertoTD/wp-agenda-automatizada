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
require_once plugin_dir_path(__FILE__) . '../models/AssignmentsModel.php';

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

            // Resolver servicio: si es ID numérico, obtener nombre desde aa_services
            $servicio_raw = trim($row->servicio ?? '');
            $servicio = $servicio_raw;
            if ($servicio_raw !== '' && ctype_digit($servicio_raw) && (int) $servicio_raw > 0) {
                $svc = AssignmentsModel::get_service_by_id((int) $servicio_raw);
                $servicio = is_array($svc) && !empty($svc['name'])
                    ? sanitize_text_field($svc['name'])
                    : sanitize_text_field($servicio_raw);
            } else {
                $servicio = sanitize_text_field($servicio_raw);
            }

            // Válida → agregar a confirmed
            $confirmed[$key] = [
                'id'            => intval($row->id),
                'nombre'        => sanitize_text_field($row->nombre ?? ''),
                'email'         => sanitize_email($email),
                'telefono'      => sanitize_text_field($row->telefono ?? ''),
                'servicio'      => $servicio,
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
