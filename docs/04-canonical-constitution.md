# Instrucciones permanentes — Canon de DEO

**Vigencia:** permanente.

**Autoridad:** fuente vinculante de la arquitectura canónica de DEO.

**Regla documental:** no duplicar, resumir, reinterpretar ni reescribir esta constitución en otros archivos. Los demás documentos solo deben referenciarla.

## Propósito rector

Construir y preservar una arquitectura canónica capaz de representar, operar, compartir y consultar las distintas familias de DEO mediante una organización común.

El shell administrativo, el futuro runtime público y la futura API/CLI deben consumir el mismo contrato canónico de datos. El HTML es una representación; nunca es la fuente de verdad.

## Modelo canónico invariable

Toda familia integrada al canon se organiza en dos niveles:

Contenedor
└── Registros

Contenedores y registros poseen siempre:

title, obligatorio.
details, opcional y nullable.

Un registro pertenece obligatoriamente a un contenedor.

Toda referencia canónica debe permitir identificar:

family_key;
tipo de recurso: container o record;
identificador;
container_id cuando sea un registro.

## Persistencia canónica universal

Las familias canónicas comparten una única persistencia lógica base:

canonical_families;
canonical_containers;
canonical_records.

Los nombres físicos llevan el prefijo técnico de la instalación (actualmente planteados como `aa_canonical_families`, `aa_canonical_containers` y `aa_canonical_records`).

No existe una pareja de tablas por familia.

La tabla de contenedores identifica la familia. Un registro pertenece obligatoriamente a un contenedor y hereda de él su familia.

Las tablas base almacenan exclusivamente estado y campos universales del contrato. No admiten columnas particulares de ninguna familia (`amount`, imágenes, SKU, teléfonos, datos de agenda u equivalentes), ni JSON genérico, ni EAV, ni payloads arbitrarios como sustituto de persistencia tipada.

Toda característica no universal se implementa como capability con persistencia tipada propia, referida al contenedor o al registro. Una capability se implementa una vez y puede contribuir a persistencia, validación, formularios, cards, API y runtime público.

Las definiciones de familia son contratos de producto declarados en código. La base de datos guarda estado de instalación y habilitación, contenedores y registros; nunca clases, callbacks, SQL ni definiciones ejecutables.

Los timestamps técnicos de las tablas canónicas universales se almacenan en UTC. UTC es la fuente de verdad; la conversión a la zona configurada ocurre en la presentación.

La arquitectura debe preservar compatibilidad cercana con API, exportación e importación eficientes, identidades públicas estables y un runtime público de solo lectura que proyecte por enlace un registro o contenedor y sus registros autorizados, sin modificar la instalación de origen. Esa compatibilidad es una barrera arquitectónica: no autoriza implementar ahora API, sharing, tokens, snapshots, permisos públicos ni infraestructura del runtime, ni fija todavía si una compartición será snapshot o proyección viva.

Una familia implementada antes de esta regla puede conservar temporalmente tablas propias. Esa persistencia es implementación legacy: no constituye el nuevo canon, no autoriza dual-write ni backfill, y no debe proyectarse al shell nuevo sin un ciclo explícito.

## Separación de responsabilidades

### Shell

El shell contiene únicamente comportamiento universal:

routing por familia;
encabezados contextuales;
cards base;
navegación contenedor–registros;
paginación;
FAB;
CRUD estándar;
modales base;
estados de carga, vacío y error;
accesibilidad;
coordinación de mutaciones;
locks y manejo de resultados inciertos.

El shell no debe conocer nombres, campos, tablas, endpoints ni reglas particulares de ninguna familia.

### Familia

La familia define:

semántica de negocio;
su definición;
Application y adaptadores;
validaciones;
capabilities disponibles o predeterminadas.

La familia no define ni posee su propia persistencia base. Usa la persistencia canónica universal y expresa sus características particulares mediante capabilities.

Ninguna familia es el shell. La primera familia implementada tampoco define por sí sola el canon.

## Capabilities

Una capability representa una característica no universal.

Debe contemplarse como contrato vertical:

estructura de datos;
validación;
persistencia;
Application;
API;
UI administrativa;
representación pública.

Un booleano puede activar una capability, pero no constituye por sí mismo su implementación.

Una característica particular nunca debe añadirse al shell base solamente porque la primera familia la utiliza.

## Contrato canónico común

Cada familia implementa un adaptador que transforma sus datos al mismo contrato canónico.

El shell, el runtime público y la API no deben acceder directamente a tablas ni repositorios de familia. Deben consumir servicios de Application o un gateway canónico.

La estructura común debe permanecer estable; los datos particulares se exponen mediante extensiones identificadas por su capability.

## Consumidores

La arquitectura debe permitir tres consumidores del mismo contrato:

Shell administrativo: lectura y mutaciones autorizadas.
Runtime público: representación compartida, inicialmente de solo lectura.
API/CLI: datos estructurados mediante JSON y autorización por API key.

Estos consumidores pueden tener permisos y presentaciones diferentes, pero no modelos de datos incompatibles.

La futura compartición de contenedores o registros debe conservar su identidad canónica completa y no depender de una URL interna de administración.

## Interfaces especializadas

Una familia puede conservar interfaces especializadas, como calendario, timeline o galería.

La interfaz especializada puede coexistir con la vista canónica. No debe obligarse al shell universal a absorber comportamientos que solo corresponden a esa familia.

Una familia se integra al canon únicamente cuando puede proyectar coherentemente sus datos como contenedores y registros.

## Reglas de contención

Antes de proponer o implementar cualquier cambio:

Clasificarlo explícitamente como shell, familia, capability, runtime o transporte.
Detenerse si la responsabilidad no puede clasificarse claramente.
No colocar código particular de una familia dentro del shell.
No duplicar en cada familia una función que pertenece al shell.
No convertir una diferencia de UI en capability sin considerar datos, backend, API y runtime.
No diseñar el canon alrededor de la única familia existente.
No hacer que UI, runtime o API consulten directamente la base de datos.
No sacrificar el contrato futuro de API y compartición para simplificar una implementación inmediata.
Preferir evolución incremental mediante adaptadores; evitar reescrituras totales.
Si un ciclo mejora una familia pero aleja el sistema de esta arquitectura, detenerlo y corregir la propuesta antes de implementar.

## Criterio máximo

El proyecto conserva su dirección mientras cualquier familia compatible puede:

registrar su estructura;
resolverse por `family_key` en el shell universal (sin `variant_key` en contratos, URLs ni persistencia `aa_canonical_*`);
usar el shell sin copiarlo;
añadir capabilities sin contaminarlo;
proyectar sus datos al contrato canónico;
ser representada en administración, runtime público y API mediante la misma identidad y organización.

La identidad de un recurso canónico (contenedor o registro) requiere `family_key`. El alcance de consulta de un listado puede ser general (`module=canonical_shell` sin `family`) sin que eso sustituya ni infiera la identidad de un recurso concreto.
