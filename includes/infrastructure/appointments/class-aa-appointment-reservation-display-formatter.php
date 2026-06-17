<?php
/**
 * Appointment Reservation Display Formatter — fecha/hora legibles para tareas.
 */

defined('ABSPATH') or die('No direct access');

final class AA_Appointment_Reservation_Display_Formatter {

    /**
     * @param array<string,mixed> $reservation
     * @return array{
     *     client_name:string,
     *     phone:string,
     *     date_label:string,
     *     time_label:string,
     *     service:string
     * }
     */
    public static function format(array $reservation): array {
        $client_name = trim((string) ($reservation['nombre'] ?? ''));
        $phone = trim((string) ($reservation['telefono'] ?? ''));
        $service = trim((string) ($reservation['servicio'] ?? ''));
        $fecha = trim((string) ($reservation['fecha'] ?? ''));

        $date_label = '';
        $time_label = '';

        if ($fecha !== '') {
            $timezone_name = (string) get_option('aa_timezone', 'America/Mexico_City');

            try {
                $timezone = new DateTimeZone($timezone_name);
                $datetime = new DateTime($fecha, $timezone);
                $date_label = function_exists('date_i18n')
                    ? (string) date_i18n('j M Y', $datetime->getTimestamp())
                    : $datetime->format('j M Y');
                $time_label = function_exists('date_i18n')
                    ? (string) date_i18n('H:i', $datetime->getTimestamp())
                    : $datetime->format('H:i');
            } catch (Exception $exception) {
                error_log('[AA_Appointment_Reservation_Display_Formatter] ' . $exception->getMessage());
                $date_label = $fecha;
            }
        }

        return [
            'client_name' => $client_name,
            'phone' => $phone,
            'date_label' => $date_label,
            'time_label' => $time_label,
            'service' => $service,
        ];
    }
}
