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
            Para usar tu agenda necesitas configurar 4 cosas básicas: cliente, servicio, personal y zona de atención.
            Después podrás crear tu primera cita.
        </p>
        <p class="text-sm text-gray-600 leading-relaxed">
            El menú está en la esquina superior izquierda.
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
