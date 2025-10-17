# 🧭 Proyecto: Agenda Automatizada — WordPress Plugin

## 🎯 Propósito general

Este plugin conecta un sitio WordPress con un **backend Node.js** desplegado en Render.com  
para consultar **disponibilidad de agenda** desde Google Calendar y mostrarla al cliente final  
en un formulario de reserva (p. ej., cejas, labios, micropigmentación, etc.).

El objetivo es permitir que diferentes sitios WordPress (cada uno con este plugin instalado)  
consulten el mismo backend centralizado, sin necesidad de exponer directamente las credenciales de Google.

---

## 🧩 Arquitectura general

### 1. WordPress (frontend y plugin)
- Proporciona el formulario de citas.
- Muestra el **selector de fecha (flatpickr)**.
- Usa AJAX (vía `admin-ajax.php`) para consultar disponibilidad.
- Ejecuta código JavaScript local (`horariosapartados.js`) al abrir el datepicker.

### 2. Proxy interno en PHP (`availability-proxy.php`)
- Actúa como intermediario seguro entre WordPress y el backend Render.
- Evita problemas de CORS.
- Recibe las solicitudes AJAX y las reenvía con `wp_remote_get()` al backend.

### 3. Backend Node.js (Render.com)
- Expone los endpoints:
  - `/calendar/availability` → devuelve los rangos ocupados del calendario.
  - `/oauth/authorize` y `/oauth/callback` → gestionan autenticación con Google.
  - `/health` → verificación del estado del servidor.
- Está protegido por OAuth 2.0 y configurado con variables de entorno (`.env`).

---

## 🔄 Flujo de comunicación completo

1. El usuario abre el formulario WordPress.
2. El input de fecha (`#fecha`) activa un evento `focus` en `horariosapartados.js`.
3. Ese evento ejecuta un `fetch()` a `admin-ajax.php?action=aa_get_availability`.
4. WordPress recibe la petición AJAX, la dirige al hook `aa_ajax_get_availability()` dentro de `availability-proxy.php`.
5. Ese proxy construye la URL del backend Render (`https://deoia-oauth-backend.onrender.com/calendar/availability?domain=email...`) y envía la solicitud.
6. El backend Node responde con un JSON de disponibilidad obtenido desde Google Calendar.
7. El proxy PHP reenvía el JSON limpio al navegador.
8. `horariosapartados.js` recibe el JSON, lo almacena en `window.aa_availability` y lanza el evento `aa:availability:loaded`.
9. Cualquier script adicional (datepicker, etc.) puede escuchar ese evento para deshabilitar días ocupados.

## flujo del backend al enviar formulario
 

---

## 📂 Estructura relevante del plugin

wp-agenda-automatizada/
│
├── wp-agenda-automatizada.php # Archivo principal del plugin
├── admin-controls.php # Configuración en el panel WP (horarios, email, token)
├── availability-proxy.php # Proxy AJAX hacia backend Render (principal comunicación)
├── js/
│ ├── horariosapartados.js # Maneja el datepicker y la llamada AJAX a backend de node.js
│ ├── form-handler.js # Envía formularios de cita (por implementar)
│ |── admin-schedule.js # Configura horarios disponibles en admin
| |-- admin-controls.js # da dinamismo a los controles del admin
└── ...

## 🧠 Variables clave y opciones

| Nombre WP Option | Descripción |
|------------------|-------------|
| `aa_google_email` | Email del calendario conectado |
| `aa_google_token` | Token OAuth guardado |
| `aa_schedule` | Configuración de horarios del negocio |
| `aa_future_window` | Límite temporal de reserva (en días/meses) |

---

## ⚙️ Scripts JavaScript principales

### `horariosapartados.js`
- Detecta el input `#fecha`.
- Al hacer focus:
  - Llama a `admin-ajax.php` usando la acción `aa_get_availability`.
  - Recibe y parsea el JSON de disponibilidad.
  - Emite eventos personalizados:
    - `aa:availability:loaded`
    - `aa:availability:error`

### `admin-schedule.js`
- Permite al administrador configurar horarios desde el panel de WordPress.
- Guarda los datos en las opciones del plugin (`aa_schedule`, `aa_slot_duration`, etc.).

---

## 🧰 Backend Render (Node.js)
- `index.js` inicia el servidor Express.
- `calendar.js` maneja la comunicación con Google Calendar API.
- `oauth.js` implementa el flujo OAuth 2.0 (login con Google, callback, tokens).

Endpoints activos:
GET /calendar/availability
GET /oauth/authorize
GET /oauth/callback
GET /health


---

## 🚀 Flujo de deploy

1. El plugin se actualiza en GitHub → Hostinger detecta cambios → despliega automáticamente.
2. El backend está alojado en Render.com y también se actualiza automáticamente con `git push`.
3. Ambos se comunican mediante HTTPS, sin CORS ni claves expuestas.

---

## 🧭 Contexto para Copilot

- Este repositorio **contiene el plugin de WordPress**, no el backend.
- Todo el código PHP corre en entorno WP, así que Copilot debe sugerir usando funciones nativas de WordPress (`add_action`, `wp_remote_get`, `wp_localize_script`, etc.).
- Evitar sugerencias que usen frameworks externos (como Laravel o Express) en este proyecto.
- JavaScript corre **en el navegador**, no en Node.
- La única comunicación externa válida es vía `admin-ajax.php` hacia el backend Render.

---

## ✅ Estado actual

- Comunicación backend–plugin confirmada ✅  
- Flujo AJAX funcionando correctamente ✅  
- CORS resuelto ✅  
- Datepicker pendiente de integrar con datos de disponibilidad ⚙️  

---

**Autor:** RobertoTD  
**Última actualización:** Octubre 2025  
**Repositorio:** https://github.com/RobertoTD/wp-agenda-automatizada