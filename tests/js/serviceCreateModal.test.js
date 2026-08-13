'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

const modalPath = path.join(__dirname, '../../includes/admin/ui/modals/crearservicio/crearservicio.js');
const servicesPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/services-section/servicesSection.js');
const layoutPath = path.join(__dirname, '../../includes/admin/ui/shared/layout.php');
const assignmentsIndexPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/index.php');

const modalSrc = fs.readFileSync(modalPath, 'utf8');
const servicesSrc = fs.readFileSync(servicesPath, 'utf8');
const layoutSrc = fs.readFileSync(layoutPath, 'utf8');
const assignmentsIndexSrc = fs.readFileSync(assignmentsIndexPath, 'utf8');

describe('ServiceCreateModal edit', () => {
    it('expone openCreate y openEdit en AAAdmin.ServiceCreateModal', () => {
        assert.match(modalSrc, /AAAdmin\.ServiceCreateModal\s*=\s*\{/);
        assert.match(modalSrc, /openCreate:\s*openCreateServiceModal/);
        assert.match(modalSrc, /openEdit:\s*openEditServiceModal/);
        assert.match(modalSrc, /function openEditServiceModal\(serviceId\)/);
        assert.match(modalSrc, /return Promise\.reject/);
    });

    it('carga el servicio por ID via aa_get_services_db y no usa el DOM como fuente', () => {
        assert.match(modalSrc, /function fetchServiceById\(serviceId\)/);
        assert.match(modalSrc, /formData\.append\('action',\s*'aa_get_services_db'\)/);
        assert.match(modalSrc, /result\.data\.services/);
        assert.doesNotMatch(modalSrc, /aa-service-header-toggle/);
        assert.doesNotMatch(modalSrc, /aa-service-details-panel/);
        assert.doesNotMatch(modalSrc, /defaultAttendanceType/);
    });

    it('el formulario de edición incluye los campos canónicos y omite description', () => {
        assert.match(modalSrc, /id = 'aa-modal-form-editar-servicio'/);
        assert.match(modalSrc, /id = 'modal-editar-servicio-nombre'/);
        assert.match(modalSrc, /id = 'modal-editar-servicio-codigo'/);
        assert.match(modalSrc, /id = 'modal-editar-servicio-precio'/);
        assert.match(modalSrc, /id = 'modal-editar-servicio-publico'/);
        assert.match(modalSrc, /id = 'modal-editar-servicio-indicaciones'/);
        assert.match(modalSrc, /id = 'modal-editar-servicio-duracion'/);
        assert.match(modalSrc, /id = 'modal-editar-servicio-tipo'/);
        assert.match(modalSrc, /id = 'modal-editar-servicio-canal'/);
        assert.match(modalSrc, /title:\s*'Editar servicio'/);
        assert.match(modalSrc, /label: 'Sin definir'/);
        assert.match(modalSrc, /Usar configuración general/);
        assert.doesNotMatch(modalSrc, /modal-editar-servicio-description/);
        assert.doesNotMatch(modalSrc, /formData\.append\('description'/);
    });

    it('precio se maneja como string y rechaza negativos', () => {
        assert.match(modalSrc, /inputMode = 'decimal'/);
        assert.match(modalSrc, /function validatePriceString/);
        assert.match(modalSrc, /PRICE_RE/);
        assert.match(modalSrc, /El precio no puede ser negativo/);
        assert.doesNotMatch(modalSrc, /parseFloat\(/);
        assert.doesNotMatch(modalSrc, /Number\(service/);
        assert.match(modalSrc, /formData\.append\('price',\s*draft\.priceCheck\.value\)/);
    });

    it('tipo sin definir y canal condicional no materializan WhatsApp', () => {
        assert.match(modalSrc, /value: '', label: 'Sin definir'/);
        assert.match(modalSrc, /value: 'physical', label: 'Físico'/);
        assert.match(modalSrc, /value: 'virtual', label: 'Virtual'/);
        assert.match(modalSrc, /value: 'whatsapp', label: 'WhatsApp'/);
        assert.match(modalSrc, /function syncVirtualChannelVisibility/);
        assert.match(modalSrc, /attendanceType === 'virtual'/);
        assert.match(modalSrc, /min \(histórico\)/);
    });

    it('guarda con aa_update_service, nonce y sin aa_update_service_db', () => {
        assert.match(modalSrc, /formData\.append\('action',\s*'aa_update_service'\)/);
        assert.match(modalSrc, /formData\.append\('_wpnonce',\s*nonce\)/);
        assert.match(modalSrc, /AA_SERVICE_NONCES/);
        assert.match(modalSrc, /isEdit:\s*true/);
        assert.match(modalSrc, /aa:service:saved/);
        assert.doesNotMatch(modalSrc, /aa_update_service_db/);
        assert.match(layoutSrc, /AA_SERVICE_NONCES/);
        assert.match(layoutSrc, /update_service/);
    });

    it('cancelar cierra con data-aa-modal-close y no llama update', () => {
        const cancelBlock = modalSrc.match(/function createEditModalFooter[\s\S]*?return footer;/);
        assert.ok(cancelBlock);
        assert.match(cancelBlock[0], /data-aa-modal-close/);
        assert.doesNotMatch(cancelBlock[0], /aa_update_service/);
        assert.doesNotMatch(cancelBlock[0], /saveEditedService/);
    });

    it('no abre un formulario parcial y no acumula aperturas', () => {
        assert.match(modalSrc, /var seq = \+\+editOpenSeq/);
        assert.match(modalSrc, /if \(seq !== editOpenSeq\)/);
        assert.match(modalSrc, /function watchEditModalClose/);
        assert.match(modalSrc, /function cleanupEditModal/);
        assert.match(modalSrc, /clearSaveCloseTimer/);
    });

    it('conserva openCreate y no añade botones visibles de edición', () => {
        assert.match(modalSrc, /function openCreateServiceModal/);
        assert.match(modalSrc, /aa_create_service/);
        assert.match(layoutSrc, /modals\/crearservicio\/crearservicio\.js/);
        assert.doesNotMatch(servicesSrc, /ServiceCreateModal\.openEdit/);
        assert.doesNotMatch(assignmentsIndexSrc, /openEdit/);
        assert.doesNotMatch(assignmentsIndexSrc, /Editar servicio/);
        assert.match(servicesSrc, /aa_update_service_db/);
        assert.match(servicesSrc, /aa-service-description-/);
        assert.match(servicesSrc, /aa_toggle_service/);
    });

    it('servicesSection recarga con loadServices al escuchar aa:service:saved', () => {
        assert.match(servicesSrc, /setupServiceSavedListener/);
        assert.match(servicesSrc, /serviceSavedListenerBound/);
        assert.match(servicesSrc, /aa:service:saved/);
        assert.match(servicesSrc, /loadServices\(servicesRoot\)/);
        assert.match(servicesSrc, /Error al refrescar servicios tras guardar/);
    });

    it('edit no emite onboarding', () => {
        const editSave = modalSrc.slice(modalSrc.indexOf('function saveEditedService'));
        assert.doesNotMatch(editSave, /dispatchOnboardingSetupMutated/);
        assert.match(modalSrc, /function saveNewService[\s\S]*dispatchOnboardingSetupMutated/);
    });
});
