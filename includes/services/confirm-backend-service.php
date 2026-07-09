<?php
/**
 * Servicio: Confirmación de Citas con Backend
 * 
 * Responsable de:
 * - Obtener datos de la reserva desde WordPress
 * - Construir payload para el backend
 * - Enviar petición autenticada con HMAC
 * - Actualizar estado de la reserva en WordPress
 * - Ejecutar cancelación en cascada de citas conflictivas
 * - Enviar correos de confirmación al backend
 * 
 * NO contiene validaciones de AJAX ni permisos (eso es del controlador).
 * 
 * @package WP_Agenda_Automatizada
 * @subpackage Services
 */

if (!defined('ABSPATH')) exit;

/**
 * Transforma el valor de servicio para enviar al backend
 * 
 * - Si empieza con "fixed::", extrae solo el nombre (ej: "fixed::Informes" -> "Informes")
 * - Si es un ID numérico, busca el nombre del servicio en la BD (tabla aa_services)
 * - Si no encuentra el servicio, retorna el valor original como fallback
 * 
 * @param string $servicio_raw Valor de servicio tal como viene del formulario o BD
 * @return string Nombre legible del servicio
 */
function aa_transform_servicio_for_backend($servicio_raw) {
    // Caso 1: Servicio con prefijo "fixed::"
    if (strpos($servicio_raw, 'fixed::') === 0) {
        $nombre = substr($servicio_raw, 7); // strlen('fixed::') = 7
        error_log("🔄 [ServicioTransform] fixed:: detectado, extrayendo nombre: '$nombre'");
        return $nombre;
    }
    
    // Caso 2: ID numérico (servicio de assignment)
    if (is_numeric($servicio_raw)) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_services';
        
        $service_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $table WHERE id = %d",
            intval($servicio_raw)
        ));
        
        if ($service_name) {
            error_log("🔄 [ServicioTransform] ID $servicio_raw resuelto a nombre: '$service_name'");
            return $service_name;
        }
        
        error_log("⚠️ [ServicioTransform] No se encontró servicio con ID $servicio_raw, usando valor original");
    }
    
    // Caso 3: Ya es un nombre o valor desconocido, retornar tal cual
    return $servicio_raw;
}

/**
 * Transforma el valor de servicio y devuelve nombre + description + indicaciones_cita para el backend
 *
 * - Si es ID numérico: consulta aa_services y devuelve name, description, indicaciones_cita
 * - Si es fixed::... o no hay fila: name (legible) y description/indicaciones_cita como null
 *
 * @param string $servicio_raw Valor de servicio tal como viene del formulario o BD
 * @return array{name: string, description: string|null, indicaciones_cita: string|null}
 */
function aa_transform_servicio_for_backend_full($servicio_raw) {
    $empty = ['name' => (string) $servicio_raw, 'description' => null, 'indicaciones_cita' => null];

    if (strpos($servicio_raw, 'fixed::') === 0) {
        $nombre = substr($servicio_raw, 7);
        return ['name' => $nombre, 'description' => null, 'indicaciones_cita' => null];
    }

    if (is_numeric($servicio_raw)) {
        global $wpdb;
        $table = $wpdb->prefix . 'aa_services';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT name, description, indicaciones_cita FROM $table WHERE id = %d",
            intval($servicio_raw)
        ), ARRAY_A);

        if ($row) {
            return [
                'name' => isset($row['name']) ? (string) $row['name'] : (string) $servicio_raw,
                'description' => isset($row['description']) && $row['description'] !== '' ? (string) $row['description'] : null,
                'indicaciones_cita' => isset($row['indicaciones_cita']) && $row['indicaciones_cita'] !== '' ? (string) $row['indicaciones_cita'] : null,
            ];
        }
        $empty['name'] = (string) $servicio_raw;
        return $empty;
    }

    $empty['name'] = (string) $servicio_raw;
    return $empty;
}

