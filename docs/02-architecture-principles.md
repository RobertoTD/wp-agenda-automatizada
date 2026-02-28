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
│       │   │   └── slotCalculator.js
│       │   ├── adminCalendarService.js
│       │   ├── availabilityService.js
│       │   ├── confirmService.js
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
│           └── dateUtils.js
│
├── css/
│   ├── calendar-default.css
│   └── styles.css
│
├── docs/
│   ├── 01-mvp-scope.md
│   ├── 02-architecture-principles.md
│   └── DESIGN_BRIEF.md
│
├── includes/
│   ├── admin/
│   │   ├── iframe-test.php
│   │   └── ui/
│   │       ├── index.php, README.md, TAILWIND.md
│   │       ├── assets/ (css: admin.css, admin.source.css | js: main.js, notifications.js, sidebar.js)
│   │       ├── modals/ (appointments, assignment, crearcliente, reservation)
│   │       ├── modules/ (assignments, calendar, clients, settings)
│   │       └── shared/ (footer.php, header.php, layout.php, modals.php, sidebar.php)
│   ├── controllers/
│   │   ├── availability-controller.php
│   │   ├── confirmController.php
│   │   ├── enqueueController.php
│   │   ├── proximasCitasController.php
│   │   └── WebhooksController.php
│   ├── models/
│   │   ├── AssignmentsModel.php
│   │   └── ReservationsModel.php
│   ├── routes/
│   │   └── agenda-app.php
│   └── services/
│       ├── assignments/ (areasService.php, servicesService.php, staffService.php)
│       ├── appointmentsService.php
│       ├── assignmentsService.php
│       ├── auth-helper.php
│       ├── ClienteService.php
│       ├── confirm-backend-service.php
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

- **services/**  
  Lógica del negocio (domain logic).  
  Pueden incluir llamadas a APIs externas (Node backend, WP Ajax).  
  Cuando existe una carpeta de servicio (ej. `availability/`), el archivo principal del service actúa como *administrador* de sus sub-módulos.

- **ui/**  
  Manipulación del DOM, renderizado, interacción visual, calendarios, listas de slots, componentes, etc.  
  Ninguna lógica del negocio aquí.

- **utils/**  
  Funciones puras y reutilizables: fechas, helpers, parsing, formateo.  
  No tienen estado ni acceso al DOM.

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

- **routes/**  
  Páginas especiales vía rewrite rules.  
  `agenda-app.php`: registro de la ruta `/agenda-app`, query var y `template_redirect`.

## Estilos y documentación

- **css/** (en raíz del plugin)  
  Estilos globales: `styles.css`, `calendar-default.css`.

- **docs/** (en raíz del plugin)  
  Documentación: scope MVP, principios de arquitectura, design brief.

## Vistas y plantillas

- **views/**  
  Vistas completas del plugin (ej. `admin-controls.php`).

- **includes/admin/ui/**  
  UI del admin: módulos (calendar, assignments, clients, settings), modales, layout compartido.

## Plugin Root

- **wp-agenda-automatizada.php**  
  Archivo principal. Registra hooks, shortcode del formulario, `aa_save_reservation`, creación de tablas y migraciones, carga de rutas y controladores.

- **clientes.php**  
  Tabla y lógica de clientes; migraciones de columnas en reservas (`id_cliente`, `join_token`).

- **historial-citas.php**  
  Pantalla de historial de citas.

- **includes/routes/agenda-app.php**  
  Ruta `/agenda-app`: rewrite rule, query var `aa_agenda_app`, `template_redirect` (login o redirect al iframe del admin).

---

### Regla Global
**Cada función debe residir en el módulo que coincide con su responsabilidad.**  
No mezclar UI con lógica, no mezclar controladores con domain logic, no mezclar models con orquestación.  
Respetar estrictamente:  
**main → controller → service → utils/UI**, según el flujo natural del plugin.