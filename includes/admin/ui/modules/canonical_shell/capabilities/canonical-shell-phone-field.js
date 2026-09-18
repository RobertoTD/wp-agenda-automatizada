/**
 * Módulo phone del formulario de registro del shell.
 * Select de país + input tel; candidata E.164 con + hacia el servidor.
 */
(function (global) {
    'use strict';

    var STATUS_KNOWN_VALUE = 'known_value';
    var STATUS_KNOWN_ABSENT = 'known_absent';
    var STATUS_READ_FAILED = 'read_failed';

    var COUNTRY_CODES = ['593', '502', '52', '54', '57', '56', '51', '58', '34', '1'];

    var COUNTRY_CONFIG = {
        '52': { label: 'México (+52)', placeholder: '5512345678', selectLabel: '(+52)' },
        '1': { label: 'Estados Unidos (+1)', placeholder: '2025550123', selectLabel: '(+1)' },
        '34': { label: 'España (+34)', placeholder: '612345678', selectLabel: '(+34)' },
        '57': { label: 'Colombia (+57)', placeholder: '3001234567', selectLabel: '(+57)' },
        '54': {
            label: 'Argentina (+54)',
            placeholder: '91112345678',
            selectLabel: '(+54)',
            help: 'Incluye el código de área, sin 0 inicial ni 15 de marcación local. Para celulares, agrega 9 antes del código de área.'
        },
        '56': { label: 'Chile (+56)', placeholder: '912345678', selectLabel: '(+56)' },
        '51': { label: 'Perú (+51)', placeholder: '912345678', selectLabel: '(+51)' },
        '593': { label: 'Ecuador (+593)', placeholder: '991234567', selectLabel: '(+593)' },
        '58': { label: 'Venezuela (+58)', placeholder: '4121234567', selectLabel: '(+58)' },
        '502': { label: 'Guatemala (+502)', placeholder: '51234567', selectLabel: '(+502)' }
    };

    var wrap = null;
    var countrySelect = null;
    var input = null;
    var helpEl = null;
    var errorEl = null;
    var unavailableEl = null;
    var active = false;
    var sendMode = 'omit'; // omit | set | clear
    var pasteHandlerBound = false;

    function ensureNodes() {
        if (!wrap) {
            wrap = document.getElementById('aa-shell-record-phone-field');
        }
        if (!countrySelect) {
            countrySelect = document.getElementById('aa-shell-record-phone-country');
        }
        if (!input) {
            input = document.getElementById('aa-shell-record-phone');
        }
        if (!helpEl) {
            helpEl = document.getElementById('aa-shell-record-phone-help');
        }
        if (!errorEl) {
            errorEl = document.getElementById('aa-shell-record-phone-error');
        }
        if (!unavailableEl) {
            unavailableEl = document.getElementById('aa-shell-record-phone-unavailable');
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

    function updateHelpAndPlaceholder() {
        ensureNodes();
        if (!countrySelect) {
            return;
        }
        var code = countrySelect.value || '52';
        var cfg = COUNTRY_CONFIG[code] || COUNTRY_CONFIG['52'];
        if (input) {
            input.placeholder = cfg.placeholder;
        }
        if (helpEl) {
            if (cfg.help) {
                helpEl.textContent = cfg.help;
                helpEl.classList.remove('hidden');
            } else {
                helpEl.textContent = '';
                helpEl.classList.add('hidden');
            }
        }
    }

    function matchCountryCode(digits) {
        var i;
        for (i = 0; i < COUNTRY_CODES.length; i++) {
            var code = COUNTRY_CODES[i];
            if (digits.length > code.length && digits.indexOf(code) === 0) {
                return { country: code, national: digits.slice(code.length) };
            }
        }
        return null;
    }

    function parseStored(e164) {
        if (!e164 || typeof e164 !== 'string' || e164.charAt(0) !== '+') {
            return null;
        }
        var digits = e164.slice(1).replace(/[^0-9]/g, '');
        if (!digits) {
            return null;
        }
        return matchCountryCode(digits);
    }

    function digitsOnly(raw) {
        return String(raw || '').replace(/[^0-9]/g, '');
    }

    function bindPasteOnce() {
        ensureNodes();
        if (!input || pasteHandlerBound) {
            return;
        }
        pasteHandlerBound = true;
        input.addEventListener('paste', function (event) {
            var text = '';
            if (event.clipboardData && event.clipboardData.getData) {
                text = event.clipboardData.getData('text') || '';
            }
            text = String(text).trim();
            if (!text || text.charAt(0) !== '+') {
                return;
            }
            var parsed = parseStored(text);
            if (!parsed || !COUNTRY_CONFIG[parsed.country]) {
                return;
            }
            event.preventDefault();
            if (countrySelect) {
                countrySelect.value = parsed.country;
            }
            input.value = parsed.national;
            updateHelpAndPlaceholder();
            setError('');
        });
        if (countrySelect) {
            countrySelect.addEventListener('change', function () {
                updateHelpAndPlaceholder();
                setError('');
            });
        }
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
        if (countrySelect) {
            countrySelect.value = '52';
            countrySelect.disabled = true;
        }
        setError('');
        setUnavailable(false);
        updateHelpAndPlaceholder();
        hideField();
    }

    /**
     * @param {null|{status:string,value?:string}} state
     */
    function apply(state) {
        clear();
        ensureNodes();
        bindPasteOnce();
        if (!wrap || !input || !countrySelect) {
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
            countrySelect.disabled = true;
            setUnavailable(true);
            showField();
            return;
        }

        if (state.status === STATUS_KNOWN_VALUE) {
            var parsed = parseStored(typeof state.value === 'string' ? state.value : '');
            if (!parsed || !COUNTRY_CONFIG[parsed.country]) {
                active = true;
                sendMode = 'omit';
                input.value = '';
                input.disabled = true;
                countrySelect.disabled = true;
                setUnavailable(true);
                showField();
                return;
            }
            active = true;
            sendMode = 'set';
            input.disabled = false;
            countrySelect.disabled = false;
            countrySelect.value = parsed.country;
            input.value = parsed.national;
            updateHelpAndPlaceholder();
            showField();
            return;
        }

        if (state.status === STATUS_KNOWN_ABSENT) {
            active = true;
            sendMode = 'set';
            input.disabled = false;
            countrySelect.disabled = false;
            countrySelect.value = '52';
            input.value = '';
            updateHelpAndPlaceholder();
            showField();
        }
    }

    function collect(formData) {
        if (!active || sendMode === 'omit' || !formData || typeof formData.append !== 'function') {
            return;
        }
        ensureNodes();
        if (!input || input.disabled || !countrySelect || countrySelect.disabled) {
            return;
        }
        var national = digitsOnly(input.value);
        if (national === '') {
            formData.append('phone', '');
            return;
        }
        formData.append('phone', '+' + countrySelect.value + national);
    }

    function handleError(code, message) {
        var phoneCodes = {
            invalid_phone: true,
            phone_invalid_length: true,
            phone_unsupported_country: true
        };
        if (!phoneCodes[code]) {
            return false;
        }
        ensureNodes();
        if (!active || !input || input.disabled) {
            return false;
        }
        setError(message || 'Revisa el teléfono.');
        input.focus();
        return true;
    }

    global.AA_CANONICAL_SHELL_CAPABILITY_MODULES = global.AA_CANONICAL_SHELL_CAPABILITY_MODULES || {};
    global.AA_CANONICAL_SHELL_CAPABILITY_MODULES.phone = {
        key: 'phone',
        clear: clear,
        clearError: clearError,
        apply: apply,
        collect: collect,
        handleError: handleError
    };
}(typeof window !== 'undefined' ? window : this));
