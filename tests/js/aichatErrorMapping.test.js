'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const uxPath = path.join(__dirname, '../../assets/js/services/aiChatErrorUx.js');
const ux = require(uxPath);

const MSG_REQUIRES_ACCOUNT = ux._MSG_REQUIRES_ACCOUNT;

describe('AIChatErrorUx.mapChatAjaxErrorToUi', () => {
  it('ai_backend_not_configured → copy A + CTA, sin texto técnico', () => {
    const ui = ux.mapChatAjaxErrorToUi({
      code: 'ai_backend_not_configured',
      message: 'Falta el client secret del backend para conectar.',
    });
    assert.equal(ui.text, MSG_REQUIRES_ACCOUNT);
    assert.equal(ui.code, 'ai_backend_not_configured');
    assert.match(ui.text, /cuenta DEOIA activa/i);
    assert.doesNotMatch(ui.text, /client secret|backend|installation/i);
    assert.equal(ui.actions.length, 1);
    assert.equal(ui.actions[0].label, 'Vincular cuenta');
    assert.match(ui.actions[0].url, /setup_focus=google_calendar/);
    assert.match(ui.actions[0].url, /#aa-google-calendar-root/);
  });

  it('no_installation_id → copy A + action', () => {
    const ui = ux.mapChatAjaxErrorToUi({
      code: 'no_installation_id',
      message: 'La agenda no está vinculada a una instalación.',
    });
    assert.equal(ui.text, MSG_REQUIRES_ACCOUNT);
    assert.equal(ui.actions.length, 1);
    assert.equal(ui.actions[0].label, 'Vincular cuenta');
    assert.doesNotMatch(ui.text, /installation/i);
  });

  it('backend_disabled → copy plan, sin action Google', () => {
    const ui = ux.mapChatAjaxErrorToUi({
      code: 'backend_disabled',
      message: 'AI disabled on tenant',
    });
    assert.match(ui.text, /plan actual no incluye consultas del Asistente IA/i);
    assert.equal(ui.actions.length, 0);
  });

  it('quota_exceeded → copy cuota', () => {
    const ui = ux.mapChatAjaxErrorToUi({
      code: 'quota_exceeded',
      message: 'raw quota message from server',
    });
    assert.match(ui.text, /límite de consultas de IA/i);
    assert.equal(ui.actions.length, 0);
  });

  it('error genérico → copy temporal de conexión', () => {
    const ui = ux.mapChatAjaxErrorToUi({
      code: 'ai_unavailable',
      message: 'Connection refused',
    });
    assert.match(ui.text, /No pude conectarme con el asistente/i);
    assert.equal(ui.actions.length, 0);
  });

  it('código desconocido → copy temporal', () => {
    const ui = ux.mapChatAjaxErrorToUi({
      code: 'weird_internal',
      message: 'stack trace here',
    });
    assert.match(ui.text, /No pude conectarme con el asistente/i);
    assert.doesNotMatch(ui.text, /stack trace/i);
  });
});

describe('AIChatErrorUx.shouldShowFixBlockerDetail', () => {
  it('no duplica texto principal como blocker', () => {
    const main = MSG_REQUIRES_ACCOUNT;
    assert.equal(ux.shouldShowFixBlockerDetail(main, main), false);
    assert.equal(ux.shouldShowFixBlockerDetail(main, '  ' + main + '  '), false);
  });

  it('muestra blocker distinto al texto principal', () => {
    assert.equal(
      ux.shouldShowFixBlockerDetail(MSG_REQUIRES_ACCOUNT, 'Espera 2 segundos'),
      true
    );
  });
});

describe('AIChatErrorUx.buildGoogleCalendarSetupUrl', () => {
  it('fallback relativo sin window.location', () => {
    const url = ux.buildGoogleCalendarSetupUrl();
    assert.match(url, /admin-post\.php/);
    assert.match(url, /setup_focus=google_calendar/);
    assert.match(url, /#aa-google-calendar-root/);
  });
});
