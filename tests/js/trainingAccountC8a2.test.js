'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { describe, it } = require('node:test');

const uxPath = path.join(__dirname, '../../assets/js/services/trainingAccountUx.js');
const ux = require(uxPath);

const accountModulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/account/module.js'
);
const accountModuleSrc = fs.readFileSync(accountModulePath, 'utf8');

const trainingModulePath = path.join(
    __dirname,
    '../../includes/admin/ui/modules/training/module.js'
);
const trainingModule = require(trainingModulePath);
const trainingModuleSrc = fs.readFileSync(trainingModulePath, 'utf8');

describe('TrainingAccountUx enrollment states (C8A2)', () => {
    it('1. Loading inicial', () => {
        const p = ux.buildEnrollmentPresentation(ux.ACCESS.LOADING);
        assert.equal(p.accessState, 'loading');
        assert.match(p.copy, /Consultando tu capacitación/i);
        assert.equal(p.primaryAction, null);
        assert.equal(p.showConsent, false);
    });

    it('2. not_eligible sin CTA', () => {
        const p = ux.buildEnrollmentPresentation(ux.ACCESS.NOT_ELIGIBLE);
        assert.match(p.copy, /no está disponible con tu acceso actual/i);
        assert.equal(p.primaryAction, null);
        assert.equal(p.secondaryAction, null);
        assert.equal(p.showConsent, false);
    });

    it('3. not_enrolled muestra Inscribirme', () => {
        const p = ux.buildEnrollmentPresentation(ux.ACCESS.NOT_ENROLLED);
        assert.match(p.copy, /Método DEOIA/i);
        assert.equal(p.primaryAction.id, 'enroll');
        assert.equal(p.primaryAction.label, 'Inscribirme');
    });

    it('4. active muestra Abrir curso', () => {
        const p = ux.buildEnrollmentPresentation(ux.ACCESS.ACTIVE);
        assert.equal(p.copy, 'Tu curso está activo.');
        assert.equal(p.primaryAction.id, 'open');
        assert.equal(p.primaryAction.label, 'Abrir curso');
        assert.equal(p.primaryAction.kind, 'link');
        assert.equal(p.secondaryAction.id, 'unsubscribe');
        assert.equal(p.showConsent, true);
    });

    it('5. unsubscribed muestra Reactivar', () => {
        const p = ux.buildEnrollmentPresentation(ux.ACCESS.UNSUBSCRIBED);
        assert.match(p.copy, /desinscribiste/i);
        assert.equal(p.primaryAction.id, 'reactivate');
        assert.equal(p.primaryAction.label, 'Reactivar curso');
    });

    it('6. suspended no permite abrir', () => {
        const p = ux.buildEnrollmentPresentation(ux.ACCESS.SUSPENDED);
        assert.match(p.copy, /acceso está suspendido/i);
        assert.equal(p.primaryAction, null);
        assert.equal(p.showConsent, false);
    });

    it('8. reactivate usa enroll', () => {
        assert.equal(ux.mapActionToService('reactivate'), 'enroll');
        assert.equal(ux.mapActionToService('enroll'), 'enroll');
    });

    it('9. unsubscribe requiere confirmación (mensaje definido)', () => {
        assert.match(ux.UNSUBSCRIBE_CONFIRM_MESSAGE, /desinscribirte/i);
        assert.match(accountModuleSrc, /UNSUBSCRIBE_CONFIRM_MESSAGE/);
        assert.match(accountModuleSrc, /confirmFn/);
    });

    it('10. error permite retry', () => {
        const p = ux.buildEnrollmentPresentation(ux.ACCESS.ERROR);
        assert.match(p.copy, /No pudimos consultar tu capacitación/i);
        assert.equal(p.primaryAction.id, 'retry');
        assert.equal(p.primaryAction.label, 'Reintentar');
        assert.doesNotMatch(p.copy, /training_|HMAC|backend/i);
    });

    it('course_unavailable sin CTA', () => {
        const p = ux.buildEnrollmentPresentation(ux.ACCESS.COURSE_UNAVAILABLE);
        assert.match(p.copy, /no está disponible temporalmente/i);
        assert.equal(p.primaryAction, null);
    });

    it('resolveAccessState lee access_state del payload', () => {
        assert.equal(ux.resolveAccessState({ access_state: 'active' }), 'active');
        assert.equal(ux.resolveAccessState({ access_state: 'not_enrolled' }), 'not_enrolled');
        assert.equal(ux.resolveAccessState({}), 'error');
        assert.equal(ux.resolveAccessState(null), 'error');
    });
});

