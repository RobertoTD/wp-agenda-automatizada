<?php
/**
 * Shared Header - Navigation and branding
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>
<header class="bg-white shadow-sm border-b border-gray-200">
    <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <h1 class="text-xl font-semibold text-gray-800">Agenda Automatizada</h1>
            </div>
            <nav class="flex space-x-2">
                <a href="?module=settings" 
                   class="px-4 py-2 rounded-md text-sm font-medium <?php echo (isset($active_module) && $active_module === 'settings') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    Configuración
                </a>
                <a href="?module=services" 
                   class="px-4 py-2 rounded-md text-sm font-medium <?php echo (isset($active_module) && $active_module === 'services') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    Servicios
                </a>
                <a href="?module=calendar" 
                   class="px-4 py-2 rounded-md text-sm font-medium <?php echo (isset($active_module) && $active_module === 'calendar') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?>">
                    Calendario
                </a>
            </nav>
        </div>
    </div>
</header>

