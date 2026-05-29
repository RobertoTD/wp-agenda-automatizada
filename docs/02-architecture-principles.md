<!-- Última actualización del documento: 2026-04-17 -->

Architecture Principles (Summary)

## North Star (resumen ejecutivo)

> Para la guía operativa diaria (qué entra dónde, cómo decidir en caliente),
> ver `docs/00-paradigm-cheatsheet.md`. Este documento es la referencia larga.

**Paradigma:** Hexagonal ligero + Use Cases + Single Source of Truth en PHP.

**Capas objetivo dentro de `includes/`:**

- `http/`            → entrada HTTP/AJAX. Parsea, autentica, delega, serializa.
- `application/`     → casos de uso. 1 flujo del producto = 1 clase con `execute()`.
- `domain/`          → reglas puras del negocio. Sin WP, sin SQL. Testeable en aislamiento.
- `repositories/`    → SQL puro (lo que hoy es `models/`). Cero reglas.
- `infrastructure/`  → integración con WP, schema, providers (LLM, Node backend), notificaciones.
- `ui/`              → admin UI (lo que hoy es `admin/ui/`).

**Migración por contagio, no por revolución.** Las features nuevas nacen ya
en estas carpetas. Las viejas se reubican solo cuando se tocan por otra
razón. Convivencia explícita durante la transición.

**Regla diaria (la única que hay que recordar):**

> *¿Esta feature es dominio, flujo o UI?*
> - Dominio → `includes/domain/`
> - Flujo   → `includes/application/` como `{Verbo}{Cosa}UseCase`
> - UI      → `assets/js/` y consume un Use Case por AJAX
>
> Models = SQL. Controllers AJAX = parsean y delegan.

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
│   ├── domain/                           ← capa de reglas puras (paradigma objetivo)
│   │   ├── availability/
│   │   │   └── class-aa-area-availability-service.php   ← canónico (Domain Service)
│   │   ├── onboarding/
│   │   │   └── class-aa-onboarding-activation-policy.php ← reglas puras del onboarding inicial
│   │   └── tenant/
│   │       └── class-aa-tenant-domain.php               ← identidad canónica del tenant (espejo de utils/tenantDomain.js del backend)
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
│       │   ├── providers/backend/
│       │   ├── chat/
│       │   ├── prompts/
│       │   ├── mappers/
│       │   └── skills/
│       ├── assignments/ (areasService.php, servicesService.php, staffService.php)
│       ├── availability/
│       │   └── class-aa-area-availability-service.php   ← SHIM deprecated → ver includes/domain/availability/
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
  Capa **de proyección de UI**, no fuente de verdad del dominio.  
  Su responsabilidad es: hablar con endpoints AJAX/HTTP del backend, normalizar las respuestas para el consumo del DOM, cachear de forma efímera lo que necesita la pantalla y exponer una API ergonómica para los controladores.  
  **No definen reglas de negocio**: cualquier regla operativa (qué cuenta como ocupado, qué bloquea una zona, qué se puede confirmar, qué duración aplica, cómo se resuelve una colisión) es responsabilidad de los servicios PHP de dominio (ver *Domain Logic Ownership* y *Availability Domain Layer*).  
  Cuando existe una carpeta de servicio (ej. `availability/`), el archivo principal actúa como *administrador* de sus sub-módulos, pero sigue siendo un cliente del dominio PHP, no su réplica.  
  **Dashboard:** `dashboardService.js` normaliza respuestas de endpoints `aa_get_*` usados solo por el módulo Resumen; no sustituye a los servicios de dominio en PHP.  
  **Nota histórica:** algunos servicios de `availability/` (ej. `fastAppointmentTimeAvailabilityService.js`) aún concentran lógica de cálculo de huecos por razones de UX en tiempo real; esa lógica se considera **espejo** del dominio PHP y debe mantenerse alineada, no divergente. Cualquier nueva regla nace en PHP.

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

