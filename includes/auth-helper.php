<?php
if (!defined('ABSPATH')) exit;

/**
 * Shim de compatibilidad. La regla canónica vive en
 * `includes/domain/tenant/class-aa-tenant-domain.php` y debe coincidir
 * con `utils/tenantDomain.js` del backend.
 *
 * Devuelve el identificador canónico del tenant que viaja como
 * `X-Client-Id` y se busca en `agenda_clients.domain`.
 *
 * Ejemplos:
 *   http://localhost/deoia-platform/agenda-roby/ -> localhost/deoia-platform/agenda-roby
 *   http://localhost/wpagenda/                   -> localhost/wpagenda
 *   https://www.example.com/                     -> example.com
 *   https://sitio.deoia.com/                     -> sitio.deoia.com
 *   https://cliente.com/agenda/                  -> cliente.com/agenda
 *
 * @return string Dominio canónico
 */
function aa_get_clean_domain() {
    if (!class_exists('AA_Tenant_Domain')) {
        $candidate = dirname(__FILE__) . '/domain/tenant/class-aa-tenant-domain.php';
        if (is_readable($candidate)) {
            require_once $candidate;
        }
    }

    if (class_exists('AA_Tenant_Domain')) {
        return AA_Tenant_Domain::canonical(get_site_url());
    }

    // Fallback defensivo si el archivo de dominio no estuviera disponible
    // (no debería ocurrir en runtime normal): replica la regla canónica
    // mínima sin romper instalaciones existentes.
    $parsed = parse_url((string) get_site_url());
    $host = isset($parsed['host']) ? strtolower((string) $parsed['host']) : 'localhost';

    if (!empty($parsed['port']) && (int) $parsed['port'] !== 80 && (int) $parsed['port'] !== 443) {
        $host .= ':' . (int) $parsed['port'];
    }

    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }

    $path = isset($parsed['path']) ? rtrim((string) $parsed['path'], '/') : '';
    if ($path !== '' && $path !== '/') {
        return $host . $path;
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
    
    $headers = [
        'Content-Type' => 'application/json',
        'X-Client-Id' => $domain,
        'X-Timestamp' => (string)$timestamp,
        'X-Nonce' => $nonce,
        'X-Signature' => $signature,
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

    return wp_remote_request($endpoint, $args);
}