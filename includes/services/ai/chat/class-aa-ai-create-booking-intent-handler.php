<?php
/**
 * Create Booking Intent Handler
 *
 * Primer caso de uso real del bounded context AI.
 * Recibe un parsed normalizado con intent=create_booking y devuelve
 * una respuesta de negocio inicial sin resolver entidades ni disponibilidad.
 *
 * Frontera actual:
 * - No ejecuta SQL.
 * - No resuelve cliente/staff/servicio/zona contra BD.
 * - No convierte fecha natural a DateTime.
 * - No consulta disponibilidad.
 * - No crea reservas.
 *
 * Fases posteriores agregarán resolución de entidades, disponibilidad
 * y ejecución real aquí o en clases delegadas desde aquí.
 */

defined('ABSPATH') or die('No direct access');

final class AA_AI_Create_Booking_Intent_Handler {

    /**
     * Procesa un parsed normalizado de create_booking.
     *
     * @param array $parsed El array normalizado con las 8 claves del parser.
     * @return array {
     *     @type string $intent     Siempre 'create_booking'.
     *     @type string $status     Estado del flujo de negocio.
     *     @type string $reply      Texto legible para el admin.
     *     @type array  $resolution Datos de resolución parcial.
     * }
     */
    public function handle(array $parsed) {
        $missing = $this->detect_missing_fields($parsed);

        $reply = $this->build_reply($parsed, $missing);

        return [
            'intent'     => 'create_booking',
            'status'     => 'needs_resolution',
            'reply'      => $reply,
            'resolution' => [
                'parsed_input'   => $parsed,
                'missing_fields' => $missing,
            ],
        ];
    }

    /**
     * Detecta qué campos clave faltan para una reserva.
     *
     * @param array $parsed
     * @return string[]
     */
    private function detect_missing_fields(array $parsed) {
        $required = ['client_name', 'service_name', 'date_text', 'time_text'];
        $missing  = [];

        foreach ($required as $field) {
            if (empty($parsed[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Genera un texto de respuesta indicando el estado de la solicitud.
     *
     * @param array    $parsed
     * @param string[] $missing
     * @return string
     */
    private function build_reply(array $parsed, array $missing) {
        if (empty($missing)) {
            return 'Solicitud de reserva recibida. Se iniciará la resolución de datos.';
        }

        $labels = [
            'client_name'  => 'cliente',
            'service_name' => 'servicio',
            'date_text'    => 'fecha',
            'time_text'    => 'hora',
        ];

        $readable = array_map(
            function ($field) use ($labels) {
                return $labels[$field] ?? $field;
            },
            $missing
        );

        return 'Solicitud de reserva recibida. Faltan datos: ' . implode(', ', $readable) . '.';
    }
}
