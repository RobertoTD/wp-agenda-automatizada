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
7. Antes de cerrar un cambio de capability, aplica el cierre de proyección de `docs/05-canonical-capabilities.md` §5.1: prueba activa → inactiva → reactivada. La desactivación debe retirar toda proyección y escritura de la capability, conservar sus datos y dejar la lectura canónica base sin filtros, navegación ni UI de esa capability. No uses una condición de familia como sustituto de la activación efectiva de la lista.
8. Si el cambio toca presentación de capabilities en el Shell Canónico (cards, acciones, formularios, labels, assets o módulos cliente), lee también `docs/plans/capability-presentation-contract-v0.md`. No añadas comportamiento nuevo al Legacy Presentation Bridge: registra una contribución del contrato v0. El bridge solo admite correcciones de bug, seguridad o compatibilidad.
9. Si el cambio toca consulta, filtros, vistas de registros, parámetros de URL o paginación canónica, lee también `docs/plans/canonical-record-view-composition-v0.md`. Los criterios se componen por propietario; el shell no conoce claves ni SQL de capabilities.

Fuera de ese ámbito, no cargues esos documentos.
