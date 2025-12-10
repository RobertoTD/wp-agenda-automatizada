<?php
/**
 * Services Module - Services Management UI
 * 
 * This module handles:
 * - Display of services list
 * - UI for adding/editing services
 * - No business logic (data operations handled outside)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold text-gray-800">Servicios</h2>
            <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Agregar Servicio
            </button>
        </div>
        
        <div class="space-y-4">
            <!-- Placeholder for services list -->
            <div class="p-4 bg-gray-50 rounded-md">
                <p class="text-gray-600">Lista de servicios - En desarrollo</p>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'module.js'); ?>" defer></script>

