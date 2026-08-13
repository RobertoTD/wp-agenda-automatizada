'use strict';

const assert = require('node:assert/strict');
const { describe, it } = require('node:test');
const path = require('node:path');
const fs = require('node:fs');

const headerPath = path.join(__dirname, '../../includes/admin/ui/shared/header.php');
const indexPath = path.join(__dirname, '../../includes/admin/ui/modules/calendar/index.php');
const calendarModulePath = path.join(__dirname, '../../includes/admin/ui/modules/calendar/calendar-module.js');
const appointmentsModalPath = path.join(__dirname, '../../includes/admin/ui/modals/appointments/appointments-modal.js');
const aichatJsPath = path.join(__dirname, '../../includes/admin/ui/modals/aichat/aichat.js');
const aichatIndexPath = path.join(__dirname, '../../includes/admin/ui/modals/aichat/index.php');

const headerSrc = fs.readFileSync(headerPath, 'utf8');
const indexSrc = fs.readFileSync(indexPath, 'utf8');
const calendarModuleSrc = fs.readFileSync(calendarModulePath, 'utf8');
const appointmentsModalSrc = fs.readFileSync(appointmentsModalPath, 'utf8');
const aichatJsSrc = fs.readFileSync(aichatJsPath, 'utf8');
const aichatIndexSrc = fs.readFileSync(aichatIndexPath, 'utf8');

describe('calendar area tools', () => {
    it('header expone menú de opciones de agenda junto a #aa-page-title', () => {
        assert.match(headerSrc, /id="aa-calendar-area-tools"/);
        assert.match(headerSrc, /id="aa-calendar-options-trigger"/);
        assert.match(headerSrc, /id="aa-calendar-options-menu"/);
        assert.match(headerSrc, /data-calendar-tool="search-appointments"/);
        assert.match(headerSrc, /Buscar citas/);
        assert.match(headerSrc, /data-calendar-tool="open-aichat"/);
        assert.match(headerSrc, /Asistente IA/);
        assert.match(headerSrc, /\$active_module === 'calendar'/);
    });

    it('index del calendario ya no incluye aa-btn-search en el toolbar', () => {
        assert.doesNotMatch(indexSrc, /id="aa-btn-search"/);
        assert.doesNotMatch(indexSrc, /id="aa-calendar-area-tools"/);
    });

    it('calendar-module.js enlaza el menú de opciones del header', () => {
        assert.match(calendarModuleSrc, /bindCalendarOptionsMenu/);
        assert.match(calendarModuleSrc, /aa-calendar-options-trigger/);
        assert.match(calendarModuleSrc, /data-calendar-tool="search-appointments"/);
        assert.match(calendarModuleSrc, /data-calendar-tool="open-aichat"/);
    });

    it('appointments-modal.js abre búsqueda desde el ítem del menú del header', () => {
        assert.match(appointmentsModalSrc, /data-calendar-tool="search-appointments"/);
        assert.match(appointmentsModalSrc, /bindSearchTrigger/);
    });

    it('aichat se abre desde el menú de agenda y ya no monta FAB', () => {
        assert.match(aichatJsSrc, /data-calendar-tool="open-aichat"/);
        assert.doesNotMatch(aichatJsSrc, /aa-ai-chat-fab-template/);
        assert.doesNotMatch(aichatIndexSrc, /id="aa-btn-open-aichat"/);
        assert.doesNotMatch(aichatIndexSrc, /aa-ai-chat-fab-template/);
    });
});
