<?php
/**
 * Account Module - Account & subscription UI shell
 *
 * This module handles:
 * - Placeholder for account, access and subscription management
 * - No business logic (billing and portal integration in later stages)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');
?>

<div class="max-w-5xl mx-auto py-2">

    <!-- ═══════════════════════════════════════════════════════════════
         SECCIÓN: Cuenta
    ═══════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden">
        <div class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white rounded-t-xl">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </span>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Cuenta</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Gestiona el acceso, suscripción y datos principales de esta agenda.</p>
                </div>
            </div>
        </div>

        <div class="p-4 transition-all duration-200">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <p class="text-sm text-gray-600">
                    La información de suscripción se conectará en una etapa posterior.
                </p>
            </div>
        </div>
    </div>

</div>
