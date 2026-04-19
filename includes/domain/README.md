# `includes/domain/` — Reglas puras del negocio

Aquí viven las **reglas que serían ciertas aunque WordPress no existiera**.
Determinista, testeable en aislamiento, sin dependencias del entorno.

## Qué entra aquí

- Domain Services (ej. `AA_Area_Availability_Service`, `AA_Staff_Availability_Service`).
- Cálculos puros del negocio (ventanas de tiempo, colisiones, factibilidad).
- Value Objects si aparecen (`TimeWindow`, `Money`, `ServiceDuration`).
- Reglas de validación del negocio (qué cuenta como "ocupado", qué bloquea
  una zona, qué constituye una colisión, qué precio aplica).

## Qué NO entra aquí

- `$wpdb`, `get_option`, `add_action`, `wp_send_json_*`, `error_log`. **Nada** de WP.
- SQL ni acceso a BD → eso lo hacen los `repositories/`.
- Llamadas a APIs externas, LLMs, sistemas de notificaciones → `infrastructure/`.
- Orquestación de flujos del producto → `application/`.

## Reglas

1. Si esto **no podría correr en un script CLI sin WP cargado**, no es dominio.
2. Recibe datos crudos como parámetros; devuelve resultados estructurados.
3. No conoce de dónde vienen los datos ni a dónde van los resultados.
4. Toda regla nueva del negocio nace aquí, aunque la haya pedido la AI o el JS.

## Organización por contexto

```
domain/
├── availability/
│   ├── AreaAvailabilityService.php
│   ├── StaffAvailabilityService.php
│   └── SlotAvailabilityService.php
├── booking/
│   └── BookingCollisionService.php
└── pricing/
    └── PriceSnapshotService.php
```

## Naming

- Convención de archivos nuevos: `class-aa-{contexto}-{rol}.php` con clase
  `AA_{Contexto}_{Rol}` (consistente con `AA_Area_Availability_Service`).

Ver `docs/00-paradigm-cheatsheet.md`.
