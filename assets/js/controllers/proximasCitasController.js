/**
 * Controlador: Próximas Citas
 * 
 * Responsable de:
 * - Gestión de estado (paginación, filtros)
 * - Coordinación entre UI y servicios
 * - Orquestación del flujo de carga
 * 
 * NO contiene llamadas AJAX directas ni renderizado de UI.
 */

window.ProximasCitasController = (function() {
    'use strict';
    
    let paginaActual = 1;
    let containerElement = null;
    let paginacionElement = null;
    
    /**
     * Inicializar controlador
     */
    function init() {
        containerElement = document.getElementById('aa-proximas-container');
        paginacionElement = document.getElementById('aa-proximas-paginacion');
        
        if (!containerElement) {
            console.warn('⚠️ Container de próximas citas no encontrado');
            return;
        }
        
        // Inicializar AdminConfirmController con callback de recarga
        if (window.AdminConfirmController) {
            window.AdminConfirmController.init(cargarProximasCitas);
        }
        
        // Cargar próximas citas al inicio
        cargarProximasCitas();
        
        // Configurar event listeners
        setupEventListeners();
    }
    
    /**
     * Configurar event listeners para filtros
     */
    function setupEventListeners() {
        // Botón buscar
        const btnBuscar = document.getElementById('aa-btn-buscar-proximas');
        if (btnBuscar) {
            btnBuscar.addEventListener('click', function() {
                paginaActual = 1;
                cargarProximasCitas();
            });
        }
        
        // Botón limpiar
        const btnLimpiar = document.getElementById('aa-btn-limpiar-proximas');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', function() {
                const inputBuscar = document.getElementById('aa-buscar-proximas');
                const selectOrdenar = document.getElementById('aa-ordenar-proximas');
                
                if (inputBuscar) inputBuscar.value = '';
                if (selectOrdenar) selectOrdenar.value = 'fecha_asc';
                
                paginaActual = 1;
                cargarProximasCitas();
            });
        }
        
        // Cambio en ordenamiento
        const selectOrdenar = document.getElementById('aa-ordenar-proximas');
        if (selectOrdenar) {
            selectOrdenar.addEventListener('change', function() {
                paginaActual = 1;
                cargarProximasCitas();
            });
        }
        
        // Enter en el buscador
        const inputBuscar = document.getElementById('aa-buscar-proximas');
        if (inputBuscar) {
            inputBuscar.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    paginaActual = 1;
                    cargarProximasCitas();
                }
            });
        }
    }
    
    /**
     * Cargar próximas citas desde el servidor
     */
    function cargarProximasCitas() {
        if (!containerElement) return;
        
        const inputBuscar = document.getElementById('aa-buscar-proximas');
        const selectOrdenar = document.getElementById('aa-ordenar-proximas');
        
        const buscar = inputBuscar ? inputBuscar.value : '';
        const ordenar = selectOrdenar ? selectOrdenar.value : 'fecha_asc';
        
        // Mostrar estado de carga (UI)
        if (window.ProximasCitasUI) {
            window.ProximasCitasUI.mostrarCargando(containerElement);
        } else {
            containerElement.innerHTML = '<p style="text-align: center; color: #999;">⏳ Cargando...</p>';
        }
        
        // Realizar llamada AJAX
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
                renderizarResultados(data.data);
            } else {
                mostrarError(data.data?.message || 'No se pudo cargar las próximas citas.');
            }
        })
        .catch(err => {
            console.error('Error al cargar próximas citas:', err);
            mostrarError('Error de conexión.');
        });
    }
    
    /**
     * Renderizar resultados usando el módulo UI
     * @param {Object} data - Datos de la respuesta AJAX
     */
    function renderizarResultados(data) {
        if (!window.ProximasCitasUI) {
            console.error('❌ Módulo ProximasCitasUI no cargado');
            if (containerElement) {
                containerElement.innerHTML = '<p style="color: #e74c3c;">❌ Error: Módulo UI no disponible.</p>';
            }
            return;
        }
        
        if (!window.AdminConfirmController) {
            console.error('❌ Módulo AdminConfirmController no cargado');
            if (containerElement) {
                containerElement.innerHTML = '<p style="color: #e74c3c;">❌ Error: Controlador de confirmación no disponible.</p>';
            }
            return;
        }
        
        // Renderizar tabla
        window.ProximasCitasUI.renderizarProximasCitas(data.citas, containerElement, {
            onConfirmar: window.AdminConfirmController.onConfirmar,
            onCancelar: window.AdminConfirmController.onCancelar,
            onCrearCliente: window.AdminConfirmController.onCrearCliente
        });
        
        // Renderizar paginación
        if (paginacionElement) {
            window.ProximasCitasUI.renderizarPaginacion(
                data.pagina_actual,
                data.total_paginas,
                paginacionElement,
                function(nuevaPagina) {
                    paginaActual = nuevaPagina;
                    cargarProximasCitas();
                }
            );
        }
    }
    
    /**
     * Mostrar error usando el módulo UI
     * @param {string} mensaje - Mensaje de error
     */
    function mostrarError(mensaje) {
        if (window.ProximasCitasUI && containerElement) {
            window.ProximasCitasUI.mostrarError(containerElement, mensaje);
        } else if (containerElement) {
            containerElement.innerHTML = '<p style="color: #e74c3c;">❌ Error: ' + mensaje + '</p>';
        }
    }
    
    // ===============================
    // 🔹 API Pública
    // ===============================
    return {
        init
    };
})();

// ===============================
// 🔹 Auto-inicialización
// ===============================
document.addEventListener('DOMContentLoaded', function() {
    if (window.ProximasCitasController) {
        window.ProximasCitasController.init();
    }
});
