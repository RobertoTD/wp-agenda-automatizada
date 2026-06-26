/**
 * Benefit notification mapper (UX-3A) — pure translation layer.
 * Converts benefit_notices[] + legacy AJAX fields into notification models.
 * No DOM, no alerts, no toast renderer.
 *
 * @see docs/ops/benefit-notifications-js.md
 */
(function () {
  'use strict';

  var BILLING_DEGRADED_LINE =
    'Tu suscripción Pro está pendiente de pago; estás usando límites gratuitos.';

  var DURATION_MS = {
    success: 3500,
    info: 4000,
    warning: 5000,
    error: 7000,
  };

  var CONTEXTS = {
    CANCEL_ADMIN: 'cancel_admin',
    CONFIRM_ADMIN: 'confirm_admin',
    SEND_CONFIRMATION_REQUEST: 'send_confirmation_request',
    AI_CHAT: 'ai_chat',
  };

  var REQUIRES_DEOIA_ACCOUNT_CODES = {
    no_installation_id: true,
    google_calendar_no_installation_id: true,
    backend_disabled: true,
    google_calendar_backend_disabled: true,
  };

  var AUTOMATION_UNAVAILABLE_TITLE = 'Automatización no disponible';
  var AUTOMATION_UNAVAILABLE_MESSAGE =
    'La cita se guardó en la agenda, pero Google Calendar y el correo al cliente requieren una cuenta DEOIA activa.';
  var AUTOMATION_UNAVAILABLE_FALLBACK = 'Vincula tu cuenta para activar estos beneficios.';
  var AUTOMATION_UNAVAILABLE_ACTION_LABEL = 'Vincular cuenta';
  var GOOGLE_CALENDAR_SETUP_URL_FALLBACK =
    'admin-post.php?action=aa_iframe_content&module=settings&setup_focus=google_calendar#aa-google-calendar-root';

  /**
   * @param {unknown} value
   * @returns {string}
   */
  function asTrimmedString(value) {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value).trim();
  }

  /**
   * @param {unknown} value
   * @returns {string}
   */
  function asLowerString(value) {
    return asTrimmedString(value).toLowerCase();
  }

  /**
   * @param {unknown} obj
   * @param {string} key
   * @returns {unknown}
   */
  function getOwn(obj, key) {
    if (obj === null || obj === undefined || typeof obj !== 'object' || Array.isArray(obj)) {
      return undefined;
    }
    return Object.prototype.hasOwnProperty.call(obj, key) ? obj[key] : undefined;
  }

  /**
   * WordPress AJAX payload layer (response.data when present).
   *
   * @param {unknown} response
   * @returns {Record<string, unknown>}
   */
  function getPayload(response) {
    if (response === null || response === undefined || typeof response !== 'object' || Array.isArray(response)) {
      return {};
    }
    var data = getOwn(response, 'data');
    if (data !== null && data !== undefined && typeof data === 'object' && !Array.isArray(data)) {
      return /** @type {Record<string, unknown>} */ (data);
    }
    return /** @type {Record<string, unknown>} */ (response);
  }

  /**
   * @param {unknown} response
   * @returns {unknown[]}
   */
  function extractBenefitNoticesFromResponse(response) {
    if (response === null || response === undefined || typeof response !== 'object' || Array.isArray(response)) {
      return [];
    }

    var payload = getPayload(response);
    var candidates = [
      getOwn(payload, 'benefit_notices'),
      getOwn(response, 'benefit_notices'),
    ];

    var backendInPayload = getOwn(payload, 'backend_response');
    if (backendInPayload !== null && backendInPayload !== undefined && typeof backendInPayload === 'object' && !Array.isArray(backendInPayload)) {
      candidates.push(getOwn(backendInPayload, 'benefit_notices'));
    }

    var backendTop = getOwn(response, 'backend_response');
    if (backendTop !== null && backendTop !== undefined && typeof backendTop === 'object' && !Array.isArray(backendTop)) {
      candidates.push(getOwn(backendTop, 'benefit_notices'));
    }

    for (var i = 0; i < candidates.length; i++) {
      var list = candidates[i];
      if (Array.isArray(list)) {
        return list.slice();
      }
    }

    return [];
  }

  /**
   * @param {unknown} raw
   * @returns {Record<string, unknown>|null}
   */
  function normalizeBenefitNotice(raw) {
    if (raw === null || raw === undefined || typeof raw !== 'object' || Array.isArray(raw)) {
      return null;
    }

    var src = /** @type {Record<string, unknown>} */ (raw);
    var resource = asLowerString(src.resource);
    var operation = asLowerString(src.operation);
    var status = asLowerString(src.status);
    var code = asTrimmedString(src.code);
    var reason = asTrimmedString(src.reason);

    if (!resource || !operation || !status || !code || !reason) {
      return null;
    }

    var out = {
      resource: resource,
      operation: operation,
      status: status,
      code: code,
      reason: reason,
    };

    var quotaKey = asTrimmedString(src.quota_key);
    if (quotaKey) {
      out.quota_key = quotaKey;
    }

    var effectiveAccessTier = asLowerString(src.effective_access_tier);
    if (effectiveAccessTier) {
      out.effective_access_tier = effectiveAccessTier;
    }

    var contractedPlanTier = asLowerString(src.contracted_plan_tier);
    if (contractedPlanTier) {
      out.contracted_plan_tier = contractedPlanTier;
    }

    var stripeStatus = asLowerString(src.stripe_status);
    if (stripeStatus) {
      out.stripe_status = stripeStatus;
    }

    if (src.billing_degraded === true) {
      out.billing_degraded = true;
    }

    if (typeof src.limit === 'number' && Number.isFinite(src.limit)) {
      out.limit = src.limit;
    }

    if (typeof src.remaining === 'number' && Number.isFinite(src.remaining)) {
      out.remaining = src.remaining;
    }

    var period = asTrimmedString(src.period_yyyymm);
    if (period) {
      out.period_yyyymm = period;
    }

    return out;
  }

  /**
   * @param {string} code
   * @returns {string}
   */
  function reasonFromQuotaCode(code) {
    var lower = asLowerString(code);
    if (lower === 'email_quota_exceeded' || lower === 'google_calendar_quota_exceeded') {
      return 'quota_exceeded';
    }
    if (lower === 'backend_disabled' || lower === 'google_calendar_backend_disabled') {
      return 'backend_disabled';
    }
    if (lower === 'no_installation_id' || lower === 'google_calendar_no_installation_id') {
      return 'no_installation_id';
    }
    if (lower === 'quota_service_unavailable' || lower === 'google_calendar_quota_service_unavailable') {
      return 'quota_service_unavailable';
    }
    if (lower.indexOf('quota') !== -1 && lower.indexOf('exceeded') !== -1) {
      return 'quota_exceeded';
    }
    return lower || 'skipped';
  }

  /**
   * @param {unknown} response
   * @param {string} context
   * @returns {Record<string, unknown>[]}
   */
  function synthesizeLegacyNotices(response, context) {
    var payload = getPayload(response);
    var legacyData = getOwn(payload, 'data');
    var dataNode =
      legacyData !== null && legacyData !== undefined && typeof legacyData === 'object' && !Array.isArray(legacyData)
        ? /** @type {Record<string, unknown>} */ (legacyData)
        : {};

    var synthesized = [];

    if (context === CONTEXTS.CANCEL_ADMIN) {
      var deleteSkipped =
        payload.calendar_delete_skipped === true || response.calendar_delete_skipped === true;
      if (deleteSkipped) {
        var calCode = asTrimmedString(payload.calendar_quota_code) || 'google_calendar_delete_skipped';
        synthesized.push({
          resource: 'google_calendar_sync',
          operation: 'delete_event',
          status: 'skipped',
          code: calCode,
          reason: reasonFromQuotaCode(calCode) || 'calendar_delete_skipped',
        });
      }
    }

    if (context === CONTEXTS.CONFIRM_ADMIN) {
      var calendarSkipped =
        payload.calendar_skipped === true || dataNode.calendarSkipped === true;
      if (calendarSkipped) {
        var createCode =
          asTrimmedString(payload.calendar_quota_code) ||
          asTrimmedString(dataNode.calendarQuotaCode) ||
          'google_calendar_create_skipped';
        synthesized.push({
          resource: 'google_calendar_sync',
          operation: 'create_event',
          status: 'skipped',
          code: createCode,
          reason: reasonFromQuotaCode(createCode) || 'calendar_create_skipped',
        });
      }

      var emailNode = getOwn(payload, 'email');
      if (emailNode !== null && emailNode !== undefined && typeof emailNode === 'object' && !Array.isArray(emailNode)) {
        var emailObj = /** @type {Record<string, unknown>} */ (emailNode);
        if (emailObj.skipped === true) {
          var emailCode =
            asTrimmedString(emailObj.code) ||
            asTrimmedString(emailObj.reason) ||
            'email_skipped';
          synthesized.push({
            resource: 'email',
            operation: 'send_confirmed_email',
            status: 'skipped',
            code: emailCode,
            reason: asTrimmedString(emailObj.reason) || reasonFromQuotaCode(emailCode) || 'email_skipped',
          });
        }
      }
    }

    if (context === CONTEXTS.SEND_CONFIRMATION_REQUEST) {
      var wpFailed = response && typeof response === 'object' && response.success === false;
      var innerFailed = payload.success === false;
      var hasErrorSignal = !!(asTrimmedString(payload.code) || asTrimmedString(payload.error));

      if ((wpFailed || innerFailed) && hasErrorSignal) {
        var blockedCode =
          asTrimmedString(payload.code) || 'send_confirmation_request_blocked';
        synthesized.push({
          resource: 'email',
          operation: 'send_confirmation_request',
          status: 'blocked',
          code: blockedCode,
          reason:
            asTrimmedString(payload.reason) ||
            reasonFromQuotaCode(blockedCode) ||
            'send_confirmation_request_blocked',
        });
      } else if (payload.skipped === true) {
        var skipCode =
          asTrimmedString(payload.code) ||
          asTrimmedString(payload.reason) ||
          'send_confirmation_request_skipped';
        synthesized.push({
          resource: 'email',
          operation: 'send_confirmation_request',
          status: 'skipped',
          code: skipCode,
          reason: asTrimmedString(payload.reason) || skipCode,
        });
      }
    }

    return synthesized;
  }

  /**
   * @param {unknown} response
   * @param {string} context
   * @returns {Record<string, unknown>[]}
   */
  function normalizeBenefitNoticesFromResponse(response, context) {
    var ctx = asLowerString(context);
    var extracted = extractBenefitNoticesFromResponse(response);
    var rawList = extracted.length > 0 ? extracted : synthesizeLegacyNotices(response, ctx);

    var normalized = [];
    for (var i = 0; i < rawList.length; i++) {
      var notice = normalizeBenefitNotice(rawList[i]);
      if (notice) {
        normalized.push(notice);
      }
    }
    return normalized;
  }

  /**
   * @param {string} code
   * @returns {boolean}
   */
  function isRequiresDeoiaAccountCode(code) {
    return Object.prototype.hasOwnProperty.call(REQUIRES_DEOIA_ACCOUNT_CODES, asLowerString(code));
  }

  /**
   * @param {Record<string, unknown>[]} notices
   * @returns {boolean}
   */
  function noticesAreAllRequiresDeoiaAccount(notices) {
    if (!notices || notices.length === 0) {
      return false;
    }
    for (var i = 0; i < notices.length; i++) {
      if (!isRequiresDeoiaAccountCode(notices[i].code)) {
        return false;
      }
    }
    return true;
  }

  /**
   * @returns {string}
   */
  function buildGoogleCalendarSetupUrl() {
    if (typeof window !== 'undefined' && window.location && window.location.href) {
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('action', 'aa_iframe_content');
        url.searchParams.set('module', 'settings');
        url.searchParams.set('setup_focus', 'google_calendar');
        url.hash = '#aa-google-calendar-root';
        return url.toString();
      } catch (_err) {
        // fall through to relative fallback
      }
    }
    return GOOGLE_CALENDAR_SETUP_URL_FALLBACK;
  }

  /**
   * @param {Record<string, unknown>[]} notices
   * @returns {Record<string, unknown>}
   */
  function buildAutomationUnavailableNotification(notices) {
    return {
      severity: 'warning',
      title: AUTOMATION_UNAVAILABLE_TITLE,
      message: AUTOMATION_UNAVAILABLE_MESSAGE,
      details: [],
      fallback: AUTOMATION_UNAVAILABLE_FALLBACK,
      durationMs: DURATION_MS.warning,
      blocking: false,
      actions: [
        {
          label: AUTOMATION_UNAVAILABLE_ACTION_LABEL,
          url: buildGoogleCalendarSetupUrl(),
        },
      ],
      notices: notices.slice(),
    };
  }

  /**
   * @param {Record<string, unknown>} notice
   * @param {string} context
   * @returns {{ detail: string, fallback: string|null }}
   */
  function mapNoticeToCopy(notice, context) {
    var resource = asLowerString(notice.resource);
    var operation = asLowerString(notice.operation);
    var status = asLowerString(notice.status);
    var code = asLowerString(notice.code);
    var ctx = asLowerString(context);

    var detail = '';
    var fallback = null;

    if (code === 'quota_service_unavailable') {
      return {
        detail: 'No se pudo verificar la cuota. Intenta más tarde.',
        fallback: null,
      };
    }

    if (code === 'no_installation_id' || code === 'backend_disabled' || code === 'google_calendar_backend_disabled') {
      return {
        detail: 'No se pudo validar el estado de esta agenda.',
        fallback: null,
      };
    }

    if (resource === 'google_calendar_sync' && operation === 'delete_event' && status === 'skipped') {
      detail = 'No se eliminó el evento en Google Calendar.';
      if (code === 'google_calendar_quota_exceeded' || notice.reason === 'quota_exceeded') {
        detail = detail + ' Límite de sincronizaciones alcanzado.';
      }
      fallback = 'Puedes eliminarlo manualmente desde Google Calendar.';
      return { detail: detail, fallback: fallback };
    }

    if (resource === 'google_calendar_sync' && operation === 'create_event' && status === 'skipped') {
      detail = 'No se creó el evento en Google Calendar.';
      if (code === 'google_calendar_quota_exceeded' || notice.reason === 'quota_exceeded') {
        detail = detail + ' Límite de sincronizaciones alcanzado.';
      }
      return { detail: detail, fallback: null };
    }

    if (resource === 'email' && operation === 'send_confirmed_email' && status === 'skipped') {
      detail = 'No se envió el correo de confirmación.';
      if (code === 'email_quota_exceeded' || notice.reason === 'quota_exceeded') {
        detail = detail + ' Cuota de emails alcanzada.';
      }
      fallback = 'Puedes contactar al cliente manualmente.';
      return { detail: detail, fallback: fallback };
    }

    if (resource === 'email' && operation === 'send_confirmation_request' && status === 'blocked') {
      detail = '';
      if (code === 'email_quota_exceeded' || notice.reason === 'quota_exceeded') {
        detail = 'Cuota de emails alcanzada.';
      }
      fallback = 'Puedes confirmar la cita manualmente o contactar al cliente.';
      return { detail: detail, fallback: fallback };
    }

    if (resource === 'email' && operation === 'send_confirmation_request' && status === 'skipped') {
      if (code === 'email_not_provided' || notice.reason === 'email_not_provided') {
        detail = 'El cliente no tiene email registrado.';
      } else if (code === 'duplicate_reminder' || notice.reason === 'duplicate_reminder') {
        detail = 'La solicitud ya estaba registrada; no se volvió a enviar.';
      } else if (code === 'no_billable_recipients' || notice.reason === 'no_billable_recipients') {
        detail = 'No hay destinatarios válidos para enviar el correo.';
      } else {
        detail = 'No se envió la solicitud de confirmación.';
      }
      return { detail: detail, fallback: null };
    }

    if (ctx === CONTEXTS.AI_CHAT && (code === 'quota_exceeded' || status === 'blocked')) {
      return {
        detail: 'Has alcanzado el límite de consultas de IA para este período.',
        fallback: null,
      };
    }

    return {
      detail: asTrimmedString(notice.reason) || asTrimmedString(notice.code) || 'Beneficio no completado.',
      fallback: null,
    };
  }

  /**
   * @param {Record<string, unknown>[]} notices
   * @returns {boolean}
   */
  function hasBillingDegraded(notices) {
    for (var i = 0; i < notices.length; i++) {
      if (notices[i].billing_degraded === true) {
        return true;
      }
    }
    return false;
  }

  /**
   * @param {Record<string, unknown>[]} notices
   * @returns {string}
   */
  function computeSeverity(notices) {
    for (var i = 0; i < notices.length; i++) {
      if (asLowerString(notices[i].status) === 'blocked') {
        return 'error';
      }
    }
    if (notices.length > 0) {
      return 'warning';
    }
    return 'info';
  }

  /**
   * @param {string} context
   * @param {Record<string, unknown>[]} notices
   * @returns {string}
   */
  function buildTitle(context, notices) {
    var ctx = asLowerString(context);
    var hasBlocked = false;
    var allSkipped = notices.length > 0;

    for (var i = 0; i < notices.length; i++) {
      if (asLowerString(notices[i].status) === 'blocked') {
        hasBlocked = true;
      }
      if (asLowerString(notices[i].status) !== 'skipped') {
        allSkipped = false;
      }
    }

    if (ctx === CONTEXTS.CANCEL_ADMIN) {
      return 'Cita cancelada';
    }
    if (ctx === CONTEXTS.CONFIRM_ADMIN) {
      return 'Cita confirmada';
    }
    if (ctx === CONTEXTS.SEND_CONFIRMATION_REQUEST) {
      if (hasBlocked) {
        return 'Solicitud no enviada';
      }
      if (allSkipped && notices.length > 0) {
        return 'Solicitud omitida';
      }
      return 'Solicitud de confirmación';
    }
    if (ctx === CONTEXTS.AI_CHAT) {
      return 'Límite de IA';
    }
    return 'Aviso';
  }

  /**
   * @param {string} context
   * @param {Record<string, unknown>[]} notices
   * @param {Array<{ detail: string }>} detailEntries
   * @returns {string}
   */
  function buildMessage(context, notices, detailEntries) {
    var ctx = asLowerString(context);

    if (notices.length === 1 && detailEntries.length === 1) {
      var single = detailEntries[0].detail;
      if (ctx === CONTEXTS.SEND_CONFIRMATION_REQUEST && asLowerString(notices[0].status) === 'blocked') {
        return 'No se envió la solicitud de confirmación.';
      }
      if (single) {
        return single;
      }
    }

    if (ctx === CONTEXTS.CONFIRM_ADMIN && notices.length >= 2) {
      return 'La cita se confirmó, pero algunos beneficios no se completaron.';
    }

    if (ctx === CONTEXTS.CANCEL_ADMIN) {
      return 'La cita se canceló, pero algunos beneficios no se completaron.';
    }

    if (ctx === CONTEXTS.SEND_CONFIRMATION_REQUEST && notices.some(function (n) {
      return asLowerString(n.status) === 'blocked';
    })) {
      return 'No se envió la solicitud de confirmación.';
    }

    if (detailEntries.length > 0 && detailEntries[0].detail) {
      return detailEntries[0].detail;
    }

    return 'Algunos beneficios no se completaron.';
  }

  /**
   * @param {Array<string|null>} fallbacks
   * @returns {string|null}
   */
  function combineFallbacks(fallbacks) {
    var unique = [];
    var seen = {};
    for (var i = 0; i < fallbacks.length; i++) {
      var fb = fallbacks[i];
      if (!fb) {
        continue;
      }
      var key = asLowerString(fb);
      if (seen[key]) {
        continue;
      }
      seen[key] = true;
      unique.push(fb);
    }
    if (unique.length === 0) {
      return null;
    }
    if (unique.length === 1) {
      return unique[0];
    }
    return unique.join(' ');
  }

  /**
   * @param {string} context
   * @param {{ status?: string }} baseOutcome
   * @returns {Record<string, unknown>}
   */
  function buildSuccessNotification(context, baseOutcome) {
    var ctx = asLowerString(context);
    var title = 'Operación completada';
    if (ctx === CONTEXTS.CANCEL_ADMIN) {
      title = 'Cita cancelada';
    } else if (ctx === CONTEXTS.CONFIRM_ADMIN) {
      title = 'Cita confirmada';
    } else if (ctx === CONTEXTS.SEND_CONFIRMATION_REQUEST) {
      title = 'Solicitud enviada';
    }

    return {
      severity: 'success',
      title: title,
      message: asTrimmedString(baseOutcome && baseOutcome.message) || 'La operación se completó correctamente.',
      details: [],
      fallback: null,
      durationMs: DURATION_MS.success,
      blocking: false,
      actions: [],
      notices: [],
    };
  }

  /**
   * @param {Record<string, unknown>[]} notices
   * @param {string} context
   * @returns {Record<string, unknown>}
   */
  function buildCompositeNotification(notices, context) {
    var severity = computeSeverity(notices);
    var detailSeen = {};
    var details = [];
    var fallbacks = [];

    for (var i = 0; i < notices.length; i++) {
      var copy = mapNoticeToCopy(notices[i], context);
      var detailLine = asTrimmedString(copy.detail);
      if (detailLine) {
        var detailKey = asLowerString(detailLine);
        if (!detailSeen[detailKey]) {
          detailSeen[detailKey] = true;
          details.push(detailLine);
        }
      }
      if (copy.fallback) {
        fallbacks.push(copy.fallback);
      }
    }

    if (hasBillingDegraded(notices)) {
      var billingKey = asLowerString(BILLING_DEGRADED_LINE);
      if (!detailSeen[billingKey]) {
        detailSeen[billingKey] = true;
        details.push(BILLING_DEGRADED_LINE);
      }
    }

    var detailEntries = details.map(function (d) {
      return { detail: d };
    });

    return {
      severity: severity,
      title: buildTitle(context, notices),
      message: buildMessage(context, notices, detailEntries),
      details: details,
      fallback: combineFallbacks(fallbacks),
      durationMs: DURATION_MS[severity] || DURATION_MS.info,
      blocking: false,
      actions: [],
      notices: notices.slice(),
    };
  }

  /**
   * @param {{
   *   response?: unknown,
   *   context?: string,
   *   baseOutcome?: { status?: string, message?: string },
   *   legacy?: unknown
   * }} input
   * @returns {Record<string, unknown>[]}
   */
  function mapBenefitResponseToNotifications(input) {
    var opts = input || {};
    var response = opts.response;
    var context = asLowerString(opts.context);
    // legacy reserved for future overrides; not required in UX-3A
    void opts.legacy;

    var notices = normalizeBenefitNoticesFromResponse(response, context);

    if (notices.length === 0) {
      var baseOutcome = opts.baseOutcome;
      if (baseOutcome && asLowerString(baseOutcome.status) === 'success') {
        return [buildSuccessNotification(context, baseOutcome)];
      }
      return [];
    }

    if (context === CONTEXTS.CONFIRM_ADMIN && noticesAreAllRequiresDeoiaAccount(notices)) {
      return [buildAutomationUnavailableNotification(notices)];
    }

    return [buildCompositeNotification(notices, context)];
  }

  var api = {
    extractBenefitNoticesFromResponse: extractBenefitNoticesFromResponse,
    normalizeBenefitNoticesFromResponse: normalizeBenefitNoticesFromResponse,
    mapBenefitResponseToNotifications: mapBenefitResponseToNotifications,
    /** @internal exposed for tests */
    _CONTEXTS: CONTEXTS,
  };

  if (typeof window !== 'undefined') {
    window.BenefitNotificationMapper = api;
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
})();
