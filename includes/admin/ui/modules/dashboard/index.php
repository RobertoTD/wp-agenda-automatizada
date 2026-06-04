<?php
/**
 * Dashboard Module - Resumen (overview) UI
 * 
 * This module handles:
 * - Daily summary cards (appointments, revenue)
 * - Next appointment preview
 * - Weekly comparison
 * - Alerts / action items
 * - No business logic (data operations handled via AJAX)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$plugin_url = plugin_dir_url(__FILE__);
$dashboard_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
?>

<div class="max-w-5xl mx-auto py-2">

    <!-- ═══════════════════════════════════════════════════════════════
         GRID PRINCIPAL: Resumen del día
    ═══════════════════════════════════════════════════════════════ -->

    <!-- Greeting -->
    <div class="mb-4">
        <h2 id="aa-dashboard-greeting" class="text-xl font-bold text-gray-900"></h2>
        <p id="aa-dashboard-date" class="text-sm text-gray-500 mt-0.5"></p>
    </div>

    <!-- Tarea actual -->
    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden mb-3">
        <div class="px-4 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-violet-100 text-violet-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </span>
                    <h3 class="text-base font-semibold text-gray-900">Tarea actual</h3>
                </div>
                <a
                    href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=learning')); ?>"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-violet-700 bg-violet-50 hover:bg-violet-100 rounded-lg border border-violet-200 transition-colors"
                >
                    Ir a Listas/tareas
                </a>
            </div>
        </div>
        <div id="aa-dash-current-task" class="p-4">
            <p id="aa-dash-current-task-loading" class="text-sm text-gray-500">Cargando tarea…</p>
            <p id="aa-dash-current-task-empty" class="hidden text-sm text-gray-500">Sin tareas pendientes por ahora.</p>
            <p id="aa-dash-current-task-error" class="hidden text-sm text-red-600">Error al cargar la tarea.</p>
            <div id="aa-dash-current-task-content" class="hidden"></div>
        </div>
    </div>

    <!-- Row 1: Hoy + Próxima cita -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">

        <!-- Card: Hoy -->
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-4 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 text-blue-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <h3 class="text-base font-semibold text-gray-900">Hoy</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <p id="aa-dash-total" class="text-2xl font-bold text-blue-700">--</p>
                        <p class="text-xs text-blue-600 mt-0.5">Citas hoy</p>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <p id="aa-dash-confirmed" class="text-2xl font-bold text-green-700">--</p>
                        <p class="text-xs text-green-600 mt-0.5">Confirmadas</p>
                    </div>
                    <div class="text-center p-3 bg-yellow-50 rounded-lg">
                        <p id="aa-dash-pending" class="text-2xl font-bold text-yellow-700">--</p>
                        <p class="text-xs text-yellow-600 mt-0.5">Pendientes</p>
                    </div>
                    <div class="text-center p-3 bg-red-50 rounded-lg">
                        <p id="aa-dash-cancelled" class="text-2xl font-bold text-red-700">--</p>
                        <p class="text-xs text-red-600 mt-0.5">Canceladas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card: Próxima cita -->
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-4 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <h3 class="text-base font-semibold text-gray-900">Próxima cita</h3>
                </div>
            </div>
            <div id="aa-dash-next-appointment" class="p-4">
                <div class="flex items-start gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p id="aa-dash-next-client" class="text-sm font-semibold text-gray-900 truncate">--</p>
                        <p id="aa-dash-next-service" class="text-sm text-gray-500 truncate">--</p>
                        <div class="flex items-center gap-1.5 mt-1.5">
                            <span id="aa-dash-next-time-badge" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                --
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Row 2: Ingreso estimado + Resumen semanal -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">

        <!-- Card: Ingreso estimado -->
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-2.5">
                        <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <select
                            id="aa-dash-revenue-mode"
                            class="text-sm font-semibold text-gray-900 bg-transparent border-none focus:ring-0 focus:outline-none cursor-pointer pr-6 -ml-1 appearance-none"
                            style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236b7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0 center; background-repeat: no-repeat; background-size: 1.25em;"
                        >
                            <option value="day">Ingresos del día</option>
                            <option value="week">Ingresos de la semana</option>
                            <option value="month">Ingresos del mes</option>
                        </select>
                    </div>
                    <div id="aa-dash-revenue-control">
                        <input type="text" id="aa-dash-revenue-date" class="text-xs border border-gray-200 rounded-lg px-2.5 py-1 w-28 text-center text-gray-700 cursor-pointer focus:ring-1 focus:ring-emerald-300 focus:border-emerald-300" readonly>
                        <select id="aa-dash-revenue-select" class="text-xs border border-gray-200 rounded-lg px-2.5 py-1 text-gray-700 focus:ring-1 focus:ring-emerald-300 focus:border-emerald-300 hidden"></select>
                    </div>
                </div>
            </div>
            <div class="p-4">
                <h3 id="aa-dash-revenue-title" class="text-xs font-medium text-gray-500 mb-1">Ingresos del día</h3>
                <p id="aa-dash-revenue" class="text-3xl font-bold text-emerald-700 transition-opacity duration-150">--</p>
                <p id="aa-dash-revenue-detail" class="text-xs text-gray-500 mt-1 transition-opacity duration-150">--</p>
            </div>
        </div>

        <!-- Card: Comparativa -->
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="flex items-center gap-2.5">
                        <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-purple-100 text-purple-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </span>
                        <select
                            id="aa-dash-week-metric"
                            class="text-sm font-semibold text-gray-900 bg-transparent border-none focus:ring-0 focus:outline-none cursor-pointer pr-6 -ml-1 appearance-none"
                            style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236b7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0 center; background-repeat: no-repeat; background-size: 1.25em;"
                        >
                            <option value="effective">Citas efectivas</option>
                            <option value="confirmed">Confirmadas</option>
                            <option value="attended">Asistidas</option>
                            <option value="pending">Pendientes</option>
                            <option value="cancelled">Canceladas</option>
                            <option value="no_show">No asistieron</option>
                        </select>
                    </div>
                    <div id="aa-dash-week-period" class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                        <button type="button" data-period="7d" class="px-2.5 py-1 text-xs font-medium transition-colors bg-purple-100 text-purple-700">7 días</button>
                        <button type="button" data-period="30d" class="px-2.5 py-1 text-xs font-medium transition-colors bg-white text-gray-500 hover:bg-gray-50">30 días</button>
                    </div>
                </div>
            </div>
            <div class="p-4">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span id="aa-dash-week-current-label" class="text-sm text-gray-600">Últimos 7 días</span>
                        <span id="aa-dash-week-current" class="text-sm font-semibold text-gray-900">-- citas</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div id="aa-dash-week-bar" class="bg-purple-500 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span id="aa-dash-week-previous-label" class="text-sm text-gray-600">7 días previos</span>
                        <span id="aa-dash-week-previous" class="text-sm font-semibold text-gray-900">-- citas</span>
                    </div>
                    <div id="aa-dash-week-comparison" class="flex items-center gap-1.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">--</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Row 3: Alertas -->
    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden mb-3">
        <div class="px-4 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <div class="flex items-center gap-2.5">
                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.832c-.77-.833-2.194-.833-2.964 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </span>
                <h3 class="text-base font-semibold text-gray-900">Alertas</h3>
            </div>
        </div>
        <div id="aa-dash-alerts" class="p-4">
            <div class="space-y-2">
                <!-- Alerts rendered by JS -->
            </div>
        </div>
    </div>

    <!-- Acción: Ir al calendario -->
    <div class="flex justify-center">
        <a 
            href="<?php echo esc_url(admin_url('admin-post.php?action=aa_iframe_content&module=calendar')); ?>"
            class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg border border-blue-200 transition-colors"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Ver agenda
        </a>
    </div>

</div>

<!-- Dashboard module data -->
<script>
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    }

    window.AA_DASHBOARD_DATA = {
        ajaxUrl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
        nonceProximasCitas: '<?php echo esc_js(wp_create_nonce('aa_proximas_citas')); ?>',
        nonceDashboardRevenue: '<?php echo esc_js(wp_create_nonce('aa_dashboard_revenue')); ?>',
        nonceDashboardComparison: '<?php echo esc_js(wp_create_nonce('aa_dashboard_comparison')); ?>',
        nonceDashboardAlerts: '<?php echo esc_js(wp_create_nonce('aa_dashboard_alerts')); ?>',
        today: '<?php
            $tz_string = get_option('aa_timezone', 'America/Mexico_City');
            echo esc_js(wp_date('Y-m-d', null, new DateTimeZone($tz_string)));
        ?>',
        currency: '<?php echo esc_js(get_option('aa_currency', 'MXN')); ?>'
    };

    window.AA_LEARNING_DATA = {
        ajaxUrl: window.ajaxurl || '<?php echo admin_url('admin-ajax.php'); ?>',
        action: 'aa_get_learning_recommendations',
        nonce: '<?php echo esc_js(wp_create_nonce('aa_get_learning_recommendations_nonce')); ?>'
    };
</script>

<!-- Dashboard Service (consumes aa_get_citas_por_dia, must load before module) -->
<?php
$learning_service_js = AA_PLUGIN_URL . 'assets/js/services/learningService.js';
$learning_renderer_js = AA_PLUGIN_URL . 'assets/js/ui/learningRecommendationRenderer.js';
$dashboard_service_js = AA_PLUGIN_URL . 'assets/js/services/dashboardService.js';
$dashboard_module_js = $plugin_url . 'dashboard-module.js';
?>
<script src="<?php echo esc_url($learning_service_js . '?ver=' . rawurlencode($dashboard_ver)); ?>" defer></script>
<script src="<?php echo esc_url($learning_renderer_js . '?ver=' . rawurlencode($dashboard_ver)); ?>" defer></script>
<script src="<?php echo esc_url($dashboard_service_js . '?ver=' . rawurlencode($dashboard_ver)); ?>" defer></script>
<script src="<?php echo esc_url($dashboard_module_js . '?ver=' . rawurlencode($dashboard_ver)); ?>" defer></script>
