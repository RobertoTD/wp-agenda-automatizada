<?php
/**
 * Shared Header - Compact tab navigation with icons
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>
<header class="bg-white border-b border-gray-200 shadow-sm">
    <div class="px-4 py-3">
        <!-- Branding + Action Button Row -->
        <div class="flex items-center justify-between mb-3">
            <!-- Left: Branding -->
            <div class="flex items-center gap-2">
                <span class="flex items-center justify-center w-7 h-7 rounded-lg bg-blue-100 text-blue-600 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
            </div>
            
            <!-- Right: Notifications + Agendar Button -->
            <div class="flex items-center gap-2">
                <!-- Group: Small icon buttons (Notifications) -->
                <div class="flex items-center gap-0">
                    <!-- Notifications Container (relative for popover positioning) -->
                    <div class="relative">
                        <!-- Notifications Bell -->
                        <button 
                            id="aa-btn-notifications" 
                            type="button"
                            class="inline-flex items-center justify-center w-9 h-9 text-gray-600 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                            aria-label="Notificaciones"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </button>
                        <span id="aa-notifications-badge" class="absolute top-0 right-2 text-xs font-medium text-gray-500 leading-none">0</span>
                        
                        <!-- Notifications Popover (hidden by default) -->
                        <div id="aa-notifications-popover" class="hidden absolute right-0 top-full mt-2 z-50 w-80 bg-white rounded-lg shadow-lg border border-gray-200">
                            <div class="p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold text-gray-900">Notificaciones</h3>
                                    <button 
                                        id="aa-btn-close-notifications" 
                                        type="button"
                                        class="text-gray-400 hover:text-gray-600 transition-colors"
                                        aria-label="Cerrar"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="aa-notifications-content">
                                    <!-- Content will be rendered dynamically by notifications.js -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Agendar Button -->
                <button 
                    id="aa-btn-open-reservation-modal" 
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg border border-gray-300 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Agendar</span>
                </button>
            </div>
        </div>
        
        <!-- Tab Navigation - Icon above, label below -->
        <nav class="flex items-center justify-center gap-2">
            <!-- Configuración -->
            <a href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=settings')); ?>" 
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
            <a href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=calendar')); ?>" 
               class="flex flex-col items-center justify-center min-w-[60px] px-2 py-2 rounded-lg transition-all <?php echo (isset($active_module) && $active_module === 'calendar') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <span class="flex items-center justify-center w-6 h-6 mb-1 <?php echo (isset($active_module) && $active_module === 'calendar') ? 'text-blue-600' : 'text-gray-500'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
                <span class="text-xs font-medium leading-tight text-center">Calendario</span>
            </a>
            
            <!-- Clientes -->
            <a href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=clients')); ?>" 
               class="flex flex-col items-center justify-center min-w-[60px] px-2 py-2 rounded-lg transition-all <?php echo (isset($active_module) && $active_module === 'clients') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <span class="flex items-center justify-center w-6 h-6 mb-1 <?php echo (isset($active_module) && $active_module === 'clients') ? 'text-blue-600' : 'text-gray-500'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </span>
                <span class="text-xs font-medium leading-tight text-center">Clientes</span>
            </a>
            
            <!-- Asignaciones -->
            <a href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=assignments')); ?>" 
               class="flex flex-col items-center justify-center min-w-[60px] px-2 py-2 rounded-lg transition-all <?php echo (isset($active_module) && $active_module === 'assignments') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'; ?>">
                <span class="flex items-center justify-center w-6 h-6 mb-1 <?php echo (isset($active_module) && $active_module === 'assignments') ? 'text-blue-600' : 'text-gray-500'; ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </span>
                <span class="text-xs font-medium leading-tight text-center">Asignaciones</span>
            </a>
        </nav>
    </div>
</header>

