/**
 * Client Create/Edit Modal - Independent Modal Component
 * 
 * This modal handles creating and editing clients.
 * It's independent from the clients module and can be used from any context.
 * 
 * API:
 * - AAAdmin.ClientCreateModal.openCreate() - Open modal to create new client
 * - AAAdmin.ClientCreateModal.openEdit(cliente) - Open modal to edit existing client
 * 
 * Events:
 * - 'aa:client:saved' - Emitted when a client is successfully saved (create or edit)
 *   Event detail: { cliente: {...}, isEdit: boolean }
 */

(function() {
    'use strict';

    /** Configuración de países para teléfono canónico (código + longitud nacional) */
    var COUNTRY_CONFIG = {
        '52': { label: 'México (+52)', placeholder: 'Ej: 5512345678', nationalLen: 10, totalLen: 12 },
        '1':  { label: 'USA (+1)',   placeholder: 'Ej: 2025550123', nationalLen: 10, totalLen: 11 },
        '34': { label: 'España (+34)', placeholder: 'Ej: 612345678', nationalLen: 9, totalLen: 11 }
    };

    /**
     * Obtiene dígitos nacionales y teléfono canónico desde input + país.
     * Si el usuario pegó el número completo (country+national), lo recorta.
     * @param {string} digits - Solo dígitos del input
     * @param {string} country - Código de país ('52','1','34')
     * @returns {{ nationalDigits: string, telefonoCanon: string }|{ error: string }}
     */
    function normalizePhoneForCountry(digits, country) {
        var cfg = COUNTRY_CONFIG[country];
        if (!cfg) return { error: 'País no soportado.' };

        var nationalDigits = digits;

        // Si empieza con el código de país y la longitud total es la esperada, recortar
        if (digits.length === cfg.totalLen && digits.indexOf(country) === 0) {
            nationalDigits = digits.slice(country.length);
        }

        if (nationalDigits.length !== cfg.nationalLen) {
            var msg = 'Teléfono inválido. ';
            if (country === '52') msg += 'México: 10 dígitos (ej: 5512345678).';
            else if (country === '1') msg += 'USA: 10 dígitos (ej: 2025550123).';
            else if (country === '34') msg += 'España: 9 dígitos (ej: 612345678).';
            else msg += 'Longitud incorrecta para el país seleccionado.';
            return { error: msg };
        }

        var telefonoCanon = country + nationalDigits;
        return { nationalDigits: nationalDigits, telefonoCanon: telefonoCanon };
    }

    /**
     * Actualiza el placeholder del input teléfono según el país seleccionado
     * @param {string} countrySelectId - ID del select de país
     * @param {string} phoneInputId - ID del input teléfono
     */
    function updatePhonePlaceholder(countrySelectId, phoneInputId) {
        var sel = document.getElementById(countrySelectId);
        var input = document.getElementById(phoneInputId);
        if (sel && input && COUNTRY_CONFIG[sel.value]) {
            input.placeholder = COUNTRY_CONFIG[sel.value].placeholder;
        }
    }

    /**
     * Parsea un teléfono canónico guardado para obtener país y parte nacional (para formulario editar)
     * @param {string} telefono - Teléfono en BD (canónico o legacy 10 dígitos)
     * @returns {{ country: string, nationalDigits: string }}
     */
    function parseStoredPhone(telefono) {
        var digits = (telefono || '').replace(/\D/g, '');
        if (digits.length === 12 && digits.indexOf('52') === 0) {
            return { country: '52', nationalDigits: digits.slice(2) };
        }
        if (digits.length === 11 && digits.indexOf('1') === 0) {
            return { country: '1', nationalDigits: digits.slice(1) };
        }
        if (digits.length === 11 && digits.indexOf('34') === 0) {
            return { country: '34', nationalDigits: digits.slice(2) };
        }
        // Legacy: 10 dígitos asumir México
        if (digits.length === 10) {
            return { country: '52', nationalDigits: digits };
        }
        return { country: '52', nationalDigits: digits };
    }

    /**
     * Crear contenido del formulario para nuevo cliente
     * @returns {HTMLElement} - Elemento del formulario
     */
    function createNewClientForm() {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-cliente';
        form.className = 'aa-modal-form';

        // Campo: Nombre
        const nombreGroup = document.createElement('div');
        nombreGroup.className = 'aa-form-group';
        
        const nombreLabel = document.createElement('label');
        nombreLabel.setAttribute('for', 'modal-cliente-nombre');
        nombreLabel.textContent = 'Nombre completo *';
        
        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-cliente-nombre';
        nombreInput.name = 'nombre';
        nombreInput.required = true;
        nombreInput.placeholder = 'Ej: Juan Pérez';
        
        nombreGroup.appendChild(nombreLabel);
        nombreGroup.appendChild(nombreInput);

        // Campo: País + Teléfono
        const telefonoGroup = document.createElement('div');
        telefonoGroup.className = 'aa-form-group';

        const telefonoRow = document.createElement('div');
        telefonoRow.style.cssText = 'display:flex;gap:8px;align-items:flex-start;';

        const countrySelect = document.createElement('select');
        countrySelect.id = 'modal-cliente-country';
        countrySelect.name = 'country';
        countrySelect.style.cssText = 'flex:0 0 auto;min-width:140px;';
        countrySelect.innerHTML = '<option value="52">México (+52)</option><option value="1">USA (+1)</option><option value="34">España (+34)</option>';

        const phoneWrapper = document.createElement('div');
        phoneWrapper.style.flex = '1';
        const telefonoLabel = document.createElement('label');
        telefonoLabel.setAttribute('for', 'modal-cliente-telefono');
        telefonoLabel.textContent = 'Teléfono *';
        const telefonoInput = document.createElement('input');
        telefonoInput.type = 'tel';
        telefonoInput.id = 'modal-cliente-telefono';
        telefonoInput.name = 'telefono';
        telefonoInput.required = true;
        telefonoInput.placeholder = 'Ej: 5512345678';
        phoneWrapper.appendChild(telefonoLabel);
        phoneWrapper.appendChild(telefonoInput);

        telefonoRow.appendChild(countrySelect);
        telefonoRow.appendChild(phoneWrapper);
        telefonoGroup.appendChild(telefonoRow);

        countrySelect.addEventListener('change', function() {
            updatePhonePlaceholder('modal-cliente-country', 'modal-cliente-telefono');
        });

        // Campo: Correo
        const correoGroup = document.createElement('div');
        correoGroup.className = 'aa-form-group';
        
        const correoLabel = document.createElement('label');
        correoLabel.setAttribute('for', 'modal-cliente-correo');
        correoLabel.textContent = 'Correo electrónico';
        
        const correoInput = document.createElement('input');
        correoInput.type = 'email';
        correoInput.id = 'modal-cliente-correo';
        correoInput.name = 'correo';
        correoInput.required = false;
        correoInput.placeholder = 'Ej: cliente@email.com (opcional)';
        
        correoGroup.appendChild(correoLabel);
        correoGroup.appendChild(correoInput);

        // Mensaje de estado (para errores/éxito)
        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-cliente-status';
        statusMsg.className = 'aa-form-status';
        statusMsg.style.display = 'none';

        // Ensamblar formulario
        form.appendChild(nombreGroup);
        form.appendChild(telefonoGroup);
        form.appendChild(correoGroup);
        form.appendChild(statusMsg);

        return form;
    }

    /**
     * Crear footer del modal con botones
     * @returns {HTMLElement} - Elemento del footer
     */
    function createModalFooter() {
        const footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        // Botón Cancelar
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');

        // Botón Guardar
        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.id = 'aa-modal-save-cliente';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Cliente';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    /**
     * Mostrar mensaje de estado en el formulario
     */
    function showFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-cliente-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    /**
     * Guardar cliente via AJAX
     * @param {boolean} isInlineMode - Si está en modo inline, no cerrar modales
     * @param {HTMLElement} inlineContainer - Contenedor inline a limpiar después de guardar
     */
    function saveNewClient(isInlineMode, inlineContainer) {
        const form = document.getElementById('aa-modal-form-cliente');
        if (!form) return;

        const nombre = document.getElementById('modal-cliente-nombre').value.trim();
        const telefonoRaw = document.getElementById('modal-cliente-telefono').value.trim();
        const correo = document.getElementById('modal-cliente-correo').value.trim();
        const country = (document.getElementById('modal-cliente-country') || {}).value || '52';

        // Validación básica (correo es opcional)
        if (!nombre || !telefonoRaw) {
            showFormStatus('Nombre y teléfono son obligatorios.', true);
            return;
        }

        // Normalizar teléfono canónico por país
        var digits = telefonoRaw.replace(/\D/g, '');
        var phoneResult = normalizePhoneForCountry(digits, country);
        if (phoneResult.error) {
            showFormStatus(phoneResult.error, true);
            return;
        }
        var telefono = phoneResult.telefonoCanon;

        // Validar formato de correo solo si se proporcionó
        if (correo) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(correo)) {
                showFormStatus('El correo electrónico no es válido.', true);
                return;
            }
        }

        // Obtener nonce
        const nonce = window.AA_CLIENTS_NONCES ? window.AA_CLIENTS_NONCES.crear_cliente : '';
        if (!nonce) {
            showFormStatus('Error de seguridad: nonce no disponible.', true);
            return;
        }

        // Preparar datos
        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'aa_crear_cliente');
        formData.append('_ajax_nonce', nonce);
        formData.append('nombre', nombre);
        formData.append('telefono', telefono);
        formData.append('correo', correo);

        // Deshabilitar botón mientras se procesa
        const saveBtn = document.getElementById('aa-modal-save-cliente');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        // Llamar AJAX
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            if (result.success) {
                showFormStatus('Cliente guardado correctamente.', false);
                
                // Emitir evento para que otros módulos puedan reaccionar
                const clienteData = result.data && result.data.cliente ? result.data.cliente : {
                    nombre: nombre,
                    telefono: telefono,
                    correo: correo
                };
                
                document.dispatchEvent(new CustomEvent('aa:client:saved', {
                    detail: {
                        telefono: telefono, // Incluir teléfono explícitamente
                        cliente: clienteData,
                        isEdit: false
                    }
                }));
                
                if (isInlineMode && inlineContainer) {
                    // Modo inline: remover contenido y ocultar contenedor después de un breve delay
                    setTimeout(function() {
                        inlineContainer.innerHTML = '';
                        inlineContainer.style.display = 'none';
                    }, 1500);
                } else {
                    // Modo modal: cerrar modal después de 1 segundo
                    setTimeout(function() {
                        if (window.AAAdmin && window.AAAdmin.closeModal) {
                            window.AAAdmin.closeModal();
                        }
                    }, 1000);
                }
            } else {
                const errorMsg = result.data && result.data.message 
                    ? result.data.message 
                    : 'Error al guardar el cliente.';
                showFormStatus(errorMsg, true);
                
                // Rehabilitar botón
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar Cliente';
                }
            }
        })
        .catch(function(error) {
            console.error('Error AJAX:', error);
            showFormStatus('Error de conexión. Intenta de nuevo.', true);
            
            // Rehabilitar botón
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar Cliente';
            }
        });
    }

    /**
     * Renderizar formulario inline en un contenedor
     * @param {HTMLElement} container - Contenedor donde renderizar
     */
    function renderInlineForm(container) {
        if (!container) {
            console.error('Contenedor no proporcionado para modo inline');
            return;
        }

        // Limpiar contenedor
        container.innerHTML = '';

        // Crear wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'aa-client-inline-form';
        wrapper.style.cssText = 'padding: 16px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-top: 8px;';

        // Título
        const title = document.createElement('h3');
        title.textContent = 'Nuevo Cliente';
        title.style.cssText = 'margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;';
        wrapper.appendChild(title);

        // Formulario
        const formContent = createNewClientForm();
        wrapper.appendChild(formContent);

        // Footer
        const footerContent = createModalFooter();
        // Modificar botón cancelar para limpiar el contenedor
        const cancelBtn = footerContent.querySelector('.aa-btn-cancelar');
        if (cancelBtn) {
            cancelBtn.removeAttribute('data-aa-modal-close');
            cancelBtn.addEventListener('click', function() {
                container.innerHTML = '';
                container.style.display = 'none';
            });
        }
        wrapper.appendChild(footerContent);

        container.appendChild(wrapper);

        // Agregar event listener al botón guardar
        setTimeout(function() {
            const saveBtn = document.getElementById('aa-modal-save-cliente');
            if (saveBtn) {
                // Remover listeners previos para evitar duplicados
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', function() {
                    saveNewClient(true, container);
                });
            }

            // Focus en primer campo
            const nombreInput = document.getElementById('modal-cliente-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 50);
    }

    /**
     * Abrir modal de nuevo cliente
     * @param {Object} [options] - Opciones de apertura
     * @param {string} [options.mode='modal'] - Modo: 'modal' o 'inline'
     * @param {HTMLElement} [options.container] - Contenedor para modo inline
     */
    function openNewClientModal(options) {
        // Valores por defecto
        const opts = options || {};
        const mode = opts.mode || 'modal';
        const container = opts.container;

        // Modo inline
        if (mode === 'inline') {
            if (!container) {
                console.error('Container es requerido para modo inline');
                return;
            }
            renderInlineForm(container);
            return;
        }

        // Modo modal (comportamiento por defecto)
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

        const formContent = createNewClientForm();
        const footerContent = createModalFooter();

        window.AAAdmin.openModal({
            title: 'Nuevo Cliente',
            body: formContent,
            footer: footerContent
        });

        // Agregar event listener al botón guardar después de que el modal esté abierto
        setTimeout(function() {
            const saveBtn = document.getElementById('aa-modal-save-cliente');
            if (saveBtn) {
                // Remover listeners previos para evitar duplicados
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', function() {
                    saveNewClient(false, null);
                });
            }

            // Focus en primer campo
            const nombreInput = document.getElementById('modal-cliente-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

    /**
     * Crear contenido del formulario para editar cliente
     * @param {Object} cliente - Datos del cliente a editar
     * @returns {HTMLElement} - Elemento del formulario
     */
    function createEditClientForm(cliente) {
        const form = document.createElement('form');
        form.id = 'aa-modal-form-editar-cliente';
        form.className = 'aa-modal-form';

        // Campo oculto: ID del cliente
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.id = 'modal-editar-cliente-id';
        idInput.name = 'cliente_id';
        idInput.value = cliente.id || '';
        form.appendChild(idInput);

        // Campo: Nombre
        const nombreGroup = document.createElement('div');
        nombreGroup.className = 'aa-form-group';
        
        const nombreLabel = document.createElement('label');
        nombreLabel.setAttribute('for', 'modal-editar-cliente-nombre');
        nombreLabel.textContent = 'Nombre completo *';
        
        const nombreInput = document.createElement('input');
        nombreInput.type = 'text';
        nombreInput.id = 'modal-editar-cliente-nombre';
        nombreInput.name = 'nombre';
        nombreInput.required = true;
        nombreInput.value = cliente.nombre || '';
        nombreInput.placeholder = 'Ej: Juan Pérez';
        
        nombreGroup.appendChild(nombreLabel);
        nombreGroup.appendChild(nombreInput);

        // Campo: País + Teléfono
        var parsed = parseStoredPhone(cliente.telefono || '');
        const telefonoGroup = document.createElement('div');
        telefonoGroup.className = 'aa-form-group';

        const telefonoRow = document.createElement('div');
        telefonoRow.style.cssText = 'display:flex;gap:8px;align-items:flex-start;';

        const countrySelect = document.createElement('select');
        countrySelect.id = 'modal-cliente-country-edit';
        countrySelect.name = 'country';
        countrySelect.style.cssText = 'flex:0 0 auto;min-width:140px;';
        countrySelect.innerHTML = '<option value="52">México (+52)</option><option value="1">USA (+1)</option><option value="34">España (+34)</option>';
        countrySelect.value = parsed.country;

        const phoneWrapper = document.createElement('div');
        phoneWrapper.style.flex = '1';
        const telefonoLabel = document.createElement('label');
        telefonoLabel.setAttribute('for', 'modal-editar-cliente-telefono');
        telefonoLabel.textContent = 'Teléfono *';
        const telefonoInput = document.createElement('input');
        telefonoInput.type = 'tel';
        telefonoInput.id = 'modal-editar-cliente-telefono';
        telefonoInput.name = 'telefono';
        telefonoInput.required = true;
        telefonoInput.value = parsed.nationalDigits;
        telefonoInput.placeholder = (COUNTRY_CONFIG[parsed.country] || COUNTRY_CONFIG['52']).placeholder;
        phoneWrapper.appendChild(telefonoLabel);
        phoneWrapper.appendChild(telefonoInput);

        telefonoRow.appendChild(countrySelect);
        telefonoRow.appendChild(phoneWrapper);
        telefonoGroup.appendChild(telefonoRow);

        countrySelect.addEventListener('change', function() {
            updatePhonePlaceholder('modal-cliente-country-edit', 'modal-editar-cliente-telefono');
        });

        // Campo: Correo
        const correoGroup = document.createElement('div');
        correoGroup.className = 'aa-form-group';
        
        const correoLabel = document.createElement('label');
        correoLabel.setAttribute('for', 'modal-editar-cliente-correo');
        correoLabel.textContent = 'Correo electrónico';
        
        const correoInput = document.createElement('input');
        correoInput.type = 'email';
        correoInput.id = 'modal-editar-cliente-correo';
        correoInput.name = 'correo';
        correoInput.required = false;
        correoInput.value = cliente.correo || '';
        correoInput.placeholder = 'Ej: cliente@email.com (opcional)';
        
        correoGroup.appendChild(correoLabel);
        correoGroup.appendChild(correoInput);

        // Mensaje de estado (para errores/éxito)
        const statusMsg = document.createElement('div');
        statusMsg.id = 'modal-editar-cliente-status';
        statusMsg.className = 'aa-form-status';
        statusMsg.style.display = 'none';

        // Ensamblar formulario
        form.appendChild(nombreGroup);
        form.appendChild(telefonoGroup);
        form.appendChild(correoGroup);
        form.appendChild(statusMsg);

        return form;
    }

    /**
     * Crear footer del modal de edición con botones
     * @returns {HTMLElement} - Elemento del footer
     */
    function createEditModalFooter() {
        const footer = document.createElement('div');
        footer.className = 'aa-modal-actions';

        // Botón Cancelar
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'aa-btn-cancelar';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.setAttribute('data-aa-modal-close', '');

        // Botón Guardar
        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.id = 'aa-modal-save-editar-cliente';
        saveBtn.className = 'aa-btn-guardar';
        saveBtn.textContent = 'Guardar Cambios';

        footer.appendChild(cancelBtn);
        footer.appendChild(saveBtn);

        return footer;
    }

    /**
     * Mostrar mensaje de estado en el formulario de edición
     */
    function showEditFormStatus(message, isError) {
        const statusEl = document.getElementById('modal-editar-cliente-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.style.display = 'block';
        statusEl.className = 'aa-form-status ' + (isError ? 'aa-form-error' : 'aa-form-success');
    }

    /**
     * Guardar cliente editado via AJAX
     */
    function saveEditedClient() {
        const form = document.getElementById('aa-modal-form-editar-cliente');
        if (!form) return;

        const clienteId = document.getElementById('modal-editar-cliente-id').value;
        const nombre = document.getElementById('modal-editar-cliente-nombre').value.trim();
        const telefonoRaw = document.getElementById('modal-editar-cliente-telefono').value.trim();
        const correo = document.getElementById('modal-editar-cliente-correo').value.trim();
        const country = (document.getElementById('modal-cliente-country-edit') || {}).value || '52';

        // Validación básica (correo es opcional)
        if (!clienteId || !nombre || !telefonoRaw) {
            showEditFormStatus('Nombre y teléfono son obligatorios.', true);
            return;
        }

        // Normalizar teléfono canónico por país
        var digitsEdit = telefonoRaw.replace(/\D/g, '');
        var phoneResultEdit = normalizePhoneForCountry(digitsEdit, country);
        if (phoneResultEdit.error) {
            showEditFormStatus(phoneResultEdit.error, true);
            return;
        }
        var telefono = phoneResultEdit.telefonoCanon;

        // Validar formato de correo solo si se proporcionó
        if (correo) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(correo)) {
                showEditFormStatus('El correo electrónico no es válido.', true);
                return;
            }
        }

        // Obtener nonce
        const nonce = window.AA_CLIENTS_NONCES ? window.AA_CLIENTS_NONCES.editar_cliente : '';
        if (!nonce) {
            showEditFormStatus('Error de seguridad: nonce no disponible.', true);
            return;
        }

        // Preparar datos
        const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'aa_editar_cliente');
        formData.append('_ajax_nonce', nonce);
        formData.append('cliente_id', clienteId);
        formData.append('nombre', nombre);
        formData.append('telefono', telefono);
        formData.append('correo', correo);

        // Deshabilitar botón mientras se procesa
        const saveBtn = document.getElementById('aa-modal-save-editar-cliente');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Guardando...';
        }

        // Llamar AJAX
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            if (result.success) {
                showEditFormStatus('Cliente actualizado correctamente.', false);
                
                // Emitir evento para que otros módulos puedan reaccionar
                const clienteData = result.data && result.data.cliente ? result.data.cliente : {
                    id: clienteId,
                    nombre: nombre,
                    telefono: telefono,
                    correo: correo
                };
                
                document.dispatchEvent(new CustomEvent('aa:client:saved', {
                    detail: {
                        cliente: clienteData,
                        isEdit: true
                    }
                }));
                
                // Cerrar modal después de 1 segundo
                setTimeout(function() {
                    if (window.AAAdmin && window.AAAdmin.closeModal) {
                        window.AAAdmin.closeModal();
                    }
                }, 1000);
            } else {
                const errorMsg = result.data && result.data.message 
                    ? result.data.message 
                    : 'Error al actualizar el cliente.';
                showEditFormStatus(errorMsg, true);
                
                // Rehabilitar botón
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Guardar Cambios';
                }
            }
        })
        .catch(function(error) {
            console.error('Error AJAX:', error);
            showEditFormStatus('Error de conexión. Intenta de nuevo.', true);
            
            // Rehabilitar botón
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Guardar Cambios';
            }
        });
    }

    /**
     * Abrir modal de editar cliente
     * @param {Object} cliente - Datos del cliente a editar
     */
    function openEditClientModal(cliente) {
        if (!window.AAAdmin || !window.AAAdmin.openModal) {
            console.error('AAAdmin.openModal no está disponible');
            alert('Error: Sistema de modales no disponible');
            return;
        }

        const formContent = createEditClientForm(cliente);
        const footerContent = createEditModalFooter();

        window.AAAdmin.openModal({
            title: 'Editar Cliente',
            body: formContent,
            footer: footerContent
        });

        // Agregar event listener al botón guardar después de que el modal esté abierto
        setTimeout(function() {
            const saveBtn = document.getElementById('aa-modal-save-editar-cliente');
            if (saveBtn) {
                // Remover listeners previos para evitar duplicados
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', saveEditedClient);
            }

            // Focus en primer campo
            const nombreInput = document.getElementById('modal-editar-cliente-nombre');
            if (nombreInput) {
                nombreInput.focus();
            }
        }, 100);
    }

    // Asegurar que el namespace exista
    window.AAAdmin = window.AAAdmin || {};

    // Exponer API pública
    window.AAAdmin.ClientCreateModal = {
        openCreate: openNewClientModal,
        openEdit: openEditClientModal
    };

})();

