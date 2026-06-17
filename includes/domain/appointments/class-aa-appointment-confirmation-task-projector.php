<?php
/**
 * Appointment Confirmation Task Projector — copy e identidad de tarea por cita.
 *
 * Dominio puro: sin WordPress, SQL ni límites de infraestructura.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-appointment-actions-catalog.php';

final class AA_Appointment_Confirmation_Task_Projector {

    public const DEFAULT_CLIENT_LABEL = 'cliente';

    /**
     * @return array{
     *     action_key:string,
     *     type:string,
     *     label:string,
     *     placement:string,
     *     category:string,
     *     handler:string,
     *     enabled:int,
     *     position:int
     * }
     */
    public static function action_definition(): array {
        return [
            'action_key' => AA_Appointment_Actions_Catalog::TASK_ACTION_KEY,
            'type' => 'handler',
            'label' => AA_Appointment_Actions_Catalog::TASK_ACTION_LABEL,
            'placement' => 'primary',
            'category' => 'mechanical',
            'handler' => AA_Appointment_Actions_Catalog::TASK_ACTION_HANDLER,
            'enabled' => 1,
            'position' => 0,
        ];
    }

    public static function task_origin_key(int $reservation_id): string {
        return AA_Appointment_Actions_Catalog::task_origin_key($reservation_id);
    }

    public static function build_title(string $client_name): string {
        $name = trim($client_name);

        if ($name === '') {
            $name = self::DEFAULT_CLIENT_LABEL;
        }

        return 'Confirmar cita con ' . $name;
    }

    /**
     * @param array{
     *     phone?:string,
     *     date_label?:string,
     *     time_label?:string,
     *     service?:string
     * } $display
     */
    public static function build_notes(array $display): string {
        $lines = [];
        $phone = trim((string) ($display['phone'] ?? ''));

        if ($phone !== '') {
            $lines[] = 'Teléfono: ' . $phone;
        }

        $date_label = trim((string) ($display['date_label'] ?? ''));

        if ($date_label !== '') {
            $lines[] = 'Fecha: ' . $date_label;
        }

        $time_label = trim((string) ($display['time_label'] ?? ''));

        if ($time_label !== '') {
            $lines[] = 'Hora: ' . $time_label;
        }

        $service = trim((string) ($display['service'] ?? ''));

        if ($service !== '') {
            $lines[] = 'Servicio: ' . $service;
        }

        return implode("\n", $lines);
    }

    public static function truncate_text(string $value, int $max_length): string {
        if ($max_length < 1) {
            return '';
        }

        if (strlen($value) <= $max_length) {
            return $value;
        }

        if ($max_length <= 3) {
            return substr($value, 0, $max_length);
        }

        return rtrim(substr($value, 0, $max_length - 3)) . '...';
    }
}