/**
 * benefit_notices cuando la agenda no puede autenticarse con el backend (sin OAuth / client secret).
 * Shape compatible con Node collectCrearReservaDirectaBenefitNotices + BenefitNotificationMapper.
 *
 * @return array<int, array<string, string>>
 */
function aa_confirm_backend_automation_unavailable_benefit_notices() {
    return [
        [
            'resource' => 'google_calendar_sync',
            'operation' => 'create_event',
            'status' => 'skipped',
            'code' => 'google_calendar_no_installation_id',
            'reason' => 'no_installation_id',
        ],
        [
            'resource' => 'email',
            'operation' => 'send_confirmed_email',
            'status' => 'skipped',
            'code' => 'no_installation_id',
            'reason' => 'no_installation_id',
        ],
    ];
}

/**
 * WP_Error de preflight local (configuración), no fallo de red/Node.
 *
 * @param mixed $response
 * @return bool
 */
function aa_confirm_backend_is_local_config_wp_error($response) {
    if (!is_wp_error($response)) {
        return false;
    }

    return (string) $response->get_error_code() === 'no_secret';
}

/**
 * Resultado de confirmación local OK con automatización externa no disponible por falta de vinculación.
 *
 * @param WP_Error $response
 * @return array<string, mixed>
 */
function aa_confirm_backend_build_local_config_failure_result($response) {
    return [
        'success' => true,
        'message' => 'Cita confirmada en WordPress, pero no se pudo notificar al backend: ' . $response->get_error_message(),
        'local_confirmed' => true,
        'calendar_sync' => false,
        'calendar_skipped' => true,
        'benefit_notices' => aa_confirm_backend_automation_unavailable_benefit_notices(),
        'email' => [
            'sent' => false,
            'skipped' => true,
            'code' => 'no_installation_id',
            'reason' => 'no_installation_id',
        ],
        'data' => [
            'calendar_sync' => false,
            'calendarSkipped' => true,
        ],
    ];
}

