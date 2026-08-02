'use strict';

const assert = require('node:assert/strict');
const { describe, it, beforeEach } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const modulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/clients/expediente-registros.js'
);
const moduleSrc = fs.readFileSync(modulePath, 'utf8');

function loadModule(overrides) {
    const opts = overrides || {};
    const toastCalls = [];
    const sandbox = {
        window: {
            AAAdmin: {
                toast: {
                    show: function (notification, options) {
                        toastCalls.push({ notification: notification, options: options });
                    },
                    showMany: function () {
                        toastCalls.push({ showMany: true, args: Array.prototype.slice.call(arguments) });
                    }
                }
            },
            AccountStatusService: opts.AccountStatusService,
            location: opts.location || {
                href: 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=clients'
            }
        },
        document: {
            createElement: () => ({}),
            getElementById: () => null,
            querySelector: () => null,
            contains: () => true
        },
        console,
        fetch: () => Promise.resolve({ status: 200, json: async () => ({}) }),
        URL: typeof URL !== 'undefined'
            ? URL
            : {
                createObjectURL: () => 'blob:test',
                revokeObjectURL: () => {}
            },
        Image: function () {},
        setTimeout: (fn) => fn(),
        clearTimeout: () => {},
        FormData: function () {
            this.append = () => {};
        },
        Blob: function () {},
        File: function () {},
        Promise,
        Math,
        JSON,
        String,
        Number,
        Array,
        Object,
        parseInt,
        isNaN,
        Error,
        Object: Object
    };

    // Ensure URL constructor works in sandbox when available.
    if (typeof URL !== 'undefined') {
        sandbox.URL = URL;
    }

    vm.runInNewContext(moduleSrc, sandbox, { filename: 'expediente-registros.js' });
    const mod = sandbox.window.AAAdmin.ExpedienteRegistros.__test__;
    return { mod: mod, toastCalls: toastCalls, sandbox: sandbox };
}

function hasDigits(text) {
    return /\d/.test(String(text || ''));
}

function collectCopy(notification) {
    return [
        notification.title,
        notification.message,
        notification.fallback,
        (notification.details || []).join(' ')
    ].join(' ');
}

describe('expediente save toast — resolveAccount', () => {
    let mod;

    beforeEach(() => {
        mod = loadModule().mod;
        mod.resetAccountPromise();
    });

    it('distinguishes freemium real from pro past_due', () => {
        const freemium = mod.resolveAccount({
            billing_state: 'active',
            plan_tier: 'freemium',
            effective_access_tier: 'freemium',
            payment_action_required: false,
            upgrade_to_pro_available: true
        });
        const pastDue = mod.resolveAccount({
            billing_state: 'inactive',
            plan_tier: 'pro',
            effective_access_tier: 'freemium',
            payment_action_required: true,
            upgrade_to_pro_available: false
        });

        assert.equal(freemium.commercialState, 'freemium');
        assert.equal(freemium.upgradeAvailable, true);
        assert.equal(pastDue.commercialState, 'pro_past_due');
        assert.equal(pastDue.upgradeAvailable, false);
    });

    it('does not infer past_due from effective tier alone', () => {
        const result = mod.resolveAccount({
            plan_tier: 'pro',
            effective_access_tier: 'freemium',
            payment_action_required: false
        });
        assert.equal(result.commercialState, 'unknown');
    });

    it('payment_action_required with non-matching tiers → unknown', () => {
        const result = mod.resolveAccount({
            plan_tier: 'freemium',
            effective_access_tier: 'freemium',
            payment_action_required: true
        });
        assert.equal(result.commercialState, 'unknown');
    });

    it('sync_pending wins over payment_action_required', () => {
        const result = mod.resolveAccount({
            sync_pending: true,
            plan_tier: 'pro',
            effective_access_tier: 'freemium',
            payment_action_required: true
        });
        assert.equal(result.commercialState, 'unknown');
    });

    it('classifies free, pro_active and null/empty safely', () => {
        assert.equal(
            mod.resolveAccount({ effective_access_tier: 'free' }).commercialState,
            'free'
        );
        assert.equal(
            mod.resolveAccount({ effective_access_tier: 'pro' }).commercialState,
            'pro_active'
        );
        assert.equal(mod.resolveAccount(null).commercialState, 'unknown');
        assert.equal(mod.resolveAccount(undefined).commercialState, 'unknown');
        assert.equal(mod.resolveAccount({}).commercialState, 'unknown');
    });

    it('upgradeAvailable only when upgrade_to_pro_available === true', () => {
        assert.equal(
            mod.resolveAccount({
                plan_tier: 'freemium',
                effective_access_tier: 'freemium',
                upgrade_to_pro_available: true
            }).upgradeAvailable,
            true
        );
        assert.equal(
            mod.resolveAccount({
                plan_tier: 'freemium',
                effective_access_tier: 'freemium',
                upgrade_to_pro_available: 'true'
            }).upgradeAvailable,
            false
        );
    });
});

