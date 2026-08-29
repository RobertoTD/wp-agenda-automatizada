'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');

const servicePath = path.join(__dirname, '../../assets/js/services/shellAccessProjection.js');
// El IIFE de navegador se auto-inhibe en Node (typeof window === 'undefined').
const { __test } = require(servicePath);

describe('shellAccessProjection helpers (UX-only)', () => {
    it('cacheKey aísla por blogId + authSessionId', () => {
        assert.equal(__test.cacheKey(3, 'sess-a'), 'aa_shell_access:3:sess-a');
        assert.notEqual(__test.cacheKey(3, 'sess-a'), __test.cacheKey(3, 'sess-b'));
        assert.notEqual(__test.cacheKey(3, 'sess-a'), __test.cacheKey(4, 'sess-a'));
        assert.equal(__test.cacheKey(null, undefined), 'aa_shell_access::');
    });

    it('solo cachea full y free concluyentes (no legal_gate ni errores)', () => {
        assert.equal(__test.isCacheable('full', 'documents_accepted'), true);
        assert.equal(__test.isCacheable('free', 'no_subscription'), true);
        // legal_gate nunca se cachea (evento de una sola actuación)
        assert.equal(__test.isCacheable('legal_gate', 'documents_pending'), false);
        // free derivado de fallo de transporte / desconocido = inconcluso → no cachear
        assert.equal(__test.isCacheable('free', 'transport_error'), false);
        assert.equal(__test.isCacheable('free', 'unknown'), false);
        // estados no concluyentes
        assert.equal(__test.isCacheable('pending', ''), false);
        assert.equal(__test.isCacheable('', ''), false);
    });

    it('isFresh respeta TTL y solo full/free', () => {
        const now = 1000000;
        assert.equal(__test.isFresh({ access: 'full', ts: now - 1000 }, now, 60000), true);
        assert.equal(__test.isFresh({ access: 'free', ts: now - 59999 }, now, 60000), true);
        // expirado por TTL
        assert.equal(__test.isFresh({ access: 'full', ts: now - 60001 }, now, 60000), false);
        // access no cacheable aunque esté fresco
        assert.equal(__test.isFresh({ access: 'legal_gate', ts: now }, now, 60000), false);
        // entradas inválidas
        assert.equal(__test.isFresh(null, now, 60000), false);
        assert.equal(__test.isFresh({ access: 'full' }, now, 60000), false);
    });

    it('buildGateUrl construye URL con aa_gate y _wpnonce a partir de URL base segura', () => {
        const base = 'https://example.com/wp-admin/admin-post.php?action=aa_iframe_content&module=canonical&family=finance&variant=general';
        const gateUrl = __test.buildGateUrl(base, 'aa_gate', 'testnonce123');

        assert.ok(gateUrl.includes('action=aa_iframe_content'));
        assert.ok(gateUrl.includes('module=canonical'));
        assert.ok(gateUrl.includes('family=finance'));
        assert.ok(gateUrl.includes('variant=general'));
        assert.ok(gateUrl.includes('aa_gate=1'));
        assert.ok(gateUrl.includes('_wpnonce=testnonce123'));
        assert.equal(gateUrl.includes('arbitrary'), false);
    });
});
