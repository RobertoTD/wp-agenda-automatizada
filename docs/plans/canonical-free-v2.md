# Canon libre v2 — cierre de transición Family

**Estado:** histórico hasta C1; sustituido para trabajo nuevo por `docs/plans/canonical-horizontal-foundation-v2.md`.

**Autoridad:** registro de la transición que retiró Family. No autoriza trabajo posterior de capabilities, runtime, clasificadores ni búsqueda.

## Resultado alcanzado

La transición C0–C1 dejó la instalación local `policyytest` (`blog_id=61`) con `DB_VERSION=39`, listas y registros canónicos sin Family y una raíz única `module=canonical_shell`.

- Family no es identidad, parámetro de URL, navegación ni requisito de CRUD.
- Las tablas canónicas locales pudieron reiniciarse sin backfill; no hay datos locales que preservar.
- `contact_dossier` y las capabilities quedaron desregistrados de la superficie activa.
- El acceso sigue restringido a `manage_options`.

La interfaz C1 prueba la persistencia y el CRUD, pero es deliberadamente mínima. No es el shell administrativo de producto y no debe ampliarse como puente hacia el shell legacy.

## Decisiones que permanecen vigentes

- Una lista puede existir sin clasificación, preset, solution o capability.
- No hay presets en esta etapa. Una futura receta sólo podrá ser configuración inicial, nunca identidad ni autoridad continua.
- No existe excepción de conservación para fixtures o registros locales de este entorno.
- La configuración de Settings no vuelve a presentar Family ni la usa para habilitar el canon.

## Trabajo expresamente fuera de este plan

La propuesta anterior de C2–C4 —catálogo global, combinaciones, configurador y proyecciones de capabilities— queda diferida. También quedan fuera runtime público, clasificadores, etiquetas/categorías, buscador, facetas, solutions y un nuevo sistema de permisos.

Cada uno requiere su propio proceso de conceptualización y una etapa independiente. Ninguno se incorpora para completar la transición Family.

## Referencia siguiente

El trabajo activo continúa únicamente en `docs/plans/canonical-horizontal-foundation-v2.md`: núcleo canónico e integridad, shell administrativo limpio y revisión/pulido de ese shell hasta cerrar la etapa.
