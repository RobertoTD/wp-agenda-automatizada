/**
 * Settings Module - Module-specific JavaScript
 * 
 * Handles all UI logic for the Settings module:
 * - Motivos (appointment types) management
 * - Virtual address toggle
 * - Schedule intervals (day toggles, interval creation/deletion)
 * - Time input conversion (mobile vs desktop)
 * - Minute normalization (00/30 only)
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        console.log('Settings module loaded');

        // ================================
        // 🔹 Motivos Management
        // ================================
        initMotivos();

        // ================================
        // 🔹 Virtual Address Toggle
        // ================================
        initVirtualAddressToggle();

        // ================================
        // 🔹 Schedule Intervals Management
        // ================================
        initDayToggles();
        initIntervals();
        convertInputsToDesktopSelectors();
        observeResizeChanges();
    });

    // ================================
    // 🔹 UTILITY FUNCTIONS
    // ================================

    /**
     * Detect if current viewport is mobile
     */
    function isMobile() {
        return window.innerWidth < 640; // sm breakpoint de Tailwind
    }

    /**
     * Parse HH:mm string to {hour, minute} object
     */
    function parseTime(timeStr) {
        if (!timeStr) return { hour: 0, minute: 0 };
        const [hour, minute] = timeStr.split(':').map(Number);
        return { hour: hour || 0, minute: minute || 0 };
    }

    /**
     * Format {hour, minute} to HH:mm string
     */
    function formatTime(hour, minute) {
        return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
    }

    /**
     * Normalize minute to valid value (00 or 30)
     */
    function normalizeMinutes(minute) {
        if (minute < 15) return 0;
        if (minute >= 15 && minute < 45) return 30;
        return 0;
    }

    // ================================
    // 🔹 MOTIVOS MANAGEMENT
    // ================================

    function initMotivos() {
        const hiddenInput = document.getElementById("aa-google-motivo-hidden");
        const motivosList = document.getElementById("aa-motivos-list");
        const addButton = document.getElementById("aa-add-motivo");
        const motivoInput = document.getElementById("aa-motivo-input");

        if (!hiddenInput || !motivosList || !addButton || !motivoInput) return;

        // Read saved motivos or use empty array
        let motivos = [];
        try {
            motivos = JSON.parse(hiddenInput.value || "[]");
            if (!Array.isArray(motivos)) motivos = [];
        } catch (e) {
            motivos = [];
        }

        function renderMotivos() {
            motivosList.innerHTML = "";
            motivos.forEach((motivo, index) => {
                const li = document.createElement("li");
                li.style.marginBottom = "5px";
                li.style.display = "flex";
                li.style.alignItems = "center";
                li.style.gap = "10px";

                li.innerHTML = `
                    <span>${motivo}</span>
                    <button type="button" class="remove-motivo px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600" data-index="${index}">Eliminar</button>
                `;
                motivosList.appendChild(li);
            });
            hiddenInput.value = JSON.stringify(motivos);
        }

        addButton.addEventListener("click", function () {
            const nuevo = motivoInput.value.trim();
            if (!nuevo) return;
            motivos.push(nuevo);
            motivoInput.value = "";
            renderMotivos();
        });

        motivosList.addEventListener("click", function (e) {
            if (e.target.classList.contains("remove-motivo")) {
                const index = parseInt(e.target.dataset.index, 10);
                motivos.splice(index, 1);
                renderMotivos();
            }
        });

        renderMotivos();
    }

    // ================================
    // 🔹 VIRTUAL ADDRESS TOGGLE
    // ================================

    function initVirtualAddressToggle() {
        const virtualCheckbox = document.getElementById('aa-is-virtual-checkbox');
        const addressField = document.getElementById('aa-business-address');
        const addressRow = document.getElementById('aa-address-row');

        if (!virtualCheckbox || !addressField || !addressRow) return;

        function toggleAddressField() {
            if (virtualCheckbox.checked) {
                addressField.disabled = true;
                addressField.style.opacity = '0.5';
                addressRow.style.opacity = '0.6';
            } else {
                addressField.disabled = false;
                addressField.style.opacity = '1';
                addressRow.style.opacity = '1';
            }
        }

        toggleAddressField();
        virtualCheckbox.addEventListener('change', toggleAddressField);
    }

    // ================================
    // 🔹 TIME INPUT CREATION
    // ================================

    /**
     * Create time selector (mobile: native input, desktop: select dropdowns)
     */
    function createTimeSelector(name, value) {
        const { hour, minute } = parseTime(value);
        const wrapper = document.createElement('div');
        wrapper.className = 'aa-time-input-wrapper flex items-center gap-1';
        
        if (isMobile()) {
            return createMobileTimeInput(wrapper, name, hour, minute);
        } else {
            return createDesktopTimeSelectors(wrapper, name, hour, minute);
        }
    }

    /**
     * Create mobile native time input
     */
    function createMobileTimeInput(wrapper, name, hour, minute) {
        const input = document.createElement('input');
        input.type = 'time';
        input.name = name;
        input.step = '1800'; // 30 minutes in seconds
        const normalizedM = normalizeMinutes(minute);
        input.value = formatTime(hour, normalizedM) || '00:00';
        input.className = 'aa-timepicker-mobile w-20 sm:w-20 px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow';
        wrapper.appendChild(input);
        return wrapper;
    }

    /**
     * Create desktop time selectors (hour + minute dropdowns)
     */
    function createDesktopTimeSelectors(wrapper, name, hour, minute) {
        const validMinutes = [0, 30];
        const normalizedMinute = normalizeMinutes(minute);

        // Hour selector
        const hourSelect = document.createElement('select');
        hourSelect.className = 'aa-time-hour w-16 px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow bg-white';
        
        for (let h = 0; h < 24; h++) {
            const option = document.createElement('option');
            option.value = h;
            option.textContent = String(h).padStart(2, '0');
            if (h === hour) option.selected = true;
            hourSelect.appendChild(option);
        }

        // Minute selector (only 00 and 30)
        const minuteSelect = document.createElement('select');
        minuteSelect.className = 'aa-time-minute w-16 px-2 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-shadow bg-white';
        
        validMinutes.forEach(m => {
            const option = document.createElement('option');
            option.value = m;
            option.textContent = String(m).padStart(2, '0');
            if (m === normalizedMinute) option.selected = true;
            minuteSelect.appendChild(option);
        });

        // Hidden input for form submission (HH:mm format)
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = name;
        hiddenInput.value = formatTime(hour, normalizedMinute);
        hiddenInput.className = 'aa-time-value';

        // Update hidden input when selectors change
        function updateHidden() {
            const h = parseInt(hourSelect.value);
            const m = parseInt(minuteSelect.value);
            const validM = validMinutes.includes(m) ? m : 0;
            hiddenInput.value = formatTime(h, validM);
        }
        hourSelect.addEventListener('change', updateHidden);
        minuteSelect.addEventListener('change', updateHidden);

        // Separator
        const colon = document.createElement('span');
        colon.className = 'text-gray-400';
        colon.textContent = ':';

        // Make wrapper clickable
        bindWrapperClicks(wrapper, hourSelect, colon);

        wrapper.appendChild(hourSelect);
        wrapper.appendChild(colon);
        wrapper.appendChild(minuteSelect);
        wrapper.appendChild(hiddenInput);
        
        return wrapper;
    }

    /**
     * Bind click and keyboard events to time wrapper
     */
    function bindWrapperClicks(wrapper, hourSelect, colon) {
        wrapper.style.cursor = 'pointer';
        wrapper.setAttribute('role', 'button');
        wrapper.setAttribute('tabindex', '0');
        
        wrapper.addEventListener('click', function(e) {
            if (e.target === wrapper || e.target === colon || (!e.target.closest('select'))) {
                e.preventDefault();
                e.stopPropagation();
                hourSelect.focus();
                hourSelect.click();
            }
        });
        
        wrapper.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                hourSelect.focus();
                hourSelect.click();
            }
        });
    }

    // ================================
    // 🔹 DAY TOGGLES
    // ================================

    /**
     * Initialize day toggle checkboxes to show/hide intervals
     */
    function initDayToggles() {
        document.querySelectorAll(".aa-day-block input[type=checkbox]").forEach(checkbox => {
            checkbox.addEventListener("change", function() {
                const day = this.name.match(/\[(.*?)\]/)[1];
                const container = document.querySelector(`.day-intervals[data-day="${day}"]`);
                if (!container) return;
                
                container.style.display = this.checked ? "block" : "none";
            });
        });
    }

    // ================================
    // 🔹 INTERVALS MANAGEMENT
    // ================================

    /**
     * Initialize interval containers and bind add/remove handlers
     */
    function initIntervals() {
        document.querySelectorAll(".day-intervals").forEach(container => {
            bindAddInterval(container);
            bindRemoveInterval(container);
        });
    }

    /**
     * Bind add interval button handler
     */
    function bindAddInterval(container) {
        container.addEventListener("click", function(e) {
            if (!e.target.classList.contains("add-interval")) return;
            
            e.preventDefault();
            const day = this.dataset.day;
            const index = this.querySelectorAll(".interval").length;

            const intervalDiv = document.createElement("div");
            intervalDiv.classList.add("interval");
            intervalDiv.classList.add("flex", "flex-wrap", "items-center", "gap-2", "sm:gap-3");
            intervalDiv.setAttribute('data-day', day);
            intervalDiv.setAttribute('data-index', index);

            const startWrapper = createTimeSelector(`aa_schedule[${day}][intervals][${index}][start]`, '00:00');
            const endWrapper = createTimeSelector(`aa_schedule[${day}][intervals][${index}][end]`, '23:30');

            const separator = document.createTextNode('—');
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-interval ml-auto p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors';
            removeBtn.title = 'Eliminar intervalo';
            removeBtn.innerHTML = `
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            `;

            intervalDiv.appendChild(startWrapper);
            intervalDiv.appendChild(separator);
            intervalDiv.appendChild(endWrapper);
            intervalDiv.appendChild(removeBtn);

            this.insertBefore(intervalDiv, this.querySelector(".add-interval"));
        });
    }

    /**
     * Bind remove interval button handler
     */
    function bindRemoveInterval(container) {
        container.addEventListener("click", function(e) {
            if (!e.target.classList.contains("remove-interval") && !e.target.closest(".remove-interval")) return;
            
            e.preventDefault();
            const interval = e.target.closest(".interval");
            if (interval) interval.remove();
        });
    }

    // ================================
    // 🔹 INPUT CONVERSION
    // ================================

    /**
     * Convert existing mobile time inputs to desktop selectors if needed
     */
    function convertInputsToDesktopSelectors() {
        document.querySelectorAll('.aa-timepicker-mobile').forEach(input => {
            if (input.dataset.converted) return;
            
            if (!isMobile()) {
                const wrapper = input.closest('.aa-time-input-wrapper');
                if (!wrapper) return;
                
                const name = input.name;
                const value = input.value || '00:00';
                const newWrapper = createTimeSelector(name, value);
                
                if (wrapper.parentNode) {
                    wrapper.parentNode.replaceChild(newWrapper, wrapper);
                }
            } else {
                input.dataset.converted = 'true';
            }
        });
    }

    // ================================
    // 🔹 RESIZE OBSERVATION
    // ================================

    /**
     * Observe window resize to reconvert inputs when switching mobile/desktop
     */
    function observeResizeChanges() {
        let resizeTimeout;
        let wasMobile = isMobile();
        
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                const nowMobile = isMobile();
                if (wasMobile !== nowMobile) {
                    // Device type changed, reload to re-render
                    location.reload();
                }
                wasMobile = nowMobile;
            }, 300);
        });
    }

})();
