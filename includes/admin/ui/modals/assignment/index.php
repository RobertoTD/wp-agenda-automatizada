<?php
/**
 * Assignment Modal - HTML Content Template
 * 
 * This file provides the body content for the assignment creation modal.
 * Uses <template> tag to avoid duplicate IDs in DOM.
 * The JS will clone and insert content when modal opens.
 * 
 * @package AgendaAutomatizada
 * @since 1.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>

<!-- Template for Assignment Modal (content not rendered until cloned by JS) -->
<template id="aa-assignment-modal-template">
    <div class="aa-assignment-modal">
        <form id="aa-assignment-form" class="space-y-4">
            
            <!-- Fecha -->
            <div>
                <label for="aa-assignment-date" class="block text-sm font-medium text-gray-700 mb-1">
                    Fecha <span class="text-red-500">*</span>
                </label>
                <input type="text" 
                       id="aa-assignment-date" 
                       name="assignment_date"
                       required
                       placeholder="Selecciona una fecha"
                       readonly
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
            </div>

            <!-- Hora inicio y fin -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="aa-assignment-start" class="block text-sm font-medium text-gray-700 mb-1">
                        Hora inicio <span class="text-red-500">*</span>
                    </label>
                    <input type="time" 
                           id="aa-assignment-start" 
                           name="start_time"
                           required
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label for="aa-assignment-end" class="block text-sm font-medium text-gray-700 mb-1">
                        Hora fin <span class="text-red-500">*</span>
                    </label>
                    <input type="time" 
                           id="aa-assignment-end" 
                           name="end_time"
                           required
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <!-- Personal -->
            <div>
                <label for="aa-assignment-staff" class="block text-sm font-medium text-gray-700 mb-1">
                    Personal <span class="text-red-500">*</span>
                </label>
                <select id="aa-assignment-staff" 
                        name="staff_id"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="">Cargando personal...</option>
                </select>
            </div>

            <!-- Zona de atención -->
            <div>
                <label for="aa-assignment-area" class="block text-sm font-medium text-gray-700 mb-1">
                    Zona de atención <span class="text-red-500">*</span>
                </label>
                <select id="aa-assignment-area" 
                        name="service_area_id"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="">Cargando zonas...</option>
                </select>
            </div>

            <!-- Servicio -->
            <div>
                <label for="aa-assignment-service" class="block text-sm font-medium text-gray-700 mb-1">
                    Servicio <span class="text-red-500">*</span>
                </label>
                <select id="aa-assignment-service" 
                        name="service_key"
                        required
                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="">Cargando servicios...</option>
                </select>
            </div>

            <!-- Capacidad -->
            <div>
                <label for="aa-assignment-capacity" class="block text-sm font-medium text-gray-700 mb-1">
                    Capacidad
                </label>
                <input type="number" 
                       id="aa-assignment-capacity" 
                       name="capacity"
                       value="1"
                       min="1"
                       max="100"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <p class="mt-1 text-xs text-gray-500">Número de citas simultáneas permitidas</p>
            </div>

            <!-- Mensaje de error -->
            <div id="aa-assignment-error" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-600"></p>
            </div>
            
        </form>
    </div>
</template>

<!-- Template for Assignment Modal Footer -->
<template id="aa-assignment-modal-footer-template">
    <div class="flex justify-end gap-3">
        <button type="button" 
                id="aa-assignment-cancel"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                data-aa-modal-close>
            Cancelar
        </button>
        <button type="button" 
                id="aa-assignment-save"
                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
            Guardar asignación
        </button>
    </div>
</template>

