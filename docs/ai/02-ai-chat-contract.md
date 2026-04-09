# AI Chat Contract

## Propósito

Este documento describe el contrato inicial esperado para el flujo `admin chat -> chat service -> provider`.

Es un contrato de trabajo para la siguiente fase. No implica que el endpoint ni el cliente Ollama ya estén activos.

## Flujo objetivo

1. La UI del calendario envía un mensaje del usuario admin.
2. El endpoint `aa_admin_ai_chat` valida permisos, nonce y payload.
3. El validator normaliza el request.
4. El chat service construye contexto y prompt.
5. El proveedor LLM ejecuta la inferencia.
6. La respuesta vuelve a la UI para mostrarse inicialmente como JSON.

## Request lógico esperado

```json
{
  "message": "Agendame una cita mañana a las 4 con Juan Perez para masaje",
  "context": {
    "surface": "calendar-admin-chat",
    "timezone": "America/Mexico_City"
  }
}
```

## Respuesta inicial esperada

Durante la primera integración, la respuesta puede mantenerse casi cruda para inspección:

```json
{
  "provider": "ollama",
  "model": "qwen2.5:3b",
  "raw": {},
  "text": "",
  "parsed": null
}
```

## Reglas del contrato

- El request HTTP no debe hablar directamente con clases del dominio de citas.
- El controller solo orquesta, no interpreta intención de negocio.
- El chat service no debe depender del DOM ni de HTML.
- El proveedor devuelve datos del runtime LLM, no comandos de negocio finales.
- El mapeo al dominio de citas ocurrirá en una capa separada.

## Evolución esperada

Más adelante este contrato podrá extenderse con:

- metadatos de tool or skill
- intent detectada
- objeto normalizado para creación de cita
- validaciones de seguridad por acción

No se debe introducir esa complejidad hasta que la primera vuelta de chat en calendar esté funcionando.
