'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const suppressionPath = path.join(
    __dirname,
    '../../includes/admin/ui/tutorials/tutorialSessionSuppression.js'
);
const suppressionSrc = fs.readFileSync(suppressionPath, 'utf8');

function makeSessionStorage() {
    var store = {};

    return {
        getItem: function (key) {
            return Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null;
        },
        setItem: function (key, value) {
            store[key] = String(value);
        },
        removeItem: function (key) {
            delete store[key];
        },
        dump: function () {
            return Object.assign({}, store);
        }
    };
}

function loadSuppression(options) {
    var opts = options || {};
    var context = {
        window: {},
        console: { warn: function () {} }
    };

    context.window = context;
    context.window.AA_ADMIN_CONTEXT = {
        blogId: opts.blogId || 44,
        authSessionId: opts.authSessionId || 'auth-session-a'
    };
    context.window.AATutorialSession = {
        resolveBlogId: function () {
            return String(context.window.AA_ADMIN_CONTEXT.blogId);
        }
    };
    context.window.sessionStorage = opts.sessionStorage || makeSessionStorage();

    vm.runInNewContext(suppressionSrc, context, { filename: suppressionPath });

    return {
        TutorialSessionSuppression: context.window.TutorialSessionSuppression,
        sessionStorage: context.window.sessionStorage,
        AA_ADMIN_CONTEXT: context.window.AA_ADMIN_CONTEXT
    };
}

describe('TutorialSessionSuppression', () => {
    it('clave scoped por blogId flowId y authSessionId', () => {
        var env = loadSuppression({ blogId: 7, authSessionId: 'sess-1' });
        var key = env.TutorialSessionSuppression.buildKey('7', 'create_test_appointment_v1', 'sess-1');

        assert.equal(key, 'aa_tutorial_suppressed_v1:7:create_test_appointment_v1:sess-1');
    });

    it('misma authSessionId persiste suppression entre reloads simulados', () => {
        var storage = makeSessionStorage();
        var first = loadSuppression({ authSessionId: 'sess-a', sessionStorage: storage });

        assert.equal(first.TutorialSessionSuppression.suppress('44', 'create_test_appointment_v1'), true);
        assert.equal(first.TutorialSessionSuppression.isSuppressed('44', 'create_test_appointment_v1'), true);

        var second = loadSuppression({ authSessionId: 'sess-a', sessionStorage: storage });
        assert.equal(second.TutorialSessionSuppression.isSuppressed('44', 'create_test_appointment_v1'), true);
    });

    it('authSessionId distinta no aplica suppression anterior', () => {
        var storage = makeSessionStorage();
        var first = loadSuppression({ authSessionId: 'sess-a', sessionStorage: storage });

        first.TutorialSessionSuppression.suppress('44', 'create_test_appointment_v1');

        var second = loadSuppression({ authSessionId: 'sess-b', sessionStorage: storage });
        assert.equal(second.TutorialSessionSuppression.isSuppressed('44', 'create_test_appointment_v1'), false);
    });

    it('flowId distinto no comparte suppression', () => {
        var storage = makeSessionStorage();
        var env = loadSuppression({ authSessionId: 'sess-a', sessionStorage: storage });

        env.TutorialSessionSuppression.suppress('44', 'create_test_appointment_v1');

        assert.equal(env.TutorialSessionSuppression.isSuppressed('44', 'other_flow'), false);
    });

    it('blogId distinto no comparte suppression', () => {
        var storage = makeSessionStorage();
        var env = loadSuppression({ blogId: 44, authSessionId: 'sess-a', sessionStorage: storage });

        env.TutorialSessionSuppression.suppress('44', 'create_test_appointment_v1');

        assert.equal(env.TutorialSessionSuppression.isSuppressed('99', 'create_test_appointment_v1'), false);
    });
});