/**
 * Confirmar una cita enviando solicitud al backend
 * 
 * @param int $reserva_id ID de la reserva
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function confirm_backend_service_confirmar($reserva_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    
    // 1️⃣ Obtener datos de la reserva
    $reserva = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $reserva_id
    ));
    
    if (!$reserva) {
        return [
            'success' => false,
            'message' => 'Reserva no encontrada.'
        ];
    }

    require_once plugin_dir_path(__FILE__) . '../models/ReservationsModel.php';
    require_once plugin_dir_path(__FILE__) . '../models/AssignmentsModel.php';

    // Calcular rango de tiempo de la reserva a confirmar
    $start = $reserva->fecha;
    $duracion_minutos = isset($reserva->duracion) && !empty($reserva->duracion)
        ? intval($reserva->duracion)
        : 60; // fallback 60 min
    $end = date('Y-m-d H:i:s', strtotime($reserva->fecha) + ($duracion_minutos * 60));
    $assignment_id = isset($reserva->assignment_id) ? intval($reserva->assignment_id) : null;

    // Regla dura de confirmación:
    // impedir doble booking confirmed del mismo staff aunque sea otra assignment/zona.
    $staff_id = 0;

    if (!empty($assignment_id)) {
        $assignment = AssignmentsModel::get_assignment_by_id($assignment_id);
        $staff_id = isset($assignment['staff_id']) ? intval($assignment['staff_id']) : 0;

        if ($staff_id > 0 && ReservationsModel::has_confirmed_staff_overlap($start, $end, $staff_id, $reserva_id)) {
            error_log("❌ [ConfirmService] Conflicto real de staff detectado al confirmar reserva ID $reserva_id");
            return [
                'success' => false,
                'message' => 'El personal seleccionado ya tiene una cita confirmada en ese horario'
            ];
        }
    }
    
    // 🔹 PASO 1: Actualizar estado en WordPress PRIMERO (operación canónica)
    require_once plugin_dir_path(__FILE__) . '../application/appointments/ConfirmReservationUseCase.php';
    $confirm_result = (new ConfirmReservationUseCase())->execute([
        'reservation_id' => (int) $reserva_id,
    ]);

    if (empty($confirm_result['success'])) {
        error_log("❌ [ConfirmService] Error al actualizar estado en WordPress");
        return [
            'success' => false,
            'message' => 'Error al actualizar el estado en la base de datos.'
        ];
    }

    error_log("✅ [ConfirmService] Cita ID $reserva_id marcada como 'confirmed' en WordPress");

    // =========================================================================
    // 🛡️ LÓGICA DE CANCELACIÓN EN CASCADA (con overlap + staff real)
    // =========================================================================
    error_log("🔍 [ConfirmService] Buscando conflictos por overlap:");
    error_log("   Rango confirmado: $start → $end");
    error_log("   Assignment ID: " . ($assignment_id === null ? 'NULL (FIXED)' : $assignment_id));

    $conflictos = $staff_id > 0
        ? ReservationsModel::get_pending_conflicts_for_staff_overlap($start, $end, $staff_id, $reserva_id)
        : [];

    if (!empty($conflictos)) {
        error_log("⚔️ [ConfirmService] Se encontraron " . count($conflictos) . " citas pendientes en conflicto por overlap");
        
        foreach ($conflictos as $conflicto) {
            $cancelado = $wpdb->update(
                $table, 
                ['estado' => 'cancelled'], 
                ['id' => $conflicto->id]
            );
            
            if ($cancelado !== false) {
                error_log("🚫 [Auto-Cancel] Cita ID {$conflicto->id} ({$conflicto->nombre}) cancelada automáticamente por ocupación de slot.");

                require_once plugin_dir_path(__FILE__) . '../application/appointments/DeleteAppointmentConfirmationTaskUseCase.php';
                DeleteAppointmentConfirmationTaskUseCase::sync_after_local_cancellation_best_effort((int) $conflicto->id);
                
                // 🔔 Marcar notificación como leída
                $notifications_table = $wpdb->prefix . 'aa_notifications';
                $notification_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $notifications_table 
                    WHERE entity_type = %s AND entity_id = %d",
                    'reservation',
                    $conflicto->id
                ));
                
                if ($notification_id) {
                    $notification_updated = $wpdb->update(
                        $notifications_table,
                        ['is_read' => 1],
                        ['id' => $notification_id],
                        ['%d'],
                        ['%d']
                    );
                    
                    if ($notification_updated !== false) {
                        error_log("✅ [Auto-Cancel] Notificación ID $notification_id marcada como leída para cita cancelada ID {$conflicto->id}");
                    } else {
                        error_log("⚠️ [Auto-Cancel] Error al marcar notificación como leída: " . $wpdb->last_error);
                    }
                }
            }
        }
    }
    // =========================================================================

    // =========================================================================
    // 🔔 MARCAR NOTIFICACIÓN PENDING COMO LEÍDA (admin ya conoce la acción)
    // =========================================================================
    $notifications_table = $wpdb->prefix . 'aa_notifications';
    
    $pending_notification_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $notifications_table 
         WHERE entity_type = %s AND entity_id = %d AND type = %s AND is_read = 0",
        'reservation',
        $reserva_id,
        'pending'
    ));
    
    if ($pending_notification_id) {
        $notif_updated = $wpdb->update(
            $notifications_table,
            ['is_read' => 1],
            ['id' => $pending_notification_id],
            ['%d'],
            ['%d']
        );
        
        if ($notif_updated !== false) {
            error_log("✅ [ConfirmService] Notificación pending ID $pending_notification_id marcada como leída para reserva confirmada ID $reserva_id");
        } else {
            error_log("⚠️ [ConfirmService] Error al marcar notificación pending como leída: " . $wpdb->last_error);
        }
    }
    // =========================================================================

    // 2️⃣ Obtener configuración
    $business_name = get_option('aa_business_name', get_bloginfo('name'));
    $business_address = get_option('aa_business_address', 'No especificada');
    
    // 🔹 Obtener duración de la reserva guardada o usar configuración como fallback
    $duracion = isset($reserva->duracion) && !empty($reserva->duracion) 
        ? intval($reserva->duracion) 
        : intval(get_option('aa_slot_duration', 60));
    // Validar que sea 30, 60 o 90
    if (!in_array($duracion, [30, 60, 90])) {
        $duracion = 60;
    }
    
    // 3️⃣ Obtener dominio limpio usando la función centralizada
    $domain = aa_get_clean_domain();
    
    // 4️⃣ Determinar URL del backend
    $backend_url = AA_API_BASE_URL . '/calendar/crear-reserva-directa';
    
    // 5️⃣ Formatear fecha según el backend espera (ISO 8601 con timezone)
    $timezone = get_option('aa_timezone', 'America/Mexico_City');
    
    try {
        $fecha_obj = new DateTime($reserva->fecha, new DateTimeZone($timezone));
        $fecha_iso = $fecha_obj->format('c'); // Formato: 2025-11-17T09:30:00-06:00
    } catch (Exception $e) {
        error_log("❌ [ConfirmService] Error al formatear fecha: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al formatear la fecha de la reserva.'
        ];
    }
    
    // 6️⃣ Construir payload para el backend (servicio nombre + description + indicaciones_cita)
    $servicio_full = aa_transform_servicio_for_backend_full($reserva->servicio ?? '');
    $whatsapp = get_option('aa_whatsapp_number', '');

    // virtual_link desde la reserva
    $virtual_link = $reserva->virtual_link ?? null;

    // attendance_type y virtual_channel desde aa_services si servicio es ID numérico
    $attendance_type = null;
    $virtual_channel = null;
    $servicio_raw = $reserva->servicio ?? '';
    if (is_numeric($servicio_raw)) {
        $services_table = $wpdb->prefix . 'aa_services';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT attendance_type, virtual_channel FROM $services_table WHERE id = %d",
            intval($servicio_raw)
        ), ARRAY_A);
        if ($row) {
            $attendance_type = isset($row['attendance_type']) && $row['attendance_type'] !== '' ? $row['attendance_type'] : null;
            $virtual_channel = isset($row['virtual_channel']) && $row['virtual_channel'] !== '' ? $row['virtual_channel'] : null;
        }
    }

    $join_url = null;
    $join_token = $reserva->join_token ?? null;
    if ($attendance_type === 'virtual' && !empty($join_token)) {
        $join_url = home_url('/citas-virtuales/?token=' . rawurlencode($join_token));
    }

    $payload = [
        'domain' => $domain,
        'nombre' => $reserva->nombre,
        'servicio' => $servicio_full['name'],
        'servicio_description' => $servicio_full['description'],
        'servicio_indicaciones_cita' => $servicio_full['indicaciones_cita'],
        'fecha' => $fecha_iso,
        'telefono' => $reserva->telefono,
        'email' => $reserva->correo,
        'slot_duration' => $duracion,
        'businessName' => $business_name,
        'businessAddress' => $business_address,
        'whatsapp' => $whatsapp,
        'id_reserva' => $reserva_id,
        'attendance_type' => $attendance_type,
        'virtual_channel' => $virtual_channel,
        'virtual_link' => $virtual_link,
        'join_url' => $join_url,
    ];
    
    error_log("📤 [ConfirmService] Enviando confirmación al backend:");
    error_log("   URL: $backend_url");
    error_log("   Payload: " . json_encode($payload, JSON_PRETTY_PRINT));
    
    // 7️⃣ Enviar petición autenticada con HMAC
    $response = aa_send_authenticated_request($backend_url, 'POST', $payload);
    
    if (is_wp_error($response)) {
        error_log("⚠️ [ConfirmService] Error al contactar backend: " . $response->get_error_message());
        if (aa_confirm_backend_is_local_config_wp_error($response)) {
            return aa_confirm_backend_build_local_config_failure_result($response);
        }

        return [
            'success' => true,
            'message' => 'Cita confirmada en WordPress, pero no se pudo notificar al backend: ' . $response->get_error_message(),
            'local_confirmed' => true,
            'calendar_sync' => false,
        ];
    }

    // 8️⃣ Procesar respuesta
    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $decoded = null;
    }

    error_log("📥 [ConfirmService] Respuesta del backend (HTTP $status):");
    error_log("   " . print_r($decoded, true));

    if ($status >= 200 && $status < 300 && is_array($decoded) && !empty($decoded['success'])) {
        $backend_response = $decoded;

        $data_node = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];

        $calendar_uid = isset($data_node['event_id']) ? $data_node['event_id'] : null;
        $event_link = isset($data_node['event_link']) ? $data_node['event_link'] : null;
        $calendar_skipped = !empty($data_node['calendarSkipped']);
        $calendar_quota_code = isset($data_node['calendarQuotaCode']) && $data_node['calendarQuotaCode'] !== ''
            ? (string) $data_node['calendarQuotaCode']
            : null;
        $existed = !empty($data_node['existed']);

        $email = isset($decoded['email']) && is_array($decoded['email']) ? $decoded['email'] : null;

        $benefit_notices = null;
        if (isset($decoded['benefit_notices']) && is_array($decoded['benefit_notices']) && count($decoded['benefit_notices']) > 0) {
            $benefit_notices = $decoded['benefit_notices'];
        }

        if ($calendar_uid) {
            $wpdb->update($table, ['calendar_uid' => $calendar_uid], ['id' => $reserva_id]);
            error_log("✅ [ConfirmService] calendar_uid actualizado: $calendar_uid");
        }

        if ($calendar_skipped) {
            $message = 'Cita confirmada. Se notificó al cliente por correo (Calendar omitido).';
        } elseif ($existed) {
            $message = 'Cita confirmada. El evento ya existía en Google Calendar.';
        } else {
            $message = 'Cita confirmada y agregada a Google Calendar.';
        }

        $legacy_data = [
            'event_id' => $calendar_uid,
            'event_link' => $event_link,
            'existed' => $existed,
            'calendar_sync' => !$calendar_skipped,
            'calendarSkipped' => $calendar_skipped,
        ];

        if ($calendar_quota_code !== null) {
            $legacy_data['calendarQuotaCode'] = $calendar_quota_code;
        }

        $result = [
            'success' => true,
            'message' => $message,
            'local_confirmed' => true,
            'calendar_uid' => $calendar_uid,
            'calendar_skipped' => $calendar_skipped,
            'data' => $legacy_data,
            'backend_response' => $backend_response,
        ];

        if ($calendar_quota_code !== null) {
            $result['calendar_quota_code'] = $calendar_quota_code;
        }

        if ($email !== null) {
            $result['email'] = $email;
        }

        if ($benefit_notices !== null) {
            $result['benefit_notices'] = $benefit_notices;
        }

        return $result;
    }

    $error_message = is_array($decoded) && isset($decoded['message'])
        ? $decoded['message']
        : 'Error desconocido del backend.';

    error_log("⚠️ [ConfirmService] Backend respondió con error: $error_message");

    return [
        'success' => true,
        'message' => "Cita confirmada en WordPress, pero no se pudo notificar al backend: $error_message",
        'local_confirmed' => true,
        'calendar_sync' => false,
    ];
}

/**
 * Payload JSON para wp_send_json_success tras confirmación admin (crear-reserva-directa).
 *
 * @param array $result Retorno de confirm_backend_service_confirmar().
 * @return array<string, mixed>
 */
