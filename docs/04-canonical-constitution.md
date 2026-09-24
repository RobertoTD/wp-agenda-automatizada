# Instrucciones permanentes — Canon de DEO

**Vigencia:** permanente desde Canon libre v2.

**Autoridad:** fuente vinculante de la arquitectura canónica de DEO.

**Estado de transición:** esta constitución define el destino obligatorio. El código y el esquema que todavía requieren `family_key`, `family_id` o enablement de familias son legado v1 pendiente de retiro; no convierten esas restricciones en norma.

## Propósito rector

DEO organiza información mediante una superficie canónica universal: listas y sus registros. Una lista puede existir, crearse, editarse y operar sin pertenecer a una categoría, familia, preset, solution ni capability.

El shell administrativo, un futuro runtime público y una futura API/CLI consumen el mismo contrato canónico. HTML y URLs son proyecciones, nunca la fuente de verdad.

## Modelo canónico invariable

```text
Lista (contenedor)
└── Registros
```

Listas y registros poseen siempre `title` obligatorio y `details` opcional/nullable. Un registro pertenece obligatoriamente a una lista.

La identidad canónica de un recurso contiene solamente su tipo, identificador y, cuando corresponda, `container_id`. No requiere ni infiere `family_key`, `variant_key`, preset, clasificación ni capability activa.

## Persistencia canónica universal

La persistencia base contiene solo dos entidades lógicas:

```text
canonical_containers
canonical_records
```

Los nombres físicos llevan el prefijo técnico de la instalación. Las tablas base almacenan únicamente estado y campos universales; no admiten columnas verticales, JSON genérico, EAV ni payloads arbitrarios como sustituto de persistencia tipada.

Cada característica no universal se implementa como capability con contrato, validación y persistencia tipada propios. Su asignación y activación por lista se rigen exclusivamente por `docs/05-canonical-capabilities.md`.

Las familias, su enablement y su catálogo no forman parte de la identidad, autorización, persistencia base, URL, navegación ni creación canónica. Eliminar sus tablas y registros locales sin backfill es una migración aceptada para este entorno de desarrollo.

Los timestamps universales se guardan en UTC; la zona configurada solo transforma la presentación.

## Shell canónico

El shell contiene comportamiento universal: raíz única de listas, navegación lista–registros, CRUD base, modales, FAB, paginación, estados de carga/vacío/error, accesibilidad, coordinación de mutaciones y manejo de resultados inciertos.

La raíz es `module=canonical_shell`, sin parámetro `family`. Las URLs de lista y registro se identifican por sus IDs canónicos. Un parámetro de capability puede seleccionar una vista registrada, pero no crea identidad ni habilita una capability.

El shell no conoce nombres, tablas, endpoints ni reglas de una capability. Recibe contribuciones declaradas por contratos de Application/presentación y las proyecta solo cuando la lista las tiene activas.

## Capabilities

Una capability es una característica reutilizable no universal. Se declara en código, con estado de disponibilidad técnica (`known` y `ready`), alcance, datos tipados, lectura, escritura, lifecycle, contribuciones de presentación y —solo cuando sea necesario— dependencias o incompatibilidades explícitas.

Una lista nueva parte sin capabilities activas. El usuario autorizado puede configurar en su creación o edición cualquier combinación válida de capabilities `known + ready`. La ausencia de una regla explícita significa que no existe una exclusión conceptual por dominio; las restricciones deben ser mecánicas, visibles y justificadas por compatibilidad, datos o seguridad.

Desactivar conserva valores y recursos, retira toda su proyección y rechaza escrituras nuevas de esa capability. Reactivar recupera la proyección conforme a su contrato.

## Solutions y presets

Una solution coordina varias piezas para una experiencia instalada; no es una capability ni una tercera entidad base. No hay solutions activas en esta etapa: `contact_dossier` y sus recursos se retiran sin rediseño ni conservación.

Un preset sería, en el futuro, una receta opcional que materializa una configuración inicial de lista. No es identidad, autoridad continua ni una clase de lista. No se construye infraestructura de presets en esta etapa.

## Barreras arquitectónicas

La arquitectura debe conservar una evolución eficiente hacia API, exportación/importación, identidades públicas estables y runtime público de solo lectura. Esta barrera no autoriza implementar ahora esas superficies, clasificadores, buscador facetado, presets, soluciones ni un sistema de permisos distinto de `manage_options`.

## Reglas de contención

Antes de cambiar el canon, clasificar el trabajo como núcleo horizontal, shell, capability, solution, transporte o consumidor futuro. Detenerse si no puede clasificarse.

- No introducir una condición de familia como sustituto de compatibilidad o activación efectiva.
- No poner código particular de una capability en el shell.
- No agregar una capability al contrato base solo porque una lista la usa.
- No persistir código, callbacks, SQL o definiciones ejecutables.
- No usar una URL, clasificación o preset como autoridad sobre la configuración vigente de una lista.
- No anticipar clasificadores o búsqueda facetada dentro de esta etapa.

## Criterio máximo

El canon conserva su dirección cuando cualquier lista puede nacer vacía, recibir una combinación válida de capabilities y seguir siendo legible, editable y navegable por su identidad canónica, sin que un origen de configuración determine permanentemente lo que la lista es.
