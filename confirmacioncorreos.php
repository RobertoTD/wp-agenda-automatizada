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

    // ==========================================================
    // 🔹 Extraer dominio limpio de la URL actual
    // ==========================================================
    $site_url = get_site_url(); // WordPress function para obtener URL completa
    $parsed_url = parse_url($site_url);
    $host = $parsed_url['host'] ?? '';
    
    // Si es localhost, usar literal
    if (stripos($host, 'localhost') !== false || $host === '127.0.0.1') {
        $domain = 'localhost';
    } else {
        // Para dominios reales, quitar 'www.' si existe
        $domain = preg_replace('/^www\./', '', $host);
    }

    // 🔹 Reorganizar los datos con la estructura correcta
    $backend_data = [
        'domain' => $domain,
        'nombre' => $data['nombre'] ?? '',
        'servicio' => $data['servicio'] ?? '',
        'fecha' => $data['fecha'] ?? '',
        'telefono' => $data['telefono'] ?? '',
        'email' => $data['correo'] ?? '',
        'businessName' => get_option('aa_business_name', 'Nuestro negocio'), // 🔹 Nombre del negocio
        'businessAddress' => get_option('aa_is_virtual', 0) == 1 
            ? 'Cita virtual' 
            : get_option('aa_business_address', 'No especificada'), // 🔹 Dirección o "virtual"
        'whatsapp' => get_option('aa_whatsapp_number', '') // 🔹 WhatsApp del negocio
    ];

    error_log("🧩 Dominio detectado: " . $domain);
    error_log("📦 Datos reorganizados para backend:");
    error_log(print_r($backend_data, true));

    // ==========================================================
    // 🔹 URL del backend
    // ==========================================================
    $backend_url = AA_API_BASE_URL . "/correo/confirmacion";

    error_log("📤 Enviando a backend: $backend_url");

    // ==========================================================
    // 🔹 Enviar al backend
    // ==========================================================
    $response = wp_remote_post($backend_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode($backend_data), // 🔹 Usar datos reorganizados
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("❌ Error al contactar backend: " . $error_message);
        wp_send_json_error(['message' => 'Error al contactar el backend', 'error' => $error_message]);
    }

    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);
    error_log("📥 Respuesta del backend: " . print_r($decoded, true));

    if (isset($decoded['success']) && $decoded['success'] === true) {
        wp_send_json_success(['message' => 'Correo de confirmación enviado', 'backend_response' => $decoded]);
    } else {
        wp_send_json_error(['message' => 'El backend respondió con error', 'backend_response' => $decoded]);
    }
}

error_log("🔥 AJAX de confirmación recibido en WordPress");
