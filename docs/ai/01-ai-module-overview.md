# AI Module Overview

## Objetivo

Este documento define la frontera inicial del bounded context de AI dentro del plugin.

La intención es preparar una base escalable para:

- chat en lenguaje natural dentro del admin
- integración con proveedores LLM locales o remotos
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
- separar proveedor, prompts, validación y adaptación al dominio

Sub-áreas iniciales:

- `contracts/`: contratos estables entre capas
- `providers/ollama/`: integración con Ollama
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

En esta fase solo existe el esqueleto arquitectónico:

- archivos ancla
- contratos base
- clases stub
- documentación mínima

Todavía no existe:

- wiring en `wp-agenda-automatizada.php`
- endpoint activo
- conexión real con Ollama
- UI del chat renderizada dentro de calendar
- registry operativo de skills

## Fases previstas

### Fase 1

- `AA_Ollama_Client`
- endpoint `aa_admin_ai_chat`
- conexión endpoint -> Ollama
- mostrar JSON en chat básico dentro de calendar

### Fase 2

- contrato de respuesta más estable
- mapeo a comandos de citas
- ejecución segura del primer caso de uso real

### Fase 3

- skills adicionales
- registry operativo
- logging especializado
- posibles proveedores adicionales