function aa_build_confirm_cita_ajax_success_payload($result) {
    $payload = [
        'success' => true,
        'message' => isset($result['message']) ? $result['message'] : 'Cita confirmada correctamente.',
        'local_confirmed' => true,
    ];

    if (array_key_exists('calendar_uid', $result)) {
        $payload['calendar_uid'] = $result['calendar_uid'];
    }

    if (array_key_exists('calendar_skipped', $result)) {
        $payload['calendar_skipped'] = (bool) $result['calendar_skipped'];
    }

    if (!empty($result['calendar_quota_code'])) {
        $payload['calendar_quota_code'] = $result['calendar_quota_code'];
    }

    if (!empty($result['email']) && is_array($result['email'])) {
        $payload['email'] = $result['email'];
    }

    if (!empty($result['benefit_notices']) && is_array($result['benefit_notices'])) {
        $payload['benefit_notices'] = $result['benefit_notices'];
    }

    if (!empty($result['backend_response']) && is_array($result['backend_response'])) {
        $payload['backend_response'] = $result['backend_response'];
    }

    if (isset($result['data']) && is_array($result['data'])) {
        $payload['data'] = $result['data'];
    }

    if (array_key_exists('calendar_sync', $result)) {
        $payload['calendar_sync'] = (bool) $result['calendar_sync'];
    }

    return $payload;
}

