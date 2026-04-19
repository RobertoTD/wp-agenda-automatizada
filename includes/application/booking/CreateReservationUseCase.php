<?php
/**
 * Create Reservation Use Case
 *
 * Caso de uso canónico para CREAR una reserva en el sistema.
 * Invocado por todos los canales del producto que crean reservas:
 *  - Formulario público (frontend) vía wp_ajax_nopriv_aa_save_reservation.
 *  - Modal admin "Reserva" vía wp_ajax_aa_save_reservation.
 *  - Modal admin "Cita rápida" vía wp_ajax_aa_save_reservation.
 *  - (Futuro) Confirmación desde AI Chat.
 *
 * Responsabilidad:
 *  - Aplicar la lógica de creación: cliente, snapshot de precio,
 *    join_token virtual, inserción con retry, notificación.
 *  - Devolver un array con resultado estructurado (success / error).
 *  - NO conoce HTTP, nonces, honeypot ni serialización JSON.
 *  - NO valida permisos WP (eso es del controller AJAX).
 *
 * Deuda técnica explícita (a limpiar en prompts posteriores):
 *  - Acceso directo a $wpdb → migrar a ReservationsRepository.
 *  - Lectura de get_option('aa_timezone') → migrar a ClockService
 *    (infrastructure/wp/).
 *  - error_log → migrar a un Logger de infrastructure.
 *  - Notificación inline → extraer NotificationsService.
 *
 * @since 1.x (extracted from wp-agenda-automatizada.php aa_save_reservation)
 */

defined('ABSPATH') or die('No direct access');

final class CreateReservationUseCase {

    /**
     * Ejecuta el caso de uso.
     *
     * @param array $input Datos crudos decodificados del request.
     *                     Esperado: claves `servicio`, `fecha`, `nombre`,
     *                     `telefono`, `correo`, `duracion`, `assignment_id`,
     *                     `virtual_link` (todos opcionales según validación
     *                     interna). NO debe incluir `nonce` ni `extra_field`
     *                     (esos los maneja el controller AJAX antes).
     *
     * @return array Resultado estructurado:
     *   - On success: [
     *       'success' => true,
     *       'data' => [
     *           'message'    => string,
     *           'id'         => int,
     *           'cliente_id' => int,
     *           'join_token' => string|null,
     *       ],
     *     ]
     *   - On error:   [
     *       'success' => false,
     *       'error' => [
     *           'message' => string,
     *           'detail'  => string|null,  // wpdb error u otros detalles
     *       ],
     *     ]
     */
    public function execute(array $input): array {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_reservas';

        // ✅ Validación básica de datos requeridos
        if (empty($input['servicio']) || empty($input['fecha']) || empty($input['nombre'])) {
            return $this->error('Datos incompletos.');
        }

        // 🔹 Obtener la zona horaria configurada
        $timezone = get_option('aa_timezone', 'America/Mexico_City');

        // ✅ Sanitización y conversión de fecha a la zona horaria del negocio
        $servicio = sanitize_text_field($input['servicio']);
        $is_fixed_service = strpos($servicio, 'fixed::') === 0;
        $service_price_snapshot = null;

        // 🔹 Determinar si el servicio es virtual (antes de recortar fixed::)
        $join_token = null;
        $is_virtual = false;
        if (!$is_fixed_service && is_numeric($servicio)) {
            $service_row = class_exists('AssignmentsModel')
                ? AssignmentsModel::get_service_by_id(intval($servicio))
                : false;
            if ($service_row && isset($service_row['attendance_type']) && $service_row['attendance_type'] === 'virtual') {
                $is_virtual = true;
            }

            $services_table = $wpdb->prefix . 'aa_services';
            $service_price = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM {$services_table} WHERE id = %d LIMIT 1",
                intval($servicio)
            ));

