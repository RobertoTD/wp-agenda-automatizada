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
        $fecha = trim((string) ($reservation['fecha'] ?? ''));
        $service = self::resolve_service_label((string) ($reservation['servicio'] ?? ''));

        $date_label = '';
        $time_label = '';

        if ($fecha !== '') {
            $timezone_name = (string) get_option('aa_timezone', 'America/Mexico_City');

            try {
                $timezone = new DateTimeZone($timezone_name);
                $datetime = new DateTime($fecha, $timezone);
                $time_label = $datetime->format('H:i');

                if (function_exists('wp_date')) {
                    $date_label = (string) wp_date('j M Y', $datetime->getTimestamp(), $timezone);
                } else {
                    $date_label = $datetime->format('j M Y');
                }
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

    private static function resolve_service_label(string $servicio_raw): string {
        $servicio_raw = trim($servicio_raw);

        if ($servicio_raw === '') {
            return '';
        }

        if (strpos($servicio_raw, 'fixed::') === 0) {
            $name = trim(substr($servicio_raw, 7));

            return $name;
        }

        if (ctype_digit($servicio_raw) && (int) $servicio_raw > 0) {
            if (!class_exists('AssignmentsModel', false)) {
                require_once dirname(__DIR__, 2) . '/models/AssignmentsModel.php';
            }

            $service = AssignmentsModel::get_service_by_id((int) $servicio_raw);

            if (is_array($service) && !empty($service['name'])) {
                return trim((string) $service['name']);
            }

            return '';
        }

        return $servicio_raw;
    }
}
