<?php
if (!defined('ABSPATH')) exit;

/**
 * Envía una petición autenticada con HMAC al backend
 * 
 * @param string $endpoint URL completa del endpoint (ej: 'http://localhost:3000/correo/confirmacion')
 * @param string $method Método HTTP ('GET' o 'POST')
 * @param array $data Datos a enviar (opcional para GET, requerido para POST)
 * @return array|WP_Error Respuesta del backend o error
 */
function aa_send_authenticated_request($endpoint, $method = 'POST', $data = []) {
    // 🔹 Obtener el dominio y el client_secret
    $domain = parse_url(home_url(), PHP_URL_HOST);
    $client_secret = get_option('aa_client_secret');
    
    if (!$client_secret) {
        return new WP_Error('no_secret', 'Client secret no configurado. Conecta con Google primero.');
    }

    // 🔹 Generar timestamp y nonce
    $timestamp = round(microtime(true) * 1000); // epoch en milisegundos
    $nonce = wp_generate_uuid4(); // UUID único
    
    // 🔹 Preparar el body (vacío si es GET)
    $body = ($method === 'POST' && !empty($data)) ? json_encode($data) : '';
    
    // 🔹 Extraer path + query string
    $parsed = parse_url($endpoint);
    $path = $parsed['path'] ?? '/';
    if (!empty($parsed['query'])) {
        $path .= '?' . $parsed['query'];
    }
    
    // 🔹 Construir mensaje a firmar: METHOD + PATH + BODY + TIMESTAMP + NONCE
    $message = $method . $path . $body . $timestamp . $nonce;
    
    // 🔹 Calcular firma HMAC-SHA256
    $signature = hash_hmac('sha256', $message, $client_secret);
    
    // 🔹 Headers de autenticación
    $headers = [
        'Content-Type' => 'application/json',
        'X-Client-Id' => $domain,
        'X-Timestamp' => (string)$timestamp,
        'X-Nonce' => $nonce,
        'X-Signature' => $signature,
        'X-Client-Secret' => $client_secret, // Necesario para que el backend lo busque en DB
    ];
    
    // 🔹 Configurar argumentos para wp_remote_request
    $args = [
        'headers' => $headers,
        'method' => $method,
        'timeout' => 30,
    ];
    
    if ($method === 'POST' && $body) {
        $args['body'] = $body;
    }
    
    // 🔹 Log para debug (comentar en producción)
    error_log("🔐 aa_auth: Enviando request autenticado");
    error_log("   Domain: $domain");
    error_log("   Method: $method");
    error_log("   Path: $path");
    error_log("   Timestamp: $timestamp");
    error_log("   Signature: $signature");
    
    return wp_remote_request($endpoint, $args);
}