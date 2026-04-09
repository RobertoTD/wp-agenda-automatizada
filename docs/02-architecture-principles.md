<!-- Última actualización del documento: 2026-03-21 -->

Architecture Principles (Summary)

PLUGIN ROOT
│
├── assets/
│   └── js/
│       ├── main-admin.js
│       ├── main-frontend.js
│       ├── controllers/
│       │   ├── adminCalendarController.js
│       │   ├── adminConfirmController.js
│       │   ├── adminFastappointmentController.js
│       │   ├── adminFastappointmentFlowController.js
│       │   ├── adminReservationAssignmentFlowController.js
│       │   ├── adminReservationController.js
│       │   ├── appointmentsController.js
│       │   ├── availabilityController.js
│       │   ├── frontendAssignmentsController.js
│       │   ├── reservationClientController.js
│       │   ├── reservationController.js
│       │   └── whatsAppController.js
│       ├── services/
│       │   ├── availability/
│       │   │   ├── availabilityAssignments.js
│       │   │   ├── busyRanges.js
│       │   │   ├── busyRangesAssignments.js
│       │   │   ├── calendarAvailabilityService.js
│       │   │   ├── fastAppointmentPrerequisitesService.js
│       │   │   ├── fastAppointmentTimeAvailabilityService.js
│       │   │   └── slotCalculator.js
│       │   ├── adminCalendarService.js
│       │   ├── availabilityService.js
│       │   ├── confirmService.js
│       │   ├── dashboardService.js          ← consumido por el módulo dashboard (AJAX al admin)
│       │   ├── localAvailabilityService.js
│       │   ├── reservationService.js
│       │   └── whatsAppService.js
│       ├── ui/
│       │   ├── calendarAdminUI.js
│       │   ├── calendarUI.js
│       │   ├── slotSelectorUI.js
│       │   └── whatsAppUI.js
│       ├── ui-adapters/
│       │   ├── calendarDefaultAdapter.js
│       │   ├── datePickerAdapter.js
│       │   ├── modalDefaultAdapter.js
│       │   ├── slotsDefaultAdapter.js
│       │   └── WPAgenda.js
│       └── utils/
│           └── dateUtils.js                 ← fechas, slots, rangos (día/semana/mes) para UI
│
├── css/
│   ├── calendar-default.css
│   └── styles.css
│
├── docs/
│   ├── 01-product-strategy.md
│   ├── 02-architecture-principles.md
│   ├── DESIGN_BRIEF.md
│   ├── ai/
│   │   ├── 01-ai-module-overview.md
│   │   └── 02-ai-chat-contract.md
│   └── fast-appointment-vs-assignment-availability.md
│
├── includes/
│   ├── admin/
│   │   ├── iframe-test.php
│   │   └── ui/
│   │       ├── index.php, README.md, TAILWIND.md
│   │       ├── assets/ (css: admin.css, admin.source.css | js: main.js, notifications.js, sidebar.js)
│   │       ├── modals/ (appointments, assignment, crearcliente, fastappointment, reservation)
│   │       ├── modules/ (assignments, calendar, clients, dashboard, settings)
│   │       └── shared/ (footer.php, header.php, layout.php, modals.php, sidebar.php)
│   ├── controllers/
│   │   ├── ai/
│   │   │   └── admin-ai-chat-controller.php
│   │   ├── availability-controller.php
│   │   ├── confirmController.php
│   │   ├── enqueueController.php
│   │   ├── proximasCitasController.php
│   │   └── WebhooksController.php
│   ├── models/
│   │   ├── AssignmentsModel.php
│   │   └── ReservationsModel.php
│   ├── routes/
│   │   ├── agenda-app.php
│   │   └── citas-virtuales.php
│   └── services/
│       ├── ai/
│       │   ├── ai-module.php
│       │   ├── contracts/
│       │   ├── providers/ollama/
│       │   ├── chat/
│       │   ├── prompts/
│       │   ├── mappers/
│       │   └── skills/
│       ├── assignments/ (areasService.php, servicesService.php, staffService.php)
│       ├── appointmentsService.php
│       ├── assignmentsService.php
│       ├── auth-helper.php
│       ├── ClienteService.php
│       ├── confirm-backend-service.php
│       ├── dashboardService.php             ← endpoints AJAX agregados del resumen (revenue, comparativa, alertas)
│       ├── notificationsService.php
│       ├── RemindersService.php
│       └── SyncService.php
│
├── js/
│   └── admin-controls.js
│
├── views/
│   └── admin-controls.php
│
├── clientes.php
├── historial-citas.php
└── wp-agenda-automatizada.php

