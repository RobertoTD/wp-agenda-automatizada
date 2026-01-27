/**
 * WhatsAppService - Servicio para interacción con WhatsApp
 * 
 * Proporciona funciones para normalizar números de teléfono,
 * construir URLs de WhatsApp y abrir chats.
 * 
 * @exports window.WhatsAppService
 */
(function() {
  'use strict';

  /**
   * Normaliza un número de teléfono para uso con WhatsApp.
   * - Elimina todos los caracteres no numéricos
   * - Si quedan 10 dígitos, asume México y prefija con código de país
   * - Si ya tiene prefijo 52 o 521, lo deja intacto
   * 
   * @param {string} raw - Número de teléfono en cualquier formato
   * @param {string} [defaultCountry='52'] - Código de país por defecto (México)
   * @returns {string} Número normalizado (solo dígitos con código de país)
   */
  function normalizePhone(raw, defaultCountry = '52') {
    if (!raw || typeof raw !== 'string') {
      return '';
    }

    // Eliminar todo lo que no sea dígito
    const digits = raw.replace(/\D/g, '');

    if (!digits) {
      return '';
    }

    // Si tiene exactamente 10 dígitos, asumir número nacional mexicano
    if (digits.length === 10) {
      return defaultCountry + digits;
    }

    // Si tiene 12 dígitos y empieza con 52, ya está normalizado (52 + 10 dígitos)
    if (digits.length === 12 && digits.startsWith('52')) {
      return digits;
    }

    // Si tiene 13 dígitos y empieza con 521 (formato antiguo móvil MX), convertir a 52
    if (digits.length === 13 && digits.startsWith('521')) {
      return '52' + digits.slice(3);
    }

    // Cualquier otro caso, retornar los dígitos tal cual
    return digits;
  }

  /**
   * Construye la URL de WhatsApp para abrir un chat.
   * 
   * @param {Object} params - Parámetros para la URL
   * @param {string} params.phone - Número de teléfono (ya normalizado o no)
   * @param {string} [params.message=''] - Mensaje precargado
   * @returns {string} URL completa de wa.me
   */
  function buildUrl({ phone, message = '' }) {
    const normalizedPhone = normalizePhone(phone);
    
    if (!normalizedPhone) {
      console.warn('[WhatsAppService] No se pudo construir URL: número inválido');
      return '';
    }

    let url = `https://wa.me/${normalizedPhone}`;
    
    if (message) {
      url += `?text=${encodeURIComponent(message)}`;
    }

    return url;
  }

  /**
   * Abre un chat de WhatsApp en una nueva pestaña o en la misma ventana.
   * 
   * @param {Object} params - Parámetros para abrir el chat
   * @param {string} params.phone - Número de teléfono
   * @param {string} [params.message=''] - Mensaje precargado
   * @param {boolean} [params.newTab=true] - Si true, abre en nueva pestaña
   * @returns {boolean} true si se abrió correctamente, false si hubo error
   */
  function openChat({ phone, message = '', newTab = true }) {
    const url = buildUrl({ phone, message });

    if (!url) {
      console.error('[WhatsAppService] No se puede abrir chat: URL inválida');
      return false;
    }

    if (newTab) {
      window.open(url, '_blank', 'noopener,noreferrer');
    } else {
      window.location.href = url;
    }

    return true;
  }

  // Exportar a window
  window.WhatsAppService = {
    normalizePhone,
    buildUrl,
    openChat
  };

  console.log('✅ WhatsAppService cargado');
})();
