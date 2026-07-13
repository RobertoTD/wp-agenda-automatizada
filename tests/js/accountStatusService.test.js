'use strict';

const assert = require('node:assert/strict');
const { describe, it, afterEach } = require('node:test');
const path = require('node:path');

const servicePath = path.join(__dirname, '../../assets/js/services/accountStatusService.js');

function loadService(fetchImpl) {
    globalThis.AA_ACCOUNT_STATUS_DATA = {
        ajaxUrl: 'https://agenda.test/wp-admin/admin-ajax.php',
        action: 'aa_get_account_status',
        nonce: 'nonce'
    };
    globalThis.fetch = fetchImpl;
    delete require.cache[servicePath];
    return require(servicePath);
}

describe('AccountStatusService', () => {
    afterEach(() => {
        delete globalThis.AccountStatusService;
        delete globalThis.AA_ACCOUNT_STATUS_DATA;
        delete globalThis.fetch;
        delete require.cache[servicePath];
    });

    it('billing_state active es suscripción activa', () => {
        var service = loadService(function () {});

        assert.equal(service.isAppSubscriptionActive({
            account_status: {
                billing_state: 'active',
                plan_tier: 'freemium',
                effective_access_tier: 'freemium'
            }
        }), true);
        assert.equal(service.isAppSubscriptionActive({
            account_status: {
                billing_state: 'active',
                plan_tier: 'pro',
                effective_access_tier: 'pro'
            }
        }), true);
    });

    it('missing inactive y sync_pending no son suscripción activa', () => {
        var service = loadService(function () {});

        ['missing', 'inactive', 'sync_pending'].forEach(function (billingState) {
            assert.equal(service.isAppSubscriptionActive({
                account_status: {
                    billing_state: billingState,
                    effective_access_tier: 'freemium'
                }
            }), false);
        });
    });

    it('memoriza una sola promesa y una sola consulta por carga', async () => {
        var calls = 0;
        var service = loadService(function () {
            calls += 1;
            return Promise.resolve({
                ok: true,
                json: function () {
                    return Promise.resolve({
                        success: true,
                        data: { account_status: { billing_state: 'active' } }
                    });
                }
            });
        });

        var first = service.fetchStatus();
        var second = service.fetchStatus();

        assert.strictEqual(first, second);
        await first;
        assert.equal(calls, 1);
    });

    it('rechaza fallo account-status para que el consumidor cierre en false', async () => {
        var service = loadService(function () {
            return Promise.resolve({
                ok: false,
                status: 503,
                json: function () {
                    return Promise.resolve({});
                }
            });
        });

        await assert.rejects(service.fetchStatus(), /HTTP 503/);
    });
});
