<?php
/**
 * Shared Header - Compact header bar with sidebar trigger and actions
 * 
 * Design: Dashboard SaaS moderno - neutros como base, color como acento
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>
<header class="bg-transparent">
    <div class="px-4 py-2.5">
        <!-- Header Row -->
        <div class="flex items-center justify-between">
            <!-- Left: Sidebar trigger (island) + Branding -->
            <div class="flex items-center gap-3">
                <!-- Sidebar Trigger: isla circular -->
                <button 
                    id="aa-btn-sidebar" 
                    type="button"
                    class="inline-flex items-center justify-center w-9 h-9 text-gray-600 bg-white border border-gray-200 shadow-sm rounded-lg hover:bg-gray-50 hover:shadow hover:border-gray-300 active:bg-gray-100 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:ring-offset-1"
                    aria-label="Abrir menú"
                    aria-expanded="false"
                    aria-controls="aa-sidebar"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                </button>
                
                <!-- Branding -->
                <span class="text-sm font-semibold text-gray-800 tracking-tight hidden sm:block">Agenda</span>
            </div>
            
            <!-- Right: Notifications (island) -->
            <div class="flex items-center gap-2">
                <!-- Notifications: isla circular -->
                <div class="relative">
                    <button 
                        id="aa-btn-notifications" 
                        type="button"
                        class="inline-flex items-center justify-center w-9 h-9 text-gray-600 bg-white border border-gray-200 shadow-sm rounded-lg hover:bg-gray-50 hover:shadow hover:border-gray-300 active:bg-gray-100 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:ring-offset-1"
                        aria-label="Notificaciones"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                        </svg>
                    </button>
                    <!-- Badge -->
                    <span 
                        id="aa-notifications-badge" 
                        class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] text-[10px] font-semibold text-white bg-blue-500 rounded-full ring-2 ring-white"
                    >0</span>
                    
                    <!-- Notifications Popover -->
                    <div id="aa-notifications-popover" class="hidden absolute right-0 top-full mt-2 z-50 w-80 bg-white rounded-lg shadow-lg border border-gray-200">
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-gray-800">Notificaciones</h3>
                                <button 
                                    id="aa-btn-close-notifications" 
                                    type="button"
                                    class="inline-flex items-center justify-center w-6 h-6 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded transition-colors duration-150"
                                    aria-label="Cerrar"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="aa-notifications-content text-sm text-gray-600">
                                <!-- Content will be rendered dynamically by notifications.js -->
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</header>