            if ($service_price !== null) {
                $service_price_snapshot = $service_price;
            }
        }

        // 🔹 Generar join_token para servicios virtuales
        if ($is_virtual) {
            $join_token = bin2hex(random_bytes(16));
        }

        // 🔹 Extraer prefijo "fixed::" si existe (guardar solo el nombre del servicio)
        if ($is_fixed_service) {
            $servicio = substr($servicio, 7); // strlen('fixed::') = 7
        }

        // 🔹 Convertir ISO UTC a DateTime en zona horaria local
        try {
            $fechaObj = new DateTime($input['fecha'], new DateTimeZone('UTC'));
            $fechaObj->setTimezone(new DateTimeZone($timezone));
            $fecha = $fechaObj->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return $this->error('Formato de fecha inválido.');
        }

        $nombre       = sanitize_text_field($input['nombre']);
        $telefono_raw = isset($input['telefono']) ? sanitize_text_field($input['telefono']) : '';
        $correo       = isset($input['correo']) ? sanitize_email($input['correo']) : '';

        // 🔹 Validar teléfono obligatorio
        if (empty($telefono_raw)) {
            return $this->error('El teléfono es obligatorio.');
        }

        // 🔹 Normalizar teléfono a formato canónico
        $telefono = aa_normalize_telefono($telefono_raw);
        if (is_wp_error($telefono)) {
            return $this->error($telefono->get_error_message());
        }

        // 🔹 Obtener duración (validar que sea 30, 60 o 90, por defecto 60)
        $duracion = isset($input['duracion']) ? intval($input['duracion']) : 60;
        if (!in_array($duracion, [30, 60, 90])) {
            $duracion = 60; // Valor por defecto si no es válido
        }

        // 🔹 Buscar o crear cliente usando ClienteService (con reglas de correo mismatch)
        $cliente_id = ClienteService::getOrCreate([
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correo' => $correo
        ]);

        if (is_wp_error($cliente_id)) {
            error_log("❌ Error al obtener/crear cliente: " . $cliente_id->get_error_message());
            return $this->error($cliente_id->get_error_message());
        }

        // ✅ Preparar datos para inserción
        $insert_data = [
            'servicio'   => $servicio,
            'fecha'      => $fecha,
            'duracion'   => $duracion,
            'nombre'     => $nombre,
            'telefono'   => $telefono,
            'correo'     => $correo,
            'id_cliente' => $cliente_id,
            'estado'     => 'pending',
            'service_price_snapshot' => $service_price_snapshot,
            'created_at' => current_time('mysql')
        ];

        // ✅ Agregar assignment_id si viene en los datos (opcional)
        if (isset($input['assignment_id']) && !empty($input['assignment_id'])) {
            $assignment_id = intval($input['assignment_id']);
            if ($assignment_id > 0) {
                $insert_data['assignment_id'] = $assignment_id;
            }
        }

        // 🔹 Incluir join_token en insert_data (null si no es virtual)
        $insert_data['join_token'] = $join_token;

        // 🔹 virtual_link: solo si viene y no vacío (permite null si no viene)
        if (isset($input['virtual_link']) && trim((string) $input['virtual_link']) !== '') {
            $insert_data['virtual_link'] = esc_url_raw(trim($input['virtual_link']));
        }

        // ✅ Inserción en la tabla (reintentos por colisión de join_token en servicios virtuales)
        $max_attempts = $is_virtual ? 3 : 1;
        $result = false;
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            if ($is_virtual && $attempt > 1) {
                $join_token = bin2hex(random_bytes(16));
                $insert_data['join_token'] = $join_token;
                error_log("🔄 [aa_save_reservation] Reintento $attempt por colisión de join_token");
            }
            $result = $wpdb->insert($table, $insert_data);
            if ($result !== false) {
                break;
            }
            if (!$is_virtual || strpos($wpdb->last_error, 'Duplicate entry') === false) {
                break;
            }
        }

        // ✅ Control de error
        if ($result === false) {
            error_log("❌ Error al insertar reserva: " . $wpdb->last_error);
            return $this->error('Error al guardar en la base de datos.', $wpdb->last_error);
        }

        // ✅ Retornar ID de la reserva creada
        $reserva_id = $wpdb->insert_id;

        if (!$reserva_id) {
            error_log("⚠️ Reserva guardada pero no se obtuvo insert_id");
            return $this->error('Reserva guardada pero ID no disponible.');
        }

        error_log("✅ Reserva guardada correctamente con ID: $reserva_id (Cliente: $cliente_id)");

        // 🔹 Crear notificación para la nueva reserva
        $notifications_table = $wpdb->prefix . 'aa_notifications';

        // ✅ Verificar si ya existe una notificación para evitar duplicados
        $existing_notification = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $notifications_table 
            WHERE entity_type = %s AND entity_id = %d AND type = %s",
            'reservation',
            $reserva_id,
            'pending'
        ));

        // ✅ Insertar notificación solo si no existe
        if (!$existing_notification) {
            $notification_result = $wpdb->insert($notifications_table, [
                'entity_type' => 'reservation',
                'entity_id'   => $reserva_id,
                'type'        => 'pending',
                'is_read'     => 0
            ]);

            if ($notification_result === false) {
                error_log("⚠️ Error al insertar notificación para reserva $reserva_id: " . $wpdb->last_error);
            } else {
                error_log("✅ Notificación creada para reserva $reserva_id");
            }
        } else {
            error_log("ℹ️ Notificación ya existe para reserva $reserva_id, omitiendo inserción");
        }

        return $this->success([
            'message'    => 'Reserva almacenada correctamente.',
            'id'         => $reserva_id,
            'cliente_id' => $cliente_id,
            'join_token' => $join_token,
        ]);
    }

    private function success(array $data): array {
        return [
            'success' => true,
            'data'    => $data,
        ];
    }

    private function error(string $message, $detail = null): array {
        return [
            'success' => false,
            'error'   => [
                'message' => $message,
                'detail'  => $detail,
            ],
        ];
    }
}
