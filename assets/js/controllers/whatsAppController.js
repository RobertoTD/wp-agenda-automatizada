/**
 * WhatsAppController - Controlador para funcionalidad de WhatsApp
 * 
 * Orquesta WhatsAppService y WhatsAppUI para el frontend.
 * Lee configuración desde window.AA_FRONTEND_DATA.
 * 
 * @exports window.WhatsAppController
 */
(function() {
  'use strict';

  /**
   * Inicializa el botón flotante de WhatsApp en el frontend.
   * Lee la configuración de window.AA_FRONTEND_DATA.
   * 
   * @returns {boolean} true si se inicializó correctamente, false si no
   */
  function initFrontend() {
    console.log('🔄 Inicializando WhatsAppController frontend...');

    // Validar dependencias
    if (typeof window.WhatsAppService === 'undefined') {
      console.error('[WhatsAppController] WhatsAppService no está disponible');
      return false;
    }

    if (typeof window.WhatsAppUI === 'undefined') {
      console.error('[WhatsAppController] WhatsAppUI no está disponible');
      return false;
    }

    // Leer configuración
    const config = window.AA_FRONTEND_DATA || {};
    const businessPhone = config.businessWhatsapp || '';
    const defaultMessage = config.defaultWhatsappMessage || 'Hola, quiero agendar una cita.';

    // Si no hay número configurado, no mostrar botón
    if (!businessPhone || !businessPhone.trim()) {
      console.warn('[WhatsAppController] No hay número de WhatsApp del negocio configurado. El botón flotante no se mostrará.');
      return false;
    }

    // Renderizar botón flotante
    window.WhatsAppUI.renderFloatingButton({
      tooltip: '¿Necesitas ayuda? Escríbenos',
      onClick: function() {
        console.log('📱 Abriendo WhatsApp con:', { phone: businessPhone, message: defaultMessage });
        window.WhatsAppService.openChat({
          phone: businessPhone,
          message: defaultMessage,
          newTab: true
        });
      }
    });

    console.log('✅ WhatsAppController frontend inicializado');
    return true;
  }

  /**
   * Construye mensaje de WhatsApp según el estado de la cita
   * @param {Object} data - Datos de la cita
   * @param {string} data.status - Estado de la cita
   * @param {string} data.name - Nombre del cliente
   * @param {string} data.service - Nombre del servicio
   * @param {string} data.datetime - Fecha/hora en formato MySQL
   * @param {string} businessName - Nombre del negocio
   * @returns {string} Mensaje formateado
   */
  function buildMessageForStatus(data, businessName) {
    const name = data.name ? ` ${data.name}` : '';
    const service = data.service || 'tu cita';
    
    // Usar DateUtils para formatear fecha
    let fecha = '';
    if (data.datetime && typeof window.DateUtils?.formatMySQLDateTimeEsMX === 'function') {
      fecha = window.DateUtils.formatMySQLDateTimeEsMX(data.datetime);
    }
    
    // Determinar si la cita es pasada usando DateUtils
    let isPast = false;
    if (data.datetime && typeof window.DateUtils?.isPastMysqlDateTime === 'function') {
      isPast = window.DateUtils.isPastMysqlDateTime(data.datetime);
    }
    
    // Aplicar regla: pending/confirmed pasados -> usar mensaje de "no asistió"
    let statusEffective = data.status || 'pending';
    if ((statusEffective === 'pending' || statusEffective === 'confirmed') && isPast) {
      statusEffective = 'no asistió';
    }
    
    const mensajes = {
      'pending': `Hola${name}, te escribo de ${businessName}. ¿Te gustaría confirmar tu cita de ${service}${fecha ? ' para el ' + fecha : ''}?`,
      'confirmed': `Hola${name}, te escribo de ${businessName} para recordarte tu cita de ${service}${fecha ? ' el ' + fecha : ''}. ¡Te esperamos!`,
      'cancelled': `Hola${name}, te escribo de ${businessName}. Vimos que tu cita de ${service} fue cancelada. ¿Te gustaría reagendar?`,
      'asistió': `Hola${name}, te escribo de ${businessName}. ¡Gracias por asistir a tu cita de ${service}! Esperamos verte pronto.`,
      'no asistió': `Hola${name}, te escribo de ${businessName}. Notamos que no pudiste asistir a tu cita de ${service}. ¿Te gustaría reagendar?`
    };
    
    return mensajes[statusEffective] || mensajes['pending'];
  }

  /**
   * Inicializa WhatsApp para el iframe de admin con delegación de eventos.
   * Un solo listener en document para todos los links .aa-whatsapp-link
   * 
   * @returns {boolean} true si se inicializó correctamente
   */
  function initAdmin() {
    // Guard: evitar doble inicialización
    if (window.WhatsAppController._adminInited) {
      console.log('[WhatsAppController] Admin ya inicializado, saltando...');
      return true;
    }
    window.WhatsAppController._adminInited = true;
    
    console.log('🔄 Inicializando WhatsAppController admin...');

    if (typeof window.WhatsAppService === 'undefined') {
      console.error('[WhatsAppController] WhatsAppService no está disponible');
      return false;
    }

    // Delegación de eventos: un solo listener para todos los links de WhatsApp
    document.addEventListener('click', function(e) {
      const el = e.target.closest('.aa-whatsapp-link');
      if (!el) return;
      
      e.preventDefault();
      e.stopPropagation();
      
      // Leer datos del elemento
      const phone = el.dataset.phone;
      const status = el.dataset.status || 'pending';
      const service = el.dataset.service || '';
      const datetime = el.dataset.datetime || '';
      const name = el.dataset.name || '';
      
      // Obtener nombre del negocio desde config global
      const businessName = window.AA_ADMIN_DATA?.businessName || 'nuestro negocio';
      
      // Construir mensaje según estado
      const message = buildMessageForStatus({ status, name, service, datetime }, businessName);
      
      console.log('📱 [WhatsAppController Admin] Abriendo chat:', { phone, status, message });
      
      // Abrir WhatsApp
      window.WhatsAppService.openChat({
        phone: phone,
        message: message,
        newTab: true
      });
    });

    console.log('✅ WhatsAppController admin inicializado (delegación activa)');
    return true;
  }

  // Exportar a window
  window.WhatsAppController = {
    initFrontend,
    initAdmin
  };

  console.log('✅ WhatsAppController cargado');
})();
