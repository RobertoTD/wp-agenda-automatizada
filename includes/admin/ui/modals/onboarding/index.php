<?php
/**
 * Onboarding Welcome Modal - HTML content templates (first-open orientation only).
 *
 * @package AgendaAutomatizada
 */

defined('ABSPATH') or die('No direct access');
?>

<template id="aa-onboarding-welcome-body-template">
    <div class="aa-onboarding-welcome space-y-3" data-aa-onboarding-welcome="1">
        <p class="text-sm text-gray-700 leading-relaxed">
            Te damos la bienvenida. En los siguientes pasos te guiaremos para crear una cita de prueba con datos
            ficticios y conocer el flujo de tu agenda sin afectar clientes reales.
        </p>
        <p class="text-sm text-gray-600 leading-relaxed">
            Para citas reales después podrás configurar tus servicios, agregar clientes y, si lo deseas, organizar
            personal y zonas de atención desde las secciones correspondientes.
        </p>
    </div>
</template>

<template id="aa-onboarding-welcome-footer-template">
    <div class="flex justify-end">
        <button
            type="button"
            id="aa-onboarding-welcome-dismiss"
            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
            Comenzar
        </button>
    </div>
</template>

<template id="aa-onboarding-activation-guide-body-template">
    <div class="aa-onboarding-activation-guide" data-aa-onboarding-activation-guide="1">
        <div id="aa-onboarding-activation-guide-summary" class="mb-3"></div>
        <div id="aa-onboarding-activation-guide-steps" class="space-y-2"></div>
        <div id="aa-onboarding-activation-guide-google-recommended" class="mt-4"></div>
    </div>
</template>

<template id="aa-onboarding-activation-guide-footer-template">
    <div class="flex justify-end">
        <button
            type="button"
            id="aa-onboarding-activation-guide-close"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Cerrar
        </button>
    </div>
</template>
