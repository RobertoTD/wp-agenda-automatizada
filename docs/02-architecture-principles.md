Architecture Principles (Summary)

PLUGIN ROOT
│
├── assets/
│   ├── js/
│   │   ├── main-admin.js
│   │   ├── main-frontend.js
│   │   │
│   │   ├── controllers/
│   │   │   ├── adminConfirmController.js
│   │   │   ├── adminReservationController.js
│   │   │   ├── availabilityController.js
│   │   │   ├── proximasCitasController.js
│   │   │   └── reservationController.js
│   │   │
│   │   ├── services/
│   │   │   ├── availability/
│   │   │   │   ├── busyRanges.js
│   │   │   │   ├── combineLocalExternal.js
│   │   │   │   ├── proxyFetch.js
│   │   │   │   └── slotCalculator.js
│   │   │   │
│   │   │   ├── availabilityService.js
│   │   │   ├── confirmService.js
│   │   │   └── reservationService.js
│   │   │
│   │   ├── ui/
│   │   │   ├── calendarAdminUI.js
│   │   │   ├── calendarUI.js
│   │   │   ├── proximasCitasUI.js
│   │   │   ├── slotSelectorAdminUI.js
│   │   │   └── slotSelectorUI.js
│   │   │
│   │   └── utils/
│   │       ├── dateUtils.js
│   │       └── (otros utilitarios *.js que no se ven en el screenshot)
│   │
│   ├── css/
│   │   └── styles.css
│   │
│   └── docs/
│       ├── 01-mvp-scope.md
│       └── 02-architecture-principles.md
│
├── includes/
│   ├── controllers/
│   │   ├── availability-controller.php
│   │   ├── confirm-admin-controller.php
│   │   ├── enqueueController.php
│   │   └── proximasCitasController.php
│   │
│   ├── models/
│   │   └── ReservationsModel.php
│   │
│   └── services/
│       ├── availability-proxy.php
│       ├── confirm-backend-service.php
│       └── auth-helper.php
│
├── views/
|   │
|   └── templates/
|
|── wp-agenda-automatizada.php

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

- **css/**  
  Estilos del plugin.

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
  Lógica de integración.

## Vistas y Plantillas

- **views/**  
  Vistas completas del plugin (pantallas principales como `assistant-controls.php`).

- **templates/**  
  Fragmentos HTML reutilizables.

## Plugin Root

- **wp-agenda-automatizada.php**  
  Archivo principal.  
  Registra hooks, carga controladores, inicia el plugin.

---

### Regla Global
**Cada función debe residir en el módulo que coincide con su responsabilidad.**  
No mezclar UI con lógica, no mezclar controladores con domain logic, no mezclar models con orquestación.  
Respetar estrictamente:  
**main → controller → service → utils/UI**, según el flujo natural del plugin.