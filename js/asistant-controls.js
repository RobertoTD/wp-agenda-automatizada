document.addEventListener('DOMContentLoaded', function() {
    // 🔹 Manejar botones de CONFIRMAR
    document.querySelectorAll('.aa-btn-confirmar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            const correo = this.dataset.correo;
            
            if (confirm('¿Confirmar la cita de ' + nombre + '?\n\nSe enviará un correo de confirmación a: ' + correo)) {
                confirmarCita(id);
            }
        });
    });
    
    // 🔹 Manejar botones de CANCELAR
    document.querySelectorAll('.aa-btn-cancelar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const nombre = this.dataset.nombre;
            const correo = this.dataset.correo;
            
            if (confirm('⚠️ ¿CANCELAR la cita de ' + nombre + '?\n\nSe enviará un correo de cancelación a: ' + correo + '\n\nEsta acción no se puede deshacer.')) {
                cancelarCita(id);
            }
        });
    });

    // ===============================
    // 🔹 SECCIÓN DE CLIENTES
    // ===============================
    
    // Toggle formulario de nuevo cliente
    const btnToggleForm = document.getElementById('btn-toggle-form-cliente');
    const formNuevoCliente = document.getElementById('form-nuevo-cliente');
    const btnCancelarForm = document.getElementById('btn-cancelar-form');
    
    if (btnToggleForm && formNuevoCliente) {
        btnToggleForm.addEventListener('click', function() {
            formNuevoCliente.classList.toggle('visible');
            if (formNuevoCliente.classList.contains('visible')) {
                btnToggleForm.textContent = '− Ocultar formulario';
            } else {
                btnToggleForm.textContent = '+ Crear nuevo cliente';
            }
        });
    }
    
    if (btnCancelarForm) {
        btnCancelarForm.addEventListener('click', function() {
            if (formNuevoCliente) {
                formNuevoCliente.classList.remove('visible');
            }
            if (btnToggleForm) {
                btnToggleForm.textContent = '+ Crear nuevo cliente';
            }
            const formElement = document.getElementById('form-crear-cliente');
            if (formElement) {
                formElement.reset();
            }
        });
    }
    
    // Enviar formulario de nuevo cliente
    const formCrearCliente = document.getElementById('form-crear-cliente');
    if (formCrearCliente) {
        formCrearCliente.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'aa_crear_cliente');
            formData.append('nombre', document.getElementById('cliente-nombre').value);
            formData.append('telefono', document.getElementById('cliente-telefono').value);
            formData.append('correo', document.getElementById('cliente-correo').value);
            formData.append('_wpnonce', aa_asistant_vars.nonce_crear_cliente);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.data.message);
                    location.reload(); // Recargar para mostrar el nuevo cliente
                } else {
                    alert('❌ Error: ' + (data.data.message || 'No se pudo guardar el cliente.'));
                }
            })
            .catch(err => {
                alert('❌ Error de conexión: ' + err.message);
            });
        });
    }
});

// 🔹 Función para confirmar cita
function confirmarCita(id) {
    const formData = new FormData();
    formData.append('action', 'aa_confirmar_cita');
    formData.append('id', id);
    formData.append('_wpnonce', aa_asistant_vars.nonce_confirmar);
    
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Cita confirmada. Se envió correo de confirmación.');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.data.message || 'No se pudo confirmar la cita.'));
        }
    })
    .catch(err => {
        alert('❌ Error de conexión: ' + err.message);
    });
}

// 🔹 Función para cancelar cita
function cancelarCita(id) {
    const formData = new FormData();
    formData.append('action', 'aa_cancelar_cita');
    formData.append('id', id);
    formData.append('_wpnonce', aa_asistant_vars.nonce_cancelar);
    
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Cita cancelada. Se envió correo de notificación.');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.data.message || 'No se pudo cancelar la cita.'));
        }
    })
    .catch(err => {
        alert('❌ Error de conexión: ' + err.message);
    });
}