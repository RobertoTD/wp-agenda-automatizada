<!-- Última actualización del documento: 2026-03-21 -->

# Estrategia de producto (estado actual)

Documento de referencia para decisiones de desarrollo y crecimiento. Describe **qué es el producto hoy**, **qué resuelve**, **dónde está el cuello de botella** y **qué hacer después**. La arquitectura técnica detallada vive en [02-architecture-principles.md](02-architecture-principles.md).

---

## 1. Qué es el producto hoy

**WP Agenda Automatizada** es un plugin de WordPress **ya operativo**: agenda citas, gestiona clientes y staff, y ofrece un panel de administración **moderno y desacoplado** que corre dentro de un **iframe** (shell en `includes/admin/ui/shared/layout.php`), no como pantallas clásicas de `wp-admin`. El shortcode expone el flujo público de reserva; rutas como `/agenda-app` enlazan la experiencia “app” con ese mismo runtime.

En la práctica es un **sistema operativo ligero para citas** en negocios pequeños: un solo lugar para ver el día, confirmar, crear citas rápidas y revisar señales de negocio, sin depender de una hoja de cálculo como fuente de verdad.

---

## 2. Capacidades actuales (lenguaje operativo)

- **Gestión diaria (calendario / timeline)**  
  Ver el día en contexto de horarios configurados, citas y bloqueos. El módulo de calendario orquesta disponibilidad, slots y vista temporal; la lógica pesada vive en servicios JS (`availability/`, `calendarAvailabilityService`) y en PHP (modelos, confirmación, sync).

- **Citas rápidas (fast appointment)**  
  Atajo en admin para agendar sin recorrer todo el flujo largo: el modal usa prerequisitos y cómputo de huecos (`fastAppointmentPrerequisitesService`, `fastAppointmentTimeAvailabilityService`) sobre el mismo modelo de assignments y reservas confirmadas. Documentado en `fast-appointment-vs-assignment-availability.md`.

- **Asignaciones vs disponibilidad dinámica**  
  El negocio define staff, áreas, servicios y **assignments** (ventanas recurrentes o puntuales). La disponibilidad que ve el cliente y el admin se deriva de esas reglas más el estado real de reservas (y busy ranges), no de un calendario “dibujado a mano” suelto.

- **Gestión de clientes**  
  Identidad por teléfono, historial y creación/edición desde modales reutilizables; encaja con el flujo de reservas y con el panel.

- **Dashboard (resumen de negocio)**  
  Módulo **dashboard**: citas del día, próxima cita, ingresos estimados por rango (día / semana / mes) con datos agregados vía `dashboardService.php` + `assets/js/services/dashboardService.js`, comparativas y alertas de pendientes. Es lectura y decisión, no configuración profunda.

- **Sincronización con Google Calendar**  
  Integración vía backend OAuth y servicios de sync ya acoplados al plugin; el valor percibido es “la agenda del negocio y la de Google no se pelean”.

- **Citas virtuales**  
  Ruta dedicada (`citas-virtuales`) para unión por token donde el producto lo requiere.

---

## 3. Qué problema resuelve realmente

No es “tener un plugin de citas”. Es reducir **fricción operativa** en negocios que viven con:

- agendas compartidas por WhatsApp, notas y memoria;
- **poca visibilidad** de cuántas citas van confirmadas, pendientes o canceladas, y de ingreso estimado en un periodo;
- **doble trabajo** al pasar manualmente lo mismo a un calendario externo;
- clientes que reservan por chat **sin reglas** (solapes, olvidos de confirmación).

El producto impone **estructura mínima** (estados, clientes, ventanas de atención) sin exigir aún un ERP.

---

## 4. Modelo de uso real

- **Dueño del negocio**  
  Configura horarios, servicios, staff y sync; mira dashboard y calendario para decidir.

- **Asistente**  
  Opera el día: confirmar, reagendar mentalmente, **cita rápida** cuando entra una llamada, crear cliente si hace falta.

- **Cliente final**  
  Reserva desde la web (shortcode / flujo público); en escenarios virtuales, accede por enlace/token. No instala nada.

---

## 5. Estado actual del sistema

- **Usable en producción** para el caso de uso objetivo (citas + admin desacoplado).
- **UI** coherente (Tailwind en admin iframe, componentes y módulos por carpeta: `dashboard`, `calendar`, `assignments`, `clients`, `settings`).
- **Capas consolidadas**: controladores JS solo orquestan; servicios JS llevan dominio de cliente; PHP separa controllers, models y `includes/services` (incl. `dashboardService.php` para agregados del resumen).
- **Base razonable para distribución** (versionado, docs de arquitectura, flujos documentados). Lo que falta no es “terminar el core”, sino **canales y permisos externos** (véase siguiente sección).

---

## 6. Cuello de botella actual (crítico)

El límite **no es** falta de features en el código del plugin.

Es **distribución y confianza del ecosistema**:

- **OAuth de Google** en modo no verificado o con fricción para usuarios finales: sin eso estable, el valor “sync con Calendar” se rompe en onboarding real.
- **Plugin no publicado** en el directorio oficial de WordPress: menos descubrimiento, menos instalación en un clic, menos señal de legitimidad.
- **Sin canal de adquisición escalable**: sin repo, sin partners y sin embudo, cada instalación sigue siendo esfuerzo manual.

Hasta que eso se desbloquee, más código marginal tiene **bajo retorno**.

---

## 7. Estrategia inmediata (siguiente fase)

### Fase 1 — Desbloqueo

- Completar **verificación / políticas de Google OAuth** (o equivalente que el producto exija) para que sync y login no sean la primera barrera de abandono.

### Fase 2 — Distribución

- **Publicar en WordPress.org** (o canal equivalente con instalación simple), con readme claro, requisitos y soporte mínimo definido.

### Fase 3 — Adopción

- **Freelancers / implementadores** que instalen en clientes finales.
- **Ventas físicas** o partnerships locales donde el dueño no busca en el repo sino por referencia.

Medir cada fase con métricas simples: instalaciones activas, tickets de soporte, retención a 30 días, no “número de commits”.

---

## 8. Qué NO hacer

- **No** apilar features sin usuarios que las validen en producción.
- **No** complejizar la arquitectura (nuevas capas, microservicios, rewrites) mientras el cuello de botella sea distribución.
- **No** “construir antes de distribuir”: el producto ya cruza el umbral de MVP técnico; el riesgo es quedarse en laboratorio.

---

## 9. Visión a futuro (breve)

- **Automatización** alrededor de recordatorios y confirmaciones, reutilizando el mismo modelo de citas y estados.
- **Crecimiento por capas**: reporting más fino, reglas de negocio opcionales, integraciones, siempre **después** de tracción y feedback real.
- Tratar el sistema como **infraestructura repetible** para muchos negocios pequeños, no como un único custom gigante.

---

*Última alineación con la estructura real del código: ver [02-architecture-principles.md](02-architecture-principles.md).*
