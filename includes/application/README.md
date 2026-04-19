# `includes/application/` — Use Cases (Application Layer)

Orquestadores de **un flujo end-to-end del producto**. Una intención del
usuario = una clase con un único método público `execute()`.

## Qué entra aquí

- Clases tipo `{Verbo}{Cosa}UseCase` (ej. `CreateFastAppointmentUseCase`).
- Coordinación entre `domain/`, `repositories/` e `infrastructure/`.
- Validación de input a nivel de caso de uso (no reglas de dominio).
- La frontera transaccional: aquí se decide "todo o nada".

## Qué NO entra aquí

- Reglas de negocio nuevas → van a `domain/`.
- SQL → va a `repositories/`.
- `$_GET`, `$_POST`, `wp_send_json_*`, `check_ajax_referer` → van a `http/`.
- Llamadas directas a `$wpdb`, `get_option`, providers externos → van a `infrastructure/`.
- Lógica de presentación / DOM → es UI (`assets/js/`).

## Reglas

1. Una clase = un Use Case = un método público (`execute`).
2. Un Use Case **no llama a otro Use Case**. Si lo necesitas, lo que hay
   debajo es un Domain Service mal nombrado.
3. Recibe arrays/DTOs limpios, devuelve arrays/DTOs limpios. Cero HTTP.
4. Un mismo Use Case puede ser invocado por varios canales (AJAX, AI, CLI).

## Organización por contexto

```
application/
├── booking/
│   ├── CreateFastAppointmentUseCase.php
│   └── ConfirmReservationUseCase.php
└── ai/
    └── InterpretBookingMessageUseCase.php
```

## Naming

- Archivo: `{Verbo}{Cosa}UseCase.php` (PascalCase).
- Clase: idem nombre del archivo.

Ver `docs/00-paradigm-cheatsheet.md` para el contexto general.
