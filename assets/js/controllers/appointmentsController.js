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
            isLoading: false
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
         * @param {Object} filters - Initial filters { type, unread }
         */
        init: function(filters = {}) {
            console.log('[AppointmentsController] Inicializando con filtros:', filters);
            
            this.state.filters = filters;
            this.state.page = 1;
            
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
            
            // Add filters
            if (this.state.filters.type) {
                params.append('type', this.state.filters.type);
            }
            if (this.state.filters.unread) {
                params.append('unread', 'true');
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
            card.className = 'aa-appointment-card';
            card.setAttribute('data-aa-card', '');
            card.setAttribute('data-appointment-id', item.id);
            
            // Get estado badge
            const badge = ESTADO_BADGES[item.estado] || ESTADO_BADGES['pending'];
            
            // Header (clickable toggle)
            const header = document.createElement('div');
            header.className = 'aa-appointment-header';
            header.setAttribute('data-aa-card-toggle', '');
            header.innerHTML = `
                <div class="flex items-center justify-between w-full">
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
                        <span class="text-gray-900">${this.escapeHtml(item.telefono || 'N/A')}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Estado:</span>
                        <span class="px-2 py-1 text-xs font-medium rounded ${badge.classes}">${badge.label}</span>
                    </div>
                </div>
            `;
            
            overlay.appendChild(body);
            card.appendChild(header);
            card.appendChild(overlay);
            
            return card;
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
        }
    };

    // Expose to global scope
    window.AAAppointmentsController = AppointmentsController;

})();


