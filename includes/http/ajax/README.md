# `includes/http/ajax/` — Controllers AJAX

Traductores entre el protocolo HTTP/AJAX de WordPress y los Use Cases.
Cada handler `wp_ajax_*` vive aquí. **Solo parsea, autentica, delega y
serializa.**

## Qué entra aquí

- Handlers registrados con `add_action('wp_ajax_aa_...', ...)`.
- Validación de permisos (`current_user_can`, `check_ajax_referer`).
- Sanitización de input (`sanitize_text_field`, `intval`, etc.).
- Construcción del array de input limpio para el Use Case.
- Llamada al Use Case correspondiente.
- Serialización del resultado (`wp_send_json_success` / `wp_send_json_error`).

## Qué NO entra aquí

- SQL → `repositories/`.
- Reglas de negocio → `domain/`.
- Orquestación de varios pasos → `application/`.
- Conocimiento de nombres de tablas, queries, joins.
- Lógica de presentación más allá del contrato del endpoint.

## Patrón canónico

```php
add_action('wp_ajax_aa_create_fast_appointment', 'aa_create_fast_appointment_handler');

function aa_create_fast_appointment_handler() {
    if (!current_user_can('aa_view_panel')) {
        wp_send_json_error(['message' => 'Sin permisos'], 403);
        return;
    }
    check_ajax_referer('aa_fast_appointment_nonce', '_wpnonce');

    $input = [
        // sanitización aquí
    ];

    $useCase = new \AA\Application\Booking\CreateFastAppointmentUseCase(/* deps */);
    $result  = $useCase->execute($input);

    wp_send_json_success($result);
}
```

Si tu handler tiene más de ~30 líneas, casi seguro está haciendo trabajo
que pertenece al Use Case.

## Migración

Los `wp_ajax_*` actuales viven en `includes/services/*Service.php` y
`includes/controllers/`. Se migran **uno por uno** cuando se toquen, no
en masa.

## Naming

- Archivo: `{Cosa}Ajax.php` (ej. `AppointmentsAjax.php`, `FastAppointmentAjax.php`).

Ver `docs/00-paradigm-cheatsheet.md`.
