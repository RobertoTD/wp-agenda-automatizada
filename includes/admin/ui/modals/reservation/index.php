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
        <form id="form-crear-cita-admin" class="space-y-4">
            
            <!-- Campo: Cliente -->
            <div>
                <label for="cita-cliente" class="block text-sm font-medium text-gray-700 mb-1">
                    Cliente <span class="text-red-500">*</span>
                </label>
                <div class="flex items-center gap-2 mb-2">
                    <input 
                        type="text" 
                        id="aa-cliente-search" 
                        class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                        placeholder="Buscar cliente..."
                        autocomplete="off"
                    >
                    <span class="text-gray-500 text-sm">o</span>
                    <button 
                        type="button" 
                        id="aa-btn-crear-cliente-reservation"
                        class="px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap"
                    >
                        + cliente
                    </button>
                </div>
                <!-- Contenedor inline para crear cliente -->
                <div id="aa-reservation-client-inline" class="hidden"></div>
                <select id="cita-cliente" name="cliente_id" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
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
            <div>
                <label for="cita-servicio" class="block text-sm font-medium text-gray-700 mb-1">
                    Motivo de la cita <span class="text-red-500">*</span>
                </label>
                <select id="cita-servicio" name="servicio" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
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
            <div>
                <label for="cita-duracion" class="block text-sm font-medium text-gray-700 mb-1">
                    Duración <span class="text-red-500">*</span>
                </label>
                <select id="cita-duracion" name="duracion" required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
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
            <div>
                <label for="cita-fecha" class="block text-sm font-medium text-gray-700 mb-1">
                    Fecha y hora <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="cita-fecha" 
                    name="fecha" 
                    required 
                    readonly 
                    placeholder="Selecciona fecha..."
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                >
            </div>
            
            <!-- Campo: Personal disponible -->
            <div>
                <label for="aa-reservation-staff" class="block text-sm font-medium text-gray-700 mb-1">
                    Personal disponible
                </label>
                <select id="aa-reservation-staff" name="staff_id" disabled
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white disabled:bg-gray-100 disabled:cursor-not-allowed">
                    <option value="">Seleccione primero un servicio y una fecha</option>
                </select>
            </div>
            
            <!-- Contenedor de slots con checkbox de confirmación -->
            <div>
                <div class="flex items-center gap-3">
                    <div id="slot-container-admin" class="flex-1"></div>
                    <label for="aa-reservation-auto-confirm" class="flex items-center gap-2 cursor-pointer whitespace-nowrap text-sm text-gray-700">
                        <input 
                            type="checkbox" 
                            id="aa-reservation-auto-confirm" 
                            name="aa_reservation_auto_confirm" 
                            value="confirmed"
                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                        >
                        <span>Confirmar</span>
                    </label>
                </div>
            </div>
            
        </form>
    </div>
</template>

<!-- Template for Reservation Modal Footer -->
<template id="aa-reservation-modal-footer-template">
    <div class="flex justify-end gap-3">
        <button type="button" 
                id="btn-cancelar-cita-form"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                data-aa-modal-close>
            Cancelar
        </button>
        <button type="submit" 
                form="form-crear-cita-admin"
                class="aa-btn-agendar-cita px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
            ✓ Agendar Cita
        </button>
    </div>
</template>
