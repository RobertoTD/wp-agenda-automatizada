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
      btnCancelar,
      form
    } = config;

    if (!form) {
      console.error('❌ No se encontró el formulario de admin');
      return;
    }

    // Cancelar formulario
    if (btnCancelar) {
      btnCancelar.addEventListener('click', function() {
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

      // 🔹 Leer checkbox de auto-confirmación al inicio del submit
      const autoConfirmEl = document.getElementById('aa-reservation-auto-confirm');
      const autoConfirm = !!(autoConfirmEl && autoConfirmEl.checked && autoConfirmEl.value === 'confirmed');

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
        // Variable para almacenar mensaje de éxito (se mostrará al final del flujo)
        let successMsg = null;

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

        // 🔹 PASO 3: Manejar auto-confirmación o envío de correo normal
        if (autoConfirm) {
          // Auto-confirmación activada: confirmar la cita inmediatamente
          if (!datos.id_reserva) {
            console.warn('⚠️ No se puede confirmar: ID de reserva no disponible');
            successMsg = '✅ Cita agendada correctamente, pero no se pudo confirmar automáticamente (ID no disponible).';
          } else if (!window.ConfirmService || typeof window.ConfirmService.confirmar !== 'function') {
            alert('❌ Error: ConfirmService no disponible. La cita se creó pero no se pudo confirmar.');
            console.error('❌ ConfirmService no disponible o método confirmar no existe');
          } else {
            try {
              const confirmResp = await window.ConfirmService.confirmar(datos.id_reserva);
              
              if (confirmResp.success) {
                successMsg = '✅ Cita agendada y confirmada.';
                console.log('✅ Cita confirmada automáticamente:', confirmResp);
              } else {
                alert('⚠️ Cita agendada pero NO se pudo confirmar: ' + (confirmResp.data?.message || 'Error desconocido'));
                console.warn('⚠️ Error al confirmar cita:', confirmResp);
              }
            } catch (confirmErr) {
              alert('⚠️ Cita agendada pero NO se pudo confirmar: ' + confirmErr.message);
              console.error('❌ Error al confirmar cita:', confirmErr);
            }
          }
          
          // NO llamar sendConfirmation cuando auto-confirm está activo
          // (el flujo de confirmar ya maneja la confirmación)
        } else {
          // Comportamiento normal: enviar correo de confirmación
          window.ReservationService.sendConfirmation(datos).catch(emailError => {
            console.warn('⚠️ Error al enviar correo (no crítico):', emailError);
          });

          // Guardar mensaje de éxito para mostrar al final
          successMsg = '✅ Cita agendada correctamente. Se ha enviado correo de confirmación.';
        }
        
        // 🔹 PASO 4: Si estamos en modal, refrescar disponibilidad local y recalcular slots
        const isModal = !!document.getElementById('form-crear-cita-admin');
        
        if (isModal) {
          try {
            // Refrescar disponibilidad local desde BD vía AJAX
            // Usar window.ajaxurl (WordPress lo define en admin) o URL directa como fallback
            const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
            const formData = new FormData();
            formData.append('action', 'aa_get_local_availability');
            
            const refreshResponse = await fetch(ajaxurl, {
              method: 'POST',
              body: formData
            });
            
            const refreshResult = await refreshResponse.json();
            
            if (refreshResult.success && refreshResult.data) {
              // Actualizar window.aa_local_availability con datos frescos desde BD
              window.aa_local_availability = refreshResult.data;
              
              // Re-disparar recálculo del modal usando el evento existente
              // Construir selectedDate como Date del día del slot seleccionado
              if (selectedSlotISO) {
                const selectedDate = new Date(selectedSlotISO);
                if (!isNaN(selectedDate.getTime())) {
                  document.dispatchEvent(new CustomEvent('aa:admin:date-selected', {
                    detail: { selectedDate }
                  }));
                }
              }
            } else {
              console.warn('⚠️ No se pudo refrescar disponibilidad local:', refreshResult);
            }
          } catch (refreshErr) {
            console.warn('⚠️ Error al refrescar disponibilidad local:', refreshErr);
          }
        }
        
        // 🔹 PASO 5: Recargar calendario del día actual sin recargar la página
        // Usar la API pública de AdminCalendarController para mantener separación de responsabilidades
        if (window.AdminCalendarController && typeof window.AdminCalendarController.recargar === 'function') {
          window.AdminCalendarController.recargar();
          console.log('✅ Calendario recargado después de crear reserva');
        } else {
          console.warn('⚠️ AdminCalendarController.recargar no disponible, el calendario no se actualizará automáticamente');
        }
        
        // 🔹 PASO 6: Mostrar mensaje de éxito al final (después de todas las actualizaciones)
        // Esto permite que reservation.js intercepte el alert y cierre el modal con el estado ya actualizado
        if (successMsg) {
          alert(successMsg);
        }
        
        // NOTA: El cierre del modal se maneja en reservation.js

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
