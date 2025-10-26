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
    // 🔹 Obtener el dominio LIMPIO (sin http:// ni rutas)
    $site_url = get_site_url(); // Ej: http://localhost/wpagenda
    $parsed = parse_url($site_url);
    $host = $parsed['host'] ?? 'localhost'; // Solo 'localhost'
    
    // 🔹 Si tiene puerto, agregarlo
    $domain = $host;
    if (!empty($parsed['port']) && $parsed['port'] != 80 && $parsed['port'] != 443) {
        $domain .= ':' . $parsed['port'];
    }
    
    $client_secret = get_option('aa_client_secret');
    
    if (!$client_secret) {
        error_log("❌ aa_auth: No hay client_secret configurado");
        return new WP_Error('no_secret', 'Client secret no configurado. Conecta con Google primero.');
    }

    // 🔹 Generar timestamp y nonce
    $timestamp = round(microtime(true) * 1000); // epoch en milisegundos
    $nonce = wp_generate_uuid4(); // UUID único
    
    // 🔹 Preparar el body (vacío si es GET)
    // ✅ IMPORTANTE: JSON sin espacios para que coincida con el backend
    $body = ($method === 'POST' && !empty($data)) ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    
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
        'X-Client-Secret' => $client_secret,
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
    
    // 🔹 Log mejorado
    error_log("🔐 aa_auth: Enviando request autenticado");
    error_log("   Domain: $domain");
    error_log("   Client Secret (primeros 10 chars): " . substr($client_secret, 0, 10) . "...");
    error_log("   Method: $method");
    error_log("   Path: $path");
    error_log("   Body length: " . strlen($body));
    error_log("   Body (primeros 100 chars): " . substr($body, 0, 100) . "...");
    error_log("   Timestamp: $timestamp");
    error_log("   Message to sign: " . substr($message, 0, 200) . "...");
    error_log("   Signature: $signature");
    
    return wp_remote_request($endpoint, $args);
}