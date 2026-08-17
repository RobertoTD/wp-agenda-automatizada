<?php
/**
 * Fast Appointment Modal - HTML Content Template
 *
 * Progressive form structure for the fast appointment modal.
 * This file defines only HTML and DOM contracts for future JS wiring.
 *
 * @package AgendaAutomatizada
 * @since 2.0.0
 */

defined('ABSPATH') or die('No direct access');
?>

<template id="aa-fastappointment-modal-template">
    <div class="aa-fastappointment-modal">
        <form id="aa-fastappointment-form" class="space-y-3">

            <div id="aa-fastappointment-step-client" data-aa-fastappointment-step="client" class="aa-fastappointment-step rounded-xl border border-indigo-100 bg-indigo-50">
                <button type="button" data-aa-fastappointment-step-header class="aa-fastappointment-step-header flex w-full items-center justify-between gap-2 px-4 py-4 text-left">
                    <div class="min-w-0">
                        <h4 class="text-lg font-semibold text-gray-600">Cliente</h4>
                    </div>
                    <div class="flex items-center gap-2 pl-2">
                        <span data-aa-fastappointment-step-summary class="hidden max-w-[12rem] truncate text-lg text-gray-500"></span>
                        <span data-aa-fastappointment-step-check class="aa-fastappointment-step-check hidden h-6 w-6 text-emerald-600" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.42l2.293 2.294 6.543-6.544a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </div>
                </button>
                <div data-aa-fastappointment-step-body class="aa-fastappointment-step-body space-y-2 p-3">
                    <select
                        id="aa-fastappointment-client"
                        name="cliente_id"
                        class="w-full px-3 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="">Selecciona un cliente</option>
                    </select>
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            id="aa-fastappointment-client-search"
                            class="min-w-0 flex-1 px-3 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                            placeholder="Buscar cliente..."
                            autocomplete="off">
                        <span class="text-gray-500 text-base">o</span>
                        <button
                            type="button"
                            id="aa-fastappointment-client-create"
                            class="inline-flex items-center rounded-lg border px-3 py-1.5 text-base font-semibold text-gray-500 hover:text-gray-500 border-gray-200 bg-white transition-colors whitespace-nowrap">
                            + cliente
                        </button>
                    </div>
                    <div id="aa-fastappointment-client-inline" class="hidden"></div>
                </div>
            </div>

            <div id="aa-fastappointment-step-service" data-aa-fastappointment-step="service" class="aa-fastappointment-step rounded-xl border border-gray-200 bg-white">
                <button type="button" data-aa-fastappointment-step-header class="aa-fastappointment-step-header flex w-full items-center justify-between gap-2 px-4 py-4 text-left">
                    <div class="min-w-0">
                        <h4 class="text-lg font-semibold text-gray-600">Servicio</h4>
                    </div>
                    <div class="flex items-center gap-2 pl-2">
                        <span data-aa-fastappointment-step-summary class="hidden max-w-[12rem] truncate text-lg text-gray-500"></span>
                        <span data-aa-fastappointment-step-check class="aa-fastappointment-step-check hidden h-6 w-6 text-emerald-600" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.42l2.293 2.294 6.543-6.544a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </div>
                </button>
                <div data-aa-fastappointment-step-body class="aa-fastappointment-step-body hidden space-y-2 p-3">
                    <select
                        id="aa-fastappointment-service"
                        name="servicio"
                        class="w-full px-3 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="">Selecciona un servicio</option>
                    </select>
                </div>
            </div>

            <div id="aa-fastappointment-step-date" data-aa-fastappointment-step="date" class="aa-fastappointment-step rounded-xl border border-gray-200 bg-white">
                <button type="button" data-aa-fastappointment-step-header class="aa-fastappointment-step-header flex w-full items-center justify-between gap-2 px-4 py-4 text-left">
                    <div class="min-w-0">
                        <h4 class="text-lg font-semibold text-gray-600">Fecha</h4>
                    </div>
                    <div class="flex items-center gap-2 pl-2">
                        <span data-aa-fastappointment-step-summary class="hidden max-w-[12rem] truncate text-lg text-gray-500"></span>
                        <span data-aa-fastappointment-step-check class="aa-fastappointment-step-check hidden h-6 w-6 text-emerald-600" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.42l2.293 2.294 6.543-6.544a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </div>
                </button>
                <div data-aa-fastappointment-step-body class="aa-fastappointment-step-body hidden space-y-2 p-3">
                    <input
                        type="text"
                        id="aa-fastappointment-date"
                        name="fecha"
                        readonly
                        placeholder="Selecciona una fecha"
                        class="w-full px-3 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                </div>
            </div>

            <div id="aa-fastappointment-step-time" data-aa-fastappointment-step="time" class="aa-fastappointment-step rounded-xl border border-gray-200 bg-white">
                <button type="button" data-aa-fastappointment-step-header class="aa-fastappointment-step-header flex w-full items-center justify-between gap-2 px-4 py-4 text-left">
                    <div class="min-w-0">
                        <h4 class="text-lg font-semibold text-gray-600">Hora</h4>
                    </div>
                    <div class="flex items-center gap-2 pl-2">
                        <span data-aa-fastappointment-step-summary class="hidden max-w-[12rem] truncate text-lg text-gray-500"></span>
                        <span data-aa-fastappointment-step-check class="aa-fastappointment-step-check hidden h-6 w-6 text-emerald-600" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.42l2.293 2.294 6.543-6.544a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </div>
                </button>
                <div data-aa-fastappointment-step-body class="aa-fastappointment-step-body hidden space-y-2 p-3">
                    <select
                        id="aa-fastappointment-time"
                        name="hora"
                        class="w-full px-3 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="">Horario de la cita</option>
                    </select>
                </div>
            </div>

            <div id="aa-fastappointment-step-staff" data-aa-fastappointment-step="staff" class="aa-fastappointment-step rounded-xl border border-gray-200 bg-white">
                <button type="button" data-aa-fastappointment-step-header class="aa-fastappointment-step-header flex w-full items-center justify-between gap-2 px-4 py-4 text-left">
                    <div class="min-w-0">
                        <h4 class="text-lg font-semibold text-gray-600">Personal</h4>
                    </div>
                    <div class="flex items-center gap-2 pl-2">
                        <span data-aa-fastappointment-step-summary class="hidden max-w-[12rem] truncate text-lg text-gray-500"></span>
                        <span data-aa-fastappointment-step-check class="aa-fastappointment-step-check hidden h-6 w-6 text-emerald-600" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.42l2.293 2.294 6.543-6.544a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </div>
                </button>
                <div data-aa-fastappointment-step-body class="aa-fastappointment-step-body hidden space-y-2 p-3">
                    <select
                        id="aa-fastappointment-staff"
                        name="staff_id"
                        class="w-full px-3 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="">Personal que atenderá la cita</option>
                    </select>
                    <div
                        id="aa-fastappointment-staff-message"
                        class="hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"></div>
                </div>
            </div>

            <div id="aa-fastappointment-step-area" data-aa-fastappointment-step="area" class="aa-fastappointment-step rounded-xl border border-gray-200 bg-white">
                <button type="button" data-aa-fastappointment-step-header class="aa-fastappointment-step-header flex w-full items-center justify-between gap-2 px-4 py-4 text-left">
                    <div class="min-w-0">
                        <h4 class="text-lg font-semibold text-gray-600">Zona de atencion</h4>
                    </div>
                    <div class="flex items-center gap-2 pl-2">
                        <span data-aa-fastappointment-step-summary class="hidden max-w-[12rem] truncate text-lg text-gray-500"></span>
                        <span data-aa-fastappointment-step-check class="aa-fastappointment-step-check hidden h-6 w-6 text-emerald-600" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6">
                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.414 0l-3-3a1 1 0 111.414-1.42l2.293 2.294 6.543-6.544a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </div>
                </button>
                <div data-aa-fastappointment-step-body class="aa-fastappointment-step-body hidden space-y-2 p-3">
                    <select
                        id="aa-fastappointment-area"
                        name="service_area_id"
                        class="w-full px-3 py-2 text-base border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                        <option value="">Zona donde se realizará la cita</option>
                    </select>
                    <div
                        id="aa-fastappointment-area-message"
                        class="hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"></div>
                </div>
            </div>

        </form>
    </div>
</template>

<template id="aa-fastappointment-modal-footer-template">
    <div class="flex items-center justify-between gap-3">
        <div class="rounded-xl bg-white px-3 py-2">
            <label for="aa-fastappointment-confirm" class="flex min-h-[2.25rem] items-center gap-2 cursor-pointer text-lg text-gray-600 py-0.5">
                <input
                    type="checkbox"
                    id="aa-fastappointment-confirm"
                    name="estado_confirmacion"
                    value="confirmed"
                    form="aa-fastappointment-form"
                    checked
                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                <span>Confirmada desde ahora</span>
            </label>
        </div>
        <button
            type="submit"
            id="aa-fastappointment-submit"
            form="aa-fastappointment-form"
            class="px-4 py-2 text-lg font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
            Agendar cita
        </button>
    </div>
</template>