/**
 * Fusiona campos de la respuesta Node en el retorno del servicio (sin inventar benefit_notices).
 *
 * @param array<string, mixed> $base
 * @param array<string, mixed>|null $decoded
 * @return array<string, mixed>
 */
function aa_enviar_correo_apply_decoded_fields(array $base, $decoded) {
    if (!is_array($decoded)) {
        return $base;
    }

    $base['backend_response'] = $decoded;

    if (isset($decoded['code']) && $decoded['code'] !== '') {
        $base['code'] = (string) $decoded['code'];
    }

    if (isset($decoded['error']) && $decoded['error'] !== '') {
        $base['error'] = is_string($decoded['error']) ? $decoded['error'] : (string) $decoded['error'];
    }

    if (array_key_exists('skipped', $decoded)) {
        $base['skipped'] = (bool) $decoded['skipped'];
    }

    if (isset($decoded['reason']) && $decoded['reason'] !== '') {
        $base['reason'] = (string) $decoded['reason'];
    }

    if (isset($decoded['sent']) && is_array($decoded['sent'])) {
        $base['sent'] = $decoded['sent'];
    }

    if (isset($decoded['benefit_notices']) && is_array($decoded['benefit_notices']) && count($decoded['benefit_notices']) > 0) {
        $base['benefit_notices'] = $decoded['benefit_notices'];
    }

    return $base;
}

