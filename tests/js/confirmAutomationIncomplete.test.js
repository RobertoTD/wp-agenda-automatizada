'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const mapperPath = path.join(__dirname, '../../assets/js/services/benefitNotificationMapper.js');
const mapper = require(mapperPath);

/**
 * Mirror of adminConfirmController.isConfirmAutomationIncomplete (conservador).
 * @param {{ success?: boolean, data?: Record<string, unknown> }} wpResponse
 * @returns {boolean}
 */
function isConfirmAutomationIncomplete(wpResponse) {
  const AUTOMATION_BACKEND_MESSAGE_MARKERS = [
    'backend',
    'notificar al backend',
    'no se pudo notificar',
    'no pudo notificar',
  ];

  if (!wpResponse || wpResponse.success !== true) {
    return false;
  }
  const payload = wpResponse.data;
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    return false;
  }
  if (payload.calendar_sync !== false) {
    return false;
  }
  const notices = payload.benefit_notices;
  if (notices && Array.isArray(notices) && notices.length > 0) {
    return false;
  }
  const msg = String(payload.message || '').toLowerCase();
  if (!msg) {
    return false;
  }
  for (let i = 0; i < AUTOMATION_BACKEND_MESSAGE_MARKERS.length; i++) {
    if (msg.indexOf(AUTOMATION_BACKEND_MESSAGE_MARKERS[i]) !== -1) {
      return true;
    }
  }
  return false;
}

describe('confirm automation incomplete vs local config payload', () => {
  it('payload sintetizado no_secret no activa Automatización incompleta', () => {
    const wpResponse = {
      success: true,
      data: {
        success: true,
        message:
          'Cita confirmada en WordPress, pero no se pudo notificar al backend: Client secret no configurado.',
        local_confirmed: true,
        calendar_sync: false,
        calendar_skipped: true,
        benefit_notices: [
          {
            resource: 'google_calendar_sync',
            operation: 'create_event',
            status: 'skipped',
            code: 'google_calendar_no_installation_id',
            reason: 'no_installation_id',
          },
          {
            resource: 'email',
            operation: 'send_confirmed_email',
            status: 'skipped',
            code: 'no_installation_id',
            reason: 'no_installation_id',
          },
        ],
      },
    };

    assert.equal(isConfirmAutomationIncomplete(wpResponse), false);

    const notifications = mapper.mapBenefitResponseToNotifications({
      response: wpResponse,
      context: 'confirm_admin',
      baseOutcome: { status: 'success', message: 'Cita confirmada.' },
    });

    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].title, 'Automatización no disponible');
  });

  it('error de red sin benefit_notices sigue activando Automatización incompleta', () => {
    const wpResponse = {
      success: true,
      data: {
        calendar_sync: false,
        message:
          'Cita confirmada en WordPress, pero no se pudo notificar al backend: cURL error 7',
      },
    };

    assert.equal(isConfirmAutomationIncomplete(wpResponse), true);
  });
});
