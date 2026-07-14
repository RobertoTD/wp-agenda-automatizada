/**
 * Account module — monthly benefit quotas presentation (UX).
 *
 * Pure mapping; no DOM. Consumed by account module.js and Node tests.
 *
 * Pool-first rule:
 * - Valid shared_pools.calendar_and_push → one shared card; skip legacy Calendar item.
 * - Missing/invalid pool → legacy deoia_google_calendar_syncs card.
 * - Never render an independent Push item.
 * - Do not recompute remaining; use pool.remaining as-is.
 */
(function () {
    'use strict';

    var MSG_UNAVAILABLE =
        'No pudimos cargar el uso de beneficios este mes.';

    var SHARED_POOL_KEY = 'calendar_and_push';
    var CALENDAR_ITEM_KEY = 'deoia_google_calendar_syncs';

    var COPY_BY_KEY = {
        deoia_email_sends: {
            available: 'Emailing disponible',
            exhausted: 'Has agotado tu cuota de emailing de este mes.'
        },
        deoia_ai_chat_queries: {
            available: 'Solicitudes IA disponibles',
            exhausted: 'Has agotado tu cuota de solicitudes IA de este mes.'
        },
        deoia_google_calendar_syncs: {
            available: 'Google Calendar disponible',
            exhausted: 'Has agotado tu cuota de sincronizaciones con Google Calendar de este mes.'
        }
    };

    var SHARED_POOL_COPY = {
        available: 'Sincronizaciones Calendar y notificaciones push',
        exhausted:
            'Has agotado tu cuota de sincronizaciones Calendar y notificaciones push de este mes.'
    };

    var KEY_ORDER = [
        'deoia_email_sends',
        'deoia_ai_chat_queries',
        'deoia_google_calendar_syncs'
    ];

    /**
     * @param {object} item
     * @returns {boolean}
     */
    function isBenefitQuotaItemExhausted(item) {
        if (!item || typeof item !== 'object') {
            return false;
        }

        if (item.at_limit === true) {
            return true;
        }

        if (item.remaining === 0) {
            return true;
        }

        if (item.limit === 0) {
            return true;
        }

        return item.can_consume === false && item.remaining !== null && item.remaining <= 0;
    }

    /**
     * @param {object} item
     * @returns {{ title: string, remainingLine: string|null, detailLine: string|null }}
     */
    function buildBenefitQuotaItemCopy(item) {
        var key = item && item.key ? String(item.key) : '';
        var copy = COPY_BY_KEY[key];
        if (!copy) {
            return { title: '', remainingLine: null, detailLine: null };
        }

        var exhausted = isBenefitQuotaItemExhausted(item);
        var title = exhausted ? copy.exhausted : copy.available;
        var remainingLine = null;

        if (item.remaining === null || item.remaining === undefined) {
            return { title: title, remainingLine: null, detailLine: null };
        }

        var remaining = Number(item.remaining);
        if (!Number.isFinite(remaining)) {
            return { title: title, remainingLine: null, detailLine: null };
        }

        remainingLine = 'Cuota restante: ' + String(remaining);
        return { title: title, remainingLine: remainingLine, detailLine: null };
    }

    /**
     * @param {unknown} benefitQuotas
     * @returns {object|null}
     */
    function resolveCalendarAndPushPool(benefitQuotas) {
        if (!benefitQuotas || typeof benefitQuotas !== 'object') {
            return null;
        }
        var pools = benefitQuotas.shared_pools;
        if (!pools || typeof pools !== 'object') {
            return null;
        }
        var pool = pools[SHARED_POOL_KEY];
        if (!pool || typeof pool !== 'object') {
            return null;
        }
        if (!pool.breakdown || typeof pool.breakdown !== 'object') {
            return null;
        }
        if (!Object.prototype.hasOwnProperty.call(pool, 'remaining')) {
            return null;
        }
        return pool;
    }

    /**
     * @param {object} pool
     * @returns {{ title: string, remainingLine: string|null, detailLine: string|null }}
     */
    function buildSharedPoolCardCopy(pool) {
        var exhausted = isBenefitQuotaItemExhausted(pool);
        var title = exhausted ? SHARED_POOL_COPY.exhausted : SHARED_POOL_COPY.available;
        var remainingLine = null;

        if (pool.remaining !== null && pool.remaining !== undefined) {
            var remaining = Number(pool.remaining);
            if (Number.isFinite(remaining)) {
                remainingLine = 'Cuota restante: ' + String(remaining);
            }
        }

        var calendar = Number(pool.breakdown.calendar);
        var push = Number(pool.breakdown.push);
        var detailLine = null;
        if (Number.isFinite(calendar) && Number.isFinite(push)) {
            detailLine = 'Calendar: ' + String(calendar) + ' · Push: ' + String(push);
        }

        return {
            title: title,
            remainingLine: remainingLine,
            detailLine: detailLine
        };
    }

    /**
     * @param {unknown} benefitQuotas
     * @returns {{
     *   visible: boolean,
     *   unavailableMessage: string|null,
     *   items: Array<{ title: string, remainingLine: string|null, detailLine: string|null }>
     * }}
     */
    function buildBenefitQuotasRenderPlan(benefitQuotas) {
        if (!benefitQuotas || typeof benefitQuotas !== 'object') {
            return { visible: false, unavailableMessage: null, items: [] };
        }

        if (benefitQuotas.usage_counters_applicable === false) {
            return {
                visible: true,
                unavailableMessage: MSG_UNAVAILABLE,
                items: []
            };
        }

        var rawItems = Array.isArray(benefitQuotas.items) ? benefitQuotas.items : [];
        var sharedPool = resolveCalendarAndPushPool(benefitQuotas);

        if (rawItems.length === 0 && !sharedPool) {
            return { visible: false, unavailableMessage: null, items: [] };
        }

        var byKey = {};
        for (var i = 0; i < rawItems.length; i++) {
            var raw = rawItems[i];
            if (!raw || typeof raw !== 'object' || !raw.key) {
                continue;
            }
            byKey[String(raw.key)] = raw;
        }

        var items = [];
        for (var j = 0; j < KEY_ORDER.length; j++) {
            var quotaKey = KEY_ORDER[j];

            if (quotaKey === CALENDAR_ITEM_KEY) {
                if (sharedPool) {
                    items.push(buildSharedPoolCardCopy(sharedPool));
                } else if (byKey[quotaKey]) {
                    var legacyCopy = buildBenefitQuotaItemCopy(byKey[quotaKey]);
                    if (legacyCopy.title) {
                        items.push(legacyCopy);
                    }
                }
                continue;
            }

            if (!byKey[quotaKey]) {
                continue;
            }
            var itemCopy = buildBenefitQuotaItemCopy(byKey[quotaKey]);
            if (!itemCopy.title) {
                continue;
            }
            items.push(itemCopy);
        }

        if (items.length === 0) {
            return { visible: false, unavailableMessage: null, items: [] };
        }

        return {
            visible: true,
            unavailableMessage: null,
            items: items
        };
    }

    var api = {
        buildBenefitQuotasRenderPlan: buildBenefitQuotasRenderPlan,
        buildBenefitQuotaItemCopy: buildBenefitQuotaItemCopy,
        buildSharedPoolCardCopy: buildSharedPoolCardCopy,
        resolveCalendarAndPushPool: resolveCalendarAndPushPool,
        isBenefitQuotaItemExhausted: isBenefitQuotaItemExhausted,
        /** @internal */
        MSG_UNAVAILABLE: MSG_UNAVAILABLE,
        SHARED_POOL_KEY: SHARED_POOL_KEY,
        SHARED_POOL_COPY: SHARED_POOL_COPY
    };

    if (typeof window !== 'undefined') {
        window.AccountBenefitQuotasUx = api;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
})();