/**
 * Códigos de negocio que no deben etiquetarse como fallo técnico de conexión al backend.
 *
 * @param string $code
 * @return bool
 */
function aa_enviar_correo_is_excluded_business_code($code) {
    if ($code === '') {
        return false;
    }
    $excluded = [
        'email_quota_exceeded',
        'quota_exceeded',
        'quota_service_unavailable',
        'google_calendar_quota_service_unavailable',
        'email_not_provided',
        'duplicate_reminder',
        'no_billable_recipients',
        'backend_disabled',
        'google_calendar_backend_disabled',
        'no_installation_id',
        'google_calendar_no_installation_id',
    ];
    return in_array(strtolower($code), $excluded, true);
}

/**
 * @param array<string, mixed> $result
 * @return bool
 */
function aa_enviar_correo_should_mark_backend_connection_failed(array $result) {
    if (!empty($result['benefit_notices']) && is_array($result['benefit_notices']) && count($result['benefit_notices']) > 0) {
        return false;
    }
    if (!empty($result['skipped'])) {
        return false;
    }
    if (isset($result['reason']) && strtolower((string) $result['reason']) === 'email_not_provided') {
        return false;
    }
    $code = isset($result['code']) ? strtolower((string) $result['code']) : '';
    if ($code === 'backend_connection_failed') {
        return false;
    }
    if (aa_enviar_correo_is_excluded_business_code($code)) {
        return false;
    }
    return true;
}

