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
   * Inicializa WhatsApp para el iframe de admin (uso futuro).
   * 
   * @param {Object} options - Opciones de configuración
   * @param {string} options.phone - Número de teléfono
   * @param {string} [options.message=''] - Mensaje precargado
   * @returns {boolean} true si se inicializó correctamente
   */
  function initAdmin(options = {}) {
    console.log('🔄 Inicializando WhatsAppController admin...');

    if (typeof window.WhatsAppService === 'undefined') {
      console.error('[WhatsAppController] WhatsAppService no está disponible');
      return false;
    }

    // Para admin, el servicio queda disponible para uso directo
    // La UI se manejará de forma diferente (no botón flotante)
    console.log('✅ WhatsAppController admin inicializado (servicio disponible)');
    return true;
  }

  // Exportar a window
  window.WhatsAppController = {
    initFrontend,
    initAdmin
  };

  console.log('✅ WhatsAppController cargado');
})();
