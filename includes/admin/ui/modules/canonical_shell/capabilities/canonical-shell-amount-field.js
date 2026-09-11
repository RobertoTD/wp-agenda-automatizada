/**
 * Módulo amount del formulario de registro del shell (A1b).
 * Markup/precarga/recogida particulares; el form genérico solo coordina por clave.
 */
(function (global) {
    'use strict';

    var STATUS_KNOWN_VALUE = 'known_value';
    var STATUS_KNOWN_ABSENT = 'known_absent';
    var STATUS_READ_FAILED = 'read_failed';

    var wrap = null;
    var input = null;
    var errorEl = null;
    var unavailableEl = null;
    var active = false;
    var sendMode = 'omit'; // omit | set | clear

    function ensureNodes() {
        if (!wrap) {
            wrap = document.getElementById('aa-shell-record-amount-field');
        }
        if (!input) {
            input = document.getElementById('aa-shell-record-amount');
        }
        if (!errorEl) {
            errorEl = document.getElementById('aa-shell-record-amount-error');
        }
        if (!unavailableEl) {
            unavailableEl = document.getElementById('aa-shell-record-amount-unavailable');
        }
    }

    function setError(message) {
        ensureNodes();
        if (!errorEl) {
            return;
        }
        if (!message) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
            return;
        }
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }

    function setUnavailable(show) {
        ensureNodes();
        if (!unavailableEl) {
            return;
        }
        if (show) {
            unavailableEl.classList.remove('hidden');
        } else {
            unavailableEl.classList.add('hidden');
        }
    }

    function hideField() {
        ensureNodes();
        if (!wrap) {
            return;
        }
        wrap.classList.add('hidden');
        wrap.setAttribute('hidden', 'hidden');
    }

    function showField() {
        ensureNodes();
        if (!wrap) {
            return;
        }
        wrap.classList.remove('hidden');
        wrap.removeAttribute('hidden');
    }

    function clearError() {
        setError('');
    }

    function clear() {
        ensureNodes();
        active = false;
        sendMode = 'omit';
        if (input) {
            input.value = '';
            input.disabled = true;
        }
        setError('');
        setUnavailable(false);
        hideField();
    }

    /**
     * @param {null|{status:string,value?:string}} state
     *   null = no ofrecida en este open (lista sin amount / create sin oferta)
     */
    function apply(state) {
        clear();
        ensureNodes();
        if (!wrap || !input) {
            return;
        }
        if (!state || typeof state !== 'object' || typeof state.status !== 'string') {
            return;
        }

        if (state.status === STATUS_READ_FAILED) {
            active = true;
            sendMode = 'omit';
            input.value = '';
            input.disabled = true;
            setUnavailable(true);
            showField();
            return;
        }

        if (state.status === STATUS_KNOWN_VALUE) {
            active = true;
            sendMode = 'set';
            input.disabled = false;
            input.value = typeof state.value === 'string' ? state.value : '';
            showField();
            return;
        }

        if (state.status === STATUS_KNOWN_ABSENT) {
            active = true;
            sendMode = 'set';
            input.disabled = false;
            input.value = '';
            showField();
        }
    }

    function collect(formData) {
        if (!active || sendMode === 'omit' || !formData || typeof formData.append !== 'function') {
            return;
        }
        ensureNodes();
        if (!input || input.disabled) {
            return;
        }
        // Vacío conocido → clear (contrato A1a). Valor (incl. 0) → set.
        formData.append('amount', input.value);
    }

    function handleError(code, message) {
        var amountCodes = {
            invalid_amount: true,
            amount_too_many_decimals: true,
            amount_out_of_range: true,
            amount_not_numeric: true
        };
        if (!amountCodes[code]) {
            return false;
        }
        ensureNodes();
        if (!active || !input || input.disabled) {
            return false;
        }
        setError(message || 'Revisa el importe.');
        input.focus();
        return true;
    }

    global.AA_CANONICAL_SHELL_CAPABILITY_MODULES = global.AA_CANONICAL_SHELL_CAPABILITY_MODULES || {};
    global.AA_CANONICAL_SHELL_CAPABILITY_MODULES.amount = {
        key: 'amount',
        clear: clear,
        clearError: clearError,
        apply: apply,
        collect: collect,
        handleError: handleError
    };
}(typeof window !== 'undefined' ? window : this));