describe('expediente save toast — buildSaveNotification', () => {
    let mod;

    beforeEach(() => {
        mod = loadModule().mod;
    });

    it('T1: save without image — success, no actions', () => {
        const created = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'none',
            failureCode: '',
            account: null
        });
        const updated = mod.buildSaveNotification({
            recordOutcome: 'updated',
            imageOutcome: 'none',
            failureCode: '',
            account: mod.UNKNOWN_ACCOUNT
        });

        assert.equal(created.severity, 'success');
        assert.equal(created.title, 'Registro guardado');
        assert.equal(created.message, '');
        assert.equal(created.fallback, null);
        assert.equal(created.actions.length, 0);

        assert.equal(updated.title, 'Registro actualizado');
        assert.equal(updated.actions.length, 0);
    });

    it('T2: image saved for non-degraded states — no past_due warning', () => {
        ['freemium', 'pro_active', 'free', 'unknown'].forEach(function (state) {
            const n = mod.buildSaveNotification({
                recordOutcome: 'created',
                imageOutcome: 'saved',
                failureCode: '',
                account: { commercialState: state, upgradeAvailable: false }
            });
            assert.equal(n.severity, 'success');
            assert.equal(n.message, mod.IMAGE_SAVED_MESSAGE);
            assert.equal(n.fallback, null);
            assert.equal(n.actions.length, 0);
            assert.equal(n.message.includes('no se guardó'), false);
        });
    });

    it('T3: image saved under pro_past_due — success + warning fallback + billing CTA', () => {
        const n = mod.buildSaveNotification({
            recordOutcome: 'updated',
            imageOutcome: 'saved',
            failureCode: '',
            account: { commercialState: 'pro_past_due', upgradeAvailable: false }
        });

        assert.equal(n.severity, 'success');
        assert.equal(n.title, 'Registro actualizado');
        assert.equal(n.message, mod.IMAGE_SAVED_MESSAGE);
        assert.equal(n.fallback, mod.PAST_DUE_SUCCESS_FALLBACK);
        assert.equal(n.actions.length, 1);
        assert.equal(n.actions[0].label, 'Actualizar pago');
        assert.equal(n.actions[0].target, 'account_billing');
        assert.equal(n.actions[0].url, undefined);
        assert.equal(collectCopy(n).includes('no se guardó'), false);
    });

    it('T4: storage_not_included + free → Suscribirme; unknown → no CTA', () => {
        const free = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'failed',
            failureCode: 'storage_not_included',
            account: { commercialState: 'free', upgradeAvailable: false }
        });
        const unknown = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'failed',
            failureCode: 'storage_not_included',
            account: null
        });

        assert.equal(free.severity, 'warning');
        assert.equal(free.message, mod.STORAGE_NOT_INCLUDED_TOAST_MESSAGE);
        assert.equal(free.actions[0].label, 'Suscribirme');
        assert.equal(free.actions[0].target, 'settings_freemium');

        assert.equal(unknown.message, mod.STORAGE_NOT_INCLUDED_TOAST_MESSAGE);
        assert.equal(unknown.actions.length, 0);
    });

    it('T5: freemium quota — Adquirir Pro only when upgradeAvailable', () => {
        const withUpgrade = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'failed',
            failureCode: 'storage_quota_exceeded',
            account: { commercialState: 'freemium', upgradeAvailable: true }
        });
        const withoutUpgrade = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'failed',
            failureCode: 'storage_quota_exceeded',
            account: { commercialState: 'freemium', upgradeAvailable: false }
        });

        assert.equal(withUpgrade.message, mod.STORAGE_QUOTA_FREEMIUM_MESSAGE);
        assert.equal(withUpgrade.actions[0].label, 'Adquirir Pro');
        assert.equal(withUpgrade.actions[0].target, 'account_upgrade');

        assert.equal(withoutUpgrade.message, mod.STORAGE_QUOTA_FREEMIUM_MESSAGE);
        assert.equal(withoutUpgrade.actions.length, 0);
    });

    it('T6: past_due quota — billing CTA and Freemium limit copy', () => {
        const n = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'failed',
            failureCode: 'storage_quota_exceeded',
            account: { commercialState: 'pro_past_due', upgradeAvailable: false }
        });

        assert.equal(n.message, mod.STORAGE_QUOTA_PAST_DUE_MESSAGE);
        assert.equal(n.actions[0].label, 'Actualizar pago');
        assert.equal(n.actions[0].target, 'account_billing');
        assert.match(n.message, /suspendidos/);
        assert.match(n.message, /límite Freemium/);
    });

    it('T7: pro_active quota — no CTA, no upgrade mention', () => {
        const n = mod.buildSaveNotification({
            recordOutcome: 'updated',
            imageOutcome: 'failed',
            failureCode: 'storage_quota_exceeded',
            account: { commercialState: 'pro_active', upgradeAvailable: false }
        });

        assert.equal(n.message, mod.STORAGE_QUOTA_PRO_MESSAGE);
        assert.equal(n.actions.length, 0);
        assert.equal(collectCopy(n).toLowerCase().includes('adquirir'), false);
        assert.equal(collectCopy(n).toLowerCase().includes('upgrade'), false);
    });

    it('T8: unknown quota — generic copy, no CTA, no invented tier names in CTA sense', () => {
        const n = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'failed',
            failureCode: 'storage_quota_exceeded',
            account: { commercialState: 'unknown', upgradeAvailable: true }
        });

        assert.equal(n.message, mod.STORAGE_QUOTA_GENERIC_MESSAGE);
        assert.equal(n.actions.length, 0);
        assert.equal(n.message.includes('Freemium'), false);
        assert.equal(n.message.includes('Pro'), false);
        assert.equal(n.message.includes('pago'), false);
    });

    it('never embeds byte figures in produced copy', () => {
        const cases = [
            { imageOutcome: 'none', failureCode: '', account: null },
            {
                imageOutcome: 'saved',
                failureCode: '',
                account: { commercialState: 'pro_past_due', upgradeAvailable: false }
            },
            {
                imageOutcome: 'failed',
                failureCode: 'storage_not_included',
                account: { commercialState: 'free', upgradeAvailable: false }
            },
            {
                imageOutcome: 'failed',
                failureCode: 'storage_quota_exceeded',
                account: { commercialState: 'freemium', upgradeAvailable: true }
            },
            {
                imageOutcome: 'failed',
                failureCode: 'storage_quota_exceeded',
                account: { commercialState: 'pro_past_due', upgradeAvailable: false }
            },
            {
                imageOutcome: 'failed',
                failureCode: 'storage_quota_exceeded',
                account: { commercialState: 'pro_active', upgradeAvailable: false }
            }
        ];

        cases.forEach(function (c) {
            const n = mod.buildSaveNotification(
                Object.assign({ recordOutcome: 'created' }, c)
            );
            const copy = collectCopy(n);
            assert.equal(hasDigits(copy), false, 'digits in: ' + copy);
            assert.equal(copy.includes('MiB'), false);
            assert.equal(copy.includes('GiB'), false);
            assert.equal(copy.includes('12582912'), false);
            assert.equal(copy.includes('2147483648'), false);
        });
    });

    it('actions never carry url; only symbolic target', () => {
        const n = mod.buildSaveNotification({
            recordOutcome: 'created',
            imageOutcome: 'failed',
            failureCode: 'storage_quota_exceeded',
            account: { commercialState: 'freemium', upgradeAvailable: true }
        });
        n.actions.forEach(function (action) {
            assert.equal(Object.prototype.hasOwnProperty.call(action, 'url'), false);
            assert.ok(
                ['account_billing', 'account_upgrade', 'settings_freemium'].indexOf(action.target) >= 0
            );
        });
    });
});