describe('TrainingAccountUx consent (C8A2)', () => {
    it('12. solo se consulta/renderiza separadamente (showConsent active-only)', () => {
        assert.equal(ux.buildEnrollmentPresentation(ux.ACCESS.ACTIVE).showConsent, true);
        assert.equal(ux.buildEnrollmentPresentation(ux.ACCESS.NOT_ENROLLED).showConsent, false);
        assert.equal(ux.buildEnrollmentPresentation(ux.ACCESS.UNSUBSCRIBED).showConsent, false);
        assert.match(accountModuleSrc, /loadTrainingConsent/);
        assert.match(accountModuleSrc, /getConsentStatus/);
    });

    it('13. not_accepted permite aceptar', () => {
        const state = ux.resolveConsentUiState({ status: 'not_accepted' });
        const p = ux.buildConsentPresentation(state);
        assert.equal(state, 'not_accepted');
        assert.equal(p.primaryAction.id, 'accept');
        assert.equal(p.primaryAction.label, 'Aceptar correos del curso');
        assert.match(p.intro, /guías, materiales y capacitación/i);
    });

    it('14. accepted permite revocar', () => {
        const state = ux.resolveConsentUiState({
            status: 'accepted',
            text_version: ux.CURRENT_CONSENT_TEXT_VERSION
        });
        const p = ux.buildConsentPresentation(state);
        assert.equal(state, 'accepted');
        assert.match(p.statusCopy, /Recibes guías y materiales/i);
        assert.equal(p.secondaryAction.id, 'revoke');
        assert.equal(p.secondaryAction.label, 'Dejar de recibirlos');
        assert.doesNotMatch(p.statusCopy, /text_version|training-email/i);
    });

    it('15. revoked permite reaceptar', () => {
        const state = ux.resolveConsentUiState({ status: 'revoked' });
        const p = ux.buildConsentPresentation(state);
        assert.equal(state, 'revoked');
        assert.equal(p.primaryAction.id, 'accept');
    });

    it('16. reaccept_required muestra copy correspondiente', () => {
        const state = ux.resolveConsentUiState({
            status: 'accepted',
            text_version: 'training-email-v0'
        });
        const p = ux.buildConsentPresentation(state);
        assert.equal(state, 'reaccept_required');
        assert.match(p.statusCopy, /Actualizamos la autorización/i);
        assert.equal(p.primaryAction.label, 'Aceptar nuevamente');
    });

    it('17. revocar no oculta Abrir curso (active sigue con open)', () => {
        const enroll = ux.buildEnrollmentPresentation(ux.ACCESS.ACTIVE);
        assert.equal(enroll.primaryAction.id, 'open');
        assert.equal(enroll.showConsent, true);
        const consent = ux.buildConsentPresentation(ux.CONSENT.ACCEPTED);
        assert.equal(consent.secondaryAction.id, 'revoke');
    });

    it('18. fallo de consentimiento no bloquea enrollment', () => {
        const enroll = ux.buildEnrollmentPresentation(ux.ACCESS.ACTIVE);
        const consentErr = ux.buildConsentPresentation(ux.CONSENT.ERROR);
        assert.equal(enroll.primaryAction.id, 'open');
        assert.equal(consentErr.primaryAction.id, 'consent_retry');
        assert.match(accountModuleSrc, /Keep enrollment UI|loadTrainingConsent/);
    });
});

describe('Account module Training wiring', () => {
    it('7. enroll refresca status', () => {
        assert.match(accountModuleSrc, /service\.enroll\(\)/);
        assert.match(accountModuleSrc, /runTrainingEnrollmentMutation/);
        assert.match(accountModuleSrc, /loadTrainingStatus\(\{ silent: true \}\)/);
    });

    it('11. fallo Training no rompe Cuenta (try/catch + init separado)', () => {
        assert.match(accountModuleSrc, /initTrainingCard\(\)/);
        assert.match(accountModuleSrc, /Training card failed to init/);
        assert.match(accountModuleSrc, /init\(\);\s*initTrainingCard\(\)/);
    });

    it('no muestra códigos técnicos en copy de error', () => {
        const p = ux.buildEnrollmentPresentation(ux.ACCESS.ERROR);
        assert.doesNotMatch(p.copy, /training_[a-z_]+/);
    });
});

describe('Training module shell', () => {
    it('24. portal C8A3 usa getCourse/getLesson', () => {
        assert.match(trainingModuleSrc, /getCourse/);
        assert.match(trainingModuleSrc, /getLesson/);
        assert.match(trainingModuleSrc, /backToCatalog/);
    });

    it('slots catálogo/lección se gestionan en module.js', () => {
        assert.match(trainingModuleSrc, /aa-training-catalog-root/);
        assert.match(trainingModuleSrc, /aa-training-lesson-root/);
    });
});

describe('Abrir curso navigation URL (C8A2 fix)', () => {
    const accountIndexPath = path.join(
        __dirname,
        '../../includes/admin/ui/modules/account/index.php'
    );
    const trainingIndexPath = path.join(
        __dirname,
        '../../includes/admin/ui/modules/training/index.php'
    );
    const accountIndexSrc = fs.readFileSync(accountIndexPath, 'utf8');
    const trainingIndexSrc = fs.readFileSync(trainingIndexPath, 'utf8');

    it('trainingModuleUrl se publica con wp_json_encode y &module=training', () => {
        assert.match(accountIndexSrc, /trainingModuleUrl:\s*<\?php echo wp_json_encode\(/);
        assert.match(accountIndexSrc, /&module=training/);
        assert.doesNotMatch(accountIndexSrc, /trainingModuleUrl:[^\n]*esc_js/);
        assert.doesNotMatch(accountIndexSrc, /trainingModuleUrl:[^\n]*&amp;/);
        assert.doesNotMatch(accountIndexSrc, /trainingModuleUrl:[^\n]*&#038;/);

        assert.match(trainingIndexSrc, /trainingModuleUrl:\s*<\?php echo wp_json_encode\(/);
        assert.match(trainingIndexSrc, /&module=training/);
    });

    it('CTA asigna trainingModuleUrl lógica a el.href sin strip de amp', () => {
        assert.match(
            accountModuleSrc,
            /el\.href = typeof cfg\.trainingModuleUrl === 'string' \? cfg\.trainingModuleUrl : '#'/
        );
        assert.doesNotMatch(accountModuleSrc, /trainingModuleUrl\.replace/);
        assert.doesNotMatch(accountModuleSrc, /&amp;.*module=training/);
    });

    it('Volver a Cuenta conserva &module=account en admin_url (escape HTML solo en href)', () => {
        assert.match(
            trainingIndexSrc,
            /admin_url\('admin-post\.php\?action=aa_iframe_content&module=account'\)/
        );
        assert.match(trainingIndexSrc, /esc_url\(\$aa_training_account_url\)/);
    });
});
