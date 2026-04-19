# Paradigma — Cheatsheet operativo

> Documento corto, pensado para abrirse antes de cada feature.
> Para la referencia larga, ver `docs/02-architecture-principles.md`.

**Paradigma:** Hexagonal ligero + Use Cases + Single Source of Truth en PHP.
**JS proyecta. AI consume. Nadie duplica reglas.**

## Capas y dirección de dependencias (estricta, unidireccional)

```
http (AJAX)  →  application (Use Cases)  →  domain (reglas puras)  →  repositories (SQL)
                                                                  ↘  infrastructure (WP, providers)
ui (JS)      →  http (vía AJAX)
```

- `domain/` no conoce `$wpdb`, `get_option`, `error_log`, `add_action` ni nada de WordPress.
- `repositories/` solo contiene SQL. Cero `if` de negocio.
- `application/` orquesta. No define reglas nuevas.
- `http/` parsea, autentica, delega y serializa. Nada más.
- `infrastructure/` integra con WP, schema, Node backend, providers (LLM, notifs).
- `ui/` (JS) pinta y consume endpoints. No decide nada vinculante.

## Invariantes (no negociables)

1. **Una regla de negocio = un único lugar en PHP.** Si aparece dos veces, es bug.
2. **JS no decide** ocupación, colisión, precio ni estado. Solo pinta y pre-valida.
3. **Cada flujo end-to-end = 1 Use Case PHP** (`VerboCosaUseCase` con un único `execute()`).
   El controlador AJAX y el handler de AI llaman al MISMO Use Case.
4. **Models (futuros Repositories) = SQL puro.** Si un método empieza a "saber" del negocio, se mueve a un domain service.
5. **AI nunca inventa dominio.** Si necesita una regla nueva, primero se crea el service de dominio; luego el evaluator lo llama.

## Decisión rápida — "¿dónde escribo esto?"

| Si lo que voy a escribir es...                | Va en...                                |
| --------------------------------------------- | --------------------------------------- |
| Una query SQL                                 | `includes/repositories/`                |
| Una regla pura del negocio                    | `includes/domain/{contexto}/`           |
| Un flujo que orquesta varias reglas           | `includes/application/{contexto}/{Verbo}UseCase.php` |
| El handler de un request HTTP/AJAX            | `includes/http/ajax/`                   |
| Llamada a WP, Node backend, LLM, notifs       | `includes/infrastructure/{adaptador}/`  |
| Pintar DOM, calendarios, modales              | `assets/js/ui/`                         |
| Cliente HTTP que consume endpoint y cachea    | `assets/js/services/`                   |

## Naming (para archivos NUEVOS, no migramos lo viejo todavía)

- **PHP clases:** `AA_{Contexto}_{Rol}` → archivo `class-aa-{contexto}-{rol}.php`
- **Use Cases:** `{Verbo}{Cosa}UseCase` → archivo `{Verbo}{Cosa}UseCase.php` (PascalCase)
- **JS:** `camelCase.js`, `export default`

Lo viejo coexiste con su nombre histórico hasta que se toque por otra razón.

## Antes de añadir código, pregúntate:

- ¿Estoy creando una segunda fuente de verdad? → si sí, **para**.
- ¿El controller/handler tiene lógica que no sea "parsear y delegar"? → extrae a Use Case.
- ¿El JS está calculando algo que el PHP debería calcular? → mueve el cálculo a PHP.
- ¿Esta regla podría correr en CLI sin WordPress? → entonces es **dominio**, no infrastructure.
- ¿Esto representa una intención del usuario formulable como "verbo + cosa"? → es un **Use Case**.

## Glosario mínimo

- **Domain:** reglas que serían ciertas aunque WordPress no existiera. Sin SQL, sin WP. Determinista y testeable.
- **Use Case (Application):** orquestador de un flujo del producto. Una clase, un método público (`execute`). No define reglas, las coordina.
- **Repository:** acceso a BD. Solo SQL. Sin `if` de negocio.
- **Controller AJAX:** traductor HTTP↔Use Case. Autentica, sanitiza, delega, serializa.
- **Infrastructure:** todo lo que toca el mundo exterior (WP, MySQL via repos, LLMs, webhooks, notifs).
- **UI:** todo lo que pinta y captura interacción. No es fuente de verdad.
