'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const uxPath = path.join(__dirname, '../../assets/js/services/accountStatusErrorUx.js');
const ux = require(uxPath);

const MSG_REQUIRES_LINK = ux._MSG_REQUIRES_LINK;
const REASON_MISSING_CLIENT_SECRET = 'missing_client_secret';

describe('AccountStatusErrorUx.mapAccountStatusErrorToUi', () => {
  it('account_backend_not_configured + missing_client_secret → copy vinculación + CTA', () => {
    const ui = ux.mapAccountStatusErrorToUi({
      code: 'account_backend_not_configured',
      reason: REASON_MISSING_CLIENT_SECRET,
      message: 'Falta el client secret del backend.',
    });
    assert.equal(ui.text, MSG_REQUIRES_LINK);
    assert.equal(ui.actions.length, 1);
    assert.equal(ui.actions[0].label, 'Vincular cuenta');
    assert.match(ui.actions[0].url, /setup_focus=google_calendar/);
    assert.doesNotMatch(ui.text, /client secret|backend|installation/i);
  });

  it('account_client_not_found → copy vinculación + CTA', () => {
    const ui = ux.mapAccountStatusErrorToUi({
      code: 'account_client_not_found',
      message: 'Client not found',
    });
    assert.equal(ui.text, MSG_REQUIRES_LINK);
    assert.equal(ui.actions.length, 1);
    assert.equal(ui.actions[0].label, 'Vincular cuenta');
  });

  it('account_backend_unreachable → copy temporal sin CTA', () => {
    const ui = ux.mapAccountStatusErrorToUi({
      code: 'account_backend_unreachable',
      message: 'Connection refused',
    });
    assert.match(ui.text, /No pudimos consultar el estado de cuenta/i);
    assert.equal(ui.actions.length, 0);
    assert.doesNotMatch(ui.text, /Connection refused/i);
  });

  it('ignora message técnico del servidor para not_configured sin reason', () => {
    const ui = ux.mapAccountStatusErrorToUi({
      code: 'account_backend_not_configured',
      message: 'Falta el client secret del backend.',
    });
    assert.match(ui.text, /No pudimos consultar el estado de cuenta/i);
    assert.equal(ui.actions.length, 0);
    assert.doesNotMatch(ui.text, /client secret/i);
  });

  it('account_backend_invalid_response → copy incompleto sin CTA', () => {
    const ui = ux.mapAccountStatusErrorToUi({
      code: 'account_backend_invalid_response',
      message: 'Respuesta del backend sin account_status.',
    });
    assert.match(ui.text, /No pudimos mostrar el estado de cuenta completo/i);
    assert.equal(ui.actions.length, 0);
    assert.doesNotMatch(ui.text, /backend/i);
  });
});

describe('AccountStatusErrorUx actions rendering contract', () => {
  it('error panel contract: actions present when requires link', () => {
    const ui = ux.mapAccountStatusErrorToUi({
      code: 'account_client_not_found',
      message: 'Client not found',
    });
    assert.ok(ui.actions.length > 0);
    assert.equal(ui.actions[0].label, 'Vincular cuenta');
    assert.match(ui.actions[0].url, /#aa-google-calendar-root/);
  });
});
