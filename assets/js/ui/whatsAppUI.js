/**
 * WhatsAppUI - Componentes de UI para WhatsApp
 * 
 * Renderiza el botón flotante de WhatsApp con estilos profesionales
 * y animaciones suaves.
 * 
 * @exports window.WhatsAppUI
 */
(function() {
  'use strict';

  // ID único para evitar duplicados
  const BUTTON_ID = 'aa-wa-floating-btn';
  const STYLES_ID = 'aa-wa-styles';

  /**
   * Inyecta los estilos CSS necesarios para el botón flotante.
   * Solo se inyectan una vez.
   */
  function injectStyles() {
    if (document.getElementById(STYLES_ID)) {
      return; // Ya están inyectados
    }

    const css = `
      /* WhatsApp Floating Button - Agenda Automatizada */
      .aa-wa-floating-container {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      }

      .aa-wa-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        border: none;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4),
                    0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: visible;
      }

      .aa-wa-btn:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 20px rgba(37, 211, 102, 0.5),
                    0 4px 8px rgba(0, 0, 0, 0.15);
      }

      .aa-wa-btn:active {
        transform: scale(0.95);
      }

      .aa-wa-btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.3),
                    0 4px 12px rgba(37, 211, 102, 0.4);
      }

      /* Icono SVG */
      .aa-wa-btn svg {
        width: 32px;
        height: 32px;
        fill: #ffffff;
        transition: transform 0.2s ease;
      }

      .aa-wa-btn:hover svg {
        transform: rotate(-8deg);
      }

      /* Pulse animation - solo activa con clase .aa-wa-animating */
      .aa-wa-btn::before {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        background: inherit;
        opacity: 0;
      }

      .aa-wa-btn.aa-wa-animating::before {
        animation: aa-wa-pulse 2s ease-out infinite;
      }

      @keyframes aa-wa-pulse {
        0% {
          transform: scale(1);
          opacity: 0.4;
        }
        100% {
          transform: scale(1.5);
          opacity: 0;
        }
      }

      /* Tooltip */
      .aa-wa-tooltip {
        position: absolute;
        right: 72px;
        top: 50%;
        transform: translateY(-50%);
        background: #333;
        color: #fff;
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.25s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
      }

      .aa-wa-tooltip::after {
        content: '';
        position: absolute;
        right: -6px;
        top: 50%;
        transform: translateY(-50%);
        border: 6px solid transparent;
        border-left-color: #333;
      }

      .aa-wa-btn:hover + .aa-wa-tooltip,
      .aa-wa-btn:focus + .aa-wa-tooltip {
        opacity: 1;
        visibility: visible;
      }

      /* Responsive: ajustar posición en móviles */
      @media (max-width: 480px) {
        .aa-wa-floating-container {
          bottom: 16px;
          right: 16px;
        }

        .aa-wa-btn {
          width: 54px;
          height: 54px;
        }

        .aa-wa-btn svg {
          width: 28px;
          height: 28px;
        }

        .aa-wa-tooltip {
          display: none;
        }
      }
    `;

    const styleEl = document.createElement('style');
    styleEl.id = STYLES_ID;
    styleEl.textContent = css;
    document.head.appendChild(styleEl);
  }

  /**
   * Crea el SVG del icono de WhatsApp.
   * @returns {string} SVG como string
   */
  function getWhatsAppIcon() {
    return `
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>
    `;
  }

  /**
   * Renderiza el botón flotante de WhatsApp.
   * 
   * @param {Object} options - Opciones de configuración
   * @param {Function} options.onClick - Callback al hacer clic en el botón
   * @param {string} [options.tooltip='¿Necesitas ayuda? Escríbenos'] - Texto del tooltip
   * @returns {HTMLElement|null} El elemento del botón o null si ya existe
   */
  function renderFloatingButton({ onClick, tooltip = '¿Necesitas ayuda? Escríbenos' }) {
    // Evitar duplicados
    if (document.getElementById(BUTTON_ID)) {
      console.warn('[WhatsAppUI] Botón flotante ya existe');
      return null;
    }

    // Inyectar estilos
    injectStyles();

    // Crear contenedor
    const container = document.createElement('div');
    container.className = 'aa-wa-floating-container';
    container.id = BUTTON_ID;

    // Crear botón
    const button = document.createElement('button');
    button.className = 'aa-wa-btn aa-wa-animating';
    button.setAttribute('type', 'button');
    button.setAttribute('aria-label', 'Contactar por WhatsApp');
    button.innerHTML = getWhatsAppIcon();

    // Detener animación después de 5 segundos
    setTimeout(function() {
      button.classList.remove('aa-wa-animating');
    }, 5000);

    // Manejar click
    if (typeof onClick === 'function') {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        onClick();
      });
    }

    // Crear tooltip
    const tooltipEl = document.createElement('span');
    tooltipEl.className = 'aa-wa-tooltip';
    tooltipEl.textContent = tooltip;

    // Ensamblar
    container.appendChild(button);
    container.appendChild(tooltipEl);
    document.body.appendChild(container);

    console.log('✅ WhatsApp floating button renderizado');
    return container;
  }

  /**
   * Remueve el botón flotante del DOM.
   */
  function removeFloatingButton() {
    const existing = document.getElementById(BUTTON_ID);
    if (existing) {
      existing.remove();
      console.log('🗑️ WhatsApp floating button removido');
    }
  }

  // Exportar a window
  window.WhatsAppUI = {
    renderFloatingButton,
    removeFloatingButton
  };

  console.log('✅ WhatsAppUI cargado');
})();
