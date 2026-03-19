/**
 * Dashboard Service
 *
 * Consumes existing AJAX endpoints and normalizes data
 * for the Dashboard module UI.
 *
 * Depends on:
 * - window.AA_DASHBOARD_DATA (ajaxUrl, nonceProximasCitas, today)
 * - window.DateUtils (optional, for date helpers)
 */
(function () {
    'use strict';

    function getConfig() {
        var cfg = window.AA_DASHBOARD_DATA;
        if (!cfg || !cfg.ajaxUrl || !cfg.nonceProximasCitas) {
            return null;
        }
        return cfg;
    }

    /**
     * Fetch day summary from aa_get_citas_por_dia and normalize counts.
     * @param {string} dateYmd - Date in YYYY-MM-DD format
     * @returns {Promise<Object>} { total, confirmed, pending, cancelled, citas }
     */
    function getTodaySummary(dateYmd) {
        var cfg = getConfig();
        if (!cfg) {
            return Promise.reject(new Error('AA_DASHBOARD_DATA not configured'));
        }

        var formData = new FormData();
        formData.append('action', 'aa_get_citas_por_dia');
        formData.append('_wpnonce', cfg.nonceProximasCitas);
        formData.append('fecha', dateYmd);

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function (result) {
            if (!result.success || !result.data || !Array.isArray(result.data.citas)) {
                throw new Error(result.data && result.data.message ? result.data.message : 'Respuesta inválida');
            }

            var citas = result.data.citas;
            var confirmed = 0;
            var pending = 0;
            var cancelled = 0;

            citas.forEach(function (cita) {
                switch (cita.estado) {
                    case 'confirmed': confirmed++; break;
                    case 'pending':   pending++;   break;
                    case 'cancelled': cancelled++; break;
                }
            });

            return {
                total: citas.length,
                confirmed: confirmed,
                pending: pending,
                cancelled: cancelled,
                citas: citas
            };
        });
    }

    var SEARCH_AHEAD_DAYS = 14;

    /**
     * Fetch citas for a single day via aa_get_citas_por_dia.
     * @param {string} dateYmd - YYYY-MM-DD
     * @returns {Promise<Array>} Array of cita objects (may be empty)
     */
    function fetchCitasForDay(dateYmd) {
        var cfg = getConfig();
        if (!cfg) {
            return Promise.reject(new Error('AA_DASHBOARD_DATA not configured'));
        }

        var formData = new FormData();
        formData.append('action', 'aa_get_citas_por_dia');
        formData.append('_wpnonce', cfg.nonceProximasCitas);
        formData.append('fecha', dateYmd);

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (result) {
            if (!result.success || !result.data || !Array.isArray(result.data.citas)) {
                return [];
            }
            return result.data.citas;
        });
    }

    /**
     * Find the next confirmed appointment starting from now,
     * searching day by day up to SEARCH_AHEAD_DAYS.
     * @returns {Promise<Object|null>} Normalized appointment or null
     */
    function getNextConfirmedAppointment() {
        var DU = window.DateUtils;
        if (!DU || !DU.parseMysqlDateTime || !DU.ymd) {
            return Promise.reject(new Error('DateUtils not available'));
        }

        var now = new Date();
        var dates = [];
        for (var i = 0; i <= SEARCH_AHEAD_DAYS; i++) {
            var d = new Date(now);
            d.setDate(d.getDate() + i);
            dates.push(DU.ymd(d));
        }

        function searchDay(index) {
            if (index >= dates.length) return Promise.resolve(null);

            return fetchCitasForDay(dates[index]).then(function (citas) {
                for (var j = 0; j < citas.length; j++) {
                    var cita = citas[j];
                    if (cita.estado !== 'confirmed') continue;

                    var fechaDate = DU.parseMysqlDateTime(cita.fecha);
                    if (!fechaDate || fechaDate <= now) continue;

                    return {
                        nombre: cita.nombre || 'Sin nombre',
                        servicio: cita.servicio || 'Sin servicio',
                        fecha: cita.fecha,
                        fechaDate: fechaDate,
                        timeUntilLabel: DU.formatTimeUntil(fechaDate, now)
                    };
                }
                return searchDay(index + 1);
            });
        }

        return searchDay(0);
    }

    /**
     * Fetch revenue summary for a date range via aa_get_dashboard_revenue.
     * @param {string} startDate - YYYY-MM-DD
     * @param {string} endDate   - YYYY-MM-DD
     * @returns {Promise<Object>} { total: number, count: number }
     */
    function getRevenueSummary(startDate, endDate) {
        var cfg = window.AA_DASHBOARD_DATA;
        if (!cfg || !cfg.ajaxUrl || !cfg.nonceDashboardRevenue) {
            return Promise.reject(new Error('AA_DASHBOARD_DATA revenue config missing'));
        }

        var formData = new FormData();
        formData.append('action', 'aa_get_dashboard_revenue');
        formData.append('_wpnonce', cfg.nonceDashboardRevenue);
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (result) {
            if (!result.success || !result.data) {
                throw new Error(result.data && result.data.message ? result.data.message : 'Respuesta inválida');
            }
            return {
                total: parseFloat(result.data.total) || 0,
                count: parseInt(result.data.count, 10) || 0
            };
        });
    }

    /**
     * Fetch comparison summary via aa_get_dashboard_comparison_summary.
     * @param {string} metricType   - effective | confirmed | attended | pending | cancelled | no_show
     * @param {string} periodPreset - 7d | 30d
     * @returns {Promise<Object>} { current_count, previous_count, pct_change, trend, metric_type, period_preset }
     */
    function getComparisonSummary(metricType, periodPreset) {
        var cfg = window.AA_DASHBOARD_DATA;
        if (!cfg || !cfg.ajaxUrl || !cfg.nonceDashboardComparison) {
            return Promise.reject(new Error('AA_DASHBOARD_DATA comparison config missing'));
        }

        var formData = new FormData();
        formData.append('action', 'aa_get_dashboard_comparison_summary');
        formData.append('_wpnonce', cfg.nonceDashboardComparison);
        formData.append('metric_type', metricType);
        formData.append('period_preset', periodPreset);

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (result) {
            if (!result.success || !result.data) {
                throw new Error(result.data && result.data.message ? result.data.message : 'Respuesta inválida');
            }
            return {
                currentCount:  parseInt(result.data.current_count, 10) || 0,
                previousCount: parseInt(result.data.previous_count, 10) || 0,
                pctChange:     parseInt(result.data.pct_change, 10) || 0,
                trend:         result.data.trend || 'neutral',
                metricType:    result.data.metric_type,
                periodPreset:  result.data.period_preset
            };
        });
    }

    /**
     * Fetch alerts summary via aa_get_dashboard_alerts_summary.
     * @returns {Promise<Object>} { pendingTodayRemaining: number, pendingNext15Days: number }
     */
    function getAlertsSummary() {
        var cfg = window.AA_DASHBOARD_DATA;
        if (!cfg || !cfg.ajaxUrl || !cfg.nonceDashboardAlerts) {
            return Promise.reject(new Error('AA_DASHBOARD_DATA alerts config missing'));
        }

        var formData = new FormData();
        formData.append('action', 'aa_get_dashboard_alerts_summary');
        formData.append('_wpnonce', cfg.nonceDashboardAlerts);

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (result) {
            if (!result.success || !result.data) {
                throw new Error(result.data && result.data.message ? result.data.message : 'Respuesta inválida');
            }
            return {
                pendingTodayRemaining: parseInt(result.data.pending_today_remaining, 10) || 0,
                pendingNext15Days:     parseInt(result.data.pending_next_15_days, 10) || 0
            };
        });
    }

    window.DashboardService = {
        getTodaySummary: getTodaySummary,
        getNextConfirmedAppointment: getNextConfirmedAppointment,
        getRevenueSummary: getRevenueSummary,
        getComparisonSummary: getComparisonSummary,
        getAlertsSummary: getAlertsSummary
    };
})();
