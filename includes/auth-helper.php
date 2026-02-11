<?php
if (!defined('ABSPATH')) exit;

/**
 * Obtiene el dominio limpio para identificar al cliente en el backend.
 * Para localhost, incluye el primer nivel del path para diferenciar instalaciones.
 * 
 * Ejemplos:
 *   http://localhost/wpagenda -> localhost/wpagenda
 *   http://localhost/agendatheme -> localhost/agendatheme
 *   https://www.example.com/ -> example.com
 *   https://sitio.deoia.com -> sitio.deoia.com
 * 
 * @return string Dominio limpio
 */
function aa_get_clean_domain() {
    $site_url = get_site_url();
    $parsed = parse_url($site_url);
    
    $host = $parsed['host'] ?? 'localhost';
    
    // Si tiene puerto no estándar, agregarlo
    if (!empty($parsed['port']) && $parsed['port'] != 80 && $parsed['port'] != 443) {
        $host .= ':' . $parsed['port'];
    }
    
    // Eliminar www.
    $host = preg_replace('/^www\./', '', $host);
    
    // 🔹 Si es localhost, incluir el primer nivel del path para diferenciar instalaciones
    if ($host === 'localhost' || strpos($host, 'localhost:') === 0 || $host === '127.0.0.1') {
        $path = trim($parsed['path'] ?? '', '/');
        $first_path = explode('/', $path)[0] ?? '';
        
        if (!empty($first_path)) {
            return $host . '/' . $first_path;
        }
    }
    
    return $host;
}

/**
 * Envía una petición autenticada con HMAC al backend
 * 
 * @param string $endpoint URL completa del endpoint (ej: 'http://localhost:3000/correo/confirmacion')
 * @param string $method Método HTTP ('GET' o 'POST')
 * @param array $data Datos a enviar (opcional para GET, requerido para POST)
 * @return array|WP_Error Respuesta del backend o error
 */
function aa_send_authenticated_request($endpoint, $method = 'POST', $data = []) {
    // 🔹 Usar la función centralizada para obtener el domain
    $domain = aa_get_clean_domain();
    
    $client_secret = get_option('aa_client_secret');
    
    if (!$client_secret) {
        error_log("❌ aa_auth: No hay client_secret configurado");
        return new WP_Error('no_secret', 'Client secret no configurado. No se puede autenticar con el backend.');
    }

    // 🔹 Generar timestamp y nonce
    $timestamp = round(microtime(true) * 1000); // epoch en milisegundos
    $nonce = wp_generate_uuid4(); // UUID único
    
    // 🔹 Preparar el body (vacío si es GET)
    // ✅ IMPORTANTE: JSON sin espacios para que coincida con el backend
    $body = '';
    if ($method === 'POST' && !empty($data)) {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // Eliminar espacios después de : y ,
        $body = preg_replace('/:\s+/', ':', $json);
        $body = preg_replace('/,\s+/', ',', $body);
    }
    
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
    error_log("   Body compacto: " . $body);
    error_log("   Timestamp: $timestamp");
    error_log("   Message to sign: " . substr($message, 0, 200) . "...");
    error_log("   Signature: $signature");
    
    return wp_remote_request($endpoint, $args);
}