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
        <form id="aa-fastappointment-form" class="space-y-4">

            <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3">
                <h3 class="text-sm font-semibold text-indigo-900">Cita rapida</h3>
                <p class="mt-1 text-sm text-indigo-700">
                    Completa los pasos en orden para preparar una cita individual.
                </p>
            </div>

            <div id="aa-fastappointment-step-client" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Cliente</h4>
                    <p class="mt-1 text-xs text-gray-500">Busca o crea el cliente antes de continuar.</p>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        id="aa-fastappointment-client-search"
                        class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                        placeholder="Buscar cliente..."
                        autocomplete="off">
                    <span class="text-gray-500 text-sm">o</span>
                    <button
                        type="button"
                        id="aa-fastappointment-client-create"
                        class="px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap">
                        + cliente
                    </button>
                </div>

                <div id="aa-fastappointment-client-inline" class="hidden"></div>

                <select
                    id="aa-fastappointment-client"
                    name="cliente_id"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="">-- Selecciona un cliente --</option>
                </select>
            </div>

            <div id="aa-fastappointment-step-date" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Fecha</h4>
                    <p class="mt-1 text-xs text-gray-500">Selecciona la fecha para empezar a acotar opciones.</p>
                </div>

                <input
                    type="text"
                    id="aa-fastappointment-date"
                    name="fecha"
                    readonly
                    placeholder="Selecciona una fecha"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
            </div>

            <div id="aa-fastappointment-step-time" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Hora</h4>
                    <p class="mt-1 text-xs text-gray-500">Elige la hora disponible para la cita.</p>
                </div>

                <select
                    id="aa-fastappointment-time"
                    name="hora"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="">-- Selecciona una hora --</option>
                </select>
            </div>

            <div id="aa-fastappointment-step-service" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Servicio</h4>
                    <p class="mt-1 text-xs text-gray-500">Selecciona el servicio que aplica para esa fecha y hora.</p>
                </div>

                <select
                    id="aa-fastappointment-service"
                    name="servicio"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="">-- Selecciona un servicio --</option>
                </select>
            </div>

            <div id="aa-fastappointment-step-staff" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Staff</h4>
                    <p class="mt-1 text-xs text-gray-500">Filtra al personal disponible para la seleccion actual.</p>
                </div>

                <select
                    id="aa-fastappointment-staff"
                    name="staff_id"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="">-- Selecciona personal --</option>
                </select>
            </div>

            <div id="aa-fastappointment-step-area" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Zona de atencion</h4>
                    <p class="mt-1 text-xs text-gray-500">Confirma la zona en la que se generara la cita.</p>
                </div>

                <select
                    id="aa-fastappointment-area"
                    name="service_area_id"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                    <option value="">-- Selecciona una zona --</option>
                </select>
            </div>

            <div id="aa-fastappointment-step-confirm" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Confirmar</h4>
                    <p class="mt-1 text-xs text-gray-500">Define si la cita debe confirmarse inmediatamente.</p>
                </div>

                <label for="aa-fastappointment-confirm" class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                    <input
                        type="checkbox"
                        id="aa-fastappointment-confirm"
                        name="estado_confirmacion"
                        value="confirmed"
                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <span>Confirmar cita al agendar</span>
                </label>
            </div>

            <div id="aa-fastappointment-step-actions" class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Acciones finales</h4>
                    <p class="mt-1 text-xs text-gray-500">El footer del modal contendra las acciones de cancelar o agendar.</p>
                </div>

                <div id="aa-fastappointment-summary" class="rounded-lg border border-dashed border-gray-300 bg-white px-3 py-3 text-sm text-gray-500">
                    Aqui se podra mostrar un resumen de la seleccion antes del envio.
                </div>
            </div>

        </form>
    </div>
</template>

<template id="aa-fastappointment-modal-footer-template">
    <div class="flex justify-end gap-3">
        <button
            type="button"
            id="aa-fastappointment-cancel"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            data-aa-modal-close>
            Cancelar
        </button>
        <button
            type="submit"
            id="aa-fastappointment-submit"
            form="aa-fastappointment-form"
            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
            Agendar cita
        </button>
    </div>
</template>
