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

    // ─── Card: Tarea actual ───────────────────────────────────

    var CURRENT_TASK_IDS = {
        loading: 'aa-dash-current-task-loading',
        empty: 'aa-dash-current-task-empty',
        error: 'aa-dash-current-task-error',
        content: 'aa-dash-current-task-content'
    };

    var DASHBOARD_EXECUTIVE_ACTION_SELECTOR = '[data-executive-action]';
    var currentTaskActionPending = false;
    var currentTaskActionsBound = false;

    function setCurrentTaskVisible(state) {
        var loadingEl = document.getElementById(CURRENT_TASK_IDS.loading);
        var emptyEl = document.getElementById(CURRENT_TASK_IDS.empty);
        var errorEl = document.getElementById(CURRENT_TASK_IDS.error);
        var contentEl = document.getElementById(CURRENT_TASK_IDS.content);

        if (loadingEl) loadingEl.classList.toggle('hidden', state !== 'loading');
        if (emptyEl) emptyEl.classList.toggle('hidden', state !== 'empty');
        if (errorEl) errorEl.classList.toggle('hidden', state !== 'error');
        if (contentEl) contentEl.classList.toggle('hidden', state !== 'content');
    }

    function findCurrentExecutiveTask(payload) {
        var tasks = payload && Array.isArray(payload.tasks) ? payload.tasks : [];
        var currentTask = null;
        var i;

        for (i = 0; i < tasks.length; i++) {
            if (tasks[i] && String(tasks[i].slot || '') === 'current') {
                currentTask = tasks[i];
                break;
            }
        }

        return currentTask;
    }

    function setDashboardExecutiveButtonsDisabled(disabled) {
        var root = document.getElementById('aa-dash-current-task');

        if (!root) {
            return;
        }

        root.querySelectorAll(DASHBOARD_EXECUTIVE_ACTION_SELECTOR).forEach(function (button) {
            button.disabled = disabled;

            if (disabled) {
                button.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                button.classList.remove('opacity-60', 'cursor-not-allowed');
            }
        });
    }

    function renderCurrentTaskFromProposal(payload) {
        var contentEl = document.getElementById(CURRENT_TASK_IDS.content);
        var renderer = window.AAExecutiveProposalRenderer;

        if (!renderer || typeof renderer.buildProposalParts !== 'function'
            || typeof renderer.renderCurrentTask !== 'function'
            || !payload || typeof payload !== 'object') {
            return false;
        }

        var parts = renderer.buildProposalParts(payload);
        var currentTask = findCurrentExecutiveTask(payload);

        if (parts.isEmpty || !currentTask) {
            if (contentEl) contentEl.innerHTML = '';
            setCurrentTaskVisible('empty');
            return true;
        }

        if (contentEl) {
            contentEl.innerHTML = renderer.renderCurrentTask(
                currentTask,
                parts.focusListTitle,
                { wrapper: 'div' }
            );
        }

        setCurrentTaskVisible('content');
        return true;
    }

    function runDashboardClientAction(clientAction) {
        var runner = window.AAExecutiveClientActionRunner;

        if (!runner || typeof runner.run !== 'function') {
            return Promise.resolve();
        }

        return runner.run(clientAction, {
            showError: function (message) {
                console.error('[DashboardModule] Executive client action error:', message);
                setCurrentTaskVisible('error');
            },
            onReload: function () {
                return loadCurrentTaskCard();
            }
        });
    }

    function handleDashboardExecutiveAction(button) {
        var service = window.AAExecutiveProposalService;
        var taskId = button.getAttribute('data-executive-task-id') || '';
        var actionKey = button.getAttribute('data-executive-action-key') || '';

        if (!service || typeof service.postExecutiveAction !== 'function') {
            console.warn('[DashboardModule] AAExecutiveProposalService not available for executive action');
            setCurrentTaskVisible('error');
            return Promise.resolve();
        }

        if (taskId === '' || actionKey === '') {
            return Promise.resolve();
        }

        if (currentTaskActionPending) {
            return Promise.resolve();
        }

        currentTaskActionPending = true;
        setDashboardExecutiveButtonsDisabled(true);

        return service.postExecutiveAction({
            taskId: taskId,
            actionKey: actionKey
        })
            .then(function (response) {
                if (response && response.proposal && typeof response.proposal === 'object') {
                    if (!renderCurrentTaskFromProposal(response.proposal)) {
                        return loadCurrentTaskCard();
                    }
                } else {
                    return loadCurrentTaskCard();
                }

                return runDashboardClientAction(response && response.client_action);
            })
            .catch(function (err) {
                console.error('[DashboardModule] Error executing executive action:', err);
                setCurrentTaskVisible('error');
            })
            .finally(function () {
                currentTaskActionPending = false;
                setDashboardExecutiveButtonsDisabled(false);
            });
    }

    function bindCurrentTaskActions() {
        var root = document.getElementById('aa-dash-current-task');

        if (!root || currentTaskActionsBound) {
            return;
        }

        currentTaskActionsBound = true;

        root.addEventListener('click', function (event) {
            if (!event || !event.target || typeof event.target.closest !== 'function') {
                return;
            }

            var button = event.target.closest(DASHBOARD_EXECUTIVE_ACTION_SELECTOR);

            if (!button || button.disabled || !root.contains(button)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            handleDashboardExecutiveAction(button);
        });
    }

    function loadCurrentTaskCard() {
        var contentEl = document.getElementById(CURRENT_TASK_IDS.content);
        var service = window.AAExecutiveProposalService;
        var renderer = window.AAExecutiveProposalRenderer;

        if (!document.getElementById(CURRENT_TASK_IDS.loading)) {
            return Promise.resolve();
        }

        if (!service || typeof service.getExecutiveProposal !== 'function') {
            console.warn('[DashboardModule] AAExecutiveProposalService not available for current task');
            setCurrentTaskVisible('error');
            return Promise.resolve();
        }

        if (!renderer || typeof renderer.buildProposalParts !== 'function'
            || typeof renderer.renderCurrentTask !== 'function') {
            console.warn('[DashboardModule] AAExecutiveProposalRenderer not available for current task');
            setCurrentTaskVisible('error');
            return Promise.resolve();
        }

        setCurrentTaskVisible('loading');

        return service.getExecutiveProposal()
            .then(function (payload) {
                if (!renderCurrentTaskFromProposal(payload)) {
                    if (contentEl) contentEl.innerHTML = '';
                    setCurrentTaskVisible('error');
                }
            })
            .catch(function (err) {
                console.error('[DashboardModule] Error loading current task:', err);
                if (contentEl) contentEl.innerHTML = '';
                setCurrentTaskVisible('error');
            });
    }

    // ─── Dashboard: filas y subsecciones colapsables ───────────

    var dashboardCollapsiblesBound = false;

    function isDashboardCollapseInteractiveTarget(target) {
        // No incluir [role="button"]: el toggle lleva role="button" y sería ignorado siempre.
        return !!target.closest(
            'button, a, select, input, textarea, label, [data-aa-no-collapse]'
        );
    }

    function notifyDashboardIframeResize() {
        if (window.self === window.top) {
            return;
        }

        window.requestAnimationFrame(function () {
            window.dispatchEvent(new Event('resize'));

            if (window.AAAdmin && typeof window.AAAdmin.iframeResize === 'function') {
                window.AAAdmin.iframeResize();
            }
        });
    }

    function setDashboardCollapseOpen(collapseEl, isOpen) {
        var toggle = collapseEl.querySelector('[data-aa-dashboard-collapse-toggle]');
        var body = collapseEl.querySelector('[data-aa-dashboard-collapse-body]');

        if (!toggle || !body) {
            return;
        }

        collapseEl.classList.toggle('is-open', isOpen);
        body.classList.toggle('hidden', !isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function toggleDashboardCollapse(collapseEl) {
        var isOpen = collapseEl.classList.contains('is-open');
        setDashboardCollapseOpen(collapseEl, !isOpen);
        notifyDashboardIframeResize();
    }

    function bindDashboardCollapsibles() {
        var root = document.getElementById('aa-dashboard-root');

        if (!root || dashboardCollapsiblesBound) {
            return;
        }

        dashboardCollapsiblesBound = true;

        root.addEventListener('click', function (event) {
            if (isDashboardCollapseInteractiveTarget(event.target)) {
                return;
            }

            var toggle = event.target.closest('[data-aa-dashboard-collapse-toggle]');

            if (!toggle || !root.contains(toggle)) {
                return;
            }

            var collapse = toggle.closest('[data-aa-dashboard-collapse]');

            if (!collapse) {
                return;
            }

            event.preventDefault();
            toggleDashboardCollapse(collapse);
        });

        root.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            var toggle = event.target.closest('[data-aa-dashboard-collapse-toggle]');

            if (!toggle || !root.contains(toggle) || isDashboardCollapseInteractiveTarget(event.target)) {
                return;
            }

            var collapse = toggle.closest('[data-aa-dashboard-collapse]');

            if (!collapse) {
                return;
            }

            event.preventDefault();
            toggleDashboardCollapse(collapse);
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

        bindDashboardCollapsibles();

        bindCurrentTaskActions();
        loadCurrentTaskCard();

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

    var moduleExports = {
        handleDashboardExecutiveAction: handleDashboardExecutiveAction,
        renderCurrentTaskFromProposal: renderCurrentTaskFromProposal,
        runDashboardClientAction: runDashboardClientAction,
        loadCurrentTaskCard: loadCurrentTaskCard
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleExports;
    }

    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        init();
    });
})();
