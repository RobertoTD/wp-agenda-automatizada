<?php
defined('ABSPATH') or die('¡Sin acceso directo!');

// Endpoint AJAX para enviar correos de confirmación
add_action('wp_ajax_nopriv_aa_enviar_confirmacion', 'aa_enviar_confirmacion');
add_action('wp_ajax_aa_enviar_confirmacion', 'aa_enviar_confirmacion');

function aa_enviar_confirmacion() {
    error_log("🔥 AJAX aa_enviar_confirmacion activado");

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        wp_send_json_error(['message' => 'No se recibió JSON válido.']);
    }

    // 🔹 Extraer dominio limpio
    $site_url = get_site_url();
    $parsed_url = parse_url($site_url);
    $host = $parsed_url['host'] ?? '';
    
    if (stripos($host, 'localhost') !== false || $host === '127.0.0.1') {
        $domain = 'localhost';
    } else {
        $domain = preg_replace('/^www\./', '', $host);
    }

    // 🔹 Reorganizar datos para el backend
    $backend_data = [
        'domain' => $domain,
        'nombre' => $data['nombre'] ?? '',
        'servicio' => $data['servicio'] ?? '',
        'fecha' => $data['fecha'] ?? '',
        'telefono' => $data['telefono'] ?? '',
        'email' => $data['correo'] ?? '',
        'businessName' => get_option('aa_business_name', 'Nuestro negocio'),
        'businessAddress' => get_option('aa_is_virtual', 0) == 1 
            ? 'Cita virtual' 
            : get_option('aa_business_address', 'No especificada'),
        'whatsapp' => get_option('aa_whatsapp_number', '')
    ];

    error_log("🧩 Dominio detectado: " . $domain);
    error_log("📦 Datos reorganizados para backend:");
    error_log(print_r($backend_data, true));

    // 🔹 Usar helper de autenticación
    $backend_url = AA_API_BASE_URL . "/correo/confirmacion";
    $response = aa_send_authenticated_request($backend_url, 'POST', $backend_data);

    // 🔹 Validar respuesta
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("❌ Error al contactar backend: " . $error_message);
        wp_send_json_error(['message' => 'Error al contactar el backend', 'error' => $error_message]);
    }

    $body = wp_remote_retrieve_body($response);
    $status_code = wp_remote_retrieve_response_code($response);
    $decoded = json_decode($body, true);
    
    error_log("📥 Respuesta del backend (status $status_code): " . print_r($decoded, true));

    // 🔹 Manejar errores de autenticación
    if ($status_code === 401 || $status_code === 403) {
        error_log("🔒 Error de autenticación: " . ($decoded['error'] ?? 'Sin detalles'));
        wp_send_json_error([
            'message' => 'Error de autenticación con el backend',
            'error' => $decoded['error'] ?? 'Unauthorized',
            'hint' => 'Verifica que el client_secret esté configurado correctamente'
        ]);
    }

    if (isset($decoded['success']) && $decoded['success'] === true) {
        wp_send_json_success(['message' => 'Correo de confirmación enviado', 'backend_response' => $decoded]);
    } else {
        wp_send_json_error(['message' => 'El backend respondió con error', 'backend_response' => $decoded]);
    }
}


