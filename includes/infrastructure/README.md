# `includes/infrastructure/` — Integración con el mundo exterior

Todo lo que toca **WordPress, BD vía repositorios, APIs externas, LLMs,
notificaciones, schema, enqueue, cron, webhooks**. Lo que hace que el
plugin funcione *dentro de* su entorno.

## Qué entra aquí

- `wp/` — adaptadores WP: `Schema.php` (DDL/migraciones), `Enqueue.php`, `Hooks.php`, `ClockService.php` (zona horaria del negocio).
- `ai/providers/` — implementaciones concretas de proveedores LLM (Ollama, OpenAI, etc.).
- `notifications/` — envío de WhatsApp, email, push.
- `node_backend/` — cliente HTTP hacia el backend Node.
- `webhooks/` — receptores de eventos externos.

## Qué NO entra aquí

- Reglas de negocio → `domain/`.
- Flujos del producto → `application/`.
- SQL puro de tablas propias del plugin → `repositories/`.
- Endpoints AJAX del plugin → `http/ajax/`.
- UI → `assets/js/` o `includes/admin/ui/`.

## Reglas

1. Todo lo que sea "WordPress sabe esto y nadie más" vive aquí.
2. Los servicios de dominio que necesiten datos del entorno (ej. zona horaria,
   currency, feature flags) los reciben **inyectados** desde infrastructure,
   no los leen directamente.
3. Cambiar el proveedor LLM, el sistema de notificaciones o el motor de
   persistencia debería tocar **solo** archivos de esta carpeta.

## Organización sugerida

```
infrastructure/
├── wp/
│   ├── Schema.php
│   ├── Enqueue.php
│   └── ClockService.php
├── ai/
│   └── providers/
│       └── ollama/
└── notifications/
    └── whatsapp/
```

Ver `docs/00-paradigm-cheatsheet.md`.
