# Fundación horizontal canónica v2

**Estado:** plan activo.

**Autoridad:** ruta de implementación temporal. La constitución vive en `docs/04-canonical-constitution.md`.

## Objetivo de etapa

Construir y estabilizar el corazón administrativo universal de DEO: listas y registros canónicos sin Family, sin capability, sin clasificación y sin semántica vertical.

La etapa termina cuando el shell limpio sea una superficie satisfactoria de producto para crear, leer, editar y eliminar listas y registros universales. El resultado será la base sobre la cual se conceptualizarán más adelante otros consumidores y extensiones, pero no los anticipa ni los implementa.

## Límites cerrados

- La raíz es siempre `module=canonical_shell`; no hay family switcher, filtro ni URL con `family`.
- El modelo visible sólo incluye `title`, `details`, identidad, pertenencia registro→lista y metadatos universales necesarios.
- El shell no contiene nombres, datos, acciones, vistas, filtros, assets ni reglas de capabilities.
- No se desarrolla runtime público, API/CLI, clasificadores, categorías, etiquetas, búsqueda, navegación facetada, presets, solutions ni roles adicionales.
- `contact_dossier` permanece retirado.
- Acceso administrativo: `manage_options`.
- No se conserva compatibilidad ni backfill de datos canónicos locales anteriores.

## Guardas de diseño provisionales

No se inicia un rediseño de marca ni se fijan tokens definitivos en esta etapa. Hasta esa decisión:

- no se introducen nuevos acentos violetas;
- títulos y enlaces de contenido se presentan como texto neutro, no como una acumulación de enlaces azules;
- hover, foco y confirmación de interacción se resuelven primero con superficie, contraste y sombra; los acentos azules se reservan para información o acciones realmente prioritarias.

Estas guardas no autorizan una nueva paleta ni cambios cosméticos aislados fuera del shell.

## Ciclos de la etapa

### FH-0 — Constitución y corte de ruta

Documentar el cierre de Canon libre C1, sustituir su ruta de capabilities, declarar la fundación horizontal y alinear las guías operativas. No modifica PHP, schema, datos ni UI.

**Aceptación:** ningún documento activo presenta capabilities como siguiente entrega de esta etapa, ni presenta runtime, clasificadores o búsqueda como trabajo autorizado; la documentación describe `DB_VERSION=39` y el shell C1 como prueba mínima, no como producto final.

### FH-1 — Núcleo canónico e integridad

Definir y probar el contrato de Application/persistencia que sirve al shell: lectura, paginación, creación, edición y eliminación de listas y registros, autorización `manage_options`, resultados de mutación y pertenencia obligatoria de cada registro a su lista. Resolver las invariantes de integridad que el CRUD mínimo C1 aún no expresa de forma durable.

No rediseña pantalla ni reutiliza el flujo Family/capability legacy.

**Aceptación:** el shell puede consumir un único contrato horizontal y las operaciones preservan las invariantes de lista–registro con pruebas proporcionales.

### FH-2 — Shell administrativo limpio

Construir sobre FH-1 la interfaz universal:

- raíz de todas las listas, estado vacío, cards/listado, paginación y navegación lista→registros;
- detalle de lista y listado compacto de registros con detalles desplegables y metadatos universales;
- FAB, modales y menús de opciones para crear, editar y eliminar listas/registros;
- errores, foco, Escape, confirmaciones, estados inciertos y accesibilidad básicos.

Se pueden extraer patrones visuales o de interacción del código legacy sólo si se desacoplan por completo de Family, capability y solution. No se reutiliza un formulario, card o ruta cuya semántica dependa de ellas.

**Aceptación:** un administrador completa el CRUD de una lista vacía y de sus registros desde un shell coherente, accesible y visualmente neutral, sin configuración vertical oculta.

### FH-2R — Revisión y cierre de diseño del shell

Iterar exclusivamente sobre hallazgos reales de uso y validación visual de FH-2 hasta que la experiencia universal sea satisfactoria. Corregir semántica, jerarquía, copy, estados y detalles de interacción; no abrir una capability para justificar una superficie nueva.

**Aceptación de etapa:** el shell limpio puede considerarse estable como consumidor administrativo del canon y no conserva dependencias funcionales o conceptuales de Family, capability o solution.

## Etapas posteriores, no planificadas aquí

Runtime público, clasificadores, búsqueda y capabilities quedan explícitamente fuera. Cada uno comenzará con exploración y conceptualización de producto propias; ningún orden entre ellos está decidido por este documento.

## Riesgos y controles

El riesgo principal es reintroducir legado vertical para recuperar rápidamente apariencia o modales. FH-1 separa primero el contrato horizontal; FH-2 sólo consume ese contrato. La autorización de reset local elimina el riesgo de migración de datos, pero no el de integridad: ésta debe comprobarse antes de confiar el shell a usuarios.