describe('expediente save toast — orchestration helpers', () => {
    it('urlForToastTarget builds canonical module URLs from location', () => {
        const { mod } = loadModule({
            location: {
                href: 'https://example.test/wp-admin/admin-post.php?action=aa_iframe_content&module=clients&view=expediente'
            }
        });

        const billing = mod.urlForToastTarget('account_billing');
        const upgrade = mod.urlForToastTarget('account_upgrade');
        const freemium = mod.urlForToastTarget('settings_freemium');

        assert.match(billing, /action=aa_iframe_content/);
        assert.match(billing, /module=account/);
        assert.match(billing, /#aa-account-billing-button$/);

        assert.match(upgrade, /module=account/);
        assert.match(upgrade, /#aa-account-upgrade-section$/);

        assert.match(freemium, /module=settings/);
        assert.match(freemium, /setup_focus=google_calendar/);
        assert.match(freemium, /#aa-google-calendar-root$/);

        assert.equal(mod.urlForToastTarget('unknown'), '');
    });

    it('emitToast translates targets to urls and disables autoDismiss when CTA present', () => {
        const { mod, toastCalls } = loadModule();
        mod.emitToast(
            mod.buildSaveNotification({
                recordOutcome: 'created',
                imageOutcome: 'failed',
                failureCode: 'storage_not_included',
                account: { commercialState: 'free', upgradeAvailable: false }
            })
        );

        assert.equal(toastCalls.length, 1);
        assert.equal(toastCalls[0].showMany, undefined);
        assert.equal(toastCalls[0].notification.actions.length, 1);
        assert.match(toastCalls[0].notification.actions[0].url, /module=settings/);
        assert.equal(toastCalls[0].options.autoDismiss, false);
    });

    it('emitToast autoDismisses when no CTA', () => {
        const { mod, toastCalls } = loadModule();
        mod.emitToast(
            mod.buildSaveNotification({
                recordOutcome: 'created',
                imageOutcome: 'none',
                failureCode: '',
                account: null
            })
        );
        assert.equal(toastCalls.length, 1);
        assert.equal(toastCalls[0].options.autoDismiss, true);
        assert.equal(toastCalls[0].notification.actions.length, 0);
    });

    it('primeAccountStatus never rejects and degrades when service missing', async () => {
        const { mod } = loadModule({ AccountStatusService: undefined });
        mod.resetAccountPromise();
        const account = await mod.primeAccountStatus();
        assert.equal(account.commercialState, 'unknown');
        assert.equal(account.upgradeAvailable, false);
    });

    it('primeAccountStatus degrades on fetch rejection', async () => {
        const { mod } = loadModule({
            AccountStatusService: {
                fetchStatus: function () {
                    return Promise.reject(new Error('network'));
                }
            }
        });
        mod.resetAccountPromise();
        const account = await mod.primeAccountStatus();
        assert.equal(account.commercialState, 'unknown');
    });

    it('primeAccountStatus resolves classified account from payload', async () => {
        const { mod } = loadModule({
            AccountStatusService: {
                fetchStatus: function () {
                    return Promise.resolve({
                        account_status: {
                            plan_tier: 'pro',
                            effective_access_tier: 'freemium',
                            payment_action_required: true,
                            upgrade_to_pro_available: false
                        }
                    });
                }
            }
        });
        mod.resetAccountPromise();
        const account = await mod.primeAccountStatus();
        assert.equal(account.commercialState, 'pro_past_due');
    });

    it('primeAccountStatus is memoized (single fetch)', async () => {
        let calls = 0;
        const { mod } = loadModule({
            AccountStatusService: {
                fetchStatus: function () {
                    calls += 1;
                    return Promise.resolve({
                        account_status: {
                            plan_tier: 'freemium',
                            effective_access_tier: 'freemium',
                            upgrade_to_pro_available: true
                        }
                    });
                }
            }
        });
        mod.resetAccountPromise();
        await mod.primeAccountStatus();
        await mod.primeAccountStatus();
        assert.equal(calls, 1);
    });

    it('messageForAttachFailure stays generic for technical inline path', () => {
        const { mod } = loadModule();
        assert.equal(mod.messageForAttachFailure('authorize_failed'), mod.PARTIAL_ATTACH_MESSAGE);
        assert.equal(mod.messageForAttachFailure('storage_not_included'), mod.PARTIAL_ATTACH_MESSAGE);
    });

    it('source wires commercial close path and primes account-status', () => {
        assert.match(moduleSrc, /function buildSaveNotification/);
        assert.match(moduleSrc, /function finishWithToast/);
        assert.match(moduleSrc, /primeAccountStatus\(\)/);
        assert.match(moduleSrc, /storage_not_included/);
        assert.match(moduleSrc, /storage_quota_exceeded/);
        assert.match(moduleSrc, /finishWithToast\(savedRecordOutcome/);
        assert.match(moduleSrc, /autoDismiss:\s*actions\.length === 0/);
        assert.equal(moduleSrc.includes('showMany'), false);
    });
});
