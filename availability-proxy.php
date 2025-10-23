<?php
if (!defined('ABSPATH')) exit;

function aa_build_availability_url_for($email) {
    $backend_url = AA_API_BASE_URL . "/calendar/availability";

    $now = new DateTime('now', new DateTimeZone('UTC'));
    // Rango reducido a 1 mes
    $future = new DateTime('+1 month', new DateTimeZone('UTC'));

    $params = [
        'domain'  => $email,
        'timeMin' => $now->format(DateTime::ATOM),
        'timeMax' => $future->format(DateTime::ATOM),
    ];

    return $backend_url . '?' . http_build_query($params);
}

// Endpoint AJAX (público y autenticado) que actúa como proxy al backend
add_action('wp_ajax_nopriv_aa_get_availability', 'aa_ajax_get_availability');
add_action('wp_ajax_aa_get_availability', 'aa_ajax_get_availability');

function aa_ajax_get_availability() {
    // Permitir pasar email por query, sino usar opción guardada
    $email = isset($_REQUEST['email']) ? sanitize_email(wp_unslash($_REQUEST['email'])) : sanitize_email(get_option('aa_google_email', ''));

    if (empty($email)) {
        wp_send_json_error(['message' => 'No email configured'], 400);
    }

    $backend_url = aa_build_availability_url_for($email);

    // 🔹 Usar autenticación HMAC
    $response = aa_send_authenticated_request($backend_url, 'GET');

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'request_failed', 'error' => $response->get_error_message()], 500);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    // 🔹 Manejar errores de autenticación
    if ($code === 401 || $code === 403) {
        $decoded = json_decode($body, true);
        error_log("🔒 Error de autenticación en availability: " . ($decoded['error'] ?? 'Sin detalles'));
        wp_send_json_error([
            'message' => 'authentication_failed',
            'error' => $decoded['error'] ?? 'Unauthorized'
        ], $code);
    }

    // Reenvía el cuerpo JSON tal cual
    status_header($code);
    header('Content-Type: application/json; charset=utf-8');
    echo $body;
    wp_die();
}



// Encolar el script que consulta disponibilidad y exponer URL del admin-ajax
add_action('wp_enqueue_scripts', function() {
    // Aseguramos que flatpickr esté disponible para el front si lo necesita
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);

    wp_enqueue_script(
        'horariosapartados',
        plugin_dir_url(__FILE__) . 'js/horariosapartados.js',
        ['flatpickr-js'],
        '1.0',
        true
    );

    $email = sanitize_email(get_option('aa_google_email', ''));

    wp_localize_script('horariosapartados', 'aa_backend', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'action'   => 'aa_get_availability',
        'email'    => $email
    ]);
});

