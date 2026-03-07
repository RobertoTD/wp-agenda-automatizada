# Auditoría de seguridad — WP Agenda Automatizada

Fecha: 2026-03-04

## Resumen ejecutivo

Se revisaron endpoints AJAX/REST y manejo de datos sensibles (PII de citas/clientes). Se identificaron problemas de **control de acceso insuficiente** y **protección CSRF incompleta** en endpoints administrativos.

## Hallazgos corregidos en este parche

### 1) IDOR / Broken Access Control en endpoints de citas administrativas
- **Riesgo:** usuarios autenticados sin permisos administrativos podían consultar citas (`aa_get_appointments`) y marcar notificaciones como leídas (`aa_mark_appointment_notification_read`).
- **Impacto:** exposición de datos personales (nombre, teléfono, correo, estado de cita) y modificación de estado de notificaciones.
- **Corrección aplicada:**
  - Se exige `current_user_can('aa_view_panel')` o `current_user_can('manage_options')`.
  - Se exige nonce con `check_ajax_referer('aa_appointments_nonce', '_wpnonce')`.

### 2) Falta de CSRF + control de acceso en notificaciones admin
- **Riesgo:** endpoints `aa_get_unread_notifications` y `aa_mark_notifications_as_read` sin verificación nonce ni autorización robusta.
- **Impacto:** lectura/alteración del estado de notificaciones por usuarios autenticados de bajo privilegio o por CSRF sobre sesión admin.
- **Corrección aplicada:**
  - Se exige `current_user_can('aa_view_panel')` o `current_user_can('manage_options')`.
  - Se exige nonce con `check_ajax_referer('aa_notifications_nonce', '_wpnonce')`.

### 3) Divulgación de errores de base de datos
- **Riesgo:** se retornaba `$wpdb->last_error` al cliente.
- **Impacto:** fuga de información interna útil para ataques de enumeración.
- **Corrección aplicada:** mensaje genérico al cliente y logging en servidor.

## Superficie revisada y recomendaciones pendientes

1. **Homogeneizar nonces en TODO endpoint `wp_ajax_aa_*` administrativo** (assignments/staff/services/areas).
2. **Capacidad estándar única** (p.ej. `aa_view_panel` / `aa_manage_agenda`) para evitar discrepancias (`manage_options` vs custom capability).
3. **Reducir `error_log` con payloads completos** en rutas sensibles (evitar logging de PII en producción).
4. **Añadir pruebas de seguridad de integración** para autorización/nonce en endpoints críticos.

