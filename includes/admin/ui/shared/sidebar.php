<?php
/**
 * Shared Sidebar - Drawer navigation component
 * 
 * This file provides:
 * - Overlay backdrop when sidebar is open
 * - Drawer panel with module navigation
 * - Same links as header tabs (calendar, clients, assignments, settings)
 * 
 * Shell component: shared across all modules.
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// $active_module is available from parent scope (layout.php → index.php)
$active_module = isset($active_module) ? $active_module : 'calendar';
?>

<!-- Sidebar Overlay (backdrop) -->
<div 
    id="aa-sidebar-overlay" 
    class="fixed inset-0 bg-black/50 z-40 hidden opacity-0 transition-opacity duration-300"
    aria-hidden="true"
></div>

<!-- Sidebar Drawer -->
<aside 
    id="aa-sidebar" 
    class="fixed top-0 left-0 h-full w-64 bg-white shadow-xl z-50 transform -translate-x-full transition-transform duration-300 ease-in-out"
    role="dialog"
    aria-modal="true"
    aria-label="Menú de navegación"
>
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between px-4 py-4 border-b border-gray-200">
        <div class="flex items-center gap-2">
            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </span>
            <span class="text-sm font-semibold text-gray-900">Agenda</span>
        </div>
        <button 
            id="aa-sidebar-close" 
            type="button"
            class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
            aria-label="Cerrar menú"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    
    <!-- Sidebar Navigation -->
    <nav class="px-3 py-4">
        <ul class="space-y-1">
            <!-- Calendario -->
            <li>
                <a 
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=calendar')); ?>" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'calendar') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'calendar') ? 'text-blue-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Calendario</span>
                </a>
            </li>
            
            <!-- Clientes -->
            <li>
                <a 
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=clients')); ?>" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'clients') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'clients') ? 'text-blue-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Clientes</span>
                </a>
            </li>
            
            <!-- Asignaciones -->
            <li>
                <a 
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=assignments')); ?>" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'assignments') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'assignments') ? 'text-blue-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Asignaciones</span>
                </a>
            </li>
            
            <!-- Separador -->
            <li class="my-3">
                <hr class="border-gray-200">
            </li>
            
            <!-- Configuración -->
            <li>
                <a 
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=settings')); ?>" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'settings') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'settings') ? 'text-blue-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Configuración</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