/**
 * @param array<string, mixed> $result
 * @param string $reason node_unreachable|backend_http_error|backend_invalid_response
 * @return array<string, mixed>
 */
function aa_enviar_correo_apply_backend_connection_failed_if_needed(array $result, $reason) {
    if (!aa_enviar_correo_should_mark_backend_connection_failed($result)) {
        return $result;
    }
    $result['code'] = 'backend_connection_failed';
    $result['reason'] = $reason;
    return $result;
}

/**
 * Payload JSON para wp_send_json_success / wp_send_json_error tras aa_enviar_confirmacion.
 *
 * @param array<string, mixed> $result Retorno de confirm_backend_service_enviar_correo().
 * @return array<string, mixed>
 */
function aa_build_enviar_confirmacion_ajax_payload($result) {
    $payload = [
        'success' => !empty($result['success']),
        'message' => isset($result['message']) ? $result['message'] : '',
    ];

    if (isset($result['code']) && $result['code'] !== '') {
        $payload['code'] = (string) $result['code'];
    }

    if (isset($result['error']) && $result['error'] !== '') {
        $payload['error'] = is_string($result['error']) ? $result['error'] : (string) $result['error'];
    }

    if (array_key_exists('skipped', $result)) {
        $payload['skipped'] = (bool) $result['skipped'];
    }

    if (isset($result['reason']) && $result['reason'] !== '') {
        $payload['reason'] = (string) $result['reason'];
    }

    if (isset($result['sent']) && is_array($result['sent'])) {
        $payload['sent'] = $result['sent'];
    }

    if (!empty($result['benefit_notices']) && is_array($result['benefit_notices'])) {
        $payload['benefit_notices'] = $result['benefit_notices'];
    }

    if (!empty($result['backend_response']) && is_array($result['backend_response'])) {
        $payload['backend_response'] = $result['backend_response'];
    }

    return $payload;
}

