'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

const modulePath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/item-options-module.js');
const areasPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/areas-section/areas.js');
const staffPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/staff-section/staff.js');
const servicesPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/services-section/servicesSection.js');
const assignmentsIndexPath = path.join(__dirname, '../../includes/admin/ui/modules/assignments/index.php');
const cssPath = path.join(__dirname, '../../includes/admin/ui/assets/css/admin.source.css');

const moduleSrc = fs.readFileSync(modulePath, 'utf8');
const areasSrc = fs.readFileSync(areasPath, 'utf8');
const staffSrc = fs.readFileSync(staffPath, 'utf8');
const servicesSrc = fs.readFileSync(servicesPath, 'utf8');
const assignmentsIndexSrc = fs.readFileSync(assignmentsIndexPath, 'utf8');
const cssSrc = fs.readFileSync(cssPath, 'utf8');

describe('Assignment item options', () => {
    it('expone renderAssignmentItemOptions y carga el módulo en assignments', () => {
        assert.match(moduleSrc, /function renderAssignmentItemOptions\(type, id\)/);
        assert.match(moduleSrc, /AAAdmin\.renderAssignmentItemOptions = renderAssignmentItemOptions/);
        assert.match(assignmentsIndexSrc, /item-options-module\.js/);
        assert.match(assignmentsIndexSrc, /section-options-module\.js/);
        assert.match(assignmentsIndexSrc, /executable-options-menu-placement\.js/);
    });

    it('areas, staff y services renderizan el menú ⋮ vía el helper compartido', () => {
        assert.match(areasSrc, /renderAssignmentItemOptions\('area', areaId\)/);
        assert.match(staffSrc, /renderAssignmentItemOptions\('staff', staffId\)/);
        assert.match(servicesSrc, /renderAssignmentItemOptions\('service', serviceId\)/);
        assert.match(moduleSrc, /aa-assignment-item-options-trigger aa-options-trigger-flat/);
        assert.match(moduleSrc, /data-aa-item-options-trigger="1"/);
        assert.match(moduleSrc, /onclick="event\.stopPropagation\(\)"/);
    });

    it('el menú solo contiene Editar y llama openEdit del modal correcto', () => {
        assert.match(moduleSrc, /data-aa-item-action="edit"/);
        assert.match(moduleSrc, /AreaCreateModal\.openEdit\(numericId\)/);
        assert.match(moduleSrc, /StaffCreateModal\.openEdit\(numericId\)/);
        assert.match(moduleSrc, /ServiceCreateModal\.openEdit\(numericId\)/);
        assert.doesNotMatch(moduleSrc, /Archivar/);
        assert.doesNotMatch(moduleSrc, /Eliminar/);
        assert.doesNotMatch(moduleSrc, /data-aa-item-action="archive"/);
        assert.doesNotMatch(moduleSrc, /data-aa-item-action="delete"/);
        assert.match(areasSrc, /AreaCreateModal\.openEdit\(areaId\)/);
        assert.match(staffSrc, /StaffCreateModal\.openEdit\(staffId\)/);
        assert.match(servicesSrc, /ServiceCreateModal\.openEdit\(serviceId\)/);
    });

    it('CSS oculta el menú ⋮ cuando la card está colapsada', () => {
        assert.match(
            cssSrc,
            /\.aa-area-header-toggle:has\(\+ \.aa-area-details-panel\.hidden\) \.aa-assignment-item-options/
        );
        assert.match(
            cssSrc,
            /\.aa-staff-header-toggle:has\(\+ \.aa-staff-services-panel\.hidden\) \.aa-assignment-item-options/
        );
        assert.match(
            cssSrc,
            /\.aa-service-header-toggle:has\(\+ \.aa-service-details-panel\.hidden\) \.aa-assignment-item-options/
        );
    });

    it('CSS pinta de azul solo el item expandido, no el header de sección', () => {
        assert.match(
            cssSrc,
            /li:has\(> \.aa-area-details-panel:not\(\.hidden\)\)/
        );
        assert.match(
            cssSrc,
            /li:has\(> \.aa-staff-services-panel:not\(\.hidden\)\)/
        );
        assert.match(
            cssSrc,
            /li:has\(> \.aa-service-details-panel:not\(\.hidden\)\)/
        );
        assert.match(cssSrc, /background-color:\s*rgb\(239 246 255\) !important;/);
        assert.doesNotMatch(
            cssSrc,
            /details\.aa-module-section-card\[open\]\s*>\s*summary\s*\{[^}]*239 246 255/
        );
    });

    it('usa placement fixed compartido y cierra al refrescar o colapsar la sección', () => {
        assert.match(moduleSrc, /AAExecutableOptionsMenuPlacement/);
        assert.match(moduleSrc, /positionOptionsMenu/);
        assert.match(moduleSrc, /aa:area:saved/);
        assert.match(moduleSrc, /aa:staff:saved/);
        assert.match(moduleSrc, /aa:service:saved/);
        assert.match(moduleSrc, /aa-module-section-card--floating-menu/);
        assert.match(moduleSrc, /event\.key !== 'Escape'/);
    });
});
