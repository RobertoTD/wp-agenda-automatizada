# WP Agenda Automatizada - Design System Brief

Este documento define el sistema visual consistente para toda la aplicación.
Consultar antes de crear o refactorizar componentes UI.

---

## 1. Filosofía de Diseño

**Estilo**: Dashboard SaaS moderno
**Base visual**: Neutros (grises) como base, color solo como acento
**Sensación**: Profesional, limpio, pulido, de calidad

---

## 2. Tokens de Diseño

### 2.1 Colores

#### Neutros (Base)
```
--aa-gray-50:  #f9fafb   /* Fondos muy claros */
--aa-gray-100: #f3f4f6   /* Fondos cards */
--aa-gray-200: #e5e7eb   /* Bordes principales */
--aa-gray-300: #d1d5db   /* Bordes secundarios */
--aa-gray-400: #9ca3af   /* Texto auxiliar */
--aa-gray-500: #6b7280   /* Texto secundario */
--aa-gray-600: #4b5563   /* Texto normal */
--aa-gray-700: #374151   /* Texto enfatizado */
--aa-gray-800: #1f2937   /* Títulos */
--aa-gray-900: #111827   /* Texto muy oscuro */
```

#### Primary / Marca (Acento principal — Índigo)
El color primario de la app es un **índigo desaturado y oscuro** (no el azul brillante).
Se usa en acciones primarias (botones sólidos), estados interactivos (toggles / switches / checkboxes),
focus rings, y el resaltado de navegación activa.

**Regla adoptada — toggles y checkboxes:** el estado *checked* / *on* usa siempre
`--aa-indigo-600` (`bg-indigo-600` / `peer-checked:bg-indigo-600`). No usar emerald,
verde de éxito u otros acentos para el track activo de un switch.
```
--aa-indigo-50:  #eef2ff   /* Fondos/hover muy suaves, nav activa */
--aa-indigo-100: #e0e7ff   /* Chips/íconos de marca */
--aa-indigo-200: #c7d2fe   /* Bordes sutiles de botón ghost de marca */
--aa-indigo-300: #a5b4fc   /* Estado disabled de botón primario */
--aa-indigo-500: #6366f1   /* Focus ring, badges pequeños */
--aa-indigo-600: #4f46e5   /* Fondo botón primario / track ON de toggle-checkbox / acento base */
--aa-indigo-700: #4338ca   /* Hover botón primario */
--aa-indigo-800: #3730a3   /* Active/pressed botón primario */
```

#### Estados (Solo como acentos)
```
/* Confirmed/Success - Verde */
--aa-green-50:  #f0fdf4
--aa-green-100: #dcfce7
--aa-green-500: #22c55e
--aa-green-600: #16a34a

/* Pending/Warning - Ámbar */
--aa-amber-50:  #fffbeb
--aa-amber-100: #fef3c7
--aa-amber-500: #f59e0b
--aa-amber-600: #d97706

/* Cancelled/Error - Rojo */
--aa-red-50:  #fef2f2
--aa-red-100: #fee2e2
--aa-red-500: #ef4444
--aa-red-600: #dc2626

/* Info - Azul (SOLO estado informativo; NUNCA como acento primario) */
--aa-blue-50:  #eff6ff
--aa-blue-100: #dbeafe
--aa-blue-500: #3b82f6
--aa-blue-600: #2563eb
--aa-blue-700: #1d4ed8
```

### 2.2 Espaciado (Scale)
```
--aa-space-1:  4px
--aa-space-2:  6px
--aa-space-3:  8px
--aa-space-4:  12px
--aa-space-5:  16px
--aa-space-6:  20px
--aa-space-7:  24px
--aa-space-8:  32px
```

### 2.3 Border Radius
```
--aa-radius-sm: 4px    /* Badges, pills pequeños */
--aa-radius-md: 6px    /* Cards, botones, inputs */
--aa-radius-lg: 8px    /* Modales, panels grandes */
--aa-radius-xl: 12px   /* Containers principales */
```

### 2.4 Sombras
```
--aa-shadow-xs:  0 1px 2px rgba(0, 0, 0, 0.05)
--aa-shadow-sm:  0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06)
--aa-shadow-md:  0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)
--aa-shadow-lg:  0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)
--aa-shadow-xl:  0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)
```

