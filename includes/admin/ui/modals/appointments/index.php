<?php
/**
 * Appointments Modal - HTML Content Template
 * 
 * This file provides the body content for the appointments modal.
 * Uses the existing card system with [data-aa-card] and [data-aa-card-toggle]
 * 
 * Structure:
 * - Filter toggle button
 * - Collapsible filters panel
 * - Cards stack (dynamically rendered by JS)
 * - Pagination controls (dynamically rendered by JS)
 * 
 * NOTE: This template uses <template> tag to avoid duplicate IDs in DOM.
 * The JS will clone and insert content when modal opens.
 * 
 * @package AgendaAutomatizada
 * @since 1.0.0
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>

<!-- Template for Appointments Modal (content not rendered until cloned by JS) -->
<template id="aa-appointments-modal-template">
    <div class="aa-appointments-modal">
        
        <!-- Filter Toggle Button -->
        <div class="aa-appointments-filter-toggle mb-3">
            <button type="button" class="aa-btn-toggle-filters inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                </svg>
                <span>Búsqueda</span>
            </button>
        </div>
        
        <!-- Filters Panel (hidden by default) -->
        <div class="aa-appointments-filters hidden mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
            
            <!-- Time filter -->
            <fieldset class="mb-3" data-filter-group="time">
                <legend class="text-sm font-medium text-gray-700 mb-2">Por tiempo</legend>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="time:all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Todas</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="time:future" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Futuras</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="time:past" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Pasadas</span>
                    </label>
                </div>
            </fieldset>
            
            <!-- Status filter -->
            <fieldset class="mb-3" data-filter-group="status">
                <legend class="text-sm font-medium text-gray-700 mb-2">Por estado</legend>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="status:all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Todas</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="status:cancelled" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Canceladas</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="status:pending" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Por confirmar</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="status:confirmed" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Confirmadas</span>
                    </label>
                </div>
            </fieldset>
            
            <!-- Notification filter -->
            <fieldset data-filter-group="notification">
                <legend class="text-sm font-medium text-gray-700 mb-2">Por notificación</legend>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="notification:all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Todas</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="notification:unread" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Nuevas</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" data-filter="notification:read" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>Vistas</span>
                    </label>
                </div>
            </fieldset>
            
        </div>
        
        <!-- Cards Stack (dynamically populated by AppointmentsController) -->
        <div class="aa-appointments-list space-y-2">
            <!-- Cards se renderizan dinámicamente -->
            <div class="aa-appointments-loading flex items-center justify-center py-8">
                <div class="text-gray-500">Cargando citas...</div>
            </div>
        </div>
        
        <!-- Pagination (dynamically populated by AppointmentsController) -->
        <div class="aa-appointments-pagination"></div>
        
    </div>
</template>
