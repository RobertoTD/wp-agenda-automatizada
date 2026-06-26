'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const mapperPath = path.join(__dirname, '../../assets/js/services/benefitNotificationMapper.js');
const mapper = require(mapperPath);

describe('BenefitNotificationMapper', () => {
  it('extract desde response.data.benefit_notices', () => {
    const response = {
      success: true,
      data: {
        benefit_notices: [
          {
            resource: 'email',
            operation: 'send_confirmation_request',
            status: 'blocked',
            code: 'email_quota_exceeded',
            reason: 'quota_exceeded',
          },
        ],
      },
    };
    const list = mapper.extractBenefitNoticesFromResponse(response);
    assert.equal(list.length, 1);
    assert.equal(list[0].code, 'email_quota_exceeded');
  });

  it('extract desde response.data.backend_response.benefit_notices', () => {
    const response = {
      success: false,
      data: {
        backend_response: {
          success: false,
          benefit_notices: [
            {
              resource: 'google_calendar_sync',
              operation: 'delete_event',
              status: 'skipped',
              code: 'google_calendar_quota_exceeded',
              reason: 'quota_exceeded',
            },
          ],
        },
      },
    };
    const list = mapper.extractBenefitNoticesFromResponse(response);
    assert.equal(list.length, 1);
    assert.equal(list[0].operation, 'delete_event');
  });

  it('fallback cancel_admin desde calendar_delete_skipped', () => {
    const response = {
      success: true,
      data: {
        calendar_delete_skipped: true,
        calendar_quota_code: 'google_calendar_quota_exceeded',
      },
    };
    const notices = mapper.normalizeBenefitNoticesFromResponse(response, 'cancel_admin');
    assert.equal(notices.length, 1);
    assert.equal(notices[0].resource, 'google_calendar_sync');
    assert.equal(notices[0].operation, 'delete_event');
    assert.equal(notices[0].status, 'skipped');
  });

  it('fallback confirm_admin desde calendar_skipped + email.skipped', () => {
    const response = {
      success: true,
      data: {
        calendar_skipped: true,
        calendar_quota_code: 'google_calendar_quota_exceeded',
        email: { skipped: true, code: 'email_quota_exceeded', reason: 'quota_exceeded' },
        data: { calendarSkipped: true, calendarQuotaCode: 'google_calendar_quota_exceeded' },
      },
    };
    const notices = mapper.normalizeBenefitNoticesFromResponse(response, 'confirm_admin');
    assert.equal(notices.length, 2);
    assert.equal(notices[0].operation, 'create_event');
    assert.equal(notices[1].operation, 'send_confirmed_email');
  });

  it('send_confirmation_request blocked desde success:false + data.code', () => {
    const response = {
      success: false,
      data: {
        success: false,
        code: 'email_quota_exceeded',
        error: 'Has alcanzado el límite de correos...',
      },
    };
    const notifications = mapper.mapBenefitResponseToNotifications({
      response,
      context: 'send_confirmation_request',
    });
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].severity, 'error');
    assert.equal(notifications[0].title, 'Solicitud no enviada');
    assert.equal(notifications[0].message, 'No se envió la solicitud de confirmación.');
  });

  it('billing_degraded aparece una sola vez con dos notices', () => {
    const response = {
      success: true,
      data: {
        benefit_notices: [
          {
            resource: 'google_calendar_sync',
            operation: 'create_event',
            status: 'skipped',
            code: 'google_calendar_quota_exceeded',
            reason: 'quota_exceeded',
            billing_degraded: true,
          },
          {
            resource: 'email',
            operation: 'send_confirmed_email',
            status: 'skipped',
            code: 'email_quota_exceeded',
            reason: 'quota_exceeded',
            billing_degraded: true,
          },
        ],
      },
    };
    const notifications = mapper.mapBenefitResponseToNotifications({
      response,
      context: 'confirm_admin',
    });
    assert.equal(notifications.length, 1);
    const billingCount = notifications[0].details.filter((d) =>
      d.includes('Tu suscripción Pro está pendiente de pago')
    ).length;
    assert.equal(billingCount, 1);
  });

  it('confirm_admin con Calendar + email devuelve una sola notification warning', () => {
    const response = {
      success: true,
      data: {
        benefit_notices: [
          {
            resource: 'google_calendar_sync',
            operation: 'create_event',
            status: 'skipped',
            code: 'google_calendar_quota_exceeded',
            reason: 'quota_exceeded',
          },
          {
            resource: 'email',
            operation: 'send_confirmed_email',
            status: 'skipped',
            code: 'email_quota_exceeded',
            reason: 'quota_exceeded',
          },
        ],
      },
    };
    const notifications = mapper.mapBenefitResponseToNotifications({
      response,
      context: 'confirm_admin',
    });
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].severity, 'warning');
    assert.equal(notifications[0].title, 'Cita confirmada');
    assert.equal(
      notifications[0].message,
      'La cita se confirmó, pero algunos beneficios no se completaron.'
    );
    assert.equal(notifications[0].details.length, 2);
  });

  const TECHNICAL_STRINGS = [
    'backend',
    'client secret',
    'installation_id',
    'no_installation_id',
    'token',
  ];

  function assertNoTechnicalCopy(notification) {
    const userFacing = {
      title: notification.title,
      message: notification.message,
      details: notification.details,
      fallback: notification.fallback,
      actions: notification.actions,
    };
    const blob = JSON.stringify(userFacing).toLowerCase();
    for (const term of TECHNICAL_STRINGS) {
      assert.equal(blob.includes(term), false, 'unexpected technical term: ' + term);
    }
  }

  it('confirm_admin sin notices devuelve success Cita confirmada', () => {
    const notifications = mapper.mapBenefitResponseToNotifications({
      response: { success: true, data: {} },
      context: 'confirm_admin',
      baseOutcome: { status: 'success', message: 'Cita confirmada.' },
    });
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].severity, 'success');
    assert.equal(notifications[0].title, 'Cita confirmada');
    assert.equal(notifications[0].message, 'Cita confirmada.');
  });

  it('confirm_admin con no_installation_id devuelve warning Automatización no disponible', () => {
    const response = {
      success: true,
      data: {
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
    const notifications = mapper.mapBenefitResponseToNotifications({
      response,
      context: 'confirm_admin',
    });
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].severity, 'warning');
    assert.equal(notifications[0].title, 'Automatización no disponible');
    assert.ok(notifications[0].message.includes('cuenta DEOIA activa'));
    assert.equal(
      notifications[0].fallback,
      'Vincula tu cuenta para activar estos beneficios.'
    );
    assert.equal(notifications[0].actions.length, 1);
    assert.equal(notifications[0].actions[0].label, 'Vincular cuenta');
    assert.ok(notifications[0].actions[0].url.includes('setup_focus=google_calendar'));
    assert.equal(notifications[0].details.length, 0);
    assertNoTechnicalCopy(notifications[0]);
  });

  it('confirm_admin con backend_disabled devuelve warning Automatización no disponible', () => {
    const response = {
      success: true,
      data: {
        benefit_notices: [
          {
            resource: 'google_calendar_sync',
            operation: 'create_event',
            status: 'skipped',
            code: 'google_calendar_backend_disabled',
            reason: 'backend_disabled',
          },
          {
            resource: 'email',
            operation: 'send_confirmed_email',
            status: 'skipped',
            code: 'backend_disabled',
            reason: 'backend_disabled',
          },
        ],
      },
    };
    const notifications = mapper.mapBenefitResponseToNotifications({
      response,
      context: 'confirm_admin',
    });
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].title, 'Automatización no disponible');
    assertNoTechnicalCopy(notifications[0]);
  });

  it('confirm_admin cuota agotada no se re-mapea como falta de cuenta', () => {
    const response = {
      success: true,
      data: {
        benefit_notices: [
          {
            resource: 'google_calendar_sync',
            operation: 'create_event',
            status: 'skipped',
            code: 'google_calendar_quota_exceeded',
            reason: 'quota_exceeded',
          },
          {
            resource: 'email',
            operation: 'send_confirmed_email',
            status: 'skipped',
            code: 'email_quota_exceeded',
            reason: 'quota_exceeded',
          },
        ],
      },
    };
    const notifications = mapper.mapBenefitResponseToNotifications({
      response,
      context: 'confirm_admin',
    });
    assert.equal(notifications.length, 1);
    assert.equal(notifications[0].title, 'Cita confirmada');
    assert.notEqual(notifications[0].title, 'Automatización no disponible');
  });

  it('send_confirmation_request blocked devuelve severity error', () => {
    const response = {
      success: false,
      data: {
        benefit_notices: [
          {
            resource: 'email',
            operation: 'send_confirmation_request',
            status: 'blocked',
            code: 'email_quota_exceeded',
            reason: 'quota_exceeded',
          },
        ],
      },
    };
    const notifications = mapper.mapBenefitResponseToNotifications({
      response,
      context: 'send_confirmation_request',
    });
    assert.equal(notifications[0].severity, 'error');
    assert.equal(notifications[0].blocking, false);
  });

  it('happy response sin notices devuelve [] si no hay baseOutcome', () => {
    const response = { success: true, data: { sent: { client: 'x' } } };
    const notifications = mapper.mapBenefitResponseToNotifications({
      response,
      context: 'send_confirmation_request',
    });
    assert.deepEqual(notifications, []);
  });

  it('no muta response original', () => {
    const response = {
      success: true,
      data: {
        benefit_notices: [
          {
            resource: 'EMAIL',
            operation: 'Send_Confirmation_Request',
            status: 'BLOCKED',
            code: 'email_quota_exceeded',
            reason: 'quota_exceeded',
          },
        ],
      },
    };
    const snapshot = JSON.stringify(response);
    mapper.mapBenefitResponseToNotifications({
      response,
      context: 'send_confirmation_request',
    });
    assert.equal(JSON.stringify(response), snapshot);
  });
});
