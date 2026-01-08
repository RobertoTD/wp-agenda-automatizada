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

        // Campo: Teléfono
        const telefonoGroup = document.createElement('div');
        telefonoGroup.className = 'aa-form-group';
        
        const telefonoLabel = document.createElement('label');
        telefonoLabel.setAttribute('for', 'modal-cliente-telefono');
        telefonoLabel.textContent = 'Teléfono *';
        
        const telefonoInput = document.createElement('input');
        telefonoInput.type = 'tel';
        telefonoInput.id = 'modal-cliente-telefono';
        telefonoInput.name = 'telefono';
        telefonoInput.required = true;
        telefonoInput.placeholder = 'Ej: 5512345678';
        
        telefonoGroup.appendChild(telefonoLabel);
        telefonoGroup.appendChild(telefonoInput);

        // Campo: Correo
        const correoGroup = document.createElement('div');
        correoGroup.className = 'aa-form-group';
        
        const correoLabel = document.createElement('label');
        correoLabel.setAttribute('for', 'modal-cliente-correo');
        correoLabel.textContent = 'Correo electrónico *';
        
        const correoInput = document.createElement('input');
        correoInput.type = 'email';
        correoInput.id = 'modal-cliente-correo';
        correoInput.name = 'correo';
        correoInput.required = true;
        correoInput.placeholder = 'Ej: cliente@email.com';
        
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
     */
    function saveNewClient() {
        const form = document.getElementById('aa-modal-form-cliente');
        if (!form) return;

        const nombre = document.getElementById('modal-cliente-nombre').value.trim();
        const telefono = document.getElementById('modal-cliente-telefono').value.trim();
        const correo = document.getElementById('modal-cliente-correo').value.trim();

        // Validación básica
        if (!nombre || !telefono || !correo) {
            showFormStatus('Todos los campos son obligatorios.', true);
            return;
        }

        // Validar formato de correo
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(correo)) {
            showFormStatus('El correo electrónico no es válido.', true);
            return;
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
                        cliente: clienteData,
                        isEdit: false
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
     * Abrir modal de nuevo cliente
     */
    function openNewClientModal() {
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
                newSaveBtn.addEventListener('click', saveNewClient);
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

        // Campo: Teléfono
        const telefonoGroup = document.createElement('div');
        telefonoGroup.className = 'aa-form-group';
        
        const telefonoLabel = document.createElement('label');
        telefonoLabel.setAttribute('for', 'modal-editar-cliente-telefono');
        telefonoLabel.textContent = 'Teléfono *';
        
        const telefonoInput = document.createElement('input');
        telefonoInput.type = 'tel';
        telefonoInput.id = 'modal-editar-cliente-telefono';
        telefonoInput.name = 'telefono';
        telefonoInput.required = true;
        telefonoInput.value = cliente.telefono || '';
        telefonoInput.placeholder = 'Ej: 5512345678';
        
        telefonoGroup.appendChild(telefonoLabel);
        telefonoGroup.appendChild(telefonoInput);

        // Campo: Correo
        const correoGroup = document.createElement('div');
        correoGroup.className = 'aa-form-group';
        
        const correoLabel = document.createElement('label');
        correoLabel.setAttribute('for', 'modal-editar-cliente-correo');
        correoLabel.textContent = 'Correo electrónico *';
        
        const correoInput = document.createElement('input');
        correoInput.type = 'email';
        correoInput.id = 'modal-editar-cliente-correo';
        correoInput.name = 'correo';
        correoInput.required = true;
        correoInput.value = cliente.correo || '';
        correoInput.placeholder = 'Ej: cliente@email.com';
        
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
        const telefono = document.getElementById('modal-editar-cliente-telefono').value.trim();
        const correo = document.getElementById('modal-editar-cliente-correo').value.trim();

        // Validación básica
        if (!clienteId || !nombre || !telefono || !correo) {
            showEditFormStatus('Todos los campos son obligatorios.', true);
            return;
        }

        // Validar formato de correo
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(correo)) {
            showEditFormStatus('El correo electrónico no es válido.', true);
            return;
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

