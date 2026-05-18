# 03 — Visión de la feature de IA

> Vivo. Lo técnico interno está en `docs/ai/01-ai-module-overview.md`
> y `docs/ai/02-ai-chat-contract.md`. Aquí solo: visión, encaje con el
> paradigma, scope, preguntas abiertas.

## Objetivo

Chat en lenguaje natural entre el dueño del negocio y el gateway AI del
backend Node, que centraliza proveedor, cuotas y seguridad. Escalable a
múltiples skills.

## Estado real

Existe un bounded context AI con controller, chat service, intent handler,
5 resolvers (cliente, datetime, servicio, staff, zona), 4 feasibility
evaluators, un cliente backend tras `interface-aa-llm-client`, mapper a
dominio, prompts versionados, skill registry y la skill
`create_appointment`. Detalle en `docs/ai/01-ai-module-overview.md`.

## Encaje con el paradigma

El **diseño interno** del bounded context (interfaz LLM, gateway backend,
mappers, prompts versionados, skills) ya es coherente
con el paradigma hexagonal. El **sitio físico** está en zonas legacy:

- `includes/controllers/ai/` → destino `includes/http/ajax/`
- `includes/services/ai/` → destino reparto por rol:
  - Resolvers / feasibility evaluators → `includes/domain/booking/`
  - Intent handler / chat service / skills / mapper → `includes/application/ai/`
  - Gateway AI backend y prompts → `includes/infrastructure/ai/`

**Migración por contagio**: cada archivo se mueve cuando se toque por
una razón funcional. No hay reorganización en bloque.

## Scope MVP

Dentro:
- Una intent: `create_booking`.
- Wiring del endpoint y conexión real con Node `/ai/parse`.
- UI mínima del chat dentro del módulo `calendar`.
- Confirmación explícita antes de ejecutar.
- Delegación final en `CreateReservationUseCase::execute()`.

Fuera (explícito):
- Otras skills (cancelar, listar, reasignar, crear cliente).
- Multi-turno avanzado, voz, multi-idioma.
- Fallback automático entre providers.

## Reglas duras

- El LLM nunca decide reglas de negocio: las decide el dominio.
- Los feasibility evaluators son fuente de verdad de "se puede o no";
  si reimplementan reglas que ya viven en `AA_Area_Availability_Service`,
  unificar al tocarlos (contagio).
- Confirmación humana antes del insert real.

## Preguntas abiertas

1. ¿El intent handler ya delega en `CreateReservationUseCase` o duplica path?
2. ¿Los evaluators consumen `AA_Area_Availability_Service` o reimplementan?
3. ¿Qué pieza se toca primero (define qué migra primero por contagio)?
4. Persistencia del historial conversacional: ¿sesión, BD, in-memory?
5. UI dentro de calendar: ¿sidebar, modal, pestaña?

## Referencias

- Paradigma: `docs/00-paradigm-cheatsheet.md`, `docs/02-architecture-principles.md`.
- Módulo AI técnico: `docs/ai/01-ai-module-overview.md`.
- Contrato del chat: `docs/ai/02-ai-chat-contract.md`.
- Use Case destino: `includes/application/booking/CreateReservationUseCase.php`.
