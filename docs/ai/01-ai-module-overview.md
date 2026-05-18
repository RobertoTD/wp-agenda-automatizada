# AI Module Overview

## Objetivo

Este documento define la frontera inicial del bounded context de AI dentro del plugin.

La intención es preparar una base escalable para:

- chat en lenguaje natural dentro del admin
- integración con el gateway AI del backend Node
- traducción de salida AI a comandos compatibles con el dominio de citas
- incorporación gradual de skills futuras

## Ubicación arquitectónica

El bounded context AI vive en:

- `includes/controllers/ai/`
- `includes/services/ai/`

No existe por ahora un módulo visual independiente en:

- `includes/admin/ui/modules/ai/`

La UI inicial del chat deberá vivir dentro del módulo real de uso:

- `includes/admin/ui/modules/calendar/`

Esto evita crear una pantalla separada antes de que exista un caso de uso validado.

## Principios de separación

### Controller

`includes/controllers/ai/`

Responsabilidad:

- exponer endpoints del chat admin
- validar permisos y nonces
- delegar al servicio de chat

No debe:

- llamar SQL
- construir prompts
- conocer el DOM del calendario

### Services AI

`includes/services/ai/`

Responsabilidad:

- contener la lógica del bounded context AI
- separar gateway backend, prompts, validación y adaptación al dominio

Sub-áreas iniciales:

- `contracts/`: contratos estables entre capas
- `providers/backend/`: integración HMAC con Node `POST /ai/parse`
- `chat/`: caso de uso del chat admin
- `prompts/`: prompts de sistema
- `mappers/`: adaptación de salida AI al dominio de citas
- `skills/`: espacio reservado para capacidades futuras

## Reglas arquitectónicas

- El proveedor LLM no conoce lógica de citas.
- El proveedor LLM no conoce UI ni endpoints AJAX.
- El chat service no ejecuta SQL ni renderiza UI.
- Los mappers aíslan el formato de salida del modelo del dominio interno.
- Los archivos reservados no deben cargarse hasta que exista un caso de uso real.

## Estado actual

El chat admin usa el backend Node como único camino de inferencia:

- endpoint AJAX `aa_admin_ai_chat`
- `AA_Backend_LLM_Client` hacia `POST /ai/parse`
- prompt y lógica conversacional en `AA_Admin_AI_Chat_Service`
- registry de skills reservado para fases futuras

## Fases previstas

### Fase 1

- `AA_Backend_LLM_Client`
- endpoint `aa_admin_ai_chat`
- conexión endpoint -> Node `/ai/parse`
- mostrar respuesta estructurada en chat dentro de calendar

### Fase 2

- contrato de respuesta más estable
- mapeo a comandos de citas
- ejecución segura del primer caso de uso real

### Fase 3

- skills adicionales
- registry operativo
- logging especializado
- posibles proveedores adicionales
