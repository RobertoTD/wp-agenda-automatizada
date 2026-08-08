<?php
/**
 * Shared Sidebar - Drawer navigation component
 * 
 * This file provides:
 * - Overlay backdrop when sidebar is open
 * - Drawer panel with module navigation
 * 
 * Shell component: shared across all modules.
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// $active_module is available from parent scope (layout.php → index.php)
$active_module = isset($active_module) ? $active_module : 'calendar';
$brand_logo_url = aa_asset_url('includes/admin/ui/assets/img/deoia-citas-logo.svg');
?>

<!-- Sidebar Overlay (backdrop) -->
<div 
    id="aa-sidebar-overlay" 
    class="fixed inset-0 bg-black/50 z-[210] hidden opacity-0 transition-opacity duration-300"
    aria-hidden="true"
></div>

<!-- Sidebar Drawer -->
<aside 
    id="aa-sidebar" 
    class="fixed top-0 left-0 h-full w-64 bg-white shadow-xl z-[220] transform -translate-x-full transition-transform duration-300 ease-in-out"
    role="dialog"
    aria-modal="true"
    aria-label="Menú de navegación"
>
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between gap-2 px-4 py-4 border-b border-gray-200">
        <div class="flex items-center gap-2 min-w-0 flex-1">
            <span class="flex shrink-0 items-center justify-center w-8 h-8 rounded-lg bg-violet-100">
                <img
                    src="<?php echo $brand_logo_url; ?>"
                    alt="DEOIA Citas"
                    class="w-5 h-5"
                >
            </span>
            <div class="flex min-w-0 flex-col leading-none">
                <span class="text-sm font-semibold text-gray-900">DEOIA Citas</span>
                <?php if (!empty($aa_installation_slug)) : ?>
                    <span class="text-xs text-gray-500 truncate">Agenda: <?php echo esc_html($aa_installation_slug); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <button 
            id="aa-sidebar-close" 
            type="button"
            class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
            <!-- Agenda (module=calendar) -->
            <li>
                <a
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=calendar')); ?>"
                    data-aa-nav-module="calendar"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'calendar') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'calendar') ? 'text-indigo-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Agenda</span>
                </a>
            </li>

            <!-- Expedientes (reutiliza module=clients) -->
            <li>
                <a
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=clients')); ?>"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'clients') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'clients') ? 'text-indigo-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Expedientes</span>
                </a>
            </li>

            <!-- Ejecutor -->
            <li>
                <a 
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=learning')); ?>" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'learning') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'learning') ? 'text-indigo-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Tareas</span>
                </a>
            </li>
            
            <!-- Resumen (Dashboard) -->
            <li>
                <a 
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=dashboard')); ?>" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'dashboard') ? 'text-indigo-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Resumen</span>
                </a>
            </li>

            <!-- Separador -->
            <li class="my-3">
                <hr class="border-gray-200">
            </li>
            
            <!-- Asignaciones -->
            <li>
                <a 
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=assignments')); ?>" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'assignments') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'assignments') ? 'text-indigo-600' : 'text-gray-500'; ?>">
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
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'settings') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'settings') ? 'text-indigo-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Configuración</span>
                </a>
            </li>

            <!-- Cuenta -->
            <li>
                <a 
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=account')); ?>" 
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors <?php echo ($active_module === 'account') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'; ?>"
                >
                    <span class="flex items-center justify-center w-6 h-6 <?php echo ($active_module === 'account') ? 'text-indigo-600' : 'text-gray-500'; ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium">Cuenta</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
