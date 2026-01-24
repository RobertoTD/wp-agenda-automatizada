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
    
    // 🔹 Flag anti-double-submit (closure scope)
    let isSubmitting = false;
    
    form.addEventListener('submit', async function(e) {
      e.preventDefault();

      // 🔹 A. Anti-double-submit: ignorar si ya está en proceso
      if (isSubmitting) {
        console.log('⚠️ Submit ignorado: ya hay una reserva en proceso');
        return;
      }
      
      // 🔹 Obtener botón submit y guardar texto original
      const submitBtn = form.querySelector('.aa-btn-agendar-cita');
      const originalBtnText = submitBtn ? submitBtn.textContent : '';

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
        // 🔹 A. Activar flag y deshabilitar botón
        isSubmitting = true;
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Agendando…';
        }

        // 🔹 B. PASO 1: Guardar la reserva usando ReservationService (await)
        const data = await window.ReservationService.saveReservation(datos);

        // 🔹 B. PASO 2: Extraer ID de la reserva
        if (data.data && data.data.id) {
          datos.id_reserva = data.data.id;
          console.log('🆔 ID de reserva asignado:', datos.id_reserva);
        } else if (data.id) {
          datos.id_reserva = data.id;
          console.warn('⚠️ ID de reserva recibido en formato alternativo:', datos.id_reserva);
        } else {
          console.warn('⚠️ No se recibió ID de reserva en la respuesta del backend.');
        }

        // 🔹 C. PASO 3: Refrescar disponibilidad local (antes de cerrar modal)
        const isModal = !!document.getElementById('form-crear-cita-admin');
        
        if (isModal) {
          try {
            const ajaxurl = window.ajaxurl || '/wp-admin/admin-ajax.php';
            const formData = new FormData();
            formData.append('action', 'aa_get_local_availability');
            
            const refreshResponse = await fetch(ajaxurl, {
              method: 'POST',
              body: formData
            });
            
            const refreshResult = await refreshResponse.json();
            
            if (refreshResult.success && refreshResult.data) {
              window.aa_local_availability = refreshResult.data;
              
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

        // 🔹 C. PASO 4: Recargar calendario INMEDIATAMENTE
        if (window.AdminCalendarController && typeof window.AdminCalendarController.recargar === 'function') {
          window.AdminCalendarController.recargar();
          console.log('✅ Calendario recargado después de crear reserva');
        } else {
          console.warn('⚠️ AdminCalendarController.recargar no disponible');
        }
        
        // 🔹 C. PASO 5: Disparar evento para cerrar modal (flujo optimista)
        document.dispatchEvent(new CustomEvent('aa:reservation:created', {
          detail: { id: datos.id_reserva, autoConfirm }
        }));
        console.log('✅ Reserva guardada en BD, modal cerrado (flujo optimista)');

        // 🔹 D. PASO 6: Confirmación en SEGUNDO PLANO (sin await)
        if (autoConfirm) {
          if (datos.id_reserva && window.ConfirmService && typeof window.ConfirmService.confirmar === 'function') {
            // Llamar sin await - no bloquea UI
            window.ConfirmService.confirmar(datos.id_reserva)
              .then(function(confirmResp) {
                if (confirmResp.success) {
                  console.log('✅ Cita confirmada en background:', confirmResp);
                  // Recargar calendario para reflejar estado "confirmed"
                  if (window.AdminCalendarController && typeof window.AdminCalendarController.recargar === 'function') {
                    window.AdminCalendarController.recargar();
                    console.log('✅ Calendario recargado tras confirmación remota');
                  }
                } else {
                  console.warn('⚠️ Confirmación remota falló:', confirmResp.data?.message || 'Error desconocido');
                }
              })
              .catch(function(confirmErr) {
                console.error('❌ Error en confirmación remota (background):', confirmErr.message);
              });
          } else if (!datos.id_reserva) {
            console.warn('⚠️ No se puede confirmar en background: ID de reserva no disponible');
          } else {
            console.error('❌ ConfirmService no disponible para confirmación en background');
          }
        } else {
          // Comportamiento normal: enviar correo de confirmación (también sin await)
          window.ReservationService.sendConfirmation(datos).catch(function(emailError) {
            console.warn('⚠️ Error al enviar correo (no crítico):', emailError);
          });
        }
        
        // 🔹 E. NO hay alert de éxito - el modal ya se cerró por evento

      } catch (err) {
        // 🔹 F. Error crítico: restaurar botón y mostrar alert
        console.error('❌ Error al agendar:', err);
        alert('❌ Error al agendar: ' + err.message);
        
        // Restaurar botón en caso de error
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalBtnText;
        }
        isSubmitting = false;
      }
    });

    console.log('✅ AdminReservationController inicializado');
  }

  /**
   * Refrescar disponibilidad local (API pública para UI)
   * @param {Date} [selectedDate] - Fecha opcional para recalcular slots
   */
  function refreshLocalAvailability(selectedDate) {
    if (typeof window.LocalAvailabilityService !== 'undefined' && typeof window.LocalAvailabilityService.refresh === 'function') {
      return window.LocalAvailabilityService.refresh(selectedDate);
    } else {
      console.warn('⚠️ LocalAvailabilityService no disponible');
      return Promise.resolve(null);
    }
  }

  // ==============================
  // 🔹 Exponer en window
  // ==============================
  window.AdminReservationController = {
    init: initAdminReservationController,
    refreshLocalAvailability: refreshLocalAvailability
  };

  console.log('✅ AdminReservationController cargado y expuesto globalmente');
})();
