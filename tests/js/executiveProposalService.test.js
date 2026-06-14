'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');

const servicePath = path.join(__dirname, '../../assets/js/services/executiveProposalService.js');

describe('executiveProposalService MC3', () => {
    it('expone postExecutiveAction y usa actionPost configurado', () => {
        const serviceSrc = fs.readFileSync(servicePath, 'utf8');

        assert.match(serviceSrc, /postExecutiveAction/);
        assert.match(serviceSrc, /actionPost/);
        assert.match(serviceSrc, /aa_executive_action/);
        assert.match(serviceSrc, /task_id/);
        assert.match(serviceSrc, /action_key/);
        assert.match(serviceSrc, /client_action/);
    });
});
