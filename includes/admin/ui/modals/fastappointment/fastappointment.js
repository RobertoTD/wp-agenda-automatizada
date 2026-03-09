/**
 * Fast Appointment Modal
 *
 * Abre el modal compartido e inicializa el controlador dedicado
 * del flujo Fast Appointment.
 *
 * GUARDRAIL: This modal uses the Fast Appointment availability motor
 * (fastAppointmentTimeAvailabilityService.js).  It does NOT share
 * availability logic with the Reservation modal, which uses
 * assignment-based availability (availabilityAssignments.js).
 * See docs/fast-appointment-vs-assignment-availability.md
 */
(function() {
    'use strict';

    const FastAppointmentModal = {
        config: {
            buttonId: 'aa-btn-open-fastappointment-modal',
            title: 'Cita rapida',
            templateId: 'aa-fastappointment-modal-template',
            footerTemplateId: 'aa-fastappointment-modal-footer-template'
        },
        controller: null,
        initTimeoutId: null,
        modalObserver: null,

        getTemplateHtml: function(templateId) {
            const template = document.getElementById(templateId);

            if (!template || !template.content) {
                console.error('[FastAppointmentModal] Template no encontrado:', templateId);
                return '';
            }

            const clone = template.content.cloneNode(true);
            const container = document.createElement('div');
            container.appendChild(clone);
            return container.innerHTML;
        },

        destroyController: function() {
            if (this.initTimeoutId) {
                clearTimeout(this.initTimeoutId);
                this.initTimeoutId = null;
            }

            if (this.modalObserver) {
                this.modalObserver.disconnect();
                this.modalObserver = null;
            }

            if (this.controller && typeof this.controller.destroy === 'function') {
                this.controller.destroy();
            }

            this.controller = null;
        },

        observeModalLifecycle: function() {
            const modalRoot = document.getElementById('aa-modal-root');

            if (!modalRoot) {
                return;
            }

            if (this.modalObserver) {
                this.modalObserver.disconnect();
            }

            this.modalObserver = new MutationObserver(() => {
                const form = document.getElementById('aa-fastappointment-form');
                const modalClosed = modalRoot.classList.contains('hidden');

                if (!form || modalClosed) {
                    this.destroyController();
                }
            });

            this.modalObserver.observe(modalRoot, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class']
            });
        },

        initController: function() {
            this.destroyController();

            this.initTimeoutId = setTimeout(() => {
                this.initTimeoutId = null;

                if (!window.AdminFastappointmentController || typeof window.AdminFastappointmentController.init !== 'function') {
                    console.warn('[FastAppointmentModal] AdminFastappointmentController no disponible');
                    return;
                }

                const form = document.getElementById('aa-fastappointment-form');
                if (!form) {
                    console.warn('[FastAppointmentModal] Formulario no disponible para inicializar');
                    return;
                }

                this.controller = window.AdminFastappointmentController.init();
                this.observeModalLifecycle();
            }, 0);
        },

        open: function() {
            if (!window.AAAdmin || typeof window.AAAdmin.openModal !== 'function') {
                console.error('[FastAppointmentModal] Sistema modal no disponible');
                return;
            }

            const body = this.getTemplateHtml(this.config.templateId);
            const footer = this.getTemplateHtml(this.config.footerTemplateId);

            if (!body) {
                return;
            }

            window.AAAdmin.openModal({
                title: this.config.title,
                body: body,
                footer: footer
            });

            this.initController();
        },

        bind: function() {
            const button = document.getElementById(this.config.buttonId);

            if (!button || button.dataset.fastappointmentBound === '1') {
                return;
            }

            button.dataset.fastappointmentBound = '1';
            button.addEventListener('click', () => this.open());
        },

        init: function() {
            this.bind();
            console.log('[FastAppointmentModal] Inicializado');
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        FastAppointmentModal.init();
    });

    window.FastAppointmentModal = FastAppointmentModal;
})();
