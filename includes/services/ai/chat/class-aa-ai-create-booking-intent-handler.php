<?php
/**
 * Create Booking Intent Handler
 *
 * Caso de uso real del bounded context AI para create_booking.
 * Recibe un parsed normalizado y devuelve una respuesta de negocio
 * con resolución temporal heurística y resolución de cliente contra BD.
 *
 * Frontera actual:
 * - Resuelve fecha/hora natural a datetime local del negocio.
 * - Resuelve client_name contra clientes reales (aa_search_clientes).
 * - No resuelve staff/servicio/zona contra BD.
 * - No consulta disponibilidad.
 * - No crea reservas ni clientes.
 */

defined('ABSPATH') or die('No direct access');

require_once __DIR__ . '/class-aa-ai-datetime-resolver.php';
require_once __DIR__ . '/class-aa-ai-client-resolver.php';

final class AA_AI_Create_Booking_Intent_Handler {

    /**
     * Procesa un parsed normalizado de create_booking.
     *
     * @param array $parsed El array normalizado con las 8 claves del parser.
     * @return array {
     *     @type string $intent     Siempre 'create_booking'.
     *     @type string $status     Estado del flujo de negocio.
     *     @type string $reply      Texto legible para el admin.
     *     @type array  $resolution {
     *         @type array  $parsed_input            Datos crudos del parser.
     *         @type array  $missing_fields          Campos requeridos ausentes.
     *         @type array  $ambiguous_fields        Campos con más de un candidato.
     *         @type array  $resolved                Entidades resueltas contra BD.
     *         @type array  $proposed                Valores propuestos por heurística (futuro).
     *         @type bool   $ready_for_confirmation  true cuando el draft esté listo para confirmar.
     *         @type array  $datetime_resolution     Resolución temporal heurística.
     *         @type array  $lookup                  Resultados de búsqueda sin resolución exitosa.
     *     }
     * }
     */
    public function handle(array $parsed) {
        $missing = $this->detect_missing_fields($parsed);

        $dt_resolver = new AA_AI_Datetime_Resolver();
        $datetime_resolution = $dt_resolver->resolve(
            $parsed['date_text'] ?? null,
            $parsed['time_text'] ?? null
        );

        $client_resolver = new AA_AI_Client_Resolver();
        $client_result   = $client_resolver->resolve($parsed['client_name'] ?? null);

        $resolved        = [];
        $ambiguous_fields = [];
        $lookup          = [];

        $this->place_client_result($client_result, $resolved, $ambiguous_fields, $lookup);

        $reply = $this->build_reply($parsed, $missing);

        return [
            'intent'     => 'create_booking',
            'status'     => 'needs_resolution',
            'reply'      => $reply,
            'resolution' => [
                'parsed_input'           => $parsed,
                'missing_fields'         => $missing,
                'ambiguous_fields'       => !empty($ambiguous_fields) ? $ambiguous_fields : new \stdClass(),
                'resolved'               => !empty($resolved) ? $resolved : new \stdClass(),
                'proposed'               => new \stdClass(),
                'ready_for_confirmation' => false,
                'datetime_resolution'    => $datetime_resolution,
                'lookup'                 => !empty($lookup) ? $lookup : new \stdClass(),
            ],
        ];
    }

    /**
     * Coloca el resultado del client resolver en el slot correcto del contrato.
     */
    private function place_client_result(array $result, array &$resolved, array &$ambiguous, array &$lookup): void {
        switch ($result['status']) {
            case 'resolved':
                $resolved['client'] = $result;
                break;

            case 'ambiguous':
                $ambiguous['client'] = $result;
                break;

            case 'missing':
            case 'no_match':
            default:
                $lookup['client'] = $result;
                break;
        }
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
