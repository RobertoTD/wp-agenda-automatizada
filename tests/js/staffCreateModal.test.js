'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

const modalPath = path.join(__dirname, '../../includes/admin/ui/modals/crearstaff/crearstaff.js');
const staffPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/staff-section/staff.js');
const layoutPath = path.join(__dirname, '../../includes/admin/ui/shared/layout.php');
const assignmentsIndexPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/index.php');

const modalSrc = fs.readFileSync(modalPath, 'utf8');
const staffSrc = fs.readFileSync(staffPath, 'utf8');
const layoutSrc = fs.readFileSync(layoutPath, 'utf8');
const assignmentsIndexSrc = fs.readFileSync(assignmentsIndexPath, 'utf8');

describe('StaffCreateModal edit', () => {
    it('expone openCreate y openEdit en AAAdmin.StaffCreateModal', () => {
        assert.match(modalSrc, /AAAdmin\.StaffCreateModal\s*=\s*\{/);
        assert.match(modalSrc, /openCreate:\s*openCreateStaffModal/);
        assert.match(modalSrc, /openEdit:\s*openEditStaffModal/);
        assert.match(modalSrc, /function openEditStaffModal\(staffId\)/);
        assert.match(modalSrc, /return Promise\.reject/);
        assert.match(modalSrc, /Promise\.all\(/);
    });

    it('carga staff, asignados y catálogo desde servidor sin usar el DOM', () => {
        assert.match(modalSrc, /function fetchStaffById\(staffId\)/);
        assert.match(modalSrc, /formData\.append\('action',\s*'aa_get_staff'\)/);
        assert.match(modalSrc, /formData\.append\('action',\s*'aa_get_staff_services'\)/);
        assert.match(modalSrc, /formData\.append\('action',\s*'aa_get_services_db'\)/);
        assert.doesNotMatch(modalSrc, /aa-staff-header-toggle/);
        assert.doesNotMatch(modalSrc, /aa-staff-services-select/);
        assert.doesNotMatch(modalSrc, /StaffSection/);
    });

    it('mantiene estado local y no persiste al agregar o quitar servicios', () => {
        assert.match(modalSrc, /function addLocalService/);
        assert.match(modalSrc, /function removeLocalService/);
        assert.match(modalSrc, /editState\.selected/);
        assert.doesNotMatch(modalSrc, /aa_add_staff_service/);
        assert.doesNotMatch(modalSrc, /aa_remove_staff_service/);
        assert.match(modalSrc, /data-aa-modal-close/);
        assert.match(modalSrc, /className = 'aa-btn-cancelar'/);
    });

    it('guarda con aa_update_staff, nonce y service_ids', () => {
        assert.match(modalSrc, /formData\.append\('action',\s*'aa_update_staff'\)/);
        assert.match(modalSrc, /formData\.append\('_wpnonce',\s*nonce\)/);
        assert.match(modalSrc, /AA_STAFF_NONCES/);
        assert.match(modalSrc, /service_ids\[\]/);
        assert.match(modalSrc, /isEdit:\s*true/);
        assert.match(modalSrc, /aa:staff:saved/);
        assert.match(layoutSrc, /AA_STAFF_NONCES/);
        assert.match(layoutSrc, /update_staff/);
    });

    it('emite onboarding solo si se añadió al menos un servicio', () => {
        assert.match(modalSrc, /function countAddedServices/);
        assert.match(modalSrc, /added_count/);
        assert.match(modalSrc, /addedCount > 0/);
        assert.match(modalSrc, /dispatchOnboardingSetupMutated\('staff_service_assignment'\)/);
        assert.match(staffSrc, /resolveCreateStaffOnboardingSource/);
        assert.doesNotMatch(
            modalSrc.slice(modalSrc.indexOf('function saveEditedStaff')),
            /dispatchOnboardingSetupMutated\('staff'\)/
        );
    });

    it('no abre un formulario parcial y no acumula aperturas', () => {
        assert.match(modalSrc, /var seq = \+\+editOpenSeq/);
        assert.match(modalSrc, /if \(seq !== editOpenSeq\)/);
        assert.match(modalSrc, /function watchEditModalClose/);
        assert.match(modalSrc, /function cleanupEditModal/);
        assert.match(modalSrc, /clearSaveCloseTimer/);
        assert.match(modalSrc, /Promise\.all\(/);
    });

    it('conserva openCreate y abre edición desde el botón visible Editar', () => {
        assert.match(modalSrc, /function openCreateStaffModal/);
        assert.match(modalSrc, /aa_create_staff/);
        assert.match(layoutSrc, /modals\/crearstaff\/crearstaff\.js/);
        assert.match(staffSrc, /class="aa-staff-edit /);
        assert.match(staffSrc, />Editar<\/button>/);
        assert.match(staffSrc, /StaffCreateModal\.openEdit\(staffId\)/);
        assert.doesNotMatch(assignmentsIndexSrc, /openEdit/);
        assert.match(staffSrc, /renderAssignmentItemOptions\('staff'/);
        assert.match(assignmentsIndexSrc, /item-options-module\.js/);
        assert.doesNotMatch(staffSrc, /aa-staff-services-select/);
        assert.doesNotMatch(staffSrc, /aa-staff-services-selected/);
        assert.doesNotMatch(staffSrc, /aa_add_staff_service/);
        assert.doesNotMatch(staffSrc, /aa_remove_staff_service/);
        assert.doesNotMatch(staffSrc, /aa-staff-services-readonly/);
        assert.doesNotMatch(staffSrc, /aa_get_staff_services/);
        assert.doesNotMatch(staffSrc, /Servicios que ofrece:/);
    });

    it('staff.js recarga con loadStaff al escuchar aa:staff:saved', () => {
        assert.match(staffSrc, /setupStaffSavedListener/);
        assert.match(staffSrc, /staffSavedListenerBound/);
        assert.match(staffSrc, /aa:staff:saved/);
        assert.match(staffSrc, /loadStaff\(staffRoot\)/);
        assert.match(staffSrc, /Error al refrescar personal tras guardar/);
        assert.doesNotMatch(staffSrc, /AAAdmin\.StaffSection/);
    });

    it('cancelar cierra con data-aa-modal-close y no llama update', () => {
        const cancelBlock = modalSrc.match(/function createEditModalFooter[\s\S]*?return footer;/);
        assert.ok(cancelBlock);
        assert.match(cancelBlock[0], /data-aa-modal-close/);
        assert.doesNotMatch(cancelBlock[0], /aa_update_staff/);
        assert.doesNotMatch(cancelBlock[0], /saveEditedStaff/);
    });
});
