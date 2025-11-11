document.addEventListener('DOMContentLoaded', function() {
    let paginaActual = 1;
    
    // 🔹 Cargar historial al inicio
    cargarHistorial();
    
    // 🔹 Botón buscar
    document.getElementById('aa-btn-buscar-historial')?.addEventListener('click', function() {
        paginaActual = 1;
        cargarHistorial();
    });
    
    // 🔹 Botón limpiar
    document.getElementById('aa-btn-limpiar-historial')?.addEventListener('click', function() {
        document.getElementById('aa-buscar-historial').value = '';
        document.getElementById('aa-ordenar-historial').value = 'fecha_desc';
        paginaActual = 1;
        cargarHistorial();
    });
    
    // 🔹 Cambio en ordenamiento
    document.getElementById('aa-ordenar-historial')?.addEventListener('change', function() {
        paginaActual = 1;
        cargarHistorial();
    });
    
    // 🔹 Enter en el buscador
    document.getElementById('aa-buscar-historial')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            paginaActual = 1;
            cargarHistorial();
        }
    });
    
    // ===============================
    // 🔹 Función para cargar historial
    // ===============================
    function cargarHistorial() {
        const container = document.getElementById('aa-historial-container');
        const paginacion = document.getElementById('aa-historial-paginacion');
        const buscar = document.getElementById('aa-buscar-historial')?.value || '';
        const ordenar = document.getElementById('aa-ordenar-historial')?.value || 'fecha_desc';
        
        if (!container) return;
        
        container.innerHTML = '<p style="text-align: center; color: #999;">⏳ Cargando...</p>';
        
        const formData = new FormData();
        formData.append('action', 'aa_get_historial_citas');
        formData.append('buscar', buscar);
        formData.append('ordenar', ordenar);
        formData.append('pagina', paginaActual);
        formData.append('_wpnonce', aa_historial_vars.nonce);
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarHistorial(data.data.citas, container);
                renderizarPaginacion(data.data.pagina_actual, data.data.total_paginas, paginacion);
            } else {
                container.innerHTML = '<p style="color: #e74c3c;">❌ Error: ' + (data.data?.message || 'No se pudo cargar el historial.') + '</p>';
            }
        })
        .catch(err => {
            console.error('Error al cargar historial:', err);
            container.innerHTML = '<p style="color: #e74c3c;">❌ Error de conexión.</p>';
        });
    }
    
    // ===============================
    // 🔹 Renderizar tabla de citas
    // ===============================
    function renderizarHistorial(citas, container) {
        if (!citas || citas.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #999;">📭 No se encontraron citas en el historial.</p>';
            return;
        }
        
        // 🔹 Wrapper para scroll horizontal
        let html = '<div class="aa-table-wrapper">';
        html += '<table class="widefat aa-table-scroll">';
        html += '<thead><tr><th>Cliente</th><th>Teléfono</th><th>Servicio</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead>';
        html += '<tbody>';
        
        citas.forEach(cita => {
            const estadoClass = 'aa-estado-' + cita.estado.toLowerCase().replace(/ó/g, 'o').replace(/ /g, '-');
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
                case 'asistió':
                    estadoTexto = 'Asistió';
                    break;
                case 'no asistió':
                    estadoTexto = 'No asistió';
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
            
            // 🔹 Mostrar botones solo si NO es "asistió", "no asistió" NI "cancelled"
            if (cita.estado !== 'asistió' && cita.estado !== 'no asistió' && cita.estado !== 'cancelled') {
                html += '<button class="aa-btn-asistio" data-id="' + cita.id + '">✓ Asistió</button> ';
                html += '<button class="aa-btn-no-asistio" data-id="' + cita.id + '">✕ No asistió</button>';
            } else if (cita.estado === 'asistió') {
                html += '<span style="color: #27ae60; font-weight: bold;">✓ Asistió</span>';
            } else if (cita.estado === 'no asistió') {
                html += '<span style="color: #e74c3c; font-weight: bold;">✕ No asistió</span>';
            } else if (cita.estado === 'cancelled') {
                html += '<span style="color: #95a5a6; font-style: italic;">—</span>';
            }
            
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>'; // Cerrar wrapper
        container.innerHTML = html;
        
        // 🔹 Asignar eventos a los botones "Asistió"
        container.querySelectorAll('.aa-btn-asistio').forEach(btn => {
            btn.addEventListener('click', function() {
                const citaId = this.dataset.id;
                marcarAsistencia(citaId);
            });
        });
        
        // 🔹 Asignar eventos a los botones "No asistió"
        container.querySelectorAll('.aa-btn-no-asistio').forEach(btn => {
            btn.addEventListener('click', function() {
                const citaId = this.dataset.id;
                marcarNoAsistencia(citaId);
            });
        });
    }
    
    // ===============================
    // 🔹 Función para marcar asistencia
    // ===============================
    function marcarAsistencia(citaId) {
        if (!confirm('¿Confirmar que el cliente asistió a esta cita?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'aa_marcar_asistencia');
        formData.append('cita_id', citaId);
        formData.append('_wpnonce', aa_historial_vars.nonce);
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.data.message);
                cargarHistorial(); // Recargar tabla
            } else {
                alert('❌ Error: ' + (data.data?.message || 'No se pudo registrar la asistencia.'));
            }
        })
        .catch(err => {
            console.error('Error al marcar asistencia:', err);
            alert('❌ Error de conexión.');
        });
    }
    
    // ===============================
    // 🔹 Función para marcar NO asistencia
    // ===============================
    function marcarNoAsistencia(citaId) {
        if (!confirm('¿Confirmar que el cliente NO asistió a esta cita?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'aa_marcar_no_asistencia');
        formData.append('cita_id', citaId);
        formData.append('_wpnonce', aa_historial_vars.nonce);
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.data.message);
                cargarHistorial(); // Recargar tabla
            } else {
                alert('❌ Error: ' + (data.data?.message || 'No se pudo registrar la no asistencia.'));
            }
        })
        .catch(err => {
            console.error('Error al marcar no asistencia:', err);
            alert('❌ Error de conexión.');
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
                cargarHistorial();
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
        return text.replace(/[&<>"']/g, m => map[m]);
    }
});