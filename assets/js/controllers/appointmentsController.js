/**
 * Appointments Controller
 * 
 * Handles fetching, rendering, and pagination of appointments
 * in the Appointments Explorer modal.
 * 
 * @package AgendaAutomatizada
 * @since 1.0.0
 */
(function() {
    'use strict';

    // Guard flags for event listeners (persist across modal re-opens)
    let _actionListenerBound = false;
    let _actionRefreshListenerBound = false;

    /**
     * Estado badge colors mapping
     */
    const ESTADO_BADGES = {
        'pending': {
            label: 'Pendiente',
            classes: 'bg-yellow-100 text-yellow-800'
        },
        'confirmed': {
            label: 'Confirmada',
            classes: 'bg-green-100 text-green-800'
        },
        'cancelled': {
            label: 'Cancelada',
            classes: 'bg-red-100 text-red-800'
        },
        'asistió': {
            label: 'Asistió',
            classes: 'bg-green-100 text-green-800'
        },
        'no asistió': {
            label: 'No asistió',
            classes: 'bg-red-100 text-red-800'
        }
    };

    /**
     * AppointmentsController namespace
     */
    const AppointmentsController = {
        /**
         * Current state
         */
        state: {
            filters: {},
            page: 1,
            totalPages: 1,
            isLoading: false,
            source: null, // Origin of modal opening ('notifications' or null)
            originType: null, // Type filter when opened from notifications
            // Panel filters (in-memory only)
            panelFilters: {
                time: [],
                status: [],
                notification: []
            }
        },

        /**
         * Container selectors
         */
        selectors: {
            list: '.aa-appointments-list',
            filters: '.aa-appointments-filters',
            pagination: '.aa-appointments-pagination',
            loading: '.aa-appointments-loading',
            empty: '.aa-appointments-empty'
        },

        /**
         * Initialize controller with filters
         * @param {Object} filters - Initial filters { type, unread, source }
         */
        init: function(filters = {}) {
            console.log('[AppointmentsController] Inicializando con filtros:', filters);
            
            this.state.filters = filters;
            this.state.page = 1;
            
            // Reset panel filters
            this.state.panelFilters = {
                time: [],
                status: [],
                notification: []
            };
            
            // Store origin information for bulk marking on close
            this.state.source = filters.source || null;
            this.state.originType = filters.type || null;
            
            // Setup modal close observer if opened from notifications
            if (this.state.source === 'notifications') {
                this.setupModalCloseObserver();
            }
            
            // Setup filter toggle and checkboxes
            this.setupFiltersToggle();
            this.setupFilterCheckboxes();
            
            // Pre-select checkboxes based on initial filters
            this.applyInitialFiltersToPanel(filters);
            
            // Setup action buttons click delegation
            this.setupActionButtonsListener();
            
            // Listen for action completion to refresh list
            this.setupActionRefreshListener();
            
            // Load appointments
            this.loadAppointments();
        },

        /**
         * Fetch appointments from server
         */
        loadAppointments: function() {
            if (this.state.isLoading) return;
            
            this.state.isLoading = true;
            this.showLoading();
            
            const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
            const params = new URLSearchParams({
                action: 'aa_get_appointments',
                page: this.state.page
            });
            
            // Add legacy filters (for backwards compatibility)
            if (this.state.filters.type && this.state.panelFilters.status.length === 0) {
                params.append('type', this.state.filters.type);
            }
            if (this.state.filters.unread && this.state.panelFilters.notification.length === 0) {
                params.append('unread', 'true');
            }
            
            // Add panel filters
            const pf = this.state.panelFilters;
            
            // Time filter
            if (pf.time.length > 0) {
                pf.time.forEach(function(val) {
                    params.append('time[]', val);
                });
            }
            
            // Status filter
            if (pf.status.length > 0) {
                pf.status.forEach(function(val) {
                    params.append('status[]', val);
                });
            }
            
            // Notification filter
            if (pf.notification.length > 0) {
                pf.notification.forEach(function(val) {
                    params.append('notification[]', val);
                });
            }
            
            const url = ajaxurl + '?' + params.toString();
            
            console.log('[AppointmentsController] Fetching:', url);
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    this.state.isLoading = false;
                    
                    if (data.success && data.data) {
                        this.state.totalPages = data.data.pagination.total_pages;
                        this.state.page = data.data.pagination.page;
                        
                        this.renderAppointments(data.data.items);
                        this.renderPagination(data.data.pagination);
                        
                        console.log('[AppointmentsController] ✅ Cargadas', data.data.items.length, 'citas');
                    } else {
                        console.error('[AppointmentsController] Error en respuesta:', data);
                        this.showError('Error al cargar las citas');
                    }
                })
                .catch(error => {
                    this.state.isLoading = false;
                    console.error('[AppointmentsController] Error:', error);
                    this.showError('Error de conexión');
                });
        },

        /**
         * Render appointments as cards
         * @param {Array} items - Array of appointment objects
         */
        renderAppointments: function(items) {
            const container = document.querySelector(this.selectors.list);
            if (!container) {
                console.error('[AppointmentsController] Container not found');
                return;
            }
            
            // Clear container
            container.innerHTML = '';
            
            // Handle empty state
            if (!items || items.length === 0) {
                this.showEmpty();
                return;
            }
            
            // Render each card
            items.forEach(item => {
                const card = this.createCard(item);
                container.appendChild(card);
            });
        },

        /**
         * Create a single appointment card
         * @param {Object} item - Appointment data
         * @returns {HTMLElement} Card element
         */
        createCard: function(item) {
            const card = document.createElement('div');
            card.className = 'aa-appointment-card relative';
            card.setAttribute('data-aa-card', '');
            card.setAttribute('data-appointment-id', item.id);
            
            // Store unread state
            const isUnread = item.unread === true;
            card.setAttribute('data-unread', isUnread ? 'true' : 'false');
            
            // Get estado badge
            const badge = ESTADO_BADGES[item.estado] || ESTADO_BADGES['pending'];
            
            // Unread bell icon (only if unread)
            const bellIcon = isUnread ? `
                <div class="aa-unread-bell absolute z-10">
                    <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                    </svg>
                </div>
            ` : '';
            
            // Header (clickable toggle)
            const header = document.createElement('div');
            header.className = 'aa-appointment-header relative';
            header.setAttribute('data-aa-card-toggle', '');
            header.innerHTML = `
                ${bellIcon}
                <div class="flex items-center justify-between w-full pr-8">
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-900">${this.escapeHtml(item.cliente_nombre)}</span>
                        <span class="text-sm text-gray-500">${this.escapeHtml(item.servicio)}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600">${item.fecha} ${item.hora}</span>
                        <span class="px-2 py-1 text-xs font-medium rounded ${badge.classes}">${badge.label}</span>
                    </div>
                </div>
            `;
            
            // Overlay wrapper
            const overlay = document.createElement('div');
            overlay.className = 'aa-card-overlay';
            
            // Construir datetime para WhatsApp (usar fecha_raw si existe, o construir desde fecha/hora)
            // fecha_raw viene en formato MySQL YYYY-MM-DD HH:MM:SS si el backend lo provee
            const datetime = item.fecha_raw || (item.fecha && item.hora ? `${item.fecha} ${item.hora}:00` : '');
            
            // Renderizar teléfono con WhatsApp link si existe
            const telefonoHtml = item.telefono ? this.renderPhoneWithWhatsApp(item.telefono, item.estado, item.servicio, datetime, item.cliente_nombre) : 'N/A';
            
            // Body (expanded content)
            const body = document.createElement('div');
            body.className = 'aa-card-body';
            body.innerHTML = `
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Servicio:</span>
                        <span class="text-gray-900">${this.escapeHtml(item.servicio)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Fecha:</span>
                        <span class="text-gray-900">${item.fecha}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Hora:</span>
                        <span class="text-gray-900">${item.hora}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Duración:</span>
                        <span class="text-gray-900">${item.duracion} min</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Cliente:</span>
                        <span class="text-gray-900">${this.escapeHtml(item.cliente_nombre)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Teléfono:</span>
                        ${telefonoHtml}
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Estado:</span>
                        <span class="px-2 py-1 text-xs font-medium rounded ${badge.classes}">${badge.label}</span>
                    </div>
                </div>
                ${this.renderActionButtons(item)}
            `;
            
            overlay.appendChild(body);
            card.appendChild(header);
            card.appendChild(overlay);
            
            // Setup card open listener to mark as read
            this.setupCardOpenListener(card, item.id);
            
            return card;
        },

        /**
         * Render phone number with WhatsApp clickable link
         * @param {string} telefono - Phone number
         * @param {string} estado - Appointment status
         * @param {string} servicio - Service name
         * @param {string} datetime - Date/time in MySQL format
         * @param {string} nombre - Client name
         * @returns {string} HTML string with WhatsApp link
         */
        renderPhoneWithWhatsApp: function(telefono, estado, servicio, datetime, nombre) {
            // SVG ícono de WhatsApp
            const svgWhatsApp = `<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="flex-shrink: 0;">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>`;
            
            return `<span class="aa-whatsapp-link" 
                data-phone="${this.escapeHtml(telefono)}" 
                data-status="${this.escapeHtml(estado || 'pending')}" 
                data-service="${this.escapeHtml(servicio || '')}" 
                data-datetime="${this.escapeHtml(datetime || '')}" 
                data-name="${this.escapeHtml(nombre || '')}"
                style="display: inline-flex; align-items: center; gap: 5px; cursor: pointer; color: #25D366; font-weight: 500;"
                title="Enviar WhatsApp">
                ${svgWhatsApp}
                <span class="aa-wa-phone-text">${this.escapeHtml(telefono)}</span>
            </span>`;
        },

        /**
         * Render pagination controls
         * @param {Object} pagination - Pagination data
         */
        renderPagination: function(pagination) {
            let paginationContainer = document.querySelector(this.selectors.pagination);
            
            // Create pagination container if doesn't exist
            if (!paginationContainer) {
                const listContainer = document.querySelector(this.selectors.list);
                if (listContainer && listContainer.parentNode) {
                    paginationContainer = document.createElement('div');
                    paginationContainer.className = 'aa-appointments-pagination flex items-center justify-between mt-4 pt-4 border-t border-gray-200';
                    listContainer.parentNode.appendChild(paginationContainer);
                }
            }
            
            if (!paginationContainer) return;
            
            // Hide if only one page
            if (pagination.total_pages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }
            
            const self = this;
            
            paginationContainer.innerHTML = `
                <button 
                    type="button" 
                    class="aa-pagination-prev px-3 py-1 text-sm rounded border ${pagination.has_prev ? 'border-gray-300 text-gray-700 hover:bg-gray-50 cursor-pointer' : 'border-gray-200 text-gray-400 cursor-not-allowed'}"
                    ${!pagination.has_prev ? 'disabled' : ''}
                >
                    ← Anterior
                </button>
                <span class="text-sm text-gray-600">
                    Página ${pagination.page} de ${pagination.total_pages}
                </span>
                <button 
                    type="button" 
                    class="aa-pagination-next px-3 py-1 text-sm rounded border ${pagination.has_next ? 'border-gray-300 text-gray-700 hover:bg-gray-50 cursor-pointer' : 'border-gray-200 text-gray-400 cursor-not-allowed'}"
                    ${!pagination.has_next ? 'disabled' : ''}
                >
                    Siguiente →
                </button>
            `;
            
            // Attach event handlers
            const prevBtn = paginationContainer.querySelector('.aa-pagination-prev');
            const nextBtn = paginationContainer.querySelector('.aa-pagination-next');
            
            if (prevBtn && pagination.has_prev) {
                prevBtn.addEventListener('click', function() {
                    self.goToPage(self.state.page - 1);
                });
            }
            
            if (nextBtn && pagination.has_next) {
                nextBtn.addEventListener('click', function() {
                    self.goToPage(self.state.page + 1);
                });
            }
        },

        /**
         * Navigate to a specific page
         * @param {number} page - Page number
         */
        goToPage: function(page) {
            if (page < 1 || page > this.state.totalPages) return;
            
            this.state.page = page;
            this.loadAppointments();
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            const container = document.querySelector(this.selectors.list);
            if (!container) return;
            
            container.innerHTML = `
                <div class="aa-appointments-loading flex items-center justify-center py-8">
                    <div class="text-gray-500">Cargando citas...</div>
                </div>
            `;
        },

        /**
         * Show empty state
         */
        showEmpty: function() {
            const container = document.querySelector(this.selectors.list);
            if (!container) return;
            
            container.innerHTML = `
                <div class="aa-appointments-empty flex flex-col items-center justify-center py-8 text-center">
                    <div class="text-gray-400 text-4xl mb-2">📋</div>
                    <div class="text-gray-500">No hay citas que mostrar</div>
                </div>
            `;
        },

        /**
         * Show error state
         * @param {string} message - Error message
         */
        showError: function(message) {
            const container = document.querySelector(this.selectors.list);
            if (!container) return;
            
            container.innerHTML = `
                <div class="aa-appointments-error flex flex-col items-center justify-center py-8 text-center">
                    <div class="text-red-400 text-4xl mb-2">⚠️</div>
                    <div class="text-red-600">${this.escapeHtml(message)}</div>
                </div>
            `;
        },

        /**
         * Escape HTML to prevent XSS
         * @param {string} str - String to escape
         * @returns {string} Escaped string
         */
        escapeHtml: function(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        /**
         * Determine if an appointment has ended (is in the past).
         * Uses fecha_raw (MySQL datetime) and duracion to calculate end time.
         * Delegates to DateUtils.isAppointmentActive when available.
         * @param {Object} item - Appointment data from aa_get_appointments
         * @returns {boolean} true if the appointment has already ended
         */
        isAppointmentPast: function(item) {
            if (!item.fecha_raw) return false;
            // Map modal item to timeline-compatible shape for DateUtils
            const citaLike = { fecha: item.fecha_raw, duracion: item.duracion };
            if (window.DateUtils && typeof window.DateUtils.isAppointmentActive === 'function') {
                return !window.DateUtils.isAppointmentActive(citaLike);
            }
            // Fallback: manual calculation
            const end = new Date(item.fecha_raw.replace(' ', 'T'));
            if (isNaN(end.getTime())) return false;
            end.setMinutes(end.getMinutes() + (parseInt(item.duracion, 10) || 60));
            return new Date() >= end;
        },

        /**
         * Render action buttons for a card based on estado and time.
         * Same rules as the timeline cards:
         *   - Próxima + pending   => Confirmar / Cancelar
         *   - Próxima + confirmed => Cancelar
         *   - Pasada  + confirmed => Asistió / No asistió
         *   - cancelled / asistió / no asistió => no buttons
         * Uses inline styles (like timeline) for consistency.
         * @param {Object} item - Appointment data
         * @returns {string} HTML string (empty string if no actions apply)
         */
        renderActionButtons: function(item) {
            const estado = item.estado || 'pending';
            const esPasada = this.isAppointmentPast(item);
            const buttons = [];

            if (!esPasada) {
                // Cita próxima / activa
                if (estado === 'pending') {
                    buttons.push({ label: 'Confirmar', action: 'confirmar', variant: 'success' });
                    buttons.push({ label: 'Cancelar', action: 'cancelar', variant: 'danger' });
                } else if (estado === 'confirmed') {
                    buttons.push({ label: 'Cancelar', action: 'cancelar', variant: 'danger' });
                }
            } else {
                // Cita pasada
                if (estado === 'confirmed') {
                    buttons.push({ label: 'Asistió', action: 'asistio', variant: 'success' });
                    buttons.push({ label: 'No asistió', action: 'no-asistio', variant: 'danger' });
                }
            }

            if (buttons.length === 0) return '';

            // Inline styles (same approach as timeline cards)
            const variantStyles = {
                success: 'padding: 8px 12px; background-color: #22c55e; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; line-height: 1; transition: all 150ms ease;',
                danger: 'padding: 8px 12px; background-color: #ffffff; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; line-height: 1; transition: all 150ms ease;'
            };

            const buttonsHtml = buttons.map(function(btn) {
                const styles = variantStyles[btn.variant] || variantStyles.danger;
                return '<button type="button" data-action="' + btn.action + '" data-id="' + item.id + '" ' +
                    'style="' + styles + '">' + btn.label + '</button>';
            }).join('');

            return '<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #f3f4f6; display: flex; gap: 8px; flex-wrap: wrap;">' + buttonsHtml + '</div>';
        },

        /**
         * Setup delegated click handler for action buttons within the modal.
         * Listens on .aa-modal-body so it catches clicks on dynamically rendered buttons.
         * Delegates to AdminCalendarController.handleCitaAction.
         */
        setupActionButtonsListener: function() {
            if (_actionListenerBound) return;
            _actionListenerBound = true;

            const modalBody = document.querySelector('.aa-modal-body');
            if (!modalBody) return;

            modalBody.addEventListener('click', function(e) {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;

                const action = btn.getAttribute('data-action');
                const citaId = btn.getAttribute('data-id');
                if (!action || !citaId) return;

                // Prevent card toggle/close
                e.stopPropagation();

                if (window.AdminCalendarController && typeof window.AdminCalendarController.handleCitaAction === 'function') {
                    window.AdminCalendarController.handleCitaAction(action, citaId);
                } else {
                    console.warn('[AppointmentsController] AdminCalendarController.handleCitaAction no disponible');
                }
            });
        },

        /**
         * Listen for custom event dispatched after a cita action completes successfully.
         * Reloads the modal list so the card reflects the new state.
         */
        setupActionRefreshListener: function() {
            if (_actionRefreshListenerBound) return;
            _actionRefreshListenerBound = true;

            const self = this;
            document.addEventListener('aa-cita-action-completed', function() {
                // Only reload if the modal is currently visible
                const modalRoot = document.getElementById('aa-modal-root');
                if (modalRoot && !modalRoot.classList.contains('hidden')) {
                    console.log('[AppointmentsController] Acción completada, recargando lista del modal...');
                    self.loadAppointments();
                }
            });
        },

        /**
         * Setup listener for card open to mark notification as read
         * @param {HTMLElement} card - Card element
         * @param {number} appointmentId - Appointment ID
         */
        setupCardOpenListener: function(card, appointmentId) {
            const self = this;
            const toggle = card.querySelector('[data-aa-card-toggle]');
            
            if (!toggle) return;
            
            // Track if we've already marked this as read
            let isMarking = false;
            
            // Listen for card open (when is-open class is added)
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const isOpen = card.classList.contains('is-open');
                        const isUnread = card.getAttribute('data-unread') === 'true';
                        
                        // If card was just opened and has unread notification
                        if (isOpen && isUnread && !isMarking) {
                            isMarking = true;
                            self.markAppointmentNotificationAsRead(card, appointmentId);
                        }
                    }
                });
            });
            
            // Start observing
            observer.observe(card, {
                attributes: true,
                attributeFilter: ['class']
            });
        },

        /**
         * Mark notification as read for a specific appointment
         * @param {HTMLElement} card - Card element
         * @param {number} appointmentId - Appointment ID
         */
        markAppointmentNotificationAsRead: function(card, appointmentId) {
            const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
            const params = new URLSearchParams({
                action: 'aa_mark_appointment_notification_read',
                appointment_id: appointmentId
            });
            
            const url = ajaxurl + '?' + params.toString();
            
            console.log('[AppointmentsController] Marcando notificación como leída para appointment:', appointmentId);
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove bell icon
                        const bell = card.querySelector('.aa-unread-bell');
                        if (bell) {
                            bell.remove();
                        }
                        
                        // Update data attribute
                        card.setAttribute('data-unread', 'false');
                        
                        // Update notifications badge if function exists
                        if (typeof window.updateNotificationsBadge === 'function') {
                            window.updateNotificationsBadge();
                        }
                        
                        console.log('[AppointmentsController] ✅ Notificación marcada como leída');
                    } else {
                        console.error('[AppointmentsController] Error marcando notificación:', data);
                    }
                })
                .catch(error => {
                    console.error('[AppointmentsController] Error marcando notificación:', error);
                });
        },

        /**
         * Setup filter toggle button
         */
        setupFiltersToggle: function() {
            const toggleBtn = document.querySelector('.aa-btn-toggle-filters');
            const filtersPanel = document.querySelector(this.selectors.filters);
            
            if (!toggleBtn || !filtersPanel) {
                console.warn('[AppointmentsController] Filter toggle elements not found');
                return;
            }
            
            toggleBtn.addEventListener('click', function() {
                filtersPanel.classList.toggle('hidden');
            });
            
            console.log('[AppointmentsController] ✅ Toggle de filtros configurado');
        },

        /**
         * Setup filter checkbox event listeners
         */
        setupFilterCheckboxes: function() {
            const self = this;
            const filtersPanel = document.querySelector(this.selectors.filters);
            
            if (!filtersPanel) return;
            
            const checkboxes = filtersPanel.querySelectorAll('input[data-filter]');
            
            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    self.handleFilterChange(this);
                });
            });
            
            console.log('[AppointmentsController] ✅ Checkboxes de filtros configurados:', checkboxes.length);
        },

        /**
         * Handle filter checkbox change
         * @param {HTMLInputElement} checkbox - Changed checkbox
         */
        handleFilterChange: function(checkbox) {
            const filterAttr = checkbox.getAttribute('data-filter');
            if (!filterAttr) return;
            
            const [group, value] = filterAttr.split(':');
            const isChecked = checkbox.checked;
            
            // Ensure the group array exists
            if (!this.state.panelFilters[group]) {
                this.state.panelFilters[group] = [];
            }
            
            // Update the state array
            if (isChecked) {
                // Add value if not already present
                if (!this.state.panelFilters[group].includes(value)) {
                    this.state.panelFilters[group].push(value);
                }
            } else {
                // Remove value from array
                this.state.panelFilters[group] = this.state.panelFilters[group].filter(v => v !== value);
            }
            
            console.log('[AppointmentsController] Filtros actualizados:', this.state.panelFilters);
            
            // Reset to page 1 and reload
            this.state.page = 1;
            this.loadAppointments();
        },

        /**
         * Apply initial filters to panel checkboxes
         * @param {Object} filters - Initial filters from modal open
         */
        applyInitialFiltersToPanel: function(filters) {
            const filtersPanel = document.querySelector(this.selectors.filters);
            if (!filtersPanel) return;
            
            // Apply type filter to status group
            if (filters.type) {
                const statusCheckbox = filtersPanel.querySelector(`[data-filter="status:${filters.type}"]`);
                if (statusCheckbox) {
                    statusCheckbox.checked = true;
                    this.state.panelFilters.status = [filters.type];
                }
            }
            
            // Apply unread filter to notification group
            if (filters.unread) {
                const unreadCheckbox = filtersPanel.querySelector('[data-filter="notification:unread"]');
                if (unreadCheckbox) {
                    unreadCheckbox.checked = true;
                    this.state.panelFilters.notification = ['unread'];
                }
            }
        },

        /**
         * Setup observer to detect when modal closes
         * Marks all notifications of the type as read when modal closes (only if opened from notifications)
         */
        setupModalCloseObserver: function() {
            const self = this;
            const modalRoot = document.getElementById('aa-modal-root');
            
            if (!modalRoot) {
                console.warn('[AppointmentsController] Modal root not found, cannot setup close observer');
                return;
            }
            
            // Track previous state to detect transition from open to closed
            let wasOpen = !modalRoot.classList.contains('hidden');
            
            // Create observer to watch for modal close (when 'hidden' class is added)
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const isHidden = modalRoot.classList.contains('hidden');
                        
                        // Detect transition from open to closed
                        if (wasOpen && isHidden) {
                            // Modal was just closed
                            // If opened from notifications, mark all notifications of that type as read
                            if (
                                self.state.source === 'notifications' &&
                                self.state.originType &&
                                typeof window.aaMarkNotificationsAsRead === 'function'
                            ) {
                                console.log('[AppointmentsController] Modal cerrado desde notificaciones, marcando todas como leídas:', self.state.originType);
                                window.aaMarkNotificationsAsRead(self.state.originType);
                            }
                            
                            // Clean up observer
                            observer.disconnect();
                        }
                        
                        // Update previous state
                        wasOpen = !isHidden;
                    }
                });
            });
            
            // Start observing
            observer.observe(modalRoot, {
                attributes: true,
                attributeFilter: ['class']
            });
            
            console.log('[AppointmentsController] ✅ Observer de cierre de modal configurado');
        }
    };

    // Expose to global scope
    window.AAAppointmentsController = AppointmentsController;

})();


