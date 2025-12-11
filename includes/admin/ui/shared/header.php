<?php
/**
 * Shared Header - Compact tab navigation with icons
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>
<header class="bg-white border-b border-gray-200 shadow-sm">
    <div class="px-4 py-3">
        <!-- Branding - Compact -->
        <div class="flex items-center gap-2 mb-3">
            <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-blue-100 text-blue-600 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </span>
            <h1 class="text-sm font-semibold text-gray-900 truncate">Agenda Automatizada</h1>
        </div>
        
        <!-- Tab Navigation - Icon above, label below -->
        <nav class="flex items-center justify-center gap-2">
            <!-- Configuración -->
            <a href="?module=settings" 
               class="flex flex-col items-center justify-center min-w-[60px] px-2 py-2 rounded-lg transition-all <?php echo (isset($active_module) && $active_module === 'settings') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <span class="flex items-center justify-center w-6 h-6 mb-1 <?php echo (isset($active_module) && $active_module === 'settings') ? 'text-blue-600' : 'text-gray-500'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </span>
                <span class="text-xs font-medium leading-tight text-center">Config</span>
            </a>
            
            <!-- Calendario -->
            <a href="?module=calendar" 
               class="flex flex-col items-center justify-center min-w-[60px] px-2 py-2 rounded-lg transition-all <?php echo (isset($active_module) && $active_module === 'calendar') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <span class="flex items-center justify-center w-6 h-6 mb-1 <?php echo (isset($active_module) && $active_module === 'calendar') ? 'text-blue-600' : 'text-gray-500'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
                <span class="text-xs font-medium leading-tight text-center">Calendario</span>
            </a>
        </nav>
    </div>
</header>

