'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach, afterEach } = require('node:test');
const path = require('node:path');

const uxPath = path.join(__dirname, '../../assets/js/services/accountUpgradeUx.js');
const accountPath = path.join(__dirname, '../../includes/admin/ui/modules/account/module.js');
const ux = require(uxPath);
const account = require(accountPath);

const freemiumUpgradeEligible = {
    billing_state: 'active',
    plan_tier: 'freemium',
    effective_access_tier: 'freemium',
    payment_action_required: false,
    sync_pending: false,
    is_cancel_scheduled: false,
    upgrade_to_pro_available: true
};

const proActive = {
    billing_state: 'active',
    plan_tier: 'pro',
    effective_access_tier: 'pro',
    payment_action_required: false,
    sync_pending: false,
    is_cancel_scheduled: false,
    upgrade_to_pro_available: false
};

const proPaymentFailed = {
    billing_state: 'inactive',
    plan_tier: 'pro',
    effective_access_tier: 'freemium',
    payment_action_required: true,
    sync_pending: false,
    is_cancel_scheduled: false,
    upgrade_to_pro_available: false
};

describe('account upgrade UX', () => {
    it('Freemium activo con upgrade_to_pro_available muestra CTA', () => {
        assert.equal(ux.shouldShowUpgradeCta(freemiumUpgradeEligible), true);
        assert.equal(account.shouldShowUpgradeCta(freemiumUpgradeEligible), true);
    });

    it('Pro activo no muestra CTA', () => {
        assert.equal(ux.shouldShowUpgradeCta(proActive), false);
        assert.equal(account.shouldShowUpgradeCta(proActive), false);
    });

    it('Pro pago fallido no muestra CTA', () => {
        assert.equal(ux.shouldShowUpgradeCta(proPaymentFailed), false);
        assert.equal(account.shouldShowUpgradeCta(proPaymentFailed), false);
    });

    it('sin upgrade_to_pro_available o backend viejo no muestra CTA', () => {
        const legacyStatus = {
            billing_state: 'active',
            plan_tier: 'freemium',
            effective_access_tier: 'freemium'
        };

        assert.equal(ux.shouldShowUpgradeCta(legacyStatus), false);
        assert.equal(account.shouldShowUpgradeCta(legacyStatus), false);
        assert.equal(ux.shouldShowUpgradeCta({ upgrade_to_pro_available: false }), false);
    });

    it('click en Adquirir Pro muestra card única de Pro', () => {
        const closed = ux.buildUpgradeUiState(true, false);
        const open = ux.buildUpgradeUiState(true, true);

        assert.equal(closed.sectionVisible, true);
        assert.equal(closed.ctaVisible, true);
        assert.equal(closed.cardVisible, false);

        assert.equal(open.sectionVisible, true);
        assert.equal(open.ctaVisible, false);
        assert.equal(open.cardVisible, true);
    });

    it('no se renderiza comparativa Freemium en la card', () => {
        const open = ux.buildUpgradeUiState(true, true);
        assert.equal(open.cardVisible, true);
        assert.equal(open.sectionVisible, true);
    });

    it('isSafeStripeCheckoutUrl acepta checkout.stripe.com y rechaza otros hosts', () => {
        assert.equal(
            ux.isSafeStripeCheckoutUrl('https://checkout.stripe.com/c/pay/cs_test'),
            true
        );
        assert.equal(account.isSafeStripeCheckoutUrl('https://checkout.stripe.com/c/pay/cs_test'), true);
        assert.equal(ux.isSafeStripeCheckoutUrl('https://evil.example.com/phish'), false);
    });

    it('mapUpgradeCheckoutErrorToUi distingue upgrade_unavailable', () => {
        const message = ux.mapUpgradeCheckoutErrorToUi({ code: 'upgrade_unavailable' });
        assert.match(message, /ya no está disponible para upgrade/i);
    });

    it('parseUpgradeReturnNotice maneja success y cancelled', () => {
        const success = ux.parseUpgradeReturnNotice('?upgrade=success');
        const cancelled = ux.parseUpgradeReturnNotice('?upgrade=cancelled');

        assert.match(success.notice, /Actualizando estado de cuenta/i);
        assert.match(cancelled.notice, /checkout fue cancelado/i);
    });
});

describe('account upgrade checkout AJAX', () => {
    const originalFetch = global.fetch;
    const originalWindow = global.window;

    beforeEach(() => {
        global.window = {
            AA_ACCOUNT_DATA: {
                ajaxUrl: 'https://tenant.example.com/wp-admin/admin-ajax.php',
                upgradeCheckoutNonce: 'upgrade-nonce'
            },
            ajaxurl: 'https://tenant.example.com/wp-admin/admin-ajax.php'
        };
    });

    afterEach(() => {
        global.fetch = originalFetch;
        global.window = originalWindow;
    });

    it('Continuar con PRO llama AJAX y devuelve checkout_url', async () => {
        let capturedBody = '';
        global.fetch = async (_url, options) => {
            capturedBody = options.body.toString();
            return {
                json: async () => ({
                    success: true,
                    data: { checkout_url: 'https://checkout.stripe.com/c/pay/cs_test' }
                })
            };
        };

        const result = await account.createUpgradeCheckoutSession();

        assert.equal(result.ok, true);
        assert.equal(result.checkout_url, 'https://checkout.stripe.com/c/pay/cs_test');
        assert.match(capturedBody, /action=aa_create_upgrade_checkout_session/);
        assert.match(capturedBody, /_wpnonce=upgrade-nonce/);
    });

    it('error AJAX muestra mensaje y no redirige', async () => {
        global.fetch = async () => ({
            json: async () => ({
                success: false,
                data: { code: 'upgrade_backend_error', message: 'No pudimos abrir el checkout de Pro. Intenta de nuevo.' }
            })
        });

        const result = await account.createUpgradeCheckoutSession();

        assert.equal(result.ok, false);
        assert.match(result.message, /No pudimos abrir el checkout de Pro/i);
    });
});
