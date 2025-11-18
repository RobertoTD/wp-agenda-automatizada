/**
 * Controlador: Próximas Citas
 * 
 * Responsable de:
 * - Lógica de negocio
 * - Llamadas AJAX
 * - Gestión de estado (paginación, filtros)
 * - Coordinación con módulo UI
 */

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
        
        // 🔹 Mostrar estado de carga (UI)
        if (window.ProximasCitasUI) {
            window.ProximasCitasUI.mostrarCargando(container);
        } else {
            container.innerHTML = '<p style="text-align: center; color: #999;">⏳ Cargando...</p>';
        }
        
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
                // 🔹 Renderizar usando módulo UI
                if (window.ProximasCitasUI) {
                    window.ProximasCitasUI.renderizarProximasCitas(data.data.citas, container, {
                        onConfirmar: confirmarCita,
                        onCancelar: cancelarCita,
                        onCrearCliente: crearClienteDesdeCita
                    });
                    
                    window.ProximasCitasUI.renderizarPaginacion(
                        data.data.pagina_actual,
                        data.data.total_paginas,
                        paginacion,
                        function(nuevaPagina) {
                            paginaActual = nuevaPagina;
                            cargarProximasCitas();
                        }
                    );
                } else {
                    console.error('❌ Módulo ProximasCitasUI no cargado');
                    container.innerHTML = '<p style="color: #e74c3c;">❌ Error: Módulo UI no disponible.</p>';
                }
            } else {
                // 🔹 Mostrar error usando módulo UI
                const mensaje = data.data?.message || 'No se pudo cargar las próximas citas.';
                if (window.ProximasCitasUI) {
                    window.ProximasCitasUI.mostrarError(container, mensaje);
                } else {
                    container.innerHTML = '<p style="color: #e74c3c;">❌ Error: ' + mensaje + '</p>';
                }
            }
        })
        .catch(err => {
            console.error('Error al cargar próximas citas:', err);
            if (window.ProximasCitasUI) {
                window.ProximasCitasUI.mostrarError(container, 'Error de conexión.');
            } else {
                container.innerHTML = '<p style="color: #e74c3c;">❌ Error de conexión.</p>';
            }
        });
    }
    
    // ===============================
    // 🔹 Funciones de acción (lógica de negocio)
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
});