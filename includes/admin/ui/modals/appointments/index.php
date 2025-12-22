<?php
/**
 * Appointments Modal - HTML Content Template
 * 
 * This file provides the body content for the appointments modal.
 * Uses the existing card system with [data-aa-card] and [data-aa-card-toggle]
 * 
 * Structure:
 * - Filters section (empty placeholder for now)
 * - Cards stack with example cards
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
        <div class="aa-appointments-filters">
            <!-- Filtros vendrán aquí -->
        </div>
        
        <!-- Cards Stack -->
        <div class="aa-appointments-list space-y-2">
            
            <!-- Card ejemplo 1 -->
            <div data-aa-card class="aa-appointment-card">
                <div class="aa-appointment-header" data-aa-card-toggle>
                    Card ejemplo 1
                </div>
                <div class="aa-card-overlay">
                    <div class="aa-card-body">
                        <div class="text-sm text-gray-700">
                            Contenido placeholder para card ejemplo 1
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card ejemplo 2 -->
            <div data-aa-card class="aa-appointment-card">
                <div class="aa-appointment-header" data-aa-card-toggle>
                    Card ejemplo 2
                </div>
                <div class="aa-card-overlay">
                    <div class="aa-card-body">
                        <div class="text-sm text-gray-700">
                            Contenido placeholder para card ejemplo 2
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card ejemplo 3 -->
            <div data-aa-card class="aa-appointment-card">
                <div class="aa-appointment-header" data-aa-card-toggle>
                    Card ejemplo 3
                </div>
                <div class="aa-card-overlay">
                    <div class="aa-card-body">
                        <div class="text-sm text-gray-700">
                            Contenido placeholder para card ejemplo 3
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</template>