## JS Layer (assets/js)

- **main-admin.js / main-frontend.js**  
  Initialization only. Arranque del plugin, bootstrapping y montaje inicial de controladores.

- **controllers/**  
  Orquestación.  
  No contienen lógica del negocio.  
  Solo coordinan flujo entre UI ↔ Services ↔ DOM.  
  **Fast appointment:** `adminFastappointmentFlowController.js` (flujo y disponibilidad) y `adminFastappointmentController.js` (wiring / modal).

- **services/**  
  Lógica del negocio (domain logic).  
  Pueden incluir llamadas a APIs externas (Node backend, WP Ajax).  
  Cuando existe una carpeta de servicio (ej. `availability/`), el archivo principal del service actúa como *administrador* de sus sub-módulos.  
  **Dashboard:** `dashboardService.js` normaliza respuestas de endpoints `aa_get_*` usados solo por el módulo Resumen; no sustituye a los servicios de dominio en PHP.

- **ui/**  
  Manipulación del DOM, renderizado, interacción visual, calendarios, listas de slots, componentes, etc.  
  Ninguna lógica del negocio aquí.

- **utils/**  
  Funciones puras y reutilizables: fechas, helpers, parsing, formateo, rangos para selectores (día / semanas calendario / meses).  
  No tienen estado ni acceso al DOM (salvo convenciones del proyecto en funciones legacy que tocan consola).

- **ui-adapters/**  
  Adaptadores para integrar el plugin con temas o entornos (calendario, modal, slots, datePicker).  
  Permiten que el frontend funcione con distintas implementaciones de UI.

## PHP Layer (includes/)

- **controllers/**  
  Orquestadores PHP.  
  No contienen lógica de dominio.  
  Reciben solicitudes, coordinan Models y Services, retornan respuestas.  
  `enqueueController.php` se encarga de encolar JS/CSS.

- **models/**  
  Acceso a base de datos.  
  Consultas, inserciones, actualizaciones.  
  Sin lógica de negocio.

- **services/**  
  Conexión con servicios externos (backend Node.js, API externas).  
  Lógica de integración. Incluye subcarpeta `assignments/` (areas, services, staff).  
  **`dashboardService.php`:** endpoints `wp_ajax_*` dedicados al panel Resumen (ingresos por rango, comparativa de citas, alertas de pendientes). Helpers compartidos (`aa_dashboard_resolve_estados`, `aa_dashboard_count_reservas`, `aa_dashboard_resolve_ranges`, permisos). Mantiene agregaciones que no encajan en un controller existente.

- **services/ai/ + controllers/ai/**  
  Bounded context de AI. Separa proveedor LLM, caso de uso de chat, prompts, mapeo al dominio y skills reservadas. No debe cargarse en bootstrap hasta que exista un caso de uso real activado.

- **routes/**  
  Páginas especiales vía rewrite rules.  
  - `agenda-app.php`: registro de la ruta `/agenda-app`, query var y `template_redirect` (entrada típica al iframe admin → módulo `dashboard`).  
  - `citas-virtuales.php`: portal de unión a citas virtuales por token.

## Admin UI (includes/admin/ui/)

- **shared/layout.php**  
  Shell del iframe: Tailwind, Flatpickr (CDN), `dateUtils.js`, servicios de disponibilidad (incl. prerequisitos y slots de *fast appointment*), controladores admin, adaptadores, modales transversales (reserva, **fast appointment**, citas, assignment, cliente).

- **modules/**  
  Vistas por pantalla: `dashboard` (resumen + cards), `calendar`, `assignments`, `clients`, `settings`. Cada módulo puede registrar su propio JS colgando del HTML del módulo (ej. dashboard carga `assets/js/services/dashboardService.js` + `dashboard-module.js`).

- **modals/**  
  UI reutilizable: reserva, assignment, appointments, crear/editar cliente, **fastappointment** (`index.php` + `fastappointment.js`).

## Módulo Dashboard (Resumen)

- **Vista:** `includes/admin/ui/modules/dashboard/index.php` — cards (hoy, próxima cita, ingresos con selector día/semana/mes, comparativa, alertas), `window.AA_DASHBOARD_DATA` (nonces, `today` en zona del negocio, moneda).

- **JS:** `includes/admin/ui/modules/dashboard/dashboard-module.js` — solo orquestación de UI; datos vía `window.DashboardService` y `window.DateUtils` (rangos de fechas, flatpickr en la card de ingresos).

- **Cliente HTTP:** `assets/js/services/dashboardService.js` — `getTodaySummary` / próxima cita (`proximasCitasController`), `getRevenueSummary` (`aa_get_dashboard_revenue`), comparativa y alertas.

- **Backend:** `includes/services/dashboardService.php` — agregaciones SQL sobre `aa_reservas` (+ join a `aa_services` donde aplica para ingresos). El ingreso respeta jerarquía por reserva (`amount_charged`, `service_price_snapshot`, precio actual del servicio) según implementación vigente.

- **Navegación:** módulo por defecto en `includes/admin/ui/index.php` y enlace en `sidebar.php`; `agenda-app` redirige al mismo contexto.

## Citas rápidas (Fast appointment)

- **Objetivo:** crear cita desde admin con flujo acortado: cliente, servicio, fecha/hora, posible creación o reutilización de assignment según disponibilidad.

- **Documentación de diseño:** `docs/fast-appointment-vs-assignment-availability.md`.

- **JS (dominio disponibilidad):**  
  - `fastAppointmentPrerequisitesService.js` — datos previos (staff, áreas, servicios, assignments).  
  - `fastAppointmentTimeAvailabilityService.js` — cómputo de huecos / conflictos con reservas confirmadas.

- **JS (flujo UI):** `adminFastappointmentFlowController.js` + `adminFastappointmentController.js` + `includes/admin/ui/modals/fastappointment/fastappointment.js`.

- **Integración:** mismos building blocks que el calendario admin (`CalendarAvailabilityService`, busy ranges assignments, etc.), cargados en `layout.php` antes de los controladores del modal.

## AI (bounded context)

- **Backend:** `includes/controllers/ai/` + `includes/services/ai/` — frontera reservada para chat admin con LLM, prompts, proveedor y adaptación al dominio.

- **UI inicial:** no existe módulo visual independiente en `includes/admin/ui/modules/ai/`; el primer punto real de uso del chat deberá vivir dentro de `includes/admin/ui/modules/calendar/`, junto al flujo donde el admin crea citas.

## Estilos y documentación

- **css/** (en raíz del plugin)  
  Estilos globales: `styles.css`, `calendar-default.css`.

- **docs/** (en raíz del plugin)  
  Documentación: estrategia de producto, principios de arquitectura, design brief, notas de fast appointment.

## Vistas y plantillas

- **views/**  
  Vistas completas del plugin (ej. `admin-controls.php`).

- **includes/admin/ui/**  
  UI del admin: módulos, modales, layout compartido.

## Plugin Root

- **wp-agenda-automatizada.php**  
  Archivo principal. Registra hooks, shortcode del formulario, `aa_save_reservation` (incl. snapshot de precio de servicio cuando aplica), creación de tablas y migraciones, carga de rutas y controladores, `require` de `includes/services/dashboardService.php`.

- **clientes.php**  
  Tabla y lógica de clientes; migraciones de columnas en reservas (`id_cliente`, `join_token`).

- **historial-citas.php**  
  Pantalla de historial de citas.

- **includes/routes/agenda-app.php**  
  Ruta `/agenda-app`: rewrite rule, query var `aa_agenda_app`, `template_redirect` (login o redirect al iframe del admin, típicamente módulo dashboard).

---

### Regla Global
**Cada función debe residir en el módulo que coincide con su responsabilidad.**  
No mezclar UI con lógica, no mezclar controladores con domain logic, no mezclar models con orquestación.  
Respetar estrictamente:  
**main → controller → service → utils/UI**, según el flujo natural del plugin.
