document.addEventListener('DOMContentLoaded', function() {
    let paginaActual = 1;
    
    // 🔹 Cargar próximas citas al inicio
    cargarProximasCitas();
    
    // 🔹 Botón buscar
    document.getElementById('aa-btn-buscar-proximas')?.addEventListener('click', function() {
        paginaActual = 1;
        cargarProximasCitas();
    });
    
    // 🔹 Botón limpiar
    document.getElementById('aa-btn-limpiar-proximas')?.addEventListener('click', function() {
        document.getElementById('aa-buscar-proximas').value = '';
        document.getElementById('aa-ordenar-proximas').value = 'fecha_asc';
        paginaActual = 1;
        cargarProximasCitas();
    });
    
    // 🔹 Cambio en ordenamiento
    document.getElementById('aa-ordenar-proximas')?.addEventListener('change', function() {
        paginaActual = 1;
        cargarProximasCitas();
    });
    
    // 🔹 Enter en el buscador
    document.getElementById('aa-buscar-proximas')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            paginaActual = 1;
            cargarProximasCitas();
        }
    });
    
    // ===============================
    // 🔹 Función para cargar próximas citas
    // ===============================
    function cargarProximasCitas() {
        const container = document.getElementById('aa-proximas-container');
        const paginacion = document.getElementById('aa-proximas-paginacion');
        const buscar = document.getElementById('aa-buscar-proximas')?.value || '';
        const ordenar = document.getElementById('aa-ordenar-proximas')?.value || 'fecha_asc';
        
        if (!container) return;
        
        container.innerHTML = '<p style="text-align: center; color: #999;">⏳ Cargando...</p>';
        
        const formData = new FormData();
        formData.append('action', 'aa_get_proximas_citas');
        formData.append('buscar', buscar);
        formData.append('ordenar', ordenar);
        formData.append('pagina', paginaActual);
        formData.append('_wpnonce', aa_proximas_vars.nonce);
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarProximasCitas(data.data.citas, container);
                renderizarPaginacion(data.data.pagina_actual, data.data.total_paginas, paginacion);
            } else {
                container.innerHTML = '<p style="color: #e74c3c;">❌ Error: ' + (data.data?.message || 'No se pudo cargar las próximas citas.') + '</p>';
            }
        })
        .catch(err => {
            console.error('Error al cargar próximas citas:', err);
            container.innerHTML = '<p style="color: #e74c3c;">❌ Error de conexión.</p>';
        });
    }
    
    // ===============================
    // 🔹 Renderizar tabla de citas
    // ===============================
    function renderizarProximasCitas(citas, container) {
        if (!citas || citas.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #999;">📭 No hay próximas citas registradas.</p>';
            return;
        }
        
        // 🔹 Wrapper para scroll horizontal
        let html = '<div class="aa-table-wrapper">';
        html += '<table class="widefat aa-table-scroll">';
        html += '<thead><tr><th>Cliente</th><th>Teléfono</th><th>Servicio</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead>';
        html += '<tbody>';
        
        citas.forEach(cita => {
            const estadoClass = 'aa-estado-' + cita.estado.toLowerCase();
            let estadoTexto = '';
            
            switch(cita.estado) {
                case 'confirmed':
                    estadoTexto = 'Confirmada';
                    break;
                case 'pending':
                    estadoTexto = 'Pendiente';
                    break;
                case 'cancelled':
                    estadoTexto = 'Cancelada';
                    break;
                default:
                    estadoTexto = cita.estado;
            }
            
            const fecha = new Date(cita.fecha.replace(' ', 'T'));
            const fechaFormateada = fecha.toLocaleDateString('es-MX', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            html += '<tr>';
            html += '<td>' + escapeHtml(cita.nombre) + '</td>';
            html += '<td>' + escapeHtml(cita.telefono) + '</td>';
            html += '<td>' + escapeHtml(cita.servicio) + '</td>';
            html += '<td>' + fechaFormateada + '</td>';
            html += '<td class="' + estadoClass + '">' + estadoTexto + '</td>';
            html += '<td>';
            
            // 🔹 Botón confirmar (solo si está pendiente)
            if (cita.estado === 'pending') {
                html += '<button class="aa-btn-confirmar" data-id="' + cita.id + '" data-nombre="' + escapeHtml(cita.nombre) + '" data-correo="' + escapeHtml(cita.correo) + '">✓ Confirmar</button> ';
            }
            
            // 🔹 Botón cancelar (si está confirmada o pendiente)
            if (cita.estado === 'confirmed' || cita.estado === 'pending') {
                html += '<button class="aa-btn-cancelar" data-id="' + cita.id + '" data-nombre="' + escapeHtml(cita.nombre) + '" data-correo="' + escapeHtml(cita.correo) + '">✕ Cancelar</button> ';
            }
            
            // 🔹 Botón crear cliente (si no tiene id_cliente)
            if (!cita.id_cliente) {
                html += '<button class="aa-btn-crear-cliente-desde-cita" data-reserva-id="' + cita.id + '" data-nombre="' + escapeHtml(cita.nombre) + '" data-telefono="' + escapeHtml(cita.telefono) + '" data-correo="' + escapeHtml(cita.correo) + '">+ Cliente</button>';
            }
            
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>'; // Cerrar wrapper
        container.innerHTML = html;
        
        // 🔹 Asignar eventos a los botones
        asignarEventosBotones(container);
    }
    
    // ===============================
    // 🔹 Asignar eventos a botones de acción
    // ===============================
    function asignarEventosBotones(container) {
        // Botones de confirmar
        container.querySelectorAll('.aa-btn-confirmar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const nombre = this.dataset.nombre;
                const correo = this.dataset.correo;
                
                if (confirm('¿Confirmar la cita de ' + nombre + '?\n\nSe enviará un correo de confirmación a: ' + correo)) {
                    confirmarCita(id);
                }
            });
        });
        
        // Botones de cancelar
        container.querySelectorAll('.aa-btn-cancelar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const nombre = this.dataset.nombre;
                const correo = this.dataset.correo;
                
                if (confirm('⚠️ ¿CANCELAR la cita de ' + nombre + '?\n\nSe enviará un correo de cancelación a: ' + correo + '\n\nEsta acción no se puede deshacer.')) {
                    cancelarCita(id);
                }
            });
        });
        
        // Botones de crear cliente
        container.querySelectorAll('.aa-btn-crear-cliente-desde-cita').forEach(btn => {
            btn.addEventListener('click', function() {
                const reservaId = this.dataset.reservaId;
                const nombre = this.dataset.nombre;
                const telefono = this.dataset.telefono;
                const correo = this.dataset.correo;
                
                if (confirm('¿Crear cliente con los siguientes datos?\n\nNombre: ' + nombre + '\nTeléfono: ' + telefono + '\nCorreo: ' + correo)) {
                    crearClienteDesdeCita(reservaId, nombre, telefono, correo);
                }
            });
        });
    }
    
    // ===============================
    // 🔹 Funciones de acción (reutilizan las del asistant-controls.js)
    // ===============================
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
                cargarProximasCitas(); // Recargar tabla
            } else {
                alert('❌ Error: ' + (data.data.message || 'No se pudo confirmar la cita.'));
            }
        })
        .catch(err => {
            alert('❌ Error de conexión: ' + err.message);
        });
    }
    
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
                let mensaje = '✅ Cita cancelada correctamente.';
                
                if (data.data.calendar_deleted) {
                    mensaje += '\n🗓️ El evento también fue eliminado de Google Calendar.';
                }
                
                alert(mensaje);
                cargarProximasCitas(); // Recargar tabla
            } else {
                alert('❌ Error: ' + (data.data?.message || 'No se pudo cancelar la cita.'));
            }
        })
        .catch(err => {
            alert('❌ Error de conexión: ' + err.message);
        });
    }
    
    function crearClienteDesdeCita(reservaId, nombre, telefono, correo) {
        const formData = new FormData();
        formData.append('action', 'aa_crear_cliente_desde_cita');
        formData.append('reserva_id', reservaId);
        formData.append('nombre', nombre);
        formData.append('telefono', telefono);
        formData.append('correo', correo);
        formData.append('_wpnonce', aa_asistant_vars.nonce_crear_cliente_desde_cita);
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.data.message);
                cargarProximasCitas(); // Recargar tabla
            } else {
                alert('❌ Error: ' + (data.data.message || 'No se pudo crear el cliente.'));
            }
        })
        .catch(err => {
            alert('❌ Error de conexión: ' + err.message);
        });
    }
    
    // ===============================
    // 🔹 Renderizar paginación
    // ===============================
    function renderizarPaginacion(actual, total, container) {
        if (!container || total <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let html = '<div class="aa-paginacion-botones">';
        
        // Botón anterior
        if (actual > 1) {
            html += '<button class="aa-btn-paginacion" data-pagina="' + (actual - 1) + '">« Anterior</button>';
        }
        
        // Números de página
        for (let i = 1; i <= total; i++) {
            if (i === actual) {
                html += '<span class="aa-pagina-actual">' + i + '</span>';
            } else if (i === 1 || i === total || (i >= actual - 2 && i <= actual + 2)) {
                html += '<button class="aa-btn-paginacion" data-pagina="' + i + '">' + i + '</button>';
            } else if (i === actual - 3 || i === actual + 3) {
                html += '<span>...</span>';
            }
        }
        
        // Botón siguiente
        if (actual < total) {
            html += '<button class="aa-btn-paginacion" data-pagina="' + (actual + 1) + '">Siguiente »</button>';
        }
        
        html += '</div>';
        container.innerHTML = html;
        
        // Eventos de paginación
        container.querySelectorAll('.aa-btn-paginacion').forEach(btn => {
            btn.addEventListener('click', function() {
                paginaActual = parseInt(this.dataset.pagina);
                cargarProximasCitas();
            });
        });
    }
    
    // ===============================
    // 🔹 Utilidad: Escapar HTML
    // ===============================
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
});