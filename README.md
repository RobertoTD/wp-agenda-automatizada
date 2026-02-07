# WP Agenda Automatizada

Plugin de WordPress para gestión de citas con integración a Google Calendar. Shortcode: `[agenda_automatizada]`.  
**Para Cursor:** Trabaja solo en este plugin; no modificar tema ni backend Node. Endpoints y flujos deben estar respaldados por `docs/`.

## Overview

## Architecture rules

- **PHP (includes/):** Estructura, datos iniciales, orquestación. Controllers coordinan Models y Services; no contienen lógica de dominio. Models = solo acceso a BD. Services = integración con backend Node y APIs.
- **JS (assets/js/):** UI y consumo de servicios/controladores existentes. `main-admin.js` / `main-frontend.js` = solo arranque. Controllers = orquestación UI ↔ Services ↔ DOM, sin lógica de negocio. Services = lógica de dominio y llamadas a APIs. `ui/` = solo DOM y render. `utils/` = funciones puras, sin estado ni DOM.
- **Regla global:** Cada función en el módulo que corresponde a su responsabilidad. Flujo: main → controller → service → utils/UI. No mezclar UI con lógica, ni controladores con domain logic, ni models con orquestación. No duplicar lógica de negocio entre PHP y JS.
- **Vistas:** `views/` y `templates/` son fragmentos HTML y pantallas; la lógica de presentación compleja puede delegarse a JS (ui/), pero los datos iniciales salen de PHP.

## Key paths

| Ruta | Uso |
|------|-----|
| `wp-agenda-automatizada.php` | Entrada del plugin; hooks y carga de controladores |
| `includes/controllers/` | Controladores PHP (availability, confirm, enqueue, proximasCitas, Webhooks, etc.) |
| `includes/models/` | Acceso a datos (ReservationsModel, AssignmentsModel) |
| `includes/services/` | Servicios PHP (confirm-backend, notifications, assignments, appointments, Sync, etc.) |
| `includes/routes/` | Rutas de la app (agenda-app.php) |
| `assets/js/main-admin.js`, `main-frontend.js` | Punto de entrada JS; solo bootstrapping |
| `assets/js/controllers/` | Orquestación JS (admin, reservas, disponibilidad, confirm, WhatsApp, etc.) |
| `assets/js/services/` | Lógica de dominio JS; subcarpeta `availability/` para slots y rangos |
| `assets/js/ui/` | Componentes de interfaz (calendario, slots, etc.); solo DOM y render |
| `assets/js/ui-adapters/` | Adaptadores de UI (WPAgenda, datePicker, modal, slots) |
| `assets/js/utils/` | Utilidades puras (fechas, helpers); sin estado ni DOM |
| `css/` | Estilos del plugin (styles.css, calendar-default.css) |
| `views/` | Vistas PHP del plugin (admin-controls, etc.) |
| `docs/` | Documentación del plugin |

## How to work in this repo

- **Do:** Respetar separación PHP = estructura + datos iniciales; JS = UI + consumo de controladores/servicios ya definidos.
- **Do:** Añadir endpoints o flujos solo si están descritos o alineados con `docs/` (MVP, arquitectura, design brief).
- **Do:** Pasar datos desde PHP (localized script) y consumirlos desde JS; no reimplementar en JS lógica que ya vive en PHP.
- **Do:** Mantener controladores como orquestadores; lógica de negocio en services; UI en módulos `ui/` o equivalentes.
- **Do:** Consultar `docs/DESIGN_BRIEF.md` para tokens y componentes antes de tocar estilos o nuevos componentes.
- **Don’t:** Inventar endpoints, acciones AJAX o flujos no documentados en los docs del plugin.
- **Don’t:** Poner lógica de negocio en controladores ni en componentes de UI.
- **Don’t:** Duplicar reglas de negocio entre capa PHP y capa JS.
- **Don’t:** Mezclar responsabilidades (p. ej. domain logic en models o en archivos de vista).
- **Don’t:** Añadir features fuera del alcance MVP (app móvil, pagos, multi-sede, etc.) sin actualizar primero los docs.

## Docs

Documentación de referencia dentro del plugin (no inventar flujos ni endpoints que no figuren aquí):

- [01-mvp-scope.md](docs/01-mvp-scope.md) — Alcance MVP, roles (Owner, Assistant, Client), features (reservas, staff, servicios, reportes), restricciones técnicas y no-goals.
- [02-architecture-principles.md](docs/02-architecture-principles.md) — Principios y capas PHP/JS; estructura de carpetas y responsabilidades de cada capa.
- [DESIGN_BRIEF.md](docs/DESIGN_BRIEF.md) — Sistema de diseño: tokens (colores, espaciado, tipografía), componentes (cards, botones, badges, toolbars), interacciones y anti-patterns.
