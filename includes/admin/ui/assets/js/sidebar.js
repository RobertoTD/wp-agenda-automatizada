/**
 * Sidebar/Drawer - Behavior script
 * 
 * Handles:
 * - Open/close drawer via trigger button
 * - Close on overlay click
 * - Close on ESC key
 * - Aria-expanded state management
 */

(function() {
    'use strict';

    // Ensure AAAdmin namespace exists
    window.AAAdmin = window.AAAdmin || {};

    /**
     * Sidebar controller
     */
    AAAdmin.Sidebar = {
        // DOM references (cached on init)
        els: {
            sidebar: null,
            overlay: null,
            trigger: null,
            closeBtn: null
        },

        // State
        isOpen: false,

        /**
         * Initialize sidebar functionality
         */
        init: function() {
            // Cache DOM elements
            this.els.sidebar = document.getElementById('aa-sidebar');
            this.els.overlay = document.getElementById('aa-sidebar-overlay');
            this.els.trigger = document.getElementById('aa-btn-sidebar');
            this.els.closeBtn = document.getElementById('aa-sidebar-close');

            // Abort if essential elements are missing
            if (!this.els.sidebar || !this.els.overlay) {
                console.warn('[AAAdmin.Sidebar] Required elements not found');
                return;
            }

            // Bind events
            this.bindEvents();
        },

        /**
         * Bind all event listeners
         */
        bindEvents: function() {
            const self = this;

            // Trigger button click
            if (this.els.trigger) {
                this.els.trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.open();
                });
            }

            // Close button click
            if (this.els.closeBtn) {
                this.els.closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.close();
                });
            }

            // Overlay click
            this.els.overlay.addEventListener('click', function() {
                self.close();
            });

            // ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            });
        },

        /**
         * Open the sidebar
         */
        open: function() {
            if (this.isOpen) return;

            // Cerrar card de cita expandida en el calendario si hay una abierta
            if (window.CalendarAppointments && typeof window.CalendarAppointments.colapsarCardExpandida === 'function') {
                window.CalendarAppointments.colapsarCardExpandida();
            }

            const sidebar = this.els.sidebar;
            const overlay = this.els.overlay;
            const trigger = this.els.trigger;

            // Show overlay
            overlay.classList.remove('hidden');
            // Force reflow for transition
            void overlay.offsetWidth;
            overlay.classList.remove('opacity-0');
            overlay.classList.add('opacity-100');

            // Slide in sidebar
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');

            // Update trigger aria state
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'true');
            }

            // Prevent body scroll
            document.body.classList.add('overflow-hidden');

            this.isOpen = true;

            // Focus first link in sidebar for accessibility
            const firstLink = sidebar.querySelector('nav a');
            if (firstLink) {
                firstLink.focus();
            }
        },

        /**
         * Close the sidebar
         */
        close: function() {
            if (!this.isOpen) return;

            const sidebar = this.els.sidebar;
            const overlay = this.els.overlay;
            const trigger = this.els.trigger;

            // Fade out overlay
            overlay.classList.remove('opacity-100');
            overlay.classList.add('opacity-0');

            // Slide out sidebar
            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add('-translate-x-full');

            // Update trigger aria state
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }

            // Restore body scroll
            document.body.classList.remove('overflow-hidden');

            this.isOpen = false;

            // Hide overlay after transition
            setTimeout(function() {
                if (!AAAdmin.Sidebar.isOpen) {
                    overlay.classList.add('hidden');
                }
            }, 300); // Match transition duration

            // Return focus to trigger
            if (trigger) {
                trigger.focus();
            }
        },

        /**
         * Toggle sidebar state
         */
        toggle: function() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }
    };

    // Auto-init on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        AAAdmin.Sidebar.init();
    });

})();
