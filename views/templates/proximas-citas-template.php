<?php
/**
 * Template: Tabla de próximas citas (AJAX)
 * 
 * Este template renderiza la sección de próximas citas con:
 * - Filtros de búsqueda y ordenamiento
 * - Contenedor para carga dinámica con AJAX
 * - Paginación
 * 
 * JavaScript responsable: assets/js/ui/proximasCitasUI.js

 */

if (!defined('ABSPATH')) exit;
?>

<h2>Próximas citas</h2>

<!-- ===============================
     🔹 FILTROS DE BÚSQUEDA
     =============================== -->
<div class="aa-historial-filtros">
    <input 
        type="text" 
        id="aa-buscar-proximas" 
        placeholder="Buscar por nombre, teléfono, correo o servicio..."
    >
    
    <select id="aa-ordenar-proximas">
        <option value="fecha_asc">Más próximas primero</option>
        <option value="fecha_desc">Más lejanas primero</option>
        <option value="cliente_asc">Cliente (A-Z)</option>
        <option value="cliente_desc">Cliente (Z-A)</option>
        <option value="estado_asc">Estado (A-Z)</option>
        <option value="estado_desc">Estado (Z-A)</option>
    </select>
    
    <button id="aa-btn-buscar-proximas" class="aa-btn-nuevo-cliente">
        🔍 Buscar
    </button>
    
    <button id="aa-btn-limpiar-proximas" class="aa-btn-cancelar-form">
        ✕ Limpiar
    </button>
</div>

<!-- ===============================
     🔹 CONTENEDOR DE TABLA (Carga AJAX)
     =============================== -->
<div id="aa-proximas-container">
    <p style="text-align: center; color: #999;">
        ⏳ Cargando próximas citas...
    </p>
</div>

<!-- ===============================
     🔹 PAGINACIÓN (Generada dinámicamente)
     =============================== -->
<div class="aa-paginacion" id="aa-proximas-paginacion"></div>