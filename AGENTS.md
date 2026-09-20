# AGENTS

Instrucciones para cualquier agente que trabaje en este repositorio.

## Referencias operativas generales

- Capas PHP/JS y reglas de contagio: `.cursor/rules/paradigm.mdc`, `docs/00-paradigm-cheatsheet.md` y `docs/02-architecture-principles.md`.
- Tests JavaScript (plugin): `.cursor/rules/safe-js-tests.mdc` y `scripts/safe-node-test.sh` (`tests/js/*.test.js`).
- Tests JavaScript (deoia-oauth-backend, multi-root): `deoia-oauth-backend/scripts/safe-node-test.sh` (`tests/*.test.js`). El hook `.cursor/hooks/guard-js-tests.py` reconoce ambas vías acotadas; no usar `npm test` / `node --test` directos.

## Canon DEO — lectura condicional

Si el trabajo pertenece al ámbito canónico —canon, familia, capability, solution, adaptador, gateway, contrato contenedor–registro, Shell Canónico o cambios de sidebar/router/shared destinados a vestir familias canónicas—:

1. Lee primero `docs/04-canonical-constitution.md`, fuente autoritativa permanente del canon.
2. Si el encargo toca capabilities (asignación, configuración, activación, valores, `amount`, imágenes u otras), lee también `docs/05-canonical-capabilities.md`, desarrollo normativo del sistema de capacidades.
3. Si el encargo toca solutions (registro, aplicación, relaciones, acciones, vistas o lifecycle), lee también `docs/06-canonical-solutions.md`.
4. Mientras se construya Shell Base v1, lee también `docs/plans/shell-canonical-base-v1.md`, que es un plan temporal y no una constitución.
5. No copies, resumas, reescribas ni dupliques esos documentos.
6. Si existe un conflicto con el cheatsheet, README o código vigente, repórtalo y detente. No lo resuelvas reinterpretando la constitución ni el documento de capacidades o solutions. No uses el módulo Finance legacy retirado como referencia operativa.

Fuera de ese ámbito, no cargues esos documentos.