### 2.5 Tipografía
```
/* Font sizes */
--aa-text-xs:   11px
--aa-text-sm:   12px
--aa-text-base: 13px
--aa-text-md:   14px
--aa-text-lg:   16px

/* Font weights */
--aa-font-normal:   400
--aa-font-medium:   500
--aa-font-semibold: 600

/* Line heights */
--aa-leading-tight:  1.2
--aa-leading-normal: 1.4
--aa-leading-relaxed: 1.5
```

### 2.6 Transiciones
```
--aa-transition-fast:   150ms ease
--aa-transition-normal: 200ms ease
--aa-transition-slow:   300ms ease
```

---

## 3. Componentes

### 3.1 Cards de Citas

#### Estructura
```
┌─────────────────────────────────┐
│▎ Nombre Cliente - Servicio      │  ← Header con barra estado izquierda
├─────────────────────────────────┤
│ [Contenido expandido]           │  ← Body (hidden por defecto)
│                                 │
│ 📱 Contacto    📋 Detalles      │
│ [WhatsApp]     Estado: Badge    │
│ [Email]        Duración: 30min  │
│                                 │
│ [Confirmar] [Cancelar]          │  ← Acciones
└─────────────────────────────────┘
```

#### Estados Visuales

**Colapsada:**
- Fondo: `#ffffff`
- Borde: `1px solid #e5e7eb`
- Barra izquierda: `3px` con color de estado
- Border-radius: `6px`
- Sombra: `shadow-xs` → `shadow-sm` en hover

**Estados de cita:**
| Estado    | Barra izquierda | Badge              | Opacidad |
|-----------|-----------------|---------------------|----------|
| pending   | `#f59e0b`       | bg amber-100, text amber-700 | 100% |
| confirmed | `#22c55e`       | bg green-100, text green-700 | 100% |
| cancelled | `#9ca3af`       | bg red-100, text red-700     | 60%  |

**Expandida:**
- Sombra: `shadow-lg`
- Z-index elevado
- Padding body: `16px`
- Border-radius mantenido

### 3.2 Overlays de Asignaciones (Áreas)

**Prioridad visual:** Citas > Estados > Áreas (contexto sutil)

**Overlay background:**
- Alpha muy bajo: `rgba(color, 0.04)` 
- NO pintar todo el bloque con alpha fuerte

**Header de área:**
```
┌─────────────────────────────────┐
│ Staff Name - Service            │  ← Fondo neutro, texto color área
└─────────────────────────────────┘
```
- Fondo: `rgba(color, 0.1)`
- Texto: color de área con buen contraste
- Font: 11px semibold
- Padding: 4px 8px
- Border-radius top: 6px

### 3.3 Botones

#### Primary (Acciones principales) — Índigo
```css
background: #4f46e5;   /* indigo-600 */
color: white;
border: none;
padding: 8px 14px;
border-radius: 6px;
font-size: 12px;
font-weight: 500;
transition: 150ms ease;

/* Hover */
background: #4338ca;   /* indigo-700 */

/* Active */
background: #3730a3;   /* indigo-800 */

/* Disabled */
background: #a5b4fc;   /* indigo-300 */
```

#### Secondary (Acciones secundarias)
```css
background: white;
color: #374151;
border: 1px solid #d1d5db;
padding: 8px 14px;
border-radius: 6px;
font-size: 12px;
font-weight: 500;

/* Hover */
background: #f9fafb;
border-color: #9ca3af;
```

#### Danger (Cancelar, eliminar)
```css
background: white;
color: #dc2626;
border: 1px solid #fecaca;
padding: 8px 14px;
border-radius: 6px;

/* Hover */
background: #fef2f2;
border-color: #ef4444;
```

#### Success (Confirmar)
```css
background: #22c55e;
color: white;
border: none;
padding: 8px 14px;
border-radius: 6px;

/* Hover */
background: #16a34a;
```

### 3.4 Badges/Pills

```css
/* Base */
display: inline-flex;
align-items: center;
padding: 2px 8px;
border-radius: 4px;
font-size: 11px;
font-weight: 500;
line-height: 1.4;

/* Estados */
.badge-pending   { background: #fef3c7; color: #92400e; }
.badge-confirmed { background: #dcfce7; color: #166534; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }
.badge-info      { background: #dbeafe; color: #1e40af; }
```

### 3.5 Toggles / Switches / Checkboxes

Controles binarios on/off (switch de fila en Asignaciones, notificaciones en Settings, etc.).

**Color adoptado (checked / ON):** índigo primario — `indigo-600` (`#4f46e5`).
Referencia canónica en UI: switches de Personal / Áreas / Servicios (`peer-checked:bg-indigo-600`).

