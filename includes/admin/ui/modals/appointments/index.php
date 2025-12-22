<?php
/**
 * Appointments Modal - HTML Content Template
 * 
 * This file provides the body content for the appointments modal.
 * Uses the existing card system with [data-aa-card] and [data-aa-card-toggle]
 * 
 * Structure:
 * - Filters section (placeholder for future filters)
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
        
        <!-- Filters Section (placeholder for future filters) -->
        <div class="aa-appointments-filters mb-4">
            <!-- Filtros vendrán aquí en futuras versiones -->
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
