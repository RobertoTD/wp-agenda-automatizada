# Debug de Overflow en Cards Expandidas

## Activar el modo debug

En la consola del navegador (dentro del iframe):

```javascript
window.AA_DEBUG_CALENDAR_OVERFLOW = true;
```

O agregar en el script de inicialización del calendario:

```javascript
window.AA_DEBUG_CALENDAR_OVERFLOW = true;
```

## Qué loguea

Cada vez que expandas o colapses una card, verás un grupo colapsado en consola:

```
[OverflowDebug] OPEN @ 14:23:45.123
  📍 Label: OPEN
  📊 Card data: {citaStartRow, citaBloquesOcupados, expanded, hasClass_expanded}
  📐 Section rect.bottom: 1234.5
  📐 Card rect.bottom: 1345.6
  ⚠️  Overflow (card.bottom - section.bottom): 111.1 px
  🎨 Section styles: {paddingBottom, marginBottom, overflow, overflowY}
  📏 Heights: {documentScrollHeight, gridScrollHeight, overlayScrollHeight, ...}
  ✂️  First clipper ancestor: {element, overflow, rectBottom} o "none (all visible)"
  📦 Overlay rect: {top, bottom, height}
  📦 Grid rect: {top, bottom, height}
```

### Si hay overflow positivo

```
🚨 OVERFLOW DETECTADO: 111.10px por debajo del section
```

Esto significa que la card expandida sobrepasa el borde inferior del `section.aa-day-timeline` por esos pixels.

### First clipper ancestor

El log busca el primer ancestro (subiendo desde la card) que tenga `overflow` o `overflowY` != `visible`. Este es el elemento que está **recortando** el contenido que se desborda.

Ejemplo:
```
✂️  First clipper ancestor: {
  element: "DIV.bg-white rounded-xl shadow border border-gray-200 overflow-hidden mt-2",
  id: "(no-id)",
  overflow: "hidden",
  overflowY: "hidden",
  rectBottom: 1234.5,
  rectHeight: 800
}
```

Esto te indica exactamente qué contenedor está cortando la card.

## Interpretación

1. **`overflowPx > 0`**: la card se sale del section por abajo
2. **`First clipper ancestor`**: ese elemento está recortando el desborde
3. **`section.marginBottom`**: margen actual del section (para calcular cuánto añadir)
4. **`documentScrollHeight` vs `gridScrollHeight`**: comparar si el grid crece correctamente y si el documento refleja ese crecimiento (importante para que el iframe envíe la altura correcta)

## Desactivar debug

```javascript
window.AA_DEBUG_CALENDAR_OVERFLOW = false;
```

o simplemente recargar la página (el flag no persiste).

## Notas

- Los logs usan `console.groupCollapsed()` para no ensuciar la consola
- Los snapshots se toman **después** de que el navegador pinta (2 `requestAnimationFrame` en OPEN, 1 en CLOSE)
- El tiempo en el label es ISO local para correlacionar eventos
