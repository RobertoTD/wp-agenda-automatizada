'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/account/module.js');
const account = require(modulePath);

describe('account module presentation', () => {
    it('Freemium activo: Activa, Freemium/Freemium, billing oculto, leyenda upgrade', () => {
        const status = {
            billing_state: 'active',
            plan_tier: 'freemium',
            effective_access_tier: 'freemium',
            payment_action_required: false,
            sync_pending: false,
            is_cancel_scheduled: false
        };

        const presentation = account.buildAccountPresentation(status);
        const billing = account.resolveBillingAction(status);

        assert.equal(account.resolveViewState(status), account.VIEW.ACTIVE);
        assert.equal(presentation.badgeLabel, 'Activa');
        assert.equal(presentation.plan, 'Freemium');
        assert.equal(presentation.access, 'Freemium');
        assert.match(presentation.primaryNotice, /upgrade en tu suscripción/i);
        assert.equal(billing.mode, 'hidden');
    });

    it('Pro activo: Activa, Pro/Pro, billing Gestionar suscripción', () => {
        const status = {
            billing_state: 'active',
            plan_tier: 'pro',
            effective_access_tier: 'pro',
            payment_action_required: false,
            sync_pending: false,
            is_cancel_scheduled: false
        };

        const presentation = account.buildAccountPresentation(status);
        const billing = account.resolveBillingAction(status);

        assert.equal(account.resolveViewState(status), account.VIEW.ACTIVE);
        assert.equal(presentation.badgeLabel, 'Activa');
        assert.equal(presentation.plan, 'Pro');
        assert.equal(presentation.access, 'Pro');
        assert.equal(presentation.primaryNotice, '');
        assert.equal(billing.mode, 'visible');
        assert.equal(billing.label, 'Gestionar suscripción');
    });

    it('Pro con pago pendiente: Pago pendiente, Pro/Freemium, billing visible, aviso pago', () => {
        const status = {
            billing_state: 'inactive',
            plan_tier: 'pro',
            effective_access_tier: 'freemium',
            payment_action_required: true,
            sync_pending: false,
            is_cancel_scheduled: false
        };

        const presentation = account.buildAccountPresentation(status);
        const billing = account.resolveBillingAction(status);

        assert.equal(account.resolveViewState(status), account.VIEW.PAYMENT_PENDING);
        assert.equal(presentation.badgeLabel, 'Pago pendiente');
        assert.equal(presentation.plan, 'Pro');
        assert.equal(presentation.access, 'Freemium');
        assert.match(presentation.primaryNotice, /El pago de tu suscripción no pudo realizarse/i);
        assert.match(presentation.primaryNotice, /Gestionar suscripción/i);
        assert.equal(billing.mode, 'visible');
        assert.equal(billing.label, 'Gestionar suscripción');
    });
});
