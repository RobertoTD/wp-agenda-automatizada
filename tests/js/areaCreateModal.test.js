'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

const modalPath = path.join(__dirname, '../../includes/admin/ui/modals/crearzona/crearzona.js');
const areasPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/areas-section/areas.js');
const layoutPath = path.join(__dirname, '../../includes/admin/ui/shared/layout.php');
const assignmentsIndexPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/index.php');
const cssPath = path.join(__dirname, '../../includes/admin/ui/assets/css/admin.source.css');

const modalSrc = fs.readFileSync(modalPath, 'utf8');
const areasSrc = fs.readFileSync(areasPath, 'utf8');
const layoutSrc = fs.readFileSync(layoutPath, 'utf8');
const assignmentsIndexSrc = fs.readFileSync(assignmentsIndexPath, 'utf8');
const cssSrc = fs.readFileSync(cssPath, 'utf8');

describe('AreaCreateModal edit', () => {
    it('expone openCreate y openEdit en AAAdmin.AreaCreateModal', () => {
        assert.match(modalSrc, /AAAdmin\.AreaCreateModal\s*=\s*\{/);
        assert.match(modalSrc, /openCreate:\s*openCreateAreaModal/);
        assert.match(modalSrc, /openEdit:\s*openEditAreaModal/);
        assert.match(modalSrc, /function openEditAreaModal\(areaId\)/);
        assert.match(modalSrc, /return Promise\.reject/);
    });

    it('carga la zona por ID via aa_get_service_areas y no usa el DOM como fuente', () => {
        assert.match(modalSrc, /function fetchServiceAreaById\(areaId\)/);
        assert.match(modalSrc, /formData\.append\('action',\s*'aa_get_service_areas'\)/);
        assert.match(modalSrc, /result\.data\.service_areas/);
        assert.doesNotMatch(modalSrc, /querySelector\(['"]\.aa-area-name-input/);
        assert.doesNotMatch(modalSrc, /aa-area-details-panel/);
        assert.doesNotMatch(modalSrc, /AreasSection/);
    });

    it('el formulario de edición solo incluye nombre y color', () => {
        assert.match(modalSrc, /id = 'aa-modal-form-editar-zona'/);
        assert.match(modalSrc, /id = 'modal-editar-zona-nombre'/);
        assert.match(modalSrc, /id = 'modal-editar-zona-color'/);
        assert.match(modalSrc, /className = 'aa-modal-zona-color-picker'/);
        assert.match(modalSrc, /title:\s*'Editar zona de atención'/);
        assert.doesNotMatch(modalSrc, /modal-editar-zona-description/);
        assert.doesNotMatch(modalSrc, /aa_update_service_area_description/);
        assert.doesNotMatch(modalSrc, /formData\.append\('description'/);
    });

    it('guarda con aa_update_service_area, nonce y sin description', () => {
        assert.match(modalSrc, /formData\.append\('action',\s*'aa_update_service_area'\)/);
        assert.match(modalSrc, /formData\.append\('_wpnonce',\s*nonce\)/);
        assert.match(modalSrc, /AA_AREAS_NONCES/);
        assert.match(modalSrc, /isEdit:\s*true/);
        assert.match(modalSrc, /aa:area:saved/);
        assert.doesNotMatch(modalSrc, /aa_update_service_area_name/);
        assert.doesNotMatch(modalSrc, /aa_update_service_area_color/);
        assert.match(layoutSrc, /AA_AREAS_NONCES/);
        assert.match(layoutSrc, /update_service_area/);
    });

    it('inicializa wpColorPicker una vez por apertura y lo destruye al cerrar', () => {
        assert.match(modalSrc, /function initEditColorPicker/);
        assert.match(modalSrc, /editColorPicker\$\.wpColorPicker\(/);
        assert.match(modalSrc, /function destroyEditColorPicker/);
        assert.match(modalSrc, /wpColorPicker\('destroy'\)/);
        assert.match(modalSrc, /function watchEditModalClose/);
        assert.match(modalSrc, /MutationObserver/);
        assert.match(modalSrc, /aa-modal-root/);
        assert.match(modalSrc, /input\.value = ui\.color\.toString\(\)/);
        assert.doesNotMatch(modalSrc, /updateServiceAreaColor\(/);
    });

    it('conserva openCreate y no añade botones visibles de edición', () => {
        assert.match(modalSrc, /function openCreateAreaModal/);
        assert.match(modalSrc, /aa_create_service_area/);
        assert.match(layoutSrc, /modals\/crearzona\/crearzona\.js/);
        assert.doesNotMatch(areasSrc, /AreaCreateModal\.openEdit/);
        assert.doesNotMatch(assignmentsIndexSrc, /openEdit/);
        assert.doesNotMatch(assignmentsIndexSrc, /Editar zona/);
        assert.match(areasSrc, /aa-area-name-input/);
        assert.match(areasSrc, /aa-area-description-input/);
        assert.match(areasSrc, /aa-area-color-picker/);
    });

    it('areas.js recarga con loadServiceAreas al escuchar aa:area:saved', () => {
        assert.match(areasSrc, /setupAreaSavedListener/);
        assert.match(areasSrc, /aa:area:saved/);
        assert.match(areasSrc, /loadServiceAreas\(areasRoot\)/);
        assert.doesNotMatch(areasSrc, /AAAdmin\.AreasSection/);
        assert.doesNotMatch(areasSrc, /window\.AAAdmin\.AreasSection/);
    });

    it('CSS del picker aplica también dentro del modal', () => {
        assert.match(cssSrc, /#aa-modal-root \.wp-picker-container \.wp-color-result\.button/);
        assert.match(cssSrc, /#aa-modal-root \.aa-modal:has\(\.aa-modal-zona-color-picker\)/);
        assert.match(cssSrc, /#aa-modal-root \.iris-picker/);
    });
});
