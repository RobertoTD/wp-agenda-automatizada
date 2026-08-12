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
        <div class="flex items-center justify-between gap-3">
            <!-- Left: Sidebar trigger + page title -->
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <!-- Sidebar Trigger: isla circular -->
                <button 
                    id="aa-btn-sidebar" 
                    type="button"
                    class="inline-flex items-center justify-center w-9 h-9 shrink-0 text-gray-600 bg-white border border-gray-200 shadow-sm rounded-lg hover:bg-gray-50 hover:shadow hover:border-gray-300 active:bg-gray-100 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:ring-offset-1"
                    aria-label="Abrir menú"
                    aria-expanded="false"
                    aria-controls="aa-sidebar"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                    </svg>
                </button>

                <!-- Dynamic page title (synced from contextual view or active sidebar label) -->
                <span
                    id="aa-page-title"
                    class="min-w-0 truncate text-base font-semibold text-gray-600 tracking-tight"
                    hidden
                ></span>

                <?php if (isset($active_module) && $active_module === 'learning') : ?>
                <div id="aa-lists-area-tools" class="relative flex items-center gap-2 shrink-0">
                    <button type="button"
                        id="aa-lists-options-trigger"
                        data-lists-tool="options-menu"
                        title="Opciones de listas"
                        aria-haspopup="true"
                        aria-expanded="false"
                        class="aa-options-trigger-flat">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.75"/>
                            <circle cx="12" cy="12" r="1.75"/>
                            <circle cx="19" cy="12" r="1.75"/>
                        </svg>
                    </button>
                    <div id="aa-lists-options-menu"
                        class="hidden absolute left-0 top-full z-50 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                        role="menu">
                        <button type="button" role="menuitem"
                            data-lists-tool="create-list"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50">
                            <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Nueva lista</span>
                        </button>
                        <button type="button" role="menuitem"
                            data-lists-tool="restore-archived"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50">
                            <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 19a9 9 0 0014-7.5M19 5a9 9 0 00-14 7.5"/>
                            </svg>
                            <span>Desarchivar listas</span>
                        </button>
                        <button type="button" role="menuitem"
                            data-lists-tool="return-ignored-tasks"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50">
                            <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14L4 9m0 0l5-5M4 9h12a4 4 0 014 4v1"/>
                            </svg>
                            <span>Regresar tareas ignoradas</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                $aa_show_clients_tools = isset($active_module)
                    && $active_module === 'clients'
                    && (!isset($view_raw) || $view_raw !== 'expediente');
                if ($aa_show_clients_tools) :
                ?>
                <div id="aa-clients-area-tools" class="relative flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        id="aa-clients-options-trigger"
                        title="Opciones de clientes"
                        aria-label="Opciones de clientes"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        class="aa-options-trigger-flat"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.75"/>
                            <circle cx="12" cy="12" r="1.75"/>
                            <circle cx="19" cy="12" r="1.75"/>
                        </svg>
                    </button>
                    <div
                        id="aa-clients-options-menu"
                        class="hidden absolute left-0 top-full z-50 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                        role="menu"
                    >
                        <button
                            type="button"
                            role="menuitem"
                            data-clients-tool="create-client"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50"
                        >
                            <span>Nuevo cliente</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($active_module) && $active_module === 'calendar') : ?>
                <div id="aa-calendar-area-tools" class="relative flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        id="aa-calendar-options-trigger"
                        title="Opciones de agenda"
                        aria-label="Opciones de agenda"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        class="aa-options-trigger-flat"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.75"/>
                            <circle cx="12" cy="12" r="1.75"/>
                            <circle cx="19" cy="12" r="1.75"/>
                        </svg>
                    </button>
                    <div
                        id="aa-calendar-options-menu"
                        class="hidden absolute left-0 top-full z-50 mt-2 min-w-[12rem] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                        role="menu"
                    >
                        <button
                            type="button"
                            role="menuitem"
                            data-calendar-tool="search-appointments"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-600 hover:bg-gray-50"
                        >
                            <svg class="w-4 h-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span>Buscar citas</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Right: Notifications (island) -->
            <div class="flex items-center gap-2 shrink-0">
                <!-- Notifications: isla circular -->
                <div class="relative">
                    <button 
                        id="aa-btn-notifications" 
                        type="button"
                        class="inline-flex items-center justify-center w-9 h-9 text-gray-600 bg-white border border-gray-200 shadow-sm rounded-lg hover:bg-gray-50 hover:shadow hover:border-gray-300 active:bg-gray-100 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:ring-offset-1"
                        aria-label="Notificaciones"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                        </svg>
                    </button>
                    <!-- Badge -->
                    <span 
                        id="aa-notifications-badge" 
                        class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] text-[10px] font-semibold text-white bg-indigo-500 rounded-full ring-2 ring-white"
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
