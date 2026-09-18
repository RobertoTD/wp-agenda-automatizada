/**
 * Módulo email del formulario de registro del shell.
 * Input type=email; candidata string hacia el servidor (normalización en PHP).
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
            wrap = document.getElementById('aa-shell-record-email-field');
        }
        if (!input) {
            input = document.getElementById('aa-shell-record-email');
        }
        if (!errorEl) {
            errorEl = document.getElementById('aa-shell-record-email-error');
        }
        if (!unavailableEl) {
            unavailableEl = document.getElementById('aa-shell-record-email-unavailable');
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
            var value = typeof state.value === 'string' ? state.value : '';
            if (!value || value.indexOf('@') === -1) {
                active = true;
                sendMode = 'omit';
                input.value = '';
                input.disabled = true;
                setUnavailable(true);
                showField();
                return;
            }
            active = true;
            sendMode = 'set';
            input.disabled = false;
            input.value = value;
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
        formData.append('email', String(input.value || '').trim());
    }

    function handleError(code, message) {
        var emailCodes = {
            invalid_email: true,
            invalid_payload: true
        };
        if (!emailCodes[code]) {
            return false;
        }
        ensureNodes();
        if (!active || !input || input.disabled) {
            return false;
        }
        setError(message || 'Revisa el correo electrónico.');
        input.focus();
        return true;
    }

    global.AA_CANONICAL_SHELL_CAPABILITY_MODULES = global.AA_CANONICAL_SHELL_CAPABILITY_MODULES || {};
    global.AA_CANONICAL_SHELL_CAPABILITY_MODULES.email = {
        key: 'email',
        clear: clear,
        clearError: clearError,
        apply: apply,
        collect: collect,
        handleError: handleError
    };
}(typeof window !== 'undefined' ? window : this));