- **models/** *(legacy, en migración)*
  Acceso a base de datos histórico. **Congelado para añadidos**: ningún
  método nuevo se agrega aquí. Sus métodos siguen funcionando y son
  invocables también vía la capa canónica `repositories/` por herencia.
  Migración por contagio: cuando un consumidor se toque, su llamada se
  actualiza al nombre `Repository` correspondiente.

- **repositories/** *(canónico)*
  Capa canónica de acceso SQL. `AssignmentsRepository` y
  `ReservationsRepository` extienden los Models actuales para garantizar
  que cualquier método antiguo sigue accesible desde el nuevo nombre.
  **Reglas:** cero `if` de negocio, métodos nuevos siempre aquí, y los
  métodos del Model que mezclen SQL + reglas se descomponen al tocarlos
  (SQL aquí, reglas en `domain/`). Ver `docs/00-paradigm-cheatsheet.md`
  → "Veda de los Models".

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

## Domain Logic Ownership

La lógica de negocio crítica del plugin **vive en PHP**, dentro de `includes/services/` (incluyendo subcarpetas como `availability/`, `assignments/`, `ai/`). El frontend la consume, no la define.

Pertenecen al dominio PHP, entre otras:

- **Disponibilidad** (zona, staff, slot): qué cuenta como libre/ocupado, ventanas válidas, reglas de overlap, prioridad entre fuentes (assignments vs reservas confirmadas).
- **Confirmación de citas:** qué reservas pueden pasar a `confirmed`, manejo de colisiones, cancelación de pendientes, snapshot de precio.
- **Colisiones:** entre reservas confirmadas (`has_confirmed_staff_overlap`), entre reservas pending (`get_pending_conflicts_overlapping`, `get_pending_conflicts_for_staff_overlap`) y entre assignments operativos (`assignment_guard` por zona).
- **Assignments y zonas:** alta/edición/cierre, validaciones operativas (mismo staff, misma zona, misma ventana), reglas de reasignación.
- **Clientes y reservas:** snapshot de datos, migraciones de columnas, validaciones de unicidad y consistencia.

El JS puede mostrar advertencias tempranas o pre-validar para dar buena UX, pero **toda decisión vinculante** (commit, escritura, ocupación efectiva) se calcula en PHP.

## Availability Domain Layer (PHP)

Carpeta canónica: `includes/domain/availability/`.
(Compatibilidad: `includes/services/availability/class-aa-area-availability-service.php`
sigue existiendo como SHIM `require_once` al canónico, hasta migrar
los consumidores legacy.)

Su propósito es **centralizar las reglas de disponibilidad** del negocio en servicios PHP unitarios y testeables, alimentados por queries puras de los models.

- **`AA_Area_Availability_Service`** (`includes/domain/availability/class-aa-area-availability-service.php`):  
  Fuente de verdad para la disponibilidad de una **zona de atención** ante una propuesta `(zone_id, staff_id, start_datetime, duration)`.  
  Devuelve dos fenómenos separados:
  - `assignment_guard`: la zona está **operativamente reservada** por una assignment activa de otro staff en ese horario (`zone_reserved_for_other_staff`).
  - `occupancy`: la zona está **físicamente ocupada** por una reserva `confirmed` (`zone_busy`, con `busy_range`).

  No mezcla ambos conceptos. No hace SQL: delega en `AssignmentsModel::get_active_assignments_overlapping_in_area()` y `ReservationsModel::get_confirmed_overlap_in_area()`.

Reglas de la capa:

- **Models** = queries puras (sin reglas).
- **Availability services** = reglas de negocio (sin SQL).
- **Consumidores** (AI evaluators, controllers de fast appointment / asignaciones, futuros endpoints): solo invocan al service y traducen el resultado a su contexto (chat, modal, AJAX).

Esta capa es la base sobre la que crecen futuros servicios análogos (`AA_Staff_Availability_Service`, `AA_Slot_Availability_Service`, `AA_Booking_Confirmation_Service`).

## AI Layer Responsibility

El bounded context AI (`includes/services/ai/`, `includes/controllers/ai/`) tiene una responsabilidad acotada y **no compite con el dominio**:

- **No define reglas de negocio.** No decide qué cuenta como ocupado, qué bloquea una zona, qué duración aplica ni qué constituye una colisión.
- **Consume servicios de dominio.** Para cada decisión operativa invoca al service correspondiente (ej. `AA_Area_Availability_Service::evaluate_zone()`), igual que lo haría cualquier otro consumidor.
- **Interpreta resultados.** Su trabajo es traducir la respuesta del dominio a un formato que la AI pueda razonar (`status` + `reason` por dimensión: `service_basic`, `staff_service_match`, `staff_time_availability`, `zone_basic`, `zone_assignment_guard`, `zone_time_occupancy`, …) y producir un texto natural cuando aplica.
- **No escribe estado vinculante.** Resuelve, evalúa y propone. La creación efectiva de la reserva pasa por los mismos servicios y validaciones que cualquier otro flujo (`confirmController`, `appointmentsService`, etc.).

Si una nueva regla aparece dentro del flujo AI, no se queda en el evaluator: se mueve al servicio de dominio correspondiente y el evaluator se limita a llamarlo.

## Single Source of Truth

**Backend (PHP) = fuente única de verdad del dominio.**  
**Frontend (JS) = proyección de esa verdad para la UI.**

Implicaciones:

- Si una regla existe en JS *y* en PHP, el PHP gana. El JS debe alinearse o eliminarse.
- Si una regla nueva nace en una pantalla, su sitio definitivo es un service PHP; el JS solo la consume.
- Cualquier discrepancia entre lo que el JS muestra y lo que el PHP escribe se resuelve **moviendo la regla al PHP**, no duplicándola.
- Endpoints AJAX (`wp_ajax_*`) son la frontera estable entre ambas capas: contratos versionables, no atajos para meter lógica en JS.
- La AI sigue la misma regla: consume el dominio PHP, no lo reemplaza ni lo bypassea.

Esto evita el problema clásico de tener "dos verdades" (UI optimista vs backend escrito) que generan bugs silenciosos en disponibilidad, ocupación y confirmación.

---

### Regla Global
**Cada función debe residir en el módulo que coincide con su responsabilidad.**  
No mezclar UI con lógica, no mezclar controladores con domain logic, no mezclar models con orquestación.  
Respetar estrictamente:  
**main → controller → service → utils/UI**, según el flujo natural del plugin.  
Y por encima de todo: **domain logic vive en PHP, JS proyecta** (ver *Single Source of Truth*).
