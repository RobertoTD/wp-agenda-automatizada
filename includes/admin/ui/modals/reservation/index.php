<?php
/**
 * Reservation Modal - HTML Content Template
 * 
 * This file provides the body content for the reservation modal.
 * 
 * Required IDs (NO CAMBIAR):
 * - form-crear-cita-admin
 * - cita-cliente
 * - cita-servicio
 * - cita-duracion
 * - cita-fecha
 * - slot-container-admin
 * - btn-cancelar-cita-form
 * 
 * NOTE: This template uses <template> tag to avoid duplicate IDs in DOM.
 * The JS will clone and insert content when modal opens.
 * 
 * @package AgendaAutomatizada
 * @since 1.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Obtener datos necesarios para los selects
$clientes = function_exists('aa_get_all_clientes') ? aa_get_all_clientes(200) : [];

// Obtener servicios activos desde la base de datos (assignments)
$servicios_bd = [];
if (class_exists('AssignmentsModel')) {
    $servicios_bd = AssignmentsModel::get_services(true); // true = solo activos (filtra is_hidden = 0 y active = 1)
}

$duracion_default = intval(get_option('aa_slot_duration', 60));
$duraciones = [30, 60, 90];
?>

<!-- Template for Reservation Modal (content not rendered until cloned by JS) -->
<template id="aa-reservation-modal-template">
    <div class="aa-reservation-modal">
        <form id="form-crear-cita-admin">
            
            <!-- Campo: Cliente -->
            <div class="aa-form-cita-group">
                <label for="cita-cliente">Cliente *</label>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <input 
                        type="text" 
                        id="aa-cliente-search" 
                        class="aa-cliente-search-input"
                        placeholder="Buscar cliente por nombre, teléfono o correo..."
                        autocomplete="off"
                        style="flex: 1; padding: 8px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px;"
                    >
                    <span style="color: #666; font-size: 14px;">o</span>
                    <button 
                        type="button" 
                        id="aa-btn-crear-cliente-reservation"
                        class="aa-btn-crear-cliente-reservation"
                        style="padding: 8px 16px; font-size: 14px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;"
                    >
                        + cliente
                    </button>
                </div>
                <!-- Contenedor inline para crear cliente (se muestra cuando se hace clic en "Crear cliente") -->
                <div id="aa-reservation-client-inline" style="display: none;"></div>
                <select id="cita-cliente" name="cliente_id" required>
                    <option value="">-- Selecciona un cliente --</option>
                    <?php foreach ($clientes as $cliente): ?>
                    <option 
                        value="<?php echo esc_attr($cliente->id); ?>"
                        data-nombre="<?php echo esc_attr($cliente->nombre); ?>"
                        data-telefono="<?php echo esc_attr($cliente->telefono); ?>"
                        data-correo="<?php echo esc_attr($cliente->correo); ?>"
                    >
                        <?php echo esc_html($cliente->nombre); ?> (<?php echo esc_html($cliente->telefono); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Campo: Servicio/Motivo -->
            <div class="aa-form-cita-group">
                <label for="cita-servicio">Motivo de la cita *</label>
                <select id="cita-servicio" name="servicio" required>
                    <option value="">-- Selecciona un servicio --</option>
                    <?php
                    // Servicios desde asignaciones (solo activos y no ocultos)
                    if (!empty($servicios_bd)) {
                        foreach ($servicios_bd as $servicio) {
                            // Filtrar solo servicios activos (active = 1)
                            if (isset($servicio['active']) && intval($servicio['active']) === 1) {
                                $service_id = esc_attr($servicio['id']);
                                $service_name = esc_html($servicio['name']);
                                echo "<option value='{$service_id}'>{$service_name}</option>";
                            }
                        }
                    }
                    ?>
                    
                    <?php
                    // Agregar opción de horario fijo si existe
                    $service_schedule = get_option('aa_service_schedule', '');
                    if (!empty($service_schedule)) {
                        $service_name = esc_html($service_schedule);
                        $service_value = esc_attr('fixed::' . $service_schedule);
                        echo "<option value='{$service_value}'>{$service_name}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <!-- Campo: Duración -->
            <div class="aa-form-cita-group">
                <label for="cita-duracion">Duración *</label>
                <select id="cita-duracion" name="duracion" required>
                    <?php foreach ($duraciones as $duracion): ?>
                    <option 
                        value="<?php echo esc_attr($duracion); ?>"
                        <?php echo ($duracion === $duracion_default) ? 'selected' : ''; ?>
                    >
                        <?php echo esc_html($duracion); ?> min
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Campo: Fecha y Hora -->
            <div class="aa-form-cita-group">
                <label for="cita-fecha">Fecha y hora *</label>
                <input 
                    type="text" 
                    id="cita-fecha" 
                    name="fecha" 
                    required 
                    readonly 
                    placeholder="Selecciona fecha..."
                >
            </div>
            
            <!-- Campo: Personal disponible (nuevo - basado en assignments) -->
            <div class="aa-form-cita-group">
                <label for="aa-reservation-staff">Personal disponible</label>
                <select id="aa-reservation-staff" name="staff_id" disabled>
                    <option value="">Seleccione primero un servicio y una fecha</option>
                </select>
            </div>
            
            <!-- Contenedor de slots con checkbox de confirmación -->
            <div class="aa-form-cita-group">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div id="slot-container-admin" style="flex: 1;"></div>
                    <div>
                        <label for="aa-reservation-auto-confirm" style="white-space: nowrap; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                id="aa-reservation-auto-confirm" 
                                name="aa_reservation_auto_confirm" 
                                value="confirmed"
                            >
                            <span>Confirmar</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Botones -->
            <div class="aa-form-cita-actions">
                <button type="submit" class="aa-btn-agendar-cita">
                    ✓ Agendar Cita
                </button>
                <button type="button" class="aa-btn-cancelar-cita-form" id="btn-cancelar-cita-form">
                    Cancelar
                </button>
            </div>
            
        </form>
    </div>
</template>
