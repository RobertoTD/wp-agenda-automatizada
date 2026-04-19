# `includes/repositories/` — Acceso a base de datos

Solo SQL. Punto. Aquí vive todo lo que hoy es `includes/models/` cuando
sea SQL puro, **sin reglas de negocio**.

## Qué entra aquí

- Clases `{Cosa}Repository` (ej. `ReservationsRepository`, `AssignmentsRepository`).
- Métodos de lectura (`findById`, `getActiveOverlapping`, `searchByPhone`).
- Métodos de escritura (`insert`, `update`, `delete`).
- Conocimiento de nombres de tablas (`$wpdb->prefix . 'aa_reservas'`).
- Mapeo de filas a arrays/objetos limpios para el dominio.

## Qué NO entra aquí

- `if` que decida cosas del negocio ("si está cancelada no contar"). Eso es dominio.
- Sanitización de input HTTP (eso ya viene saneado del controller AJAX).
- Llamadas a APIs externas, LLMs, notificaciones.
- Orquestación de varios pasos del producto.

## Reglas

1. **Cero `if` de negocio.** Si necesitas un filtro semántico, lo decide el dominio
   y se traduce a parámetros del query.
2. Devuelve datos crudos (arrays asociativos o DTOs simples), no entidades del dominio.
3. Si el método empieza a "saber" algo del negocio (ej. `has_confirmed_staff_overlap`),
   se parte en dos: el SQL puro vive aquí, la regla "qué cuenta como overlap" vive en
   un Domain Service que llama a este repository.

## Migración de `includes/models/`

Los Models actuales (`AssignmentsModel`, `ReservationsModel`) seguirán existiendo
durante la transición. Los nuevos Repositories pueden envolverlos / extenderlos
sin romper nada. La migración es por contagio.

## Naming

- Archivo: `{Cosa}Repository.php` (PascalCase, plural si la entidad lo es: `Reservations`).
- Clase: idem.

Ver `docs/00-paradigm-cheatsheet.md`.