/**
 * Enviar correo de confirmación al backend
 * 
 * @param array $datos Datos de la reserva desde AJAX
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function confirm_backend_service_enviar_correo($datos) {
    // 🔹 Si no hay correo del cliente, omitir envío de email
    $correo = $datos['correo'] ?? '';
    if (empty($correo)) {
        error_log("ℹ️ [EmailService] Correo vacío → envío de confirmación por email omitido");
        return [
            'success' => true,
            'message' => 'Correo no disponible, envío de email omitido.',
            'skipped' => true,
            'reason' => 'email_not_provided',
        ];
    }

    // 🔹 Usar la función centralizada para obtener el domain
    $domain = aa_get_clean_domain();

    error_log("🧩 [EmailService] Dominio detectado: $domain");

    // 🔹 Obtener duración de los datos de la reserva o usar configuración como fallback
    $duracion = isset($datos['duracion']) ? intval($datos['duracion']) : intval(get_option('aa_slot_duration', 60));
    // Validar que sea 30, 60 o 90
    if (!in_array($duracion, [30, 60, 90])) {
        $duracion = 60;
    }

    // 🔄 Transformar servicio con metadatos (nombre, description, indicaciones_cita)
    $servicio_raw = $datos['servicio'] ?? '';
    $servicio_full = aa_transform_servicio_for_backend_full($servicio_raw);

    // 🔹 attendance_type, virtual_channel, virtual_link (desde reserva y servicio)
    $attendance_type = null;
    $virtual_channel = null;
    $virtual_link = null;
    $join_token = null;
    $reserva_id = isset($datos['id_reserva']) ? intval($datos['id_reserva']) : 0;
    if ($reserva_id > 0) {
        global $wpdb;
        $reservas_table = $wpdb->prefix . 'aa_reservas';
        $reserva_row = $wpdb->get_row($wpdb->prepare(
            "SELECT servicio, virtual_link, join_token FROM $reservas_table WHERE id = %d",
            $reserva_id
        ), ARRAY_A);
        if ($reserva_row) {
            $virtual_link = isset($reserva_row['virtual_link']) && $reserva_row['virtual_link'] !== '' ? $reserva_row['virtual_link'] : null;
            $join_token = isset($reserva_row['join_token']) && $reserva_row['join_token'] !== '' ? $reserva_row['join_token'] : null;
            $servicio_from_reserva = $reserva_row['servicio'] ?? '';
            if (is_numeric($servicio_from_reserva)) {
                $services_table = $wpdb->prefix . 'aa_services';
                $service_row = $wpdb->get_row($wpdb->prepare(
                    "SELECT attendance_type, virtual_channel FROM $services_table WHERE id = %d",
                    intval($servicio_from_reserva)
                ), ARRAY_A);
                if ($service_row) {
                    $attendance_type = isset($service_row['attendance_type']) && $service_row['attendance_type'] !== '' ? $service_row['attendance_type'] : null;
                    $virtual_channel = isset($service_row['virtual_channel']) && $service_row['virtual_channel'] !== '' ? $service_row['virtual_channel'] : null;
                }
            }
        }
    }

    $join_url = null;
    if ($attendance_type === 'virtual' && !empty($join_token)) {
        $join_url = home_url('/citas-virtuales/?token=' . rawurlencode($join_token));
    }

    // 🔹 Reorganizar datos para enviar al backend
    $backend_data = [
        'domain' => $domain,
        'nombre' => $datos['nombre'] ?? '',
        'servicio' => $servicio_full['name'],
        'servicio_description' => $servicio_full['description'],
        'servicio_indicaciones_cita' => $servicio_full['indicaciones_cita'],
        'fecha' => $datos['fecha'] ?? '',
        'telefono' => $datos['telefono'] ?? '',
        'email' => $datos['correo'] ?? '',
        'id_reserva' => $datos['id_reserva'] ?? null,
        'businessName' => get_option('aa_business_name', 'Nuestro negocio'),
        'businessAddress' => get_option('aa_business_address', 'No especificada'),
        'whatsapp' => get_option('aa_whatsapp_number', ''),
        'slot_duration' => $duracion, // Mantener compatibilidad con backend
        'source' => $datos['source'] ?? 'frontend',
        'attendance_type' => $attendance_type,
        'virtual_channel' => $virtual_channel,
        'virtual_link' => $virtual_link,
        'join_url' => $join_url,
    ];

    // 🔹 Determinar URL del backend según entorno
    $site_url = get_site_url();
    $backend_url = AA_API_BASE_URL . '/correos/confirmacion';

    // 🔹 Enviar petición autenticada con HMAC
    $response = aa_send_authenticated_request($backend_url, 'POST', $backend_data);

    if (is_wp_error($response)) {
        error_log("❌ [EmailService] Error al contactar backend: " . $response->get_error_message());
        return [
            'success' => false,
            'message' => 'Error de conexión con el backend',
            'code' => 'backend_connection_failed',
            'reason' => 'node_unreachable',
            'error' => $response->get_error_message(),
        ];
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $decoded = null;
    }

    if ($status >= 200 && $status < 300 && is_array($decoded) && !empty($decoded['success'])) {
        $result = [
            'success' => true,
            'message' => 'Correos enviados correctamente',
        ];
        return aa_enviar_correo_apply_decoded_fields($result, $decoded);
    }

    $result = [
        'success' => false,
        'message' => 'El backend respondió con error',
    ];
    $result = aa_enviar_correo_apply_decoded_fields($result, $decoded);
    $connection_reason = is_array($decoded) ? 'backend_http_error' : 'backend_invalid_response';

    return aa_enviar_correo_apply_backend_connection_failed_if_needed($result, $connection_reason);
}