```
/* Track */
OFF: bg-gray-300
ON:  peer-checked:bg-indigo-600   /* ← color adoptado; no emerald ni green */

/* Thumb (peer pattern) */
bg-white + translate cuando checked
```

Clases Tailwind de referencia:
`bg-gray-300 peer-checked:bg-indigo-600 rounded-full transition-colors`

Todo toggle/checkbox nuevo debe heredar este tono. El verde (`emerald` / `green`) queda
reservado a badges y botones de éxito, no al track activo del switch.

### 3.6 Headers de Columnas (Timeline)

```css
/* Container */
background: #f9fafb;
padding: 6px 12px;
font-size: 11px;
font-weight: 600;
color: var(--area-color);
border-bottom: 2px solid var(--area-color);
text-overflow: ellipsis;
white-space: nowrap;
overflow: hidden;
```

### 3.7 Toolbar / Control Bar

Las toolbars son filas de controles que permiten al usuario operar sobre el contenido de una vista (navegar, filtrar, crear, etc.). Su diseño debe reducir el "UI clutter" y comunicar jerarquía clara.

#### Principios fundamentales

1. **Agrupación por zona**: Los controles se organizan en dos zonas principales:
   - **Izquierda**: Navegación y contexto (selector de fecha, navegación temporal, filtros de vista).
   - **Derecha**: Acciones (buscar, crear, exportar, configurar).

2. **Jerarquía visual**: No todos los controles deben "gritar" igual:
   - El control de **contexto principal** (ej. fecha actual) puede ser más prominente.
   - Las **acciones secundarias frecuentes** (buscar, filtrar) deben estar disponibles pero discretas.
   - Las **acciones de creación** (+ Crear) deben tener presencia sin dominar la pantalla si compiten visualmente con el contenido.

3. **Coherencia de alturas**: Todos los controles de una misma toolbar deben compartir la misma altura visual para reducir ruido. Evitar mezclar botones grandes con inputs pequeños o iconos diminutos.

4. **Controles compuestos sobre elementos sueltos**: Cuando varios controles tienen relación directa (ej. prev + fecha + next), unificarlos como un solo "segmented control" o "date navigator". El usuario debe leerlos como una unidad, no como 3 elementos independientes.

5. **Radios y bordes consistentes**: Usar el mismo border-radius en todos los elementos de la toolbar. Si se usa un grupo unificado, solo los extremos tienen radio redondeado.

#### Cuándo usar contenedor (background)

- **Usar contenedor** (franja suave o tarjeta) cuando la toolbar necesita sentirse "anclada" al contenido, separada del header, o cuando hay mucho espacio en blanco alrededor.
- **Evitar contenedor** si ya existe suficiente estructura visual (bordes de cards, headers, etc.) que proporcione separación. Más cajas = más ruido.

#### Anti-patterns de toolbars

❌ Muchos botones pequeños aislados con bordes independientes (parece fragmentado).
❌ Mezclar alturas inconsistentes en la misma fila.
❌ Acciones primarias y secundarias con el mismo peso visual.
❌ Usar background contenedor cuando ya hay mucha "caja" en la UI.

✅ Controles agrupados lógicamente.
✅ Alturas uniformes en toda la fila.
✅ Jerarquía clara: contexto → acciones secundarias → acción principal.
✅ Un solo radio consistente para elementos de la misma familia.

### 3.8 Tertiary / Ghost Buttons (Icon Buttons)

Los botones ghost o terciarios son acciones de **baja prominencia** pero **alta disponibilidad**. Típicamente usados para icon buttons frecuentes en dashboards.

#### Concepto

- Deben estar **disponibles pero no deben gritar**.
- Son ideales para acciones secundarias que el usuario usa frecuentemente (buscar, filtros, configuración) pero que no son la acción principal de la vista.
- Su estilo es minimalista: casi invisibles en reposo, se revelan sutilmente en hover.

#### Reglas de estilo

1. **Estado default**:
   - Sin borde visible o con borde ultra sutil (transparente o muy claro).
   - Fondo transparente.
   - Icono en color gris medio (no negro, no muy claro).

2. **Estado hover**:
   - Fondo muy tenue (gris claro, no blanco puro).
   - El hover debe ser **sutil**, no convertir el botón en una tarjeta o card.
   - Evitar sombras en hover; preferir solo cambio de background.

3. **Estado focus**:
   - Ring visible para accesibilidad (usando la escala de focus estándar).
   - El focus ring debe ser claro y no confundirse con hover.

4. **Estado active/pressed**:
   - Background ligeramente más oscuro que hover.
   - Feedback inmediato al click.

