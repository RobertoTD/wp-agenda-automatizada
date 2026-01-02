// ==============================
// 🔹 Admin Reservation Controller
// ==============================

(function() {
  'use strict';

  /**
   * Inicializar controlador de reservaciones admin
   * @param {Object} config - Configuración del controlador
   */
  function initAdminReservationController(config) {
    const {
      btnToggle,
      formNuevaCita,
      btnCancelar,
      form
    } = config;

    // En modal, btnToggle puede ser null
    if (!formNuevaCita || !form) {
      console.error('❌ No se encontraron los elementos del formulario de admin');
      return;
    }

    // Toggle formulario (solo si btnToggle existe)
    if (btnToggle) {
      btnToggle.addEventListener('click', function() {
        formNuevaCita.classList.toggle('visible');
        if (formNuevaCita.classList.contains('visible')) {
          btnToggle.textContent = '− Ocultar formulario';
        } else {
          btnToggle.textContent = '+ Crear nueva cita';
        }
      });
    }

    // Cancelar formulario
    if (btnCancelar) {
      btnCancelar.addEventListener('click', function() {
        if (btnToggle) {
          formNuevaCita.classList.remove('visible');
          btnToggle.textContent = '+ Crear nueva cita';
        }
        form.reset();
        const slotContainer = document.getElementById('slot-container-admin');
        if (slotContainer) {
          slotContainer.innerHTML = '';
        }
      });
    }

    // ==============================
    // 🔹 Envío del formulario (USANDO ReservationService)
    // ==============================
    form.addEventListener('submit', async function(e) {
      e.preventDefault();

      const clienteSelect = document.getElementById('cita-cliente');
      const slotSelector = document.getElementById('slot-selector-admin');
      const selectedSlotISO = slotSelector ? slotSelector.value : null;

      if (!selectedSlotISO) {
        alert('❌ Por favor, selecciona una fecha y hora válidas.');
        return;
      }

      const clienteOption = clienteSelect.options[clienteSelect.selectedIndex];

      // Leer assignment_id si existe (opcional, para reservas basadas en assignments)
      const assignmentIdInput = document.getElementById('assignment-id');
      const assignmentId = assignmentIdInput ? assignmentIdInput.value : null;

      const datos = {
        servicio: document.getElementById('cita-servicio').value,
        fecha: selectedSlotISO,
        nombre: clienteOption.dataset.nombre,
        telefono: clienteOption.dataset.telefono,
        correo: clienteOption.dataset.correo,
        duracion: parseInt(document.getElementById('cita-duracion').value, 10) || 60,
        nonce: window.aa_asistant_vars.nonce_crear_cita || ''
      };

      // Agregar assignment_id solo si existe (opcional)
      if (assignmentId) {
        datos.assignment_id = parseInt(assignmentId, 10);
        console.log('🆔 Assignment ID incluido en reserva:', datos.assignment_id);
      }

      try {
        // 🔹 PASO 1: Guardar la reserva usando ReservationService
        const data = await window.ReservationService.saveReservation(datos);

        // 🔹 PASO 2: Añadir ID de la reserva
        if (data.data && data.data.id) {
          datos.id_reserva = data.data.id;
          console.log('🆔 ID de reserva asignado:', datos.id_reserva);
        } else if (data.id) {
          datos.id_reserva = data.id;
          console.warn('⚠️ ID de reserva recibido en formato alternativo:', datos.id_reserva);
        } else {
          console.warn('⚠️ No se recibió ID de reserva en la respuesta del backend.');
        }

        // 🔹 PASO 3: Enviar confirmación usando ReservationService (sin bloquear)
        window.ReservationService.sendConfirmation(datos).catch(emailError => {
          console.warn('⚠️ Error al enviar correo (no crítico):', emailError);
        });

        // 🔹 PASO 4: Mostrar mensaje de éxito
        // NOTA: En modal, el cierre se maneja en reservation.js
        alert('✅ Cita agendada correctamente. Se ha enviado correo de confirmación.');
        
        // Solo recargar si NO estamos en modal (legacy behavior)
        if (btnToggle) {
          location.reload();
        }

      } catch (err) {
        console.error('❌ Error al agendar:', err);
        alert('❌ Error al agendar: ' + err.message);
      }
    });

    console.log('✅ AdminReservationController inicializado');
  }

  // ==============================
  // 🔹 Exponer en window
  // ==============================
  window.AdminReservationController = {
    init: initAdminReservationController
  };

  console.log('✅ AdminReservationController cargado y expuesto globalmente');
})();
