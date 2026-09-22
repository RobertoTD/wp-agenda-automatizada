# Capability Presentation Contract v0

**Estado:** decisión aceptada; CP-0 documentado.  
**Ámbito:** solo Shell Canónico administrativo.

## Propósito

Permitir que una capability aporte presentación sin que el Shell conozca una clave concreta, una familia, una tabla o una regla vertical. No sustituye la constitución ni el contrato de datos de cada capability.

## Contrato mínimo

Una capability registrada podrá declarar en código contribuciones para slots comunes:

- acción de tarjeta;
- asset y configuración de cliente;
- vista de registros mediante el contrato de composición RVC-1.

Desde RVC-2A, cada vista registrada usa una definición tipada y declara su política de creación. El registry resuelve toggles aditivos y el shell recibe la composición; RVC-2B presenta el menú neutral y aplica la política solo a la creación.

El Shell itera contribuciones ofrecidas para la lista efectiva. La activación de la lista gobierna toda contribución conforme a `docs/05-canonical-capabilities.md` §5.1. No se guarda código, HTML, callbacks ni paths en base de datos.

Fuera de v0 inicial: campos/formularios, renderers de valores, labels de selector, orden entre acciones, runtime público y un generador universal de UI. Los campos se exploran antes de `event_date`.

El slot de metadata de card acepta solo descriptores tipados (v0: `datetime` con label y UTC). El composer los localiza y la card los itera; providers no aportan HTML ni nombres verticales al shell.

## Legacy Presentation Bridge

Los puntos heredados de cards, formulario y carga directa de assets son un puente temporal. Solo cubren `amount`, `phone`, `whatsapp`, `email` e `images`.

- No se añade allí ninguna capability, campo, acción, script, label o conducta nueva.
- Solo admite correcciones de bug, seguridad o compatibilidad que preserven el producto existente.
- `completed` usa el slot v0 de acciones, su módulo cliente registrado y el contrato de composición RVC-1.
- `contact_dossier` es una solution y no una excepción de capability; su puente heredado se tratará en un contrato de presentación de solutions posterior.

Los archivos del puente llevan un marcador explícito y una prueba estática impide incorporar una capability registrada fuera de esta lista cerrada.

## Secuencia acordada

1. **CP-0:** esta frontera documental, marcadores y prueba. **Completado.**
2. **CP-1:** registry de presentación y metadata mínima, incluido `label`; sin migrar legacy. **Completado.**
3. **CP-2:** migrar exclusivamente `completed` para acción, asset/configuración cliente y slot de acción; conservar su registry de vistas C5.1. **Completado.**
4. **CP-3:** explorar `postpone` como segundo consumidor de acciones; implementarlo solo si valida el modelo.
5. Antes de `event_date`, explorar el contrato v0 de campos/formulario.

`images` es deuda delimitada, no bloqueo: se migra cuando una modificación concreta de su experiencia lo exija o cuando el contrato de campos haya sido probado con capabilities simples.
