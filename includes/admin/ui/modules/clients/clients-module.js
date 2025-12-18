/**
 * Clients Module - Module-specific JavaScript
 */

(function() {
    'use strict';

    /**
     * Renderizar grid de tarjetas de clientes
     */
    function renderClientsGrid() {
        // Validar que existan los datos
        if (!window.AA_CLIENTS_DATA || !Array.isArray(window.AA_CLIENTS_DATA.clients)) {
            console.warn('AA_CLIENTS_DATA no disponible o formato inválido');
            return;
        }

        // Obtener el contenedor
        const container = document.getElementById('aa-clients-grid');
        if (!container) {
            console.warn('Contenedor #aa-clients-grid no encontrado');
            return;
        }

        const clients = window.AA_CLIENTS_DATA.clients;

        // Limpiar contenedor
        container.innerHTML = '';

        // Iterar sobre los clientes (máximo 10)
        clients.slice(0, 10).forEach(function(cliente) {
            // Crear tarjeta
            const card = document.createElement('div');
            card.className = 'aa-appointment-card';

            // Header con nombre del cliente
            const header = document.createElement('div');
            header.className = 'aa-appointment-header';
            header.textContent = cliente.nombre || 'Sin nombre';

            // Body con información del cliente
            const body = document.createElement('div');
            body.className = 'aa-appointment-body';

            // Teléfono
            const telefono = document.createElement('div');
            telefono.textContent = 'Teléfono: ' + (cliente.telefono || 'N/A');
            body.appendChild(telefono);

            // Correo
            const correo = document.createElement('div');
            correo.textContent = 'Correo: ' + (cliente.correo || 'N/A');
            body.appendChild(correo);

            // Fecha de registro
            const fechaRegistro = document.createElement('div');
            fechaRegistro.textContent = 'Fecha de registro: ' + (cliente.created_at || 'N/A');
            body.appendChild(fechaRegistro);

            // Total de citas
            const totalCitas = document.createElement('div');
            totalCitas.textContent = 'Total de citas: ' + (cliente.total_citas || 0);
            body.appendChild(totalCitas);

            // Ensamblar tarjeta
            card.appendChild(header);
            card.appendChild(body);

            // Insertar en el contenedor
            container.appendChild(card);
        });
    }

    // Escuchar DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Clients module loaded');
        renderClientsGrid();
    });

})();

