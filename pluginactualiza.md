# Autenticación del Plugin WordPress

Este documento explica cómo el plugin de WordPress debe autenticarse con el backend para realizar peticiones seguras.

## 1. Flujo inicial: Obtener el `client_secret`

### Cuando el usuario conecta por primera vez con Google Calendar:

1. El plugin inicia el flujo OAuth llamando a:
   ```
   GET /oauth/authorize?state=DOMAIN&redirect_uri=WP_CALLBACK_URL
   ```

2. Después del callback exitoso, el backend redirige de vuelta al plugin con:
   ```
   ?success=true&email=EMAIL&client_secret=SECRET_GENERADO
   ```

3. **El plugin DEBE guardar el `client_secret` de forma segura:**
   ```php
   update_option('aa_client_secret', $client_secret, false); // false = no autoload
   ```

⚠️ **IMPORTANTE:** El `client_secret` solo se devuelve la primera vez. Guárdalo de inmediato.

---

## 2. Autenticación en cada petición

Para cada request al backend (por ejemplo, consultar disponibilidad o enviar correos), el plugin debe:

### 2.1. Preparar los headers requeridos

```php
$domain = parse_url(home_url(), PHP_URL_HOST); // ej: salonanahi.com
$timestamp = round(microtime(true) * 1000); // epoch en milisegundos
$nonce = wp_generate_uuid4(); // UUID único
$client_secret = get_option('aa_client_secret');

// Construir el mensaje a firmar
$method = 'POST'; // o 'GET' según corresponda
$path = '/correo/confirmacion'; // ruta completa con query string si aplica
$body = json_encode($data); // body del POST (vacío si es GET)
$message = $method . $path . $body . $timestamp . $nonce;

// Calcular la firma HMAC-SHA256
$signature = hash_hmac('sha256', $message, $client_secret);
```

### 2.2. Enviar los headers en el request

```php
$headers = [
    'Content-Type' => 'application/json',
    'X-Client-Id' => $domain,
    'X-Timestamp' => (string)$timestamp,
    'X-Nonce' => $nonce,
    'X-Signature' => $signature,
    'X-Client-Secret' => $client_secret, // Necesario para verificación
];

$response = wp_remote_post('http://localhost:3000/correo/confirmacion', [
    'headers' => $headers,
    'body' => json_encode($data),
    'timeout' => 30,
]);
```

---

## 3. Endpoints protegidos

Los siguientes endpoints requieren autenticación HMAC:

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/correo/confirmacion` | POST | Enviar correos de confirmación |
| `/calendar/availability` | GET | Consultar disponibilidad |

⚠️ **Nota:** `/calendar/crear-reserva` NO requiere autenticación HMAC porque se accede desde el correo del cliente (usa token firmado en la URL).

---

## 4. Manejo de errores

Si el backend rechaza la autenticación, responderá con:

```json
{
  "error": "Invalid signature",
  // o "Missing authentication headers"
  // o "Request timestamp too old"
}
```

**Códigos de estado HTTP:**
- `401`: Headers faltantes o timestamp expirado
- `403`: Firma inválida o secret incorrecto
- `404`: Dominio no encontrado en la base de datos

---

## 5. Ejemplo completo en PHP

```php
function aa_send_authenticated_request($endpoint, $method, $data = []) {
    $domain = parse_url(home_url(), PHP_URL_HOST);
    $client_secret = get_option('aa_client_secret');
    
    if (!$client_secret) {
        return new WP_Error('no_secret', 'Client secret no configurado. Conecta con Google primero.');
    }

    $timestamp = round(microtime(true) * 1000);
    $nonce = wp_generate_uuid4();
    
    $body = $method === 'POST' ? json_encode($data) : '';
    $path = parse_url($endpoint, PHP_URL_PATH);
    $query = parse_url($endpoint, PHP_URL_QUERY);
    if ($query) $path .= '?' . $query;
    
    $message = $method . $path . $body . $timestamp . $nonce;
    $signature = hash_hmac('sha256', $message, $client_secret);
    
    $headers = [
        'Content-Type' => 'application/json',
        'X-Client-Id' => $domain,
        'X-Timestamp' => (string)$timestamp,
        'X-Nonce' => $nonce,
        'X-Signature' => $signature,
        'X-Client-Secret' => $client_secret,
    ];
    
    $args = [
        'headers' => $headers,
        'method' => $method,
        'timeout' => 30,
    ];
    
    if ($method === 'POST') {
        $args['body'] = $body;
    }
    
    return wp_remote_request($endpoint, $args);
}

// Uso:
$response = aa_send_authenticated_request(
    'http://localhost:3000/correo/confirmacion',
    'POST',
    [
        'domain' => 'salonanahi.com',
        'nombre' => 'Roberto',
        'servicio' => 'Corte',
        'fecha' => '2025-10-22T18:00:00.000Z',
        'telefono' => '5512345678',
        'email' => 'cliente@example.com',
        'businessName' => get_option('aa_business_name'),
        'businessAddress' => get_option('aa_business_address'),
        'whatsapp' => get_option('aa_whatsapp_number'),
    ]
);
```

---

## 6. Seguridad adicional (opcional)

Para mayor seguridad, considera:

1. **Rotación de secrets:** Implementar un endpoint para regenerar el `client_secret` periódicamente.
2. **Nonce único:** Guardar los nonces usados en una tabla con TTL de 5 minutos para prevenir replay attacks.
3. **HTTPS obligatorio:** En producción, forzar HTTPS para todas las comunicaciones.

---

## 7. Testing

Para probar la autenticación manualmente, puedes usar `curl`:

```bash
# Calcular la firma en bash (requiere openssl)
DOMAIN="salonanahi.com"
SECRET="tu_client_secret_aqui"
TIMESTAMP=$(date +%s%3N)
NONCE=$(uuidgen)
METHOD="POST"
PATH="/correo/confirmacion"
BODY='{"domain":"salonanahi.com","nombre":"Test"}'
MESSAGE="${METHOD}${PATH}${BODY}${TIMESTAMP}${NONCE}"
SIGNATURE=$(echo -n "$MESSAGE" | openssl dgst -sha256 -hmac "$SECRET" | cut -d' ' -f2)

curl -X POST http://localhost:3000/correo/confirmacion \
  -H "Content-Type: application/json" \
  -H "X-Client-Id: $DOMAIN" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "X-Nonce: $NONCE" \
  -H "X-Signature: $SIGNATURE" \
  -H "X-Client-Secret: $SECRET" \
  -d "$BODY"
```

---

## Soporte

Si tienes dudas, revisa:
- `middleware/verifyClient.js` - Implementación del middleware de autenticación
- `utils/clientSecret.js` - Utilidades de generación y verificación de secrets
- `routes/oauth.js` - Flujo completo de OAuth y generación del secret inicial
