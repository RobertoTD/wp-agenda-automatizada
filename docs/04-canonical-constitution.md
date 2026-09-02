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
variant_key;
tipo de recurso: container o record;
identificador;
container_id cuando sea un registro.

## Persistencia por familia

Cada familia conserva su propia pareja de tablas:

aa_{family_key}_containers
aa_{family_key}_records

El prefijo de WordPress se agrega según la instalación.

La tabla de contenedores identifica la variante mediante variant_key. Los registros heredan familia y variante mediante su contenedor.

Las tablas de cada familia respetan el contrato base, pero pueden añadir columnas o tablas auxiliares para sus características particulares.

No crear una pareja universal de tablas compartida por todas las familias.

## Separación de responsabilidades

### Shell

El shell contiene únicamente comportamiento universal:

routing por familia y variante;
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
persistencia;
Application y repositorios;
adaptador canónico;
validaciones;
capabilities disponibles o predeterminadas.

Ninguna familia es el shell. La primera familia implementada tampoco define por sí sola el canon.

### Variante

La variante configura una forma reconocible de una familia. Puede ajustar:

copy;
labels;
presentación;
políticas;
capabilities activas;
configuración de capabilities.

La variante no puede romper el contrato contenedor–registro ni eliminar los campos base.

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

La interfaz especializada puede coexistir con la vista canónica. No debe obligarse al shell universal a absorber comportamientos que solo corresponden a esa familia o variante.

Una familia se integra al canon únicamente cuando puede proyectar coherentemente sus datos como contenedores y registros.

## Reglas de contención

Antes de proponer o implementar cualquier cambio:

Clasificarlo explícitamente como shell, familia, variante, capability, runtime o transporte.
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
resolver una variante;
usar el shell sin copiarlo;
añadir capabilities sin contaminarlo;
proyectar sus datos al contrato canónico;
ser representada en administración, runtime público y API mediante la misma identidad y organización.
