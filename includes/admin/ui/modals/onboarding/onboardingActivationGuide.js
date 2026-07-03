/**
 * Onboarding Activation Guide — checklist modal (MC5A render, MC5B CTAs, MC5D3 Google recommended).
 *
 * Renders backend status from OnboardingStatusService; no business rules in JS.
 * MC1: visible checklist shows only the test-appointment step; setup steps stay internal.
 */
(function () {
    'use strict';

    var ALL_STEP_KEYS = ['client', 'service', 'staff', 'area', 'first_appointment'];

    /** Steps shown in the onboarding checklist UI (presentation only). */
    var VISIBLE_STEP_KEYS = ['first_appointment'];

    var DISPLAY_LABELS = {
        first_appointment: 'Crear cita de prueba'
    };

    var BODY_TEMPLATE_ID = 'aa-onboarding-activation-guide-body-template';
    var FOOTER_TEMPLATE_ID = 'aa-onboarding-activation-guide-footer-template';

    var STATIC_INSTRUCTIONS = {
        client: 'Agrega un cliente real o de prueba para comenzar.',
        service: 'Crea el servicio que vas a ofrecer, por ejemplo: Consulta general.',
        area: 'Crea una zona o área donde se atenderán las citas.',
        first_appointment: 'Usa datos ficticios de prueba para agendar una cita y ver cómo aparece en tu agenda.'
    };

    var CTA_LABELS = {
        client: 'Crear cliente',
        service: 'Crear servicio',
        staff_missing_active_staff: 'Agregar personal',
        staff_missing_staff_service_assignment: 'Asignar servicio al personal',
        staff_fallback: 'Configurar personal',
        area: 'Crear zona de atención',
        first_appointment: 'Crear cita de prueba'
    };

    /** Same contract as AA_AI_Setup_Action_Link_Builder + module setup_focus handlers. */
    var NAV_TARGETS = {
        client: { module: 'clients', setupFocus: 'clients', hash: 'aa-clients-grid' },
        service: { module: 'assignments', setupFocus: 'services', hash: 'aa-services-root' },
        area: { module: 'assignments', setupFocus: 'areas', hash: 'aa-areas-root' }
    };

    var CHECK_ICON = '<svg class="w-4 h-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
        + '<path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.42l2.293 2.294 6.543-6.544a1 1 0 011.414 0z" clip-rule="evenodd"></path>'
        + '</svg>';

    var CTA_ENABLED_CLASS = 'aa-onboarding-activation-cta inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors';

    var GOOGLE_CALENDAR_NAV = {
        module: 'settings',
        setupFocus: 'google_calendar',
        hash: 'aa-google-calendar-root'
    };

    var GOOGLE_RECOMMENDED_TITLE = 'Recomendado: vincula tu cuenta de Google';

    var GOOGLE_RECOMMENDED_BODY = 'Sincroniza automáticamente tus citas con Google Calendar. Si usas Gmail, '
        + 'probablemente ya tienes Google Calendar incluido gratis. Esto puede ayudarte a recibir recordatorios '
        + 'y reducir olvidos o faltas a las citas.';

    var lastStatus = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function closeGuideModal() {
        if (window.AAAdmin && typeof window.AAAdmin.closeModal === 'function') {
            window.AAAdmin.closeModal();
        }
    }

    /**
     * @param {string} module
     * @param {string} setupFocus
     * @param {string} hash
     */
    function navigateToModule(module, setupFocus, hash) {
        closeGuideModal();

        try {
            var url = new URL(window.location.href);
            url.searchParams.set('action', 'aa_iframe_content');
            url.searchParams.set('module', module);
            url.searchParams.set('setup_focus', setupFocus);
            url.hash = hash.charAt(0) === '#' ? hash : '#' + hash;
            window.location.href = url.toString();
        } catch (err) {
            console.error('[OnboardingActivationGuide] navigateToModule failed:', err);
        }
    }

    function navigateToGoogleCalendarSettings() {
        navigateToModule(
            GOOGLE_CALENDAR_NAV.module,
            GOOGLE_CALENDAR_NAV.setupFocus,
            GOOGLE_CALENDAR_NAV.hash
        );
    }

    /**
     * @param {object} status
     * @returns {object|null}
     */
    function getGoogleCalendarRecommendation(status) {
        if (!status || !status.recommendations || !status.recommendations.google_calendar) {
            return null;
        }

        return status.recommendations.google_calendar;
    }

    /**
     * @param {object|null|undefined} step
     * @returns {{module:string,setupFocus:string,hash:string}}
     */
    function staffNavTarget(step) {
        if (step && step.reason === 'missing_staff_service_assignment') {
            return {
                module: 'assignments',
                setupFocus: 'staff_services',
                hash: 'aa-staff-root'
            };
        }

        return {
            module: 'assignments',
            setupFocus: 'staff',
            hash: 'aa-staff-root'
        };
    }

    /**
     * @param {string} stepKey
     * @param {object|null|undefined} step
     * @returns {{type:'navigate',module:string,setupFocus:string,hash:string}|{type:'fast_appointment'}|null}
     */
    function resolveCtaTarget(stepKey, step) {
        if (stepKey === 'first_appointment') {
            return { type: 'fast_appointment' };
        }

        if (stepKey === 'staff') {
            var staffTarget = staffNavTarget(step);
            return {
                type: 'navigate',
                module: staffTarget.module,
                setupFocus: staffTarget.setupFocus,
                hash: staffTarget.hash
            };
        }

        var nav = NAV_TARGETS[stepKey];
        if (!nav) {
            return null;
        }

        return {
            type: 'navigate',
            module: nav.module,
            setupFocus: nav.setupFocus,
            hash: nav.hash
        };
    }

    /**
     * @param {object|null|undefined} status
     * @returns {boolean}
     */
    function isFirstAppointmentPending(status) {
        if (!status || status.activation_complete) {
            return false;
        }

        var firstStep = status.steps && status.steps.first_appointment;

        return !(firstStep && firstStep.completed);
    }

    /**
     * @param {string} stepKey
     * @param {object} status
     * @returns {boolean}
     */
    function isActionableStep(stepKey, status) {
        if (!status || typeof status !== 'object') {
            return false;
        }

        if (stepKey === 'first_appointment') {
            return isFirstAppointmentPending(status);
        }

        return status.next_step === stepKey;
    }

    /**
     * Step key used to highlight the active card and bind the CTA in the visible checklist.
     *
     * @param {object|null|undefined} status
     * @returns {string|null}
     */
    function getPresentationNextStep(status) {
        if (isFirstAppointmentPending(status)) {
            return 'first_appointment';
        }

        return null;
    }

    /**
     * @param {string} stepKey
     * @param {object|null|undefined} step
     * @returns {string}
     */
    function getDisplayLabel(stepKey, step) {
        if (DISPLAY_LABELS[stepKey]) {
            return DISPLAY_LABELS[stepKey];
        }

        return (step && step.label) ? step.label : stepKey;
    }

    function handleCtaClick(stepKey, step, status) {
        if (!isActionableStep(stepKey, status)) {
            return;
        }

        var target = resolveCtaTarget(stepKey, step);
        if (!target) {
            return;
        }

        if (target.type === 'fast_appointment') {
            closeGuideModal();

            if (window.FastAppointmentModal && typeof window.FastAppointmentModal.open === 'function') {
                window.FastAppointmentModal.open();
            } else {
                console.warn('[OnboardingActivationGuide] FastAppointmentModal.open no disponible');
            }

            return;
        }

        navigateToModule(target.module, target.setupFocus, target.hash);
    }

    function getTemplateHtml(templateId) {
        var template = document.getElementById(templateId);

        if (!template || !template.content) {
            console.error('[OnboardingActivationGuide] Template no encontrado:', templateId);
            return '';
        }

        var clone = template.content.cloneNode(true);
        var container = document.createElement('div');
        container.appendChild(clone);
        return container.innerHTML;
    }

    /**
     * @param {object|null|undefined} step
     * @returns {string}
     */
    function staffInstruction(step) {
        if (!step || typeof step.reason !== 'string') {
            return 'Configura el personal que atenderá las citas.';
        }

        if (step.reason === 'missing_active_staff') {
            return 'Agrega a la persona que atenderá las citas.';
        }

        if (step.reason === 'missing_staff_service_assignment') {
            return 'Asigna un servicio activo al personal para que pueda recibir citas.';
        }

        return 'Configura el personal que atenderá las citas.';
    }

    /**
     * @param {string} stepKey
     * @param {object|null|undefined} step
     * @returns {string}
     */
    function instructionForStep(stepKey, step) {
        if (stepKey === 'staff') {
            return staffInstruction(step);
        }

        return STATIC_INSTRUCTIONS[stepKey] || '';
    }

    /**
     * @param {string} stepKey
     * @param {object|null|undefined} step
     * @returns {string}
     */
    function ctaLabelForStep(stepKey, step) {
        if (stepKey === 'staff') {
            if (step && step.reason === 'missing_active_staff') {
                return CTA_LABELS.staff_missing_active_staff;
            }
            if (step && step.reason === 'missing_staff_service_assignment') {
                return CTA_LABELS.staff_missing_staff_service_assignment;
            }
            return CTA_LABELS.staff_fallback;
        }

        return CTA_LABELS[stepKey] || 'Continuar';
    }

    /**
     * @param {string} stepKey
     * @param {object} step
     * @param {boolean} isNext
     * @returns {string}
     */
    function renderStepCard(stepKey, step, isNext, status) {
        var label = getDisplayLabel(stepKey, step);
        var completed = !!step.completed;
        var statusText = completed ? 'Completado' : 'Pendiente';
        var statusClass = completed ? 'text-emerald-700' : 'text-amber-700';
        var showCta = isNext && isActionableStep(stepKey, status);

        if (isNext) {
            var instruction = instructionForStep(stepKey, step);
            var ctaLabel = ctaLabelForStep(stepKey, step);
            var ctaHtml = showCta
                ? '  <div class="mt-3">'
                    + '    <button type="button" class="' + CTA_ENABLED_CLASS + '" data-aa-onboarding-step-key="' + escapeHtml(stepKey) + '">'
                    + escapeHtml(ctaLabel)
                    + '    </button>'
                    + '  </div>'
                : '';

            return ''
                + '<div class="rounded-xl border border-indigo-200 bg-indigo-50/80 p-4" data-aa-onboarding-step="' + escapeHtml(stepKey) + '">'
                + '  <div class="flex items-start justify-between gap-2">'
                + '    <h4 class="text-sm font-semibold text-gray-900">' + escapeHtml(label) + '</h4>'
                + '    <span class="text-xs font-medium ' + statusClass + '">' + escapeHtml(statusText) + '</span>'
                + '  </div>'
                + '  <p class="mt-2 text-sm text-gray-700 leading-relaxed">' + escapeHtml(instruction) + '</p>'
                + ctaHtml
                + '</div>';
        }

        var leading = completed ? CHECK_ICON : '<span class="w-4 h-4 shrink-0 rounded-full border border-gray-300" aria-hidden="true"></span>';

        return ''
            + '<div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm" data-aa-onboarding-step="' + escapeHtml(stepKey) + '">'
            + leading
            + '<span class="flex-1 font-medium text-gray-800">' + escapeHtml(label) + '</span>'
            + '<span class="text-xs ' + statusClass + '">' + escapeHtml(statusText) + '</span>'
            + '</div>';
    }

    /**
     * @param {object} google
     * @returns {string}
     */
    function renderGoogleCalendarRecommendationBlock(google) {
        if (!google || typeof google.status !== 'string') {
            return '';
        }

        var status = google.status;
        var email = google.email ? String(google.email) : '';

        if (status === 'connected') {
            var connectedLines = '<p class="text-sm font-medium text-emerald-800">Google Calendar conectado</p>';

            if (email) {
                connectedLines += '<p class="mt-1 text-sm text-emerald-700">Conectado como '
                    + escapeHtml(email)
                    + '</p>';
            }

            return ''
                + '<div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-4" data-aa-onboarding-google-recommended="1">'
                + '  <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Recomendado</p>'
                + '  <div class="mt-2">' + connectedLines + '</div>'
                + '</div>';
        }

        if (status === 'needs_reconnect') {
            return ''
                + '<div class="rounded-xl border border-amber-200 bg-amber-50/90 p-4" data-aa-onboarding-google-recommended="1">'
                + '  <div class="flex flex-wrap items-center gap-2">'
                + '    <span class="text-xs font-medium uppercase tracking-wide text-amber-800">Recomendado</span>'
                + '    <span class="text-xs font-semibold text-amber-900 bg-amber-100 px-2 py-0.5 rounded-full">Requiere reconexión</span>'
                + '  </div>'
                + '  <h4 class="mt-2 text-sm font-semibold text-gray-900">' + escapeHtml(GOOGLE_RECOMMENDED_TITLE) + '</h4>'
                + '  <p class="mt-2 text-sm text-gray-700 leading-relaxed">' + escapeHtml(GOOGLE_RECOMMENDED_BODY) + '</p>'
                + '  <div class="mt-3">'
                + '    <button type="button" class="' + CTA_ENABLED_CLASS + ' bg-amber-600 hover:bg-amber-700 aa-onboarding-google-calendar-cta">'
                + 'Reconectar Google Calendar'
                + '    </button>'
                + '  </div>'
                + '</div>';
        }

        if (status === 'not_connected') {
            return ''
                + '<div class="rounded-xl border border-sky-200 bg-sky-50/70 p-4" data-aa-onboarding-google-recommended="1">'
                + '  <p class="text-xs font-medium uppercase tracking-wide text-sky-800">Recomendado</p>'
                + '  <h4 class="mt-2 text-sm font-semibold text-gray-900">' + escapeHtml(GOOGLE_RECOMMENDED_TITLE) + '</h4>'
                + '  <p class="mt-2 text-sm text-gray-700 leading-relaxed">' + escapeHtml(GOOGLE_RECOMMENDED_BODY) + '</p>'
                + '  <div class="mt-3">'
                + '    <button type="button" class="' + CTA_ENABLED_CLASS + ' aa-onboarding-google-calendar-cta">'
                + 'Configurar Google Calendar'
                + '    </button>'
                + '  </div>'
                + '</div>';
        }

        return '';
    }

    /**
     * @param {object} status
     * @returns {string}
     */
    function buildGoogleCalendarRecommendationHtml(status) {
        var google = getGoogleCalendarRecommendation(status);

        return renderGoogleCalendarRecommendationBlock(google);
    }

    function bindGoogleCalendarCta() {
        var root = document.querySelector('[data-aa-onboarding-activation-guide="1"]');

        if (!root) {
            return;
        }

        var button = root.querySelector('.aa-onboarding-google-calendar-cta');

        if (!button || button.dataset.onboardingGoogleCtaBound === '1') {
            return;
        }

        button.dataset.onboardingGoogleCtaBound = '1';
        button.addEventListener('click', function () {
            navigateToGoogleCalendarSettings();
        });
    }

    /**
     * @param {object} status
     * @returns {string}
     */
    function buildSummaryHtml(status) {
        if (status.activation_complete) {
            return '<p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">'
                + 'Cita de prueba creada. Ya puedes explorar tu agenda y consultar tus citas.'
                + '</p>';
        }

        var parts = [
            '<p class="text-sm text-gray-700 leading-relaxed">'
            + 'Crea una cita de prueba con datos ficticios para conocer el flujo de tu agenda '
            + 'sin afectar clientes reales.'
            + '</p>',
            '<p class="mt-2 text-sm text-gray-600 leading-relaxed">'
            + 'Para citas reales después deberás configurar tus servicios, agregar clientes reales y, '
            + 'si lo deseas, organizar personal y zonas de atención desde las secciones correspondientes.'
            + '</p>'
        ];

        return parts.join('');
    }

    /**
     * @param {object} status
     * @returns {string}
     */
    function buildBodyHtml(status) {
        var shell = getTemplateHtml(BODY_TEMPLATE_ID);

        if (!shell) {
            return '';
        }

        var wrapper = document.createElement('div');
        wrapper.innerHTML = shell;

        var summaryEl = wrapper.querySelector('#aa-onboarding-activation-guide-summary');
        var stepsEl = wrapper.querySelector('#aa-onboarding-activation-guide-steps');
        var googleEl = wrapper.querySelector('#aa-onboarding-activation-guide-google-recommended');

        if (!summaryEl || !stepsEl) {
            console.error('[OnboardingActivationGuide] Contenedores de guía no encontrados');
            return shell;
        }

        summaryEl.innerHTML = buildSummaryHtml(status);

        var steps = status.steps || {};
        var presentationNextStep = getPresentationNextStep(status);
        var stepsHtml = '';

        VISIBLE_STEP_KEYS.forEach(function (stepKey) {
            var step = steps[stepKey] || { label: stepKey, completed: false };
            var isNext = presentationNextStep === stepKey;
            stepsHtml += renderStepCard(stepKey, step, isNext, status);
        });

        stepsEl.innerHTML = stepsHtml;

        if (googleEl) {
            googleEl.innerHTML = buildGoogleCalendarRecommendationHtml(status);
        }

        return wrapper.innerHTML;
    }

    /**
     * @param {object} status
     */
    function bindCtaButtons(status) {
        var root = document.querySelector('[data-aa-onboarding-activation-guide="1"]');
        var nextStep = getPresentationNextStep(status);

        if (!root || !nextStep) {
            return;
        }

        var button = root.querySelector('.aa-onboarding-activation-cta[data-aa-onboarding-step-key="' + nextStep + '"]');
        if (!button || button.dataset.onboardingCtaBound === '1') {
            return;
        }

        var steps = status.steps || {};
        var step = steps[nextStep] || {};

        button.dataset.onboardingCtaBound = '1';
        button.addEventListener('click', function () {
            handleCtaClick(nextStep, step, status);
        });
    }

    /**
     * @param {object} status
     * @returns {{title: string, body: string, footer: string}}
     */
    function render(status) {
        var title = status.activation_complete ? 'Listo para explorar' : 'Crea tu cita de prueba';

        return {
            title: title,
            body: buildBodyHtml(status),
            footer: getTemplateHtml(FOOTER_TEMPLATE_ID)
        };
    }

    function bindCloseButton() {
        var button = document.getElementById('aa-onboarding-activation-guide-close');

        if (!button || button.dataset.onboardingGuideBound === '1') {
            return;
        }

        button.dataset.onboardingGuideBound = '1';
        button.addEventListener('click', function () {
            closeGuideModal();
        });
    }

    /**
     * @param {object} status
     */
    function openWithStatus(status) {
        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('[OnboardingActivationGuide] AAAdmin.openModal no disponible');
            return;
        }

        var view = render(status);
        if (!view.body) {
            return;
        }

        window.AAAdmin.openModal({
            title: view.title,
            body: view.body,
            footer: view.footer
        });

        bindCloseButton();
        bindCtaButtons(status);
        bindGoogleCalendarCta();
    }

    /**
     * @returns {Promise<object|undefined>}
     */
    function open() {
        if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
            console.error('[OnboardingActivationGuide] AAAdmin.openModal no disponible');
            return Promise.resolve();
        }

        if (!window.OnboardingStatusService || typeof window.OnboardingStatusService.fetchStatus !== 'function') {
            console.error('[OnboardingActivationGuide] OnboardingStatusService.fetchStatus no disponible');
            return Promise.resolve();
        }

        return window.OnboardingStatusService.fetchStatus()
            .then(function (status) {
                lastStatus = status;
                openWithStatus(status);
                return status;
            })
            .catch(function (err) {
                console.error('[OnboardingActivationGuide] No se pudo cargar el estado:', err);
            });
    }

    /**
     * @returns {Promise<object|undefined>}
     */
    function refresh() {
        return open();
    }

    if (typeof window !== 'undefined') {
        window.OnboardingActivationGuide = {
            open: open,
            render: render,
            refresh: refresh,
            openWithStatus: openWithStatus,
            navigateToModule: navigateToModule,
            getLastStatus: function () {
                return lastStatus;
            }
        };
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            VISIBLE_STEP_KEYS: VISIBLE_STEP_KEYS,
            ALL_STEP_KEYS: ALL_STEP_KEYS,
            getPresentationNextStep: getPresentationNextStep,
            getDisplayLabel: getDisplayLabel,
            isActionableStep: isActionableStep,
            isFirstAppointmentPending: isFirstAppointmentPending,
            buildSummaryHtml: buildSummaryHtml
        };
    }
})();
