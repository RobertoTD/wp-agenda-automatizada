'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const uxPath = path.join(__dirname, '../../assets/js/services/accountBenefitQuotasUx.js');
const accountPath = path.join(__dirname, '../../includes/admin/ui/modules/account/module.js');
const ux = require(uxPath);
const account = require(accountPath);

function makeBenefitQuotas(overrides = {}) {
    return {
        period_yyyymm: '202607',
        usage_counters_applicable: true,
        quota_read_error: null,
        access_reason: null,
        items: [
            {
                key: 'deoia_email_sends',
                limit: 30,
                consumed: 5,
                remaining: 25,
                can_consume: true,
                at_limit: false,
                exceeded: false
            },
            {
                key: 'deoia_ai_chat_queries',
                limit: 30,
                consumed: 30,
                remaining: 0,
                can_consume: false,
                at_limit: true,
                exceeded: false
            },
            {
                key: 'deoia_google_calendar_syncs',
                limit: 70,
                consumed: 10,
                remaining: 60,
                can_consume: true,
                at_limit: false,
                exceeded: false
            }
        ],
        ...overrides
    };
}

describe('accountBenefitQuotasUx', () => {
    it('item disponible con remaining muestra copy correcto', () => {
        const copy = ux.buildBenefitQuotaItemCopy({
            key: 'deoia_email_sends',
            remaining: 25,
            can_consume: true,
            at_limit: false
        });

        assert.equal(copy.title, 'Emailing disponible');
        assert.equal(copy.remainingLine, 'Cuota restante: 25');
    });

    it('item agotado muestra copy de cuota agotada', () => {
        const copy = ux.buildBenefitQuotaItemCopy({
            key: 'deoia_ai_chat_queries',
            remaining: 0,
            can_consume: false,
            at_limit: true
        });

        assert.equal(copy.title, 'Has agotado tu cuota de solicitudes IA de este mes.');
        assert.equal(copy.remainingLine, 'Cuota restante: 0');
    });

    it('usage_counters_applicable false muestra mensaje suave', () => {
        const plan = ux.buildBenefitQuotasRenderPlan({
            period_yyyymm: '202607',
            usage_counters_applicable: false,
            items: []
        });

        assert.equal(plan.visible, true);
        assert.match(plan.unavailableMessage, /No pudimos cargar el uso de beneficios/i);
        assert.equal(plan.items.length, 0);
    });

    it('sin benefit_quotas oculta sección sin error', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(undefined);
        assert.equal(plan.visible, false);
        assert.equal(plan.unavailableMessage, null);
    });

    it('remaining null no muestra Cuota restante', () => {
        const copy = ux.buildBenefitQuotaItemCopy({
            key: 'deoia_google_calendar_syncs',
            remaining: null,
            can_consume: true,
            at_limit: false
        });

        assert.equal(copy.title, 'Google Calendar disponible');
        assert.equal(copy.remainingLine, null);
    });
});

describe('account module benefit quotas integration', () => {
    it('Freemium activo conserva leyenda upgrade con benefit_quotas', () => {
        const status = {
            billing_state: 'active',
            plan_tier: 'freemium',
            effective_access_tier: 'freemium',
            benefit_quotas: makeBenefitQuotas()
        };

        const presentation = account.buildAccountPresentation(status);
        const billing = account.resolveBillingAction(status);
        const plan = ux.buildBenefitQuotasRenderPlan(status.benefit_quotas);

        assert.match(presentation.primaryNotice, /upgrade en tu suscripción/i);
        assert.equal(billing.mode, 'hidden');
        assert.equal(plan.visible, true);
        assert.equal(plan.items.length, 3);
    });

    it('Pro activo muestra sección sin leyenda upgrade', () => {
        const status = {
            billing_state: 'active',
            plan_tier: 'pro',
            effective_access_tier: 'pro',
            benefit_quotas: makeBenefitQuotas()
        };

        const presentation = account.buildAccountPresentation(status);
        const plan = ux.buildBenefitQuotasRenderPlan(status.benefit_quotas);

        assert.equal(presentation.primaryNotice, '');
        assert.equal(plan.visible, true);
    });
});
