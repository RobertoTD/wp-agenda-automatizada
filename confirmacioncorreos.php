<?php
defined('ABSPATH') or die('¡Sin acceso directo!');

// Endpoint AJAX para enviar correos de confirmación
add_action('wp_ajax_nopriv_aa_enviar_confirmacion', 'aa_enviar_confirmacion');
add_action('wp_ajax_aa_enviar_confirmacion', 'aa_enviar_confirmacion');

function aa_enviar_confirmacion() {
    error_log("🔥 AJAX aa_enviar_confirmacion activado");

    $data = json_decode(file_get_contents('php://input'), true);
    error_log(print_r($data, true)); // 👈 esto te muestra el formato exacto recibido

    if (!$data) {
        wp_send_json_error(['message' => 'No se recibió JSON válido.']);
    }

    wp_send_json_success(['data_recibida' => $data]);
}

error_log("🔥 AJAX de confirmación recibido en WordPress");
