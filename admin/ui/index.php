<?php
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Agenda Automatizada</title>
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'assets/app.css'; ?>" />
</head>

<body class="bg-gray-50 text-gray-900">
    <div id="aa-app" class="min-h-screen flex flex-col">

        <!-- ========== TOPBAR ========== -->
        <header class="sticky top-0 z-40 flex items-center justify-between h-16 px-4 md:px-6 bg-white border-b border-gray-200 shadow-sm">
            <!-- Left: Hamburger + Title -->
            <div class="flex items-center gap-4">
                <button 
                    id="aa-menu-toggle" 
                    type="button" 
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                    aria-label="Abrir menú"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h1 class="text-lg font-semibold text-gray-900 tracking-tight">Agenda Automatizada</h1>
            </div>

            <!-- Right: Future actions (user menu, notifications, etc.) -->
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-medium text-sm">
                    A
                </div>
            </div>
        </header>

        <!-- ========== OFF-CANVAS BACKDROP ========== -->
        <div 
            id="aa-sidebar-backdrop" 
            class="fixed inset-0 z-40 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-300"
        ></div>

        <!-- ========== OFF-CANVAS SIDEBAR ========== -->
        <aside 
            id="aa-sidebar" 
            class="fixed top-0 left-0 z-50 h-full w-72 bg-white shadow-xl transform -translate-x-full transition-transform duration-300 ease-in-out"
        >
            <!-- Sidebar Header -->
            <div class="flex items-center justify-between h-16 px-5 border-b border-gray-200">
                <span class="text-lg font-semibold text-gray-900">Menú</span>
                <button 
                    id="aa-menu-close" 
                    type="button" 
                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
                    aria-label="Cerrar menú"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Sidebar Navigation -->
            <nav class="p-4 space-y-1">
                <a href="#dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Dashboard
                </a>
                <a href="#configuracion" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Configuración
                </a>
                <a href="#asistente" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Asistente
                </a>
                <a href="#reportes" class="flex items-center gap-3 px-4 py-3 rounded-lg text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Reportes
                </a>
            </nav>

            <!-- Sidebar Footer -->
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200">
                <p class="text-xs text-gray-400 text-center">Agenda Automatizada v2.0</p>
            </div>
        </aside>

        <!-- ========== MAIN CONTENT ========== -->
        <main class="flex-1 w-full overflow-auto">
            <div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">
                
                <!-- Page Title -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Panel Administrativo</h2>
                    <p class="mt-1 text-sm text-gray-500">Gestiona tu agenda desde un solo lugar.</p>
                </div>

                <!-- Stats Cards Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Citas Hoy</span>
                            <span class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900">12</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Pendientes</span>
                            <span class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900">5</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Confirmadas</span>
                            <span class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900">7</p>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Clientes</span>
                            <span class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900">48</p>
                    </div>
                </div>

                <!-- Placeholder Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Próximas Citas</h3>
                    <p class="text-gray-500 text-sm">Aquí se mostrarán las próximas citas programadas.</p>
                    <div class="mt-4 flex items-center justify-center h-32 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
                        <span class="text-gray-400 text-sm">Contenido próximamente</span>
                    </div>
                </div>

            </div>
        </main>

    </div>

    <script src="<?php echo plugin_dir_url(__FILE__) . 'assets/app.js'; ?>"></script>
    <script>
        // ========== OFF-CANVAS MENU TOGGLE ==========
        (function() {
            const menuToggle = document.getElementById('aa-menu-toggle');
            const menuClose = document.getElementById('aa-menu-close');
            const sidebar = document.getElementById('aa-sidebar');
            const backdrop = document.getElementById('aa-sidebar-backdrop');

            function openMenu() {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop.classList.remove('opacity-0', 'pointer-events-none');
                backdrop.classList.add('opacity-100', 'pointer-events-auto');
                document.body.style.overflow = 'hidden';
            }

            function closeMenu() {
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.remove('opacity-100', 'pointer-events-auto');
                backdrop.classList.add('opacity-0', 'pointer-events-none');
                document.body.style.overflow = '';
            }

            menuToggle.addEventListener('click', openMenu);
            menuClose.addEventListener('click', closeMenu);
            backdrop.addEventListener('click', closeMenu);

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeMenu();
            });
        })();
    </script>
</body>
</html>
