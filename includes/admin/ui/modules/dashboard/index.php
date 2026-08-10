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

<div id="aa-dashboard-root" class="max-w-5xl mx-auto py-2">

    <!-- Date -->
    <div class="mb-2 text-right">
        <p id="aa-dashboard-date" class="text-xs text-gray-500 mx-5"></p>
    </div>

    <!-- Citas (fila colapsable) -->
    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden mb-3" data-aa-dashboard-collapse>
        <div
            class="aa-dash-collapse-toggle px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer select-none"
            data-aa-dashboard-collapse-toggle
            role="button"
            tabindex="0"
            aria-expanded="false"
            aria-controls="aa-dash-citas-body"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center min-w-0">
                    <span class="aa-dash-section-icon aa-dash-section-icon--blue flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </span>
                    <h3 class="text-lg font-semibold text-gray-600">Citas</h3>
                </div>
            </div>
        </div>
        <div id="aa-dash-citas-body" class="hidden" data-aa-dashboard-collapse-body>
            <div id="aa-dash-citas-cards" class="p-4 space-y-2">

            <!-- 1. Próxima cita -->
            <div class="aa-dash-collapse rounded-xl border border-gray-200 overflow-hidden" data-aa-dashboard-collapse>
                <div
                    class="aa-dash-collapse-toggle p-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer select-none"
                    data-aa-dashboard-collapse-toggle
                    role="button"
                    tabindex="0"
                    aria-expanded="false"
                    aria-controls="aa-dash-collapse-next-body"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center min-w-0">
                            <span class="aa-dash-section-icon aa-dash-section-icon--indigo flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </span>
                            <h4 class="text-sm font-semibold text-gray-600">Próxima cita</h4>
                        </div>
                    </div>
                </div>
                <div id="aa-dash-collapse-next-body" class="hidden" data-aa-dashboard-collapse-body>
                    <div id="aa-dash-next-appointment" class="p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 text-gray-600 flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p id="aa-dash-next-client" class="text-sm font-semibold text-gray-600 truncate">--</p>
                                <p id="aa-dash-next-service" class="text-sm text-gray-500 truncate">--</p>
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <span id="aa-dash-next-time-badge" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                        --
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Citas de hoy (cerrada por defecto) -->
            <div class="aa-dash-collapse rounded-xl border border-gray-200 overflow-hidden" data-aa-dashboard-collapse>
                <div
                    class="aa-dash-collapse-toggle p-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer select-none"
                    data-aa-dashboard-collapse-toggle
                    role="button"
                    tabindex="0"
                    aria-expanded="false"
                    aria-controls="aa-dash-collapse-today-body"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center min-w-0">
                            <span class="aa-dash-section-icon aa-dash-section-icon--blue flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </span>
                            <h4 class="text-sm font-semibold text-gray-600">Citas de hoy</h4>
                        </div>
                    </div>
                </div>
                <div id="aa-dash-collapse-today-body" class="hidden" data-aa-dashboard-collapse-body>
                    <div class="p-2">
                        <div class="grid grid-cols-2 gap-1.5">
                            <div class="text-center p-1.5 bg-gray-50 rounded-lg">
                                <p id="aa-dash-total" class="text-lg font-bold text-gray-700">--</p>
                                <p class="text-xs text-gray-600 mt-0.5">Citas hoy</p>
                            </div>
                            <div class="text-center p-1.5 bg-gray-50 rounded-lg">
                                <p id="aa-dash-confirmed" class="text-lg font-bold text-gray-700">--</p>
                                <p class="text-xs text-gray-600 mt-0.5">Confirmadas</p>
                            </div>
                            <div class="text-center p-1.5 bg-gray-50 rounded-lg">
                                <p id="aa-dash-pending" class="text-lg font-bold text-gray-700">--</p>
                                <p class="text-xs text-gray-600 mt-0.5">Pendientes</p>
                            </div>
                            <div class="text-center p-1.5 bg-gray-50 rounded-lg">
                                <p id="aa-dash-cancelled" class="text-lg font-bold text-gray-700">--</p>
                                <p class="text-xs text-gray-600 mt-0.5">Canceladas</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Ingresos (cerrada por defecto) -->
            <div class="aa-dash-collapse rounded-xl border border-gray-200 overflow-hidden" data-aa-dashboard-collapse>
                <div
                    class="aa-dash-collapse-toggle p-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer select-none"
                    data-aa-dashboard-collapse-toggle
                    role="button"
                    tabindex="0"
                    aria-expanded="false"
                    aria-controls="aa-dash-collapse-revenue-body"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center min-w-0">
                            <span class="aa-dash-section-icon aa-dash-section-icon--emerald flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </span>
                            <h4 class="text-sm font-semibold text-gray-600">Ingresos</h4>
                        </div>
                    </div>
                </div>
                <div id="aa-dash-collapse-revenue-body" class="hidden" data-aa-dashboard-collapse-body>
                    <div class="p-4">
                        <div class="flex items-center gap-2 flex-wrap mb-4 pb-3 border-b border-gray-100">
                            <select
                                id="aa-dash-revenue-mode"
                                class="text-sm font-semibold text-gray-600 bg-transparent border-none focus:ring-0 focus:outline-none cursor-pointer pr-6 appearance-none"
                                style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236b7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0 center; background-repeat: no-repeat; background-size: 1.25em;"
                            >
                                <option value="day">Ingresos del día</option>
                                <option value="week">Ingresos de la semana</option>
                                <option value="month">Ingresos del mes</option>
                            </select>
                            <div id="aa-dash-revenue-control">
                                <input type="text" id="aa-dash-revenue-date" class="text-xs border border-gray-200 rounded-lg px-2.5 py-1 w-28 text-center text-gray-600 cursor-pointer focus:ring-1 focus:ring-gray-300 focus:border-gray-400" readonly>
                                <select id="aa-dash-revenue-select" class="text-xs border border-gray-200 rounded-lg px-2.5 py-1 text-gray-600 focus:ring-1 focus:ring-gray-300 focus:border-gray-400 hidden"></select>
                            </div>
                        </div>
                        <h3 id="aa-dash-revenue-title" class="text-xs font-medium text-gray-500 mb-1">Ingresos del día</h3>
                        <p id="aa-dash-revenue" class="text-3xl font-bold text-gray-700 transition-opacity duration-150">--</p>
                        <p id="aa-dash-revenue-detail" class="text-xs text-gray-500 mt-1 transition-opacity duration-150">--</p>
                    </div>
                </div>
            </div>

            <!-- 4. Resumen semanal (cerrada por defecto) -->
            <div class="aa-dash-collapse rounded-xl border border-gray-200 overflow-hidden" data-aa-dashboard-collapse>
                <div
                    class="aa-dash-collapse-toggle p-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer select-none"
                    data-aa-dashboard-collapse-toggle
                    role="button"
                    tabindex="0"
                    aria-expanded="false"
                    aria-controls="aa-dash-collapse-week-body"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center min-w-0">
                            <span class="aa-dash-section-icon aa-dash-section-icon--purple flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </span>
                            <h4 class="text-sm font-semibold text-gray-600">Resumen semanal</h4>
                        </div>
                    </div>
                </div>
                <div id="aa-dash-collapse-week-body" class="hidden" data-aa-dashboard-collapse-body>
                    <div class="p-4">
                        <div class="flex items-center gap-2 flex-wrap mb-4 pb-3 border-b border-gray-100">
                            <select
                                id="aa-dash-week-metric"
                                class="text-sm font-semibold text-gray-600 bg-transparent border-none focus:ring-0 focus:outline-none cursor-pointer pr-6 appearance-none"
                                style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236b7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0 center; background-repeat: no-repeat; background-size: 1.25em;"
                            >
                                <option value="effective">Citas efectivas</option>
                                <option value="confirmed">Confirmadas</option>
                                <option value="attended">Asistidas</option>
                                <option value="pending">Pendientes</option>
                                <option value="cancelled">Canceladas</option>
                                <option value="no_show">No asistieron</option>
                            </select>
                            <div id="aa-dash-week-period" class="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                                <button type="button" data-period="7d" class="px-2.5 py-1 text-xs font-medium transition-colors bg-gray-200 text-gray-700">7 días</button>
                                <button type="button" data-period="30d" class="px-2.5 py-1 text-xs font-medium transition-colors bg-white text-gray-500 hover:bg-gray-50">30 días</button>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span id="aa-dash-week-current-label" class="text-sm text-gray-600">Últimos 7 días</span>
                                <span id="aa-dash-week-current" class="text-sm font-semibold text-gray-600">-- citas</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div id="aa-dash-week-bar" class="bg-gray-500 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span id="aa-dash-week-previous-label" class="text-sm text-gray-600">7 días previos</span>
                                <span id="aa-dash-week-previous" class="text-sm font-semibold text-gray-600">-- citas</span>
                            </div>
                            <div id="aa-dash-week-comparison" class="flex items-center gap-1.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        </div>
    </div>

    <!-- Alertas (fila colapsable) -->
    <div id="aa-dash-alerts-section" class="hidden bg-white rounded-xl shadow border border-gray-200 overflow-hidden mb-3" data-aa-dashboard-collapse>
        <div
            class="aa-dash-collapse-toggle px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white cursor-pointer select-none"
            data-aa-dashboard-collapse-toggle
            role="button"
            tabindex="0"
            aria-expanded="false"
            aria-controls="aa-dash-alerts-body"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center min-w-0">
                    <span class="aa-dash-section-icon aa-dash-section-icon--amber flex items-center justify-center w-8 h-8 rounded-lg flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.832c-.77-.833-2.194-.833-2.964 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                    </span>
                    <h3 class="text-lg font-semibold text-gray-600">Alertas</h3>
                </div>
            </div>
        </div>
        <div id="aa-dash-alerts-body" class="hidden" data-aa-dashboard-collapse-body>
            <div id="aa-dash-alerts" class="p-4">
                <div class="space-y-2">
                    <!-- Alerts rendered by JS -->
                </div>
            </div>
        </div>
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
</script>

<!-- Dashboard Service (consumes aa_get_citas_por_dia, must load before module) -->
<?php
$dashboard_service_js = AA_PLUGIN_URL . 'assets/js/services/dashboardService.js';
$dashboard_module_js = $plugin_url . 'dashboard-module.js';
?>
<script src="<?php echo esc_url($dashboard_service_js . '?ver=' . rawurlencode($dashboard_ver)); ?>" defer></script>
<script src="<?php echo esc_url($dashboard_module_js . '?ver=' . rawurlencode($dashboard_ver)); ?>" defer></script>
