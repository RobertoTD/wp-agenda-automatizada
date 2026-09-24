# Capacidades canónicas de DEO

**Vigencia:** permanente desde Canon libre v2.

**Autoridad:** norma vinculante para definición, compatibilidad, configuración, activación, datos y proyección de capabilities. Complementa `docs/04-canonical-constitution.md`.

**Estado de transición:** Family ya no participa en el camino canónico activo. Las capabilities existentes están desregistradas y este documento conserva principios de diseño para una etapa futura; no habilita implementación, catálogo, editor ni proyecciones durante Fundación horizontal v2.

## 1. Propósito

Las capabilities expresan características reutilizables sobre listas y registros canónicos sin crear tipos de lista ni duplicar el CRUD base. Una capability puede aportar datos, validación, lectura, escritura, vistas, acciones, formularios y presentación; el shell no codifica su clave ni sus reglas.

## 2. Capas válidas

No confundir estas capas:

1. **Definición registrada en código.** Clave estable, label, estado `known`/`ready`, alcance, contrato de datos, lifecycle y contribuciones.
2. **Compatibilidad declarada.** Dependencias o incompatibilidades específicas que deben evaluarse para una combinación. Si no hay una regla declarada, no existe veto por una antigua familia o dominio.
3. **Configuración persistida de la lista.** La lista es la única unidad de asignación y activación efectiva.
4. **Valores o recursos tipados.** Datos de registros, lista o ambos, separados de la activación.

No hay repertorio, default, herencia dinámica ni enablement por familia. Una lista recién creada no materializa capabilities de forma implícita.

## 3. Elegibilidad y combinación

Cuando una etapa futura habilite configuración de capabilities, el servidor sólo podrá admitir una selección si todas las claves son `known + ready` y el conjunto final satisface las dependencias e incompatibilidades declaradas. La validación se hará sobre el conjunto resultante, no sobre el formulario parcial ni sobre lo que el cliente afirme.

La futura interfaz deberá mostrar las capabilities elegibles, sus dependencias y los bloqueos comprensibles. Una capability no lista para producción no se ofrecerá. Una restricción de cuota, permisos o integridad también se explicará como bloqueo de operación, no como pertenencia de la lista a una familia.

El contrato de transporte podrá conservar `scope + selection` si sigue siendo útil, pero su semántica v2 será:

- ambos ausentes: en create, lista sin capabilities; en update, conservar configuración;
- ambos presentes: modificación explícita; `selection` es subconjunto de `scope`;
- cada clave de `scope` debe ser `known + ready` o ya estar asignada solo para permitir su desactivación/lectura durante una retirada controlada;
- la validación de compatibilidad se hace antes de persistir; el servidor nunca completa defaults ocultos.

La forma exacta de snapshots, errores y DTOs se decidirá en una etapa de conceptualización propia, sin alterar estos principios.

## 4. Activación, datos y lifecycle

- Activar habilita la proyección y las escrituras definidas por la capability.
- Desactivar conserva valores y recursos; retira campos, valores, acciones, scripts, filtros, vistas, etiquetas, enlaces y navegación de esa capability; rechaza nuevas escrituras.
- Reactivar recupera los valores y recursos conservados según su contrato.
- Editar `title` o `details` nunca elimina valores de una capability desactivada.
- Una URL que seleccione una vista de capability conocida pero inactiva vuelve de forma segura a la vista base; una clave desconocida sigue siendo inválida.

Todo ciclo futuro de capability deberá probar `activa → inactiva → reactivada` sobre la misma lista.

## 5. Datos y presentación

Los datos son tipados y explícitos. Una capability puede usar una o varias tablas propias, recursos externos o agregados, pero declara su identidad, permisos, operaciones, borrado, recuperación e interacción con el borrado de lista/registro.

El Shell administrativo sólo podrá recibir contribuciones tipadas para slots comunes. No recibirá HTML, callbacks, SQL ni rutas ejecutables desde la base. El puente de presentación legacy queda limitado a correcciones; no se crea un contrato nuevo de presentación hasta completar el shell limpio.

Las vistas, filtros y criterios de records se componen antes de contar/paginar y solo por providers activos. El buscador y la clasificación facetada son una etapa de producto futura, fuera de esta norma operativa.

## 6. Capabilities existentes

Las claves existentes son candidatas históricas, no propietarias de una familia: `amount`, `images`, `phone`, `whatsapp`, `email` y `completed`. No forman un catálogo activo ni una promesa de que se conservará su estructura actual.

Las referencias históricas que limitaban `amount` a Finanzas, `completed` a Acciones o `images` a una matriz de familias quedan supersedidas. Una exploración futura inventariará sus contratos reales y decidirá si alguna se rediseña, se retira o se conserva antes de abrir selección alguna en UI.

### `amount`

`amount` conserva su contrato de dato: `decimal(19,2)` nullable firmado, normalización canónica, cero válido, omitir=conservar y vacío conocido=clear. Su total es una proyección derivada de todos los registros de la lista: solo se presenta si `amount` está activa; sin valores muestra `0.00`; un agregado ilegible se declara no disponible. No implica moneda, contabilidad, conversiones ni varios importes.

### `images`

`images` conserva almacenamiento tipado, admisión, acceso, cuotas, borrado deliberado y recuperación definidos en su plan especializado. Su disponibilidad ya no dependerá de una familia. Desactivarla conserva recursos; eliminar un registro o lista sigue aplicando su lifecycle de retiro/purge, incluso si está inactiva.

### `completed`, canales y otros paquetes

`completed`, `phone`, `whatsapp` y `email` se someten al mismo contrato global. Antes de declararlos seleccionables en la interfaz v2 se verifican sus dependencias, acciones, vistas y cierres de proyección. No se les atribuye una semántica de tipo de lista.

## 7. Estado implementado y trabajo diferido

Hoy `DB_VERSION=39` deja en el camino activo sólo listas y registros sin Family; capabilities y `contact_dossier` están desregistrados de la superficie C1. El reset local de `policyytest` eliminó sus tablas v1 sin backfill.

La adaptación de capabilities está deliberadamente diferida: primero se termina el núcleo y shell limpio conforme a `docs/plans/canonical-horizontal-foundation-v2.md`. Después se abrirá una exploración independiente; este documento no adelanta su alcance ni su implementación.
