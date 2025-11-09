<?php
defined('ABSPATH') or die('¡Sin acceso directo!');

// Endpoint AJAX para enviar correos de confirmación
add_action('wp_ajax_nopriv_aa_enviar_confirmacion', 'aa_enviar_confirmacion');
add_action('wp_ajax_aa_enviar_confirmacion', 'aa_enviar_confirmacion');

function aa_enviar_confirmacion() {
    error_log("🔥 AJAX aa_enviar_confirmacion activado");
    
    // 🔹 Decodificar JSON del body
    $raw_input = file_get_contents('php://input');
    $datos = json_decode($raw_input, true);

    if (!$datos) {
        wp_send_json_error(['message' => 'JSON inválido o vacío']);
        return;
    }
    
    error_log("📤 JSON COMPLETO QUE SE ENVÍA AL BACKEND:");
    error_log(json_encode($datos, JSON_PRETTY_PRINT));

    // 🔹 Extraer dominio limpio
    $site_url = get_site_url();
    $parsed_url = parse_url($site_url);
    $host = $parsed_url['host'] ?? 'localhost';
    $domain = preg_replace('/^www\./', '', $host);

    error_log("🧩 Dominio detectado: $domain");

    // 🔹 Reorganizar datos para enviar al backend
    $backend_data = [
        'domain' => $domain,
        'nombre' => $datos['nombre'] ?? '',
        'servicio' => $datos['servicio'] ?? '',
        'fecha' => $datos['fecha'] ?? '',
        'telefono' => $datos['telefono'] ?? '',
        'email' => $datos['correo'] ?? '',
        'id_reserva' => $datos['id_reserva'] ?? null,
        'businessName' => get_option('aa_business_name', 'Nuestro negocio'),
        'businessAddress' => get_option('aa_business_address', 'No especificada'),
        'whatsapp' => get_option('aa_whatsapp_number', ''),
        'slot_duration' => intval(get_option('aa_slot_duration', 60)), // 🔹 Duración de la cita en minutos
    ];

    error_log("📦 Datos reorganizados para backend:");
    error_log(print_r($backend_data, true));

    // 🔹 Determinar URL del backend según entorno
    $backend_url = (strpos($site_url, 'localhost') !== false)
        ? 'http://localhost:3000/correos/confirmacion'
        : 'https://deoia-oauth-backend.onrender.com/correos/confirmacion';

    // 🔹 Enviar petición autenticada con HMAC
    $response = aa_send_authenticated_request($backend_url, 'POST', $backend_data);

    if (is_wp_error($response)) {
        error_log("❌ Error al contactar backend: " . $response->get_error_message());
        wp_send_json_error(['message' => 'Error de conexión con el backend', 'error' => $response->get_error_message()]);
        return;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);

    error_log("📥 Respuesta del backend (status $status): " . print_r($decoded, true));

    if ($status >= 200 && $status < 300 && isset($decoded['success']) && $decoded['success']) {
        wp_send_json_success(['message' => 'Correos enviados correctamente', 'backend_response' => $decoded]);
    } else {
        wp_send_json_error(['message' => 'El backend respondió con error', 'backend_response' => $decoded]);
    }
}

// ✅ Endpoint para recibir confirmación desde el backend Node y actualizar la reserva en la tabla
add_action('rest_api_init', function () {
    register_rest_route('aa/v1', '/confirmar-reserva', [
        'methods' => 'POST',
        'callback' => 'aa_confirmar_reserva',
        'permission_callback' => '__return_true',
    ]);
});

function aa_confirmar_reserva(WP_REST_Request $request) {
    global $wpdb;
    $table = $wpdb->prefix . 'aa_reservas';
    $id = intval($request['id_reserva']);

    if (!$id) {
        return new WP_REST_Response(['error' => 'id_reserva faltante'], 400);
    }

    // 🔹 Preparar datos a actualizar
    $update_data = ['estado' => 'confirmed'];
    $update_format = ['%s'];
    
    // 🔹 Si viene calendar_uid, también lo guardamos
    $calendar_uid = sanitize_text_field($request['calendar_uid']);
    if (!empty($calendar_uid)) {
        $update_data['calendar_uid'] = $calendar_uid;
        $update_format[] = '%s';
        error_log("✅ calendar_uid recibido para reserva ID $id: $calendar_uid");
    }

    // 🔹 Actualizar registro
    $updated = $wpdb->update(
        $table,
        $update_data,
        ['id' => $id],
        $update_format,
        ['%d']
    );
    
    if ($updated === false) {
        error_log("❌ Error al actualizar reserva ID $id: " . $wpdb->last_error);
        return new WP_REST_Response(['error' => 'Error al actualizar'], 500);
    }

    error_log("✅ Reserva ID $id actualizada: estado=confirmed" . (!empty($calendar_uid) ? ", calendar_uid=$calendar_uid" : ""));
    
    $response_data = [
        'success' => true,
        'id' => $id,
        'estado' => 'confirmed'
    ];
    
    if (!empty($calendar_uid)) {
        $response_data['calendar_uid'] = $calendar_uid;
    }

    return new WP_REST_Response($response_data, 200);
}
