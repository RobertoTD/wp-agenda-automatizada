# Canon libre v2 — plan activo de transición

**Status:** active.

**Autoridad:** plan de implementación. La norma vive en `docs/04-canonical-constitution.md` y `docs/05-canonical-capabilities.md`.

## Objetivo de etapa

Terminar con una superficie donde un administrador pueda crear o editar una lista canónica sin familia y activar cualquier combinación válida de capabilities existentes, excepto dossier/expedientes. Los registros deben adoptar y retirar correctamente las proyecciones de esa configuración.

No se construyen presets, clasificadores, buscador facetado, solutions ni un modelo nuevo de roles.

## Decisiones cerradas

- Raíz única: `module=canonical_shell`, sin switcher ni filtros de familia.
- Una lista nueva es válida sin capabilities.
- La configuración de capabilities es de la lista, no de un tipo de lista.
- `known + ready` es la puerta inicial; dependencias, incompatibilidades, integridad y seguridad son las únicas restricciones admisibles y deben declararse explícitamente.
- No hay presets ahora y ninguna URL conserva el origen de una configuración.
- Se retira `contact_dossier` sin rediseñarlo ni preservar su información local.
- Entorno local: se permite reset destructivo y recreación de tablas canónicas sin backfill; el fixture `INC3-RETIRE-20260914 policy` no recibe excepción.
- Acceso: `manage_options`.

## Ciclos

### C0 — Constitución y corte documental

Reemplazar autoridad documental v1 por v2, actualizar guías de agentes y marcar planes family-dependent como históricos o con su ámbito de autoridad limitado. No modificar código, schema ni datos.

**Aceptación:** no queda ningún documento activo que presente familia como identidad, requisito de creación, repertorio/default de capabilities o autoridad de URL; los documentos distinguen destino v2 de implementación v1.

### C1 — Raíz y persistencia canónica libres

Eliminar de schema, dominio, repository, router, AJAX y shell la identidad/enablement/selector de familias. Rehacer la base local de forma controlada; crear y operar una lista y registros sin capability.

**Aceptación:** el FAB existe aunque no haya configuraciones adicionales; la URL de lista/registro no contiene family; CRUD base funciona con `manage_options`.

### C2 — Contrato global de capabilities

Inventariar el estado real de cada capability existente, retirar el repertorio/default familiar, definir un validador de combinación final y declarar dependencias/incompatibilidades solo cuando sean reales. Mantener fuera dossier.

**Aceptación:** Application puede leer y validar la configuración de cualquier lista sin conocer familias; una incompatibilidad tiene diagnóstico explícito y prueba automatizada.

### C3 — Configurador de lista y proyección de registros

Exponer en crear/editar lista las capabilities elegibles, bloqueos y selección efectiva. Conectar formularios, cards, acciones, vistas y recursos a la activación global.

**Aceptación:** el administrador crea/edita una lista con una combinación válida; sus registros proyectan solo las capabilities activas y pasan el recorrido activa→inactiva→reactivada.

### C4 — Retiro residual y validación de etapa

Retirar código, tablas, settings, rutas, pruebas y documentos operativos de familias y dossier. Ejecutar validación integrada de combinaciones y borrar la instalación local de prueba si sigue existiendo.

**Aceptación de etapa:** no hay una dependencia funcional o conceptual de familias; una combinación válida de capabilities existentes, excepto dossier/expedientes, es editable y funciona correctamente en listas y registros.

## Riesgos y controles

El mayor riesgo no es migrar datos: está autorizado no conservarlos. Es liberar combinaciones que hoy esconden supuestos verticales. Por eso C1 no abre todavía el editor; C2 convierte esos supuestos en contratos verificables; C3 los expone al usuario. Cada ciclo debe conservar pruebas de autorización, integridad de contenedor/registro y cierre de proyección.

## Etapa posterior, deliberadamente separada

Clasificadores, etiquetas, categorías, búsqueda y navegación facetada requieren una conceptualización de producto propia. No se adelantan como sustituto de Family ni se introducen en URLs durante Canon libre v2.
