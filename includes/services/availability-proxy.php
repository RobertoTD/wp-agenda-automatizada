<?php
if (!defined('ABSPATH')) exit;

function aa_build_availability_url_for($email) {
    $backend_url = AA_API_BASE_URL . "/calendar/availability";

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $future = new DateTime('+1 month', new DateTimeZone('UTC'));

    // 🔹 Usar la función centralizada para obtener el domain
    $domain = aa_get_clean_domain();

    $params = [
        'domain'  => $domain,
        'email'   => $email,
        'timeMin' => $now->format(DateTime::ATOM),
        'timeMax' => $future->format(DateTime::ATOM),
    ];

    return $backend_url . '?' . http_build_query($params);
}

// Endpoint AJAX (público y autenticado) que actúa como proxy al backend
add_action('wp_ajax_nopriv_aa_get_availability', 'aa_ajax_get_availability');
add_action('wp_ajax_aa_get_availability', 'aa_ajax_get_availability');

function aa_ajax_get_availability() {
    $email = isset($_REQUEST['email']) ? sanitize_email(wp_unslash($_REQUEST['email'])) : sanitize_email(get_option('aa_google_email', ''));

    if (empty($email)) {
        error_log("❌ aa_availability: No hay email configurado");
        wp_send_json_error(['message' => 'No email configured'], 400);
    }

    $backend_url = aa_build_availability_url_for($email);
    
    error_log("📤 aa_availability: Consultando disponibilidad");
    error_log("   Email: $email");
    error_log("   URL: $backend_url");

    $response = aa_send_authenticated_request($backend_url, 'GET');

    if (is_wp_error($response)) {
        error_log("❌ aa_availability: Error WP - " . $response->get_error_message());
        wp_send_json_error(['message' => 'request_failed', 'error' => $response->get_error_message()], 500);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    error_log("📥 aa_availability: Respuesta recibida (status $code)");

    if ($code === 401 || $code === 403) {
        $decoded = json_decode($body, true);
        error_log("🔒 Error de autenticación en availability: " . ($decoded['error'] ?? 'Sin detalles'));
        wp_send_json_error([
            'message' => 'authentication_failed',
            'error' => $decoded['error'] ?? 'Unauthorized'
        ], $code);
    }

    if ($code >= 500) {
        $decoded = json_decode($body, true);
        error_log("🔥 Error 500 del backend: " . print_r($decoded, true));
        wp_send_json_error([
            'message' => 'backend_error',
            'error' => $decoded['error'] ?? 'Internal server error'
        ], $code);
    }

    status_header($code);
    header('Content-Type: application/json; charset=utf-8');
    echo $body;
    wp_die();
}