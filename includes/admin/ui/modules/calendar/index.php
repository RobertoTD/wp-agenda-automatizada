<?php
/**
 * Calendar Module - Calendar Management UI
 * 
 * This module handles:
 * - Display of calendar view
 * - UI for managing appointments
 * - No business logic (data operations handled outside)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Calendario</h2>
        
        <div class="space-y-4">
            <!-- Placeholder for calendar view -->
            <div class="p-8 bg-gray-50 rounded-md text-center">
                <p class="text-gray-600">Vista de calendario - En desarrollo</p>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'module.js'); ?>" defer></script>

