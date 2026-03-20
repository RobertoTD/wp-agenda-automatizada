/**
 * Dashboard Module - Module-specific JavaScript
 *
 * Renders the Resumen (dashboard) UI.
 * Card "Hoy" uses real data via DashboardService.
 * Other cards use mock data from AA_DASHBOARD_DATA (for now).
 *
 * Pure UI: no business logic, no direct AJAX calls.
 */

(function () {
    'use strict';

    var TODAY_IDS = {
        total: 'aa-dash-total',
        confirmed: 'aa-dash-confirmed',
        pending: 'aa-dash-pending',
        cancelled: 'aa-dash-cancelled'
    };

    function formatCurrency(amount, currency) {
        try {
            return new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: currency || 'MXN',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        } catch (_) {
            return '$' + amount.toLocaleString('es-MX');
        }
    }

    function renderGreeting() {
        var el = document.getElementById('aa-dashboard-greeting');
        var dateEl = document.getElementById('aa-dashboard-date');
        if (!el) return;

        var hour = new Date().getHours();
        var greeting = hour < 12 ? 'Buenos días' : hour < 18 ? 'Buenas tardes' : 'Buenas noches';
        el.textContent = greeting;

        if (dateEl) {
            var now = new Date();
            var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            dateEl.textContent = now.toLocaleDateString('es-MX', options);
        }
    }

    // ─── Card: Hoy ────────────────────────────────────────────

    function setTodayValues(values) {
        Object.keys(TODAY_IDS).forEach(function (key) {
            var el = document.getElementById(TODAY_IDS[key]);
            if (el) el.textContent = values[key] != null ? values[key] : '--';
        });
    }

    function setTodayLoading() {
        Object.keys(TODAY_IDS).forEach(function (key) {
            var el = document.getElementById(TODAY_IDS[key]);
            if (el) el.textContent = '…';
        });
    }

    function setTodayError() {
        Object.keys(TODAY_IDS).forEach(function (key) {
            var el = document.getElementById(TODAY_IDS[key]);
            if (el) el.textContent = '!';
        });
    }

    function loadTodayCard() {
        var data = window.AA_DASHBOARD_DATA;
        if (!data || !data.today) return;

        if (!window.DashboardService) {
            console.warn('[DashboardModule] DashboardService not available, card Hoy stays empty');
            return;
        }

        var dateYmd = data.today;
        if (window.DateUtils && typeof window.DateUtils.ymd === 'function' && !dateYmd) {
            dateYmd = window.DateUtils.ymd(new Date());
        }

        setTodayLoading();

        window.DashboardService.getTodaySummary(dateYmd)
            .then(function (summary) {
                setTodayValues(summary);
            })
            .catch(function (err) {
                console.error('[DashboardModule] Error loading today summary:', err);
                setTodayError();
            });
    }

    // ─── Card: Próxima cita ─────────────────────────────────────

    var NEXT_IDS = {
        client: 'aa-dash-next-client',
        service: 'aa-dash-next-service',
        badge: 'aa-dash-next-time-badge'
    };

    function setNextValues(appt) {
        var clientEl = document.getElementById(NEXT_IDS.client);
        var serviceEl = document.getElementById(NEXT_IDS.service);
        var badgeEl = document.getElementById(NEXT_IDS.badge);

        if (clientEl) clientEl.textContent = appt.nombre;
        if (serviceEl) serviceEl.textContent = appt.servicio;
        if (badgeEl) badgeEl.textContent = appt.timeUntilLabel;
    }

    function setNextLoading() {
        var clientEl = document.getElementById(NEXT_IDS.client);
        var serviceEl = document.getElementById(NEXT_IDS.service);
        var badgeEl = document.getElementById(NEXT_IDS.badge);

        if (clientEl) clientEl.textContent = '…';
        if (serviceEl) serviceEl.textContent = '';
        if (badgeEl) badgeEl.textContent = '…';
    }

    function setNextEmpty() {
        var clientEl = document.getElementById(NEXT_IDS.client);
        var serviceEl = document.getElementById(NEXT_IDS.service);
        var badgeEl = document.getElementById(NEXT_IDS.badge);

        if (clientEl) clientEl.textContent = 'Sin próximas citas';
        if (serviceEl) serviceEl.textContent = '';
        if (badgeEl) badgeEl.textContent = '--';
    }

    function setNextError() {
        var clientEl = document.getElementById(NEXT_IDS.client);
        var serviceEl = document.getElementById(NEXT_IDS.service);
        var badgeEl = document.getElementById(NEXT_IDS.badge);

        if (clientEl) clientEl.textContent = 'Error al cargar';
        if (serviceEl) serviceEl.textContent = '';
        if (badgeEl) badgeEl.textContent = '!';
    }

    function loadNextAppointmentCard() {
        if (!window.DashboardService || !window.DashboardService.getNextConfirmedAppointment) {
            console.warn('[DashboardModule] DashboardService.getNextConfirmedAppointment not available');
            return;
        }

        setNextLoading();

        window.DashboardService.getNextConfirmedAppointment()
            .then(function (appt) {
                if (!appt) {
                    setNextEmpty();
                    return;
                }
                setNextValues(appt);
            })
            .catch(function (err) {
                console.error('[DashboardModule] Error loading next appointment:', err);
                setNextError();
            });
    }

    // ─── Card: Ingreso estimado ─────────────────────────────────

    var REVENUE_IDS = {
        amount: 'aa-dash-revenue',
        detail: 'aa-dash-revenue-detail',
        mode: 'aa-dash-revenue-mode',
        dateInput: 'aa-dash-revenue-date',
        select: 'aa-dash-revenue-select'
    };

    var revenueState = {
        mode: 'day',
        value: null
    };

    var revenueDatepicker = null;

    var REVENUE_TITLES = {
        day: 'Ingresos del día',
        week: 'Ingresos de la semana',
        month: 'Ingresos del mes'
    };

    function updateRevenueTitle(mode) {
        var el = document.getElementById('aa-dash-revenue-title');
        if (el) el.textContent = REVENUE_TITLES[mode] || REVENUE_TITLES.day;
    }

    function setRevenueValues(total, count, currency) {
        var amountEl = document.getElementById(REVENUE_IDS.amount);
        var detailEl = document.getElementById(REVENUE_IDS.detail);

        if (amountEl) amountEl.textContent = formatCurrency(total, currency);
        if (detailEl) detailEl.textContent = count + ' citas consideradas';
    }

    function setRevenueLoading() {
        var amountEl = document.getElementById(REVENUE_IDS.amount);
        var detailEl = document.getElementById(REVENUE_IDS.detail);

        if (amountEl) amountEl.textContent = '…';
        if (detailEl) detailEl.textContent = '';
    }

    function setRevenueEmpty() {
        var amountEl = document.getElementById(REVENUE_IDS.amount);
        var detailEl = document.getElementById(REVENUE_IDS.detail);

        if (amountEl) amountEl.textContent = formatCurrency(0, 'MXN');
        if (detailEl) detailEl.textContent = 'Sin ingresos en este periodo';
    }

    function setRevenueError() {
        var amountEl = document.getElementById(REVENUE_IDS.amount);
        var detailEl = document.getElementById(REVENUE_IDS.detail);

        if (amountEl) amountEl.textContent = '!';
        if (detailEl) detailEl.textContent = 'Error al cargar';
    }

    function resolveRevenueRange() {
        var DU = window.DateUtils;
        if (!DU) return null;

        if (revenueState.mode === 'day') {
            return DU.getDayRange(revenueState.value);
        }

        if (revenueState.mode === 'week') {
            var weeks = DU.getLast12Weeks(revenueState.value);
            for (var i = 0; i < weeks.length; i++) {
                if (weeks[i].value === revenueState.value) return weeks[i];
            }
            return weeks[0] || null;
        }

        if (revenueState.mode === 'month') {
            var months = DU.getLast12Months(revenueState.value);
            for (var j = 0; j < months.length; j++) {
                if (months[j].value === revenueState.value) return months[j];
            }
            return months[0] || null;
        }

        return null;
    }

    function renderRevenueOptions(options) {
        var sel = document.getElementById(REVENUE_IDS.select);
        if (!sel) return;
        sel.innerHTML = '';
        for (var i = 0; i < options.length; i++) {
            var opt = document.createElement('option');
            opt.value = options[i].value;
            opt.textContent = options[i].label;
            sel.appendChild(opt);
        }
    }

    function switchRevenueMode(mode) {
        var DU = window.DateUtils;
        var data = window.AA_DASHBOARD_DATA;
        var today = (data && data.today) || (DU ? DU.ymd(new Date()) : '');

        revenueState.mode = mode;
        updateRevenueTitle(mode);

        var dateInput = document.getElementById(REVENUE_IDS.dateInput);
        var sel = document.getElementById(REVENUE_IDS.select);
        if (!dateInput || !sel) return;

        if (mode === 'day') {
            dateInput.classList.remove('hidden');
            sel.classList.add('hidden');
            revenueState.value = today;
            initRevenueDatepicker(today);
        } else if (mode === 'week' && DU) {
            dateInput.classList.add('hidden');
            sel.classList.remove('hidden');
            var weeks = DU.getLast12Weeks(today);
            renderRevenueOptions(weeks);
            revenueState.value = weeks.length ? weeks[0].value : today;
            sel.value = revenueState.value;
        } else if (mode === 'month' && DU) {
            dateInput.classList.add('hidden');
            sel.classList.remove('hidden');
            var months = DU.getLast12Months(today);
            renderRevenueOptions(months);
            revenueState.value = months.length ? months[0].value : today;
            sel.value = revenueState.value;
        }

        loadRevenueCard();
    }

    function initRevenueDatepicker(initialDate) {
        var input = document.getElementById(REVENUE_IDS.dateInput);
        if (!input || typeof flatpickr === 'undefined') return;

        if (revenueDatepicker) {
            revenueDatepicker.setDate(initialDate, false);
            return;
        }

        revenueDatepicker = flatpickr(input, {
            dateFormat: 'Y-m-d',
            locale: 'es',
            allowInput: false,
            clickOpens: true,
            defaultDate: initialDate,
            onChange: function (_, dateStr) {
                if (dateStr && revenueState.mode === 'day') {
                    revenueState.value = dateStr;
                    loadRevenueCard();
                }
            }
        });
    }

    function bindRevenueControls() {
        var modeSelect = document.getElementById(REVENUE_IDS.mode);
        if (modeSelect) {
            modeSelect.addEventListener('change', function () {
                switchRevenueMode(this.value);
            });
        }

        var sel = document.getElementById(REVENUE_IDS.select);
        if (sel) {
            sel.addEventListener('change', function () {
                revenueState.value = this.value;
                loadRevenueCard();
            });
        }
    }

    function loadRevenueCard() {
        if (!window.DashboardService || !window.DashboardService.getRevenueSummary) {
            console.warn('[DashboardModule] DashboardService.getRevenueSummary not available');
            return;
        }

        var range = resolveRevenueRange();
        if (!range) return;

        var amountEl = document.getElementById(REVENUE_IDS.amount);
        var detailEl = document.getElementById(REVENUE_IDS.detail);
        if (amountEl) amountEl.style.opacity = '0.4';
        if (detailEl) detailEl.style.opacity = '0.4';

        var currency = (window.AA_DASHBOARD_DATA && window.AA_DASHBOARD_DATA.currency) || 'MXN';

        window.DashboardService.getRevenueSummary(range.startDate, range.endDate)
            .then(function (rev) {
                if (amountEl) amountEl.style.opacity = '';
                if (detailEl) detailEl.style.opacity = '';
                if (rev.count === 0) {
                    setRevenueEmpty();
                    return;
                }
                setRevenueValues(rev.total, rev.count, currency);
            })
            .catch(function (err) {
                if (amountEl) amountEl.style.opacity = '';
                if (detailEl) detailEl.style.opacity = '';
                console.error('[DashboardModule] Error loading revenue:', err);
                setRevenueError();
            });
    }

    // ─── Card: Comparativa ─────────────────────────────────────

    var WEEKLY_IDS = {
        current: 'aa-dash-week-current',
        previous: 'aa-dash-week-previous',
        currentLabel: 'aa-dash-week-current-label',
        previousLabel: 'aa-dash-week-previous-label',
        bar: 'aa-dash-week-bar',
        comparison: 'aa-dash-week-comparison',
        metricSelect: 'aa-dash-week-metric',
        periodGroup: 'aa-dash-week-period'
    };

    var WEEKLY_DEFAULTS = {
        metricType: 'effective',
        periodPreset: '7d'
    };

    var PERIOD_LABELS = {
        '7d':  { current: 'Últimos 7 días',  previous: '7 días previos' },
        '30d': { current: 'Últimos 30 días', previous: '30 días previos' }
    };

    function updateWeeklyLabels(periodPreset) {
        var labels = PERIOD_LABELS[periodPreset] || PERIOD_LABELS['7d'];
        var curLabelEl  = document.getElementById(WEEKLY_IDS.currentLabel);
        var prevLabelEl = document.getElementById(WEEKLY_IDS.previousLabel);
        if (curLabelEl)  curLabelEl.textContent  = labels.current;
        if (prevLabelEl) prevLabelEl.textContent = labels.previous;
    }

    function updatePeriodButtons(activePeriod) {
        var group = document.getElementById(WEEKLY_IDS.periodGroup);
        if (!group) return;
        var buttons = group.querySelectorAll('button[data-period]');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            if (btn.getAttribute('data-period') === activePeriod) {
                btn.className = 'px-2.5 py-1 text-xs font-medium transition-colors bg-purple-100 text-purple-700';
            } else {
                btn.className = 'px-2.5 py-1 text-xs font-medium transition-colors bg-white text-gray-500 hover:bg-gray-50';
            }
        }
    }

    function setWeeklyValues(data) {
        var currentEl  = document.getElementById(WEEKLY_IDS.current);
        var previousEl = document.getElementById(WEEKLY_IDS.previous);
        var barEl      = document.getElementById(WEEKLY_IDS.bar);
        var compEl     = document.getElementById(WEEKLY_IDS.comparison);

        if (currentEl)  currentEl.textContent  = data.currentCount + ' citas';
        if (previousEl) previousEl.textContent = data.previousCount + ' citas';

        updateWeeklyLabels(data.periodPreset);

        if (barEl) {
            var maxRef = Math.max(data.currentCount, data.previousCount, 1);
            var pct = Math.min(100, Math.round((data.currentCount / maxRef) * 100));
            barEl.style.width = pct + '%';
        }

        if (compEl) {
            if (data.currentCount === 0 && data.previousCount === 0) {
                compEl.innerHTML =
                    '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Sin datos para comparar</span>';
                return;
            }

            var prevLabel = PERIOD_LABELS[data.periodPreset]
                ? 'vs ' + PERIOD_LABELS[data.periodPreset].previous.toLowerCase()
                : 'vs periodo anterior';

            if (data.previousCount === 0 && data.currentCount > 0) {
                compEl.innerHTML =
                    '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Nuevo ' + prevLabel + '</span>';
                return;
            }

            var isUp = data.trend === 'up';
            var isDown = data.trend === 'down';
            var badgeClass = isUp
                ? 'bg-green-100 text-green-700'
                : isDown
                    ? 'bg-red-100 text-red-700'
                    : 'bg-gray-100 text-gray-600';
            var arrow = isUp ? '↑' : isDown ? '↓' : '=';

            compEl.innerHTML =
                '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' +
                badgeClass + '">' + arrow + ' ' + Math.abs(data.pctChange) + '% ' + prevLabel + '</span>';
        }
    }

    function setWeeklyLoading() {
        var currentEl  = document.getElementById(WEEKLY_IDS.current);
        var previousEl = document.getElementById(WEEKLY_IDS.previous);
        var barEl      = document.getElementById(WEEKLY_IDS.bar);
        var compEl     = document.getElementById(WEEKLY_IDS.comparison);

        if (currentEl)  currentEl.textContent  = '…';
        if (previousEl) previousEl.textContent = '…';
        if (barEl)      barEl.style.width = '0%';
        if (compEl)     compEl.innerHTML = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">…</span>';
    }

    function setWeeklyError() {
        var currentEl  = document.getElementById(WEEKLY_IDS.current);
        var previousEl = document.getElementById(WEEKLY_IDS.previous);
        var compEl     = document.getElementById(WEEKLY_IDS.comparison);

        if (currentEl)  currentEl.textContent  = '!';
        if (previousEl) previousEl.textContent = '!';
        if (compEl)     compEl.innerHTML = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Error al cargar</span>';
    }

    function getActiveMetricType() {
        var sel = document.getElementById(WEEKLY_IDS.metricSelect);
        return sel ? sel.value : WEEKLY_DEFAULTS.metricType;
    }

    function getActivePeriodPreset() {
        var group = document.getElementById(WEEKLY_IDS.periodGroup);
        if (!group) return WEEKLY_DEFAULTS.periodPreset;
        var active = group.querySelector('button.bg-purple-100');
        return active ? active.getAttribute('data-period') : WEEKLY_DEFAULTS.periodPreset;
    }

    function loadWeeklyCard(metricType, periodPreset) {
        if (!window.DashboardService || !window.DashboardService.getComparisonSummary) {
            console.warn('[DashboardModule] DashboardService.getComparisonSummary not available');
            return;
        }

        var mt = metricType || getActiveMetricType();
        var pp = periodPreset || getActivePeriodPreset();

        setWeeklyLoading();
        updateWeeklyLabels(pp);

        window.DashboardService.getComparisonSummary(mt, pp)
            .then(function (data) {
                setWeeklyValues(data);
            })
            .catch(function (err) {
                console.error('[DashboardModule] Error loading weekly comparison:', err);
                setWeeklyError();
            });
    }

    function bindWeeklyControls() {
        var metricSel = document.getElementById(WEEKLY_IDS.metricSelect);
        if (metricSel) {
            metricSel.addEventListener('change', function () {
                loadWeeklyCard(this.value, getActivePeriodPreset());
            });
        }

        var periodGroup = document.getElementById(WEEKLY_IDS.periodGroup);
        if (periodGroup) {
            periodGroup.addEventListener('click', function (e) {
                var btn = e.target.closest('button[data-period]');
                if (!btn) return;
                var pp = btn.getAttribute('data-period');
                updatePeriodButtons(pp);
                loadWeeklyCard(getActiveMetricType(), pp);
            });
        }
    }

    // ─── Card: Alertas ──────────────────────────────────────────

    var SVG_ALERT_WARNING =
        '<svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.832c-.77-.833-2.194-.833-2.964 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>' +
        '</svg>';

    var SVG_ALERT_INFO =
        '<svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' +
        '</svg>';

    function getAlertsWrapper() {
        var container = document.getElementById('aa-dash-alerts');
        if (!container) return null;
        return container.querySelector('.space-y-2') || null;
    }

    function setAlertsLoading() {
        var wrapper = getAlertsWrapper();
        if (!wrapper) return;
        wrapper.innerHTML =
            '<p class="text-sm text-gray-400">Cargando alertas…</p>';
    }

    function setAlertsEmpty() {
        var wrapper = getAlertsWrapper();
        if (!wrapper) return;
        wrapper.innerHTML =
            '<div class="flex items-start gap-2.5 p-3 rounded-lg bg-green-50">' +
            '<svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>' +
            '</svg>' +
            '<span class="text-sm text-green-700">Sin alertas por ahora.</span>' +
            '</div>';
    }

    function setAlertsError() {
        var wrapper = getAlertsWrapper();
        if (!wrapper) return;
        wrapper.innerHTML =
            '<p class="text-sm text-red-500">Error al cargar alertas.</p>';
    }

    function renderAlertsData(data) {
        var wrapper = getAlertsWrapper();
        if (!wrapper) return;

        var hasToday = data.pendingTodayRemaining > 0;
        var hasFuture = data.pendingNext15Days > 0;

        if (!hasToday && !hasFuture) {
            setAlertsEmpty();
            return;
        }

        wrapper.innerHTML = '';

        if (hasToday) {
            var todayItem = document.createElement('div');
            todayItem.className = 'flex items-start gap-2.5 p-3 rounded-lg bg-amber-50';
            todayItem.innerHTML =
                SVG_ALERT_WARNING +
                '<span class="text-sm text-amber-800">' +
                data.pendingTodayRemaining + ' cita' + (data.pendingTodayRemaining !== 1 ? 's' : '') +
                ' sin confirmar para hoy</span>';
            wrapper.appendChild(todayItem);
        }

        if (hasFuture) {
            var futureItem = document.createElement('div');
            futureItem.className = 'flex items-start gap-2.5 p-3 rounded-lg bg-blue-50';
            futureItem.innerHTML =
                SVG_ALERT_INFO +
                '<span class="text-sm text-blue-800">' +
                data.pendingNext15Days + ' cita' + (data.pendingNext15Days !== 1 ? 's' : '') +
                ' sin confirmar en los próximos 15 días</span>';
            wrapper.appendChild(futureItem);
        }
    }

    function loadAlertsCard() {
        if (!window.DashboardService || !window.DashboardService.getAlertsSummary) {
            console.warn('[DashboardModule] DashboardService.getAlertsSummary not available');
            return;
        }

        setAlertsLoading();

        window.DashboardService.getAlertsSummary()
            .then(function (data) {
                renderAlertsData(data);
            })
            .catch(function (err) {
                console.error('[DashboardModule] Error loading alerts:', err);
                setAlertsError();
            });
    }

    // ─── Init ─────────────────────────────────────────────────

    function init() {
        var data = window.AA_DASHBOARD_DATA;
        if (!data) {
            console.warn('[DashboardModule] AA_DASHBOARD_DATA not available');
            return;
        }

        renderGreeting();

        loadTodayCard();

        loadNextAppointmentCard();

        revenueState.value = data.today;
        updateRevenueTitle(revenueState.mode);
        initRevenueDatepicker(data.today);
        bindRevenueControls();
        loadRevenueCard();

        bindWeeklyControls();
        loadWeeklyCard();

        loadAlertsCard();
    }

    document.addEventListener('DOMContentLoaded', function () {
        init();
    });
})();
