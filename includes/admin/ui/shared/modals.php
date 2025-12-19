<?php
/**
 * Shared Modals - Base HTML structure for modal system
 * 
 * This file provides:
 * - Root container for modals
 * - Overlay for backdrop
 * - Modal structure (header, body, footer)
 * - Close button and data attributes
 * 
 * No PHP logic, only HTML structure.
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>

<!-- Modal System Root -->
<div id="aa-modal-root" class="aa-modal-root hidden">
    <!-- Overlay (backdrop) -->
    <div class="aa-modal-overlay" data-aa-modal-close></div>
    
    <!-- Modal Container -->
    <div class="aa-modal">
        <!-- Modal Header -->
        <div class="aa-modal-header">
            <h2 class="aa-modal-title"></h2>
            <button type="button" class="aa-modal-close" data-aa-modal-close aria-label="Cerrar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="aa-modal-body"></div>
        
        <!-- Modal Footer -->
        <div class="aa-modal-footer"></div>
    </div>
</div>