#### Cuándo usar Ghost Buttons

✅ **Usar para**:
- Acciones secundarias de alta frecuencia (búsqueda, filtros, toggle vista).
- Icon buttons en toolbars donde hay otras acciones más importantes.
- Acciones disponibles pero no promocionadas.

❌ **NO usar para**:
- Acciones primarias (crear, guardar, confirmar) → usar Primary.
- Acciones destructivas importantes → usar Danger visible.
- Botones que deben llamar la atención del usuario.

#### Jerarquía de botones

```
Primary (Índigo sólido)   → Acción principal de la vista
Secondary (Borde gris)    → Acciones alternativas importantes
Success/Danger            → Confirmar/Cancelar con semántica
Tertiary/Ghost            → Acciones disponibles pero discretas
```

---

## 4. Interacciones

### 4.1 Hover States
- Cards: Incrementar sombra + borde más oscuro
- Botones: Cambio de background suave
- Links: Underline o cambio de opacidad

### 4.2 Focus States
- Outline: `2px solid #4f46e5` (indigo-600) con offset de 2px
- O usar ring: `box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3)` (indigo-600 @ 30%)

### 4.3 Transiciones
- Todas las propiedades visuales: 150-200ms
- Expandir/colapsar: 200ms ease-out

---

## 5. Z-Index Scale

```
/* Elementos base */
z-index: 1    - Overlays de áreas (background)
z-index: 5    - Hosts de cards
z-index: 10   - Headers de área (top borders)

/* Cards normales */
z-index: 20   - Cards colapsadas base
z-index: 21-39 - Cards en stack (ordenadas)
z-index: 39   - Card activa en stack

/* Labels de overlay */
z-index: 45   - Labels de staff sobre cards

/* Controles de UI */
z-index: 60   - Controles de ciclado

/* Cards expandidas */
z-index: 70   - Host con card expandida
z-index: 80   - Card expandida
z-index: 200  - Overlay global de expandidas
z-index: 300  - Card en overlay global
```

---

## 6. Anti-Patterns (Evitar)

❌ **NO hacer:**
- Bordes gruesos de 3px como indicador de estado
- Backgrounds con alpha > 0.15 en overlays
- Mezclar radios inconsistentes (4px, 6px, 10px, 12px juntos)
- Colores saturados compitiendo entre sí
- Headers de área sin contenedor visual
- Botones con estilos diferentes (radios, alturas)
- Transiciones duras sin ease
- Toggles/checkboxes con track ON en emerald/green (usar indigo-600)

✅ **SÍ hacer:**
- Barra lateral delgada (3px) para indicar estado
- Backgrounds muy tenues para contexto
- Un solo radio consistente por tipo de componente
- Neutros como base, color como acento
- Headers con fondo y borde inferior
- Botones uniformes en mismo contexto
- Transiciones suaves de 150-200ms
- Switches checked con `peer-checked:bg-indigo-600`

---

## 7. Ejemplo de Aplicación

### Antes (Anti-pattern):
```javascript
// ❌ Borde grueso gritando el estado
header.style.border = '3px solid ' + estadoColor;
header.style.color = estadoColor;
```

### Después (Correcto):
```javascript
// ✅ Card neutra con barra lateral sutil
card.style.borderLeft = '3px solid ' + estadoColor;
card.style.border = '1px solid #e5e7eb';
header.style.color = '#374151'; // Texto neutral
// + Badge pequeño con estado
```

---

## 8. Checklist de Revisión

Antes de mergear cambios UI, verificar:

- [ ] Border-radius consistente (6px para cards/botones)
- [ ] Bordes finos (1px), no gruesos
- [ ] Estados con barra lateral, no borde completo
- [ ] Colores de área muy tenues (alpha < 0.1)
- [ ] Headers de columna con contenedor visual
- [ ] Botones con mismo alto/radio/estilo
- [ ] Toggles/checkboxes ON en indigo-600 (no emerald/green)
- [ ] Transiciones de 150-200ms
- [ ] Z-index respetando la escala
- [ ] Sombras usando la escala definida
- [ ] Spacing usando la escala (4/6/8/12/16px)

---

*Última actualización: Agosto 2026 — color primario/marca migrado de azul (#3b82f6) a índigo (#4f46e5); el azul queda reservado solo para el estado "info". Track ON de toggles/checkboxes adoptado como indigo-600 (Settings alineado con Asignaciones).*
*Mantener sincronizado con cambios de sistema de diseño*
