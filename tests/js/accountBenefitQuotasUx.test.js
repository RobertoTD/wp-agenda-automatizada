'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const uxPath = path.join(__dirname, '../../assets/js/services/accountBenefitQuotasUx.js');
const accountPath = path.join(__dirname, '../../includes/admin/ui/modules/account/module.js');
const ux = require(uxPath);
const account = require(accountPath);

function makePool(overrides = {}) {
    return {
        limit: 70,
        consumed: 3,
        reserved: 1,
        allocated: 4,
        remaining: 66,
        can_consume: true,
        at_limit: false,
        exceeded: false,
        member_keys: ['deoia_google_calendar_syncs', 'deoia_push_notifications'],
        breakdown: { calendar: 2, push: 1 },
        ...overrides
    };
}

function makeBenefitQuotas(overrides = {}) {
    return {
        period_yyyymm: '202607',
        usage_counters_applicable: true,
        quota_read_error: null,
        shared_pool_read_error: null,
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
        shared_pools: {},
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

    it('pool válido produce una sola tarjeta compartida y no duplica Calendar', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(
            makeBenefitQuotas({
                shared_pools: { calendar_and_push: makePool() }
            })
        );

        assert.equal(plan.visible, true);
        assert.equal(plan.items.length, 3);
        assert.equal(plan.items[0].title, 'Emailing disponible');
        assert.equal(plan.items[1].title, 'Has agotado tu cuota de solicitudes IA de este mes.');
        assert.equal(plan.items[2].title, 'Sincronizaciones Calendar y notificaciones push');
        assert.equal(plan.items[2].remainingLine, 'Cuota restante: 66');
        assert.equal(plan.items[2].detailLine, 'Calendar: 2 · Push: 1');
        assert.equal(plan.items.some((i) => /Google Calendar disponible/.test(i.title)), false);
        assert.equal(plan.items.some((i) => /Push independiente/i.test(i.title)), false);
        assert.equal(plan.items.filter((i) => i.detailLine).length, 1);
    });

    it('remaining viene del pool, no del item Calendar legacy', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(
            makeBenefitQuotas({
                items: [
                    {
                        key: 'deoia_email_sends',
                        remaining: 25,
                        can_consume: true,
                        at_limit: false
                    },
                    {
                        key: 'deoia_ai_chat_queries',
                        remaining: 10,
                        can_consume: true,
                        at_limit: false
                    },
                    {
                        key: 'deoia_google_calendar_syncs',
                        remaining: 60,
                        can_consume: true,
                        at_limit: false
                    }
                ],
                shared_pools: {
                    calendar_and_push: makePool({ remaining: 66, breakdown: { calendar: 2, push: 1 } })
                }
            })
        );

        assert.equal(plan.items[2].remainingLine, 'Cuota restante: 66');
        assert.notEqual(plan.items[2].remainingLine, 'Cuota restante: 60');
    });

    it('reserved no se suma visualmente a Push en el breakdown', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(
            makeBenefitQuotas({
                shared_pools: {
                    calendar_and_push: makePool({
                        reserved: 5,
                        allocated: 8,
                        remaining: 62,
                        breakdown: { calendar: 2, push: 1 }
                    })
                }
            })
        );

        assert.equal(plan.items[2].detailLine, 'Calendar: 2 · Push: 1');
        assert.equal(plan.items[2].detailLine.includes('Push: 6'), false);
    });

    it('remaining 0 muestra estado agotado del pool', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(
            makeBenefitQuotas({
                shared_pools: {
                    calendar_and_push: makePool({
                        remaining: 0,
                        at_limit: true,
                        can_consume: false,
                        breakdown: { calendar: 40, push: 30 }
                    })
                }
            })
        );

        assert.equal(
            plan.items[2].title,
            'Has agotado tu cuota de sincronizaciones Calendar y notificaciones push de este mes.'
        );
        assert.equal(plan.items[2].remainingLine, 'Cuota restante: 0');
    });

    it('limit 0 muestra estado agotado del pool', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(
            makeBenefitQuotas({
                shared_pools: {
                    calendar_and_push: makePool({
                        limit: 0,
                        consumed: 0,
                        reserved: 0,
                        allocated: 0,
                        remaining: 0,
                        can_consume: false,
                        at_limit: true,
                        breakdown: { calendar: 0, push: 0 }
                    })
                }
            })
        );

        assert.match(plan.items[2].title, /Has agotado tu cuota de sincronizaciones Calendar/);
    });

    it('pool ausente usa fallback Calendar legacy', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(makeBenefitQuotas({ shared_pools: {} }));
        assert.equal(plan.items.length, 3);
        assert.equal(plan.items[2].title, 'Google Calendar disponible');
        assert.equal(plan.items[2].remainingLine, 'Cuota restante: 60');
        assert.equal(plan.items[2].detailLine, null);
    });

    it('pool inválido usa fallback Calendar', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(
            makeBenefitQuotas({
                shared_pools: {
                    calendar_and_push: { remaining: 66 }
                }
            })
        );
        assert.equal(plan.items[2].title, 'Google Calendar disponible');
    });

    it('shared_pool_read_error sin pool usa fallback Calendar y no muestra el error', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(
            makeBenefitQuotas({
                shared_pool_read_error: 'reservations_unavailable',
                shared_pools: {}
            })
        );
        assert.equal(plan.items[2].title, 'Google Calendar disponible');
        assert.equal(plan.unavailableMessage, null);
        const serialized = JSON.stringify(plan);
        assert.equal(serialized.includes('reservations_unavailable'), false);
    });

    it('nunca aparece tarjeta Push independiente', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(
            makeBenefitQuotas({
                items: [
                    ...makeBenefitQuotas().items,
                    {
                        key: 'deoia_push_notifications',
                        remaining: 9,
                        can_consume: true,
                        at_limit: false
                    }
                ],
                shared_pools: { calendar_and_push: makePool() }
            })
        );
        assert.equal(plan.items.length, 3);
        assert.equal(
            plan.items.some((i) => String(i.title).toLowerCase().includes('push') && !i.detailLine),
            false
        );
    });

    it('Email e IA continúan iguales con pool', () => {
        const plan = ux.buildBenefitQuotasRenderPlan(
            makeBenefitQuotas({
                shared_pools: { calendar_and_push: makePool() }
            })
        );
        assert.equal(plan.items[0].title, 'Emailing disponible');
        assert.equal(plan.items[0].remainingLine, 'Cuota restante: 25');
        assert.equal(plan.items[1].title, 'Has agotado tu cuota de solicitudes IA de este mes.');
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
            benefit_quotas: makeBenefitQuotas({
                shared_pools: { calendar_and_push: makePool() }
            })
        };

        const presentation = account.buildAccountPresentation(status);
        const plan = ux.buildBenefitQuotasRenderPlan(status.benefit_quotas);

        assert.equal(presentation.primaryNotice, '');
        assert.equal(plan.visible, true);
        assert.equal(plan.items[2].detailLine, 'Calendar: 2 · Push: 1');
    });
});
