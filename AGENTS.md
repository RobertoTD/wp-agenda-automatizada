# AGENTS

Instrucciones para cualquier agente que trabaje en este repositorio.

## Referencias operativas generales

- Capas PHP/JS y reglas de contagio: `.cursor/rules/paradigm.mdc`, `docs/00-paradigm-cheatsheet.md` y `docs/02-architecture-principles.md`.
- Tests JavaScript (plugin): `.cursor/rules/safe-js-tests.mdc` y `scripts/safe-node-test.sh` (`tests/js/*.test.js`).
- Tests JavaScript (deoia-oauth-backend, multi-root): `deoia-oauth-backend/scripts/safe-node-test.sh` (`tests/*.test.js`). El hook `.cursor/hooks/guard-js-tests.py` reconoce ambas vías acotadas; no usar `npm test` / `node --test` directos.

## Canon DEO — lectura condicional

Si el trabajo pertenece al ámbito canónico —canon, capability, solution, adaptador, gateway, contrato contenedor–registro, Shell Canónico o cambios de sidebar/router/shared canónicos—:

1. Lee primero `docs/04-canonical-constitution.md`, fuente autoritativa permanente del canon.
2. Si el encargo toca capabilities (asignación, configuración, activación, valores, `amount`, imágenes u otras), lee también `docs/05-canonical-capabilities.md`, desarrollo normativo del sistema de capacidades.
3. Si el encargo toca solutions (registro, aplicación, relaciones, acciones, vistas o lifecycle), lee también `docs/06-canonical-solutions.md`.
4. Mientras se construya Fundación horizontal canónica v2, lee también `docs/plans/canonical-horizontal-foundation-v2.md`, que es un plan temporal y no una constitución. Consulta `docs/plans/canonical-free-v2.md` sólo para antecedentes del retiro de Family.
5. No copies, resumas, reescribas ni dupliques esos documentos.
6. Si existe un conflicto con el cheatsheet, README o código vigente, repórtalo y distingue destino normativo de estado transitorio. No reintroduzcas familia como identidad, ruta, autorización o restricción de capabilities. No uses el módulo Finance legacy retirado como referencia operativa.
7. Capabilities, presentación extensible, filtros/vistas particulares y sus contratos legacy están diferidos hasta una etapa propia. No los reactives ni los uses para recuperar comportamiento del shell. Si en el futuro se autoriza una capability, aplica entonces el cierre de proyección de `docs/05-canonical-capabilities.md`: prueba activa → inactiva → reactivada y ausencia total de Family como sustituto de activación.
8. Durante FH-1 y FH-2, el shell sólo admite datos y comportamientos universales. No añadas comportamiento al Legacy Presentation Bridge ni leas planes legacy de presentación o composición como autoridad de diseño nuevo; sólo pueden orientar una corrección de compatibilidad expresamente pedida.

Fuera de ese ámbito, no cargues esos documentos.
