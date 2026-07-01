<?php
/**
 * Account Module - Account & subscription UI
 *
 * This module handles:
 * - Display of account/subscription status (via admin AJAX)
 * - No business logic (data from GetAccountStatusUseCase via AJAX)
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$account_module_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
$aa_logout_url      = wp_logout_url(home_url('/agenda-app/'));
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
            <div id="aa-account-status-root" class="space-y-4">

                <div id="aa-account-status-loading" class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p class="text-sm text-gray-600">Consultando estado de cuenta…</p>
                </div>

                <div id="aa-account-status-content" class="hidden space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            id="aa-account-status-badge"
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border"
                        ></span>
                    </div>

                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-gray-500">Plan</dt>
                            <dd id="aa-account-plan" class="text-sm font-medium text-gray-900 mt-0.5">—</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Acceso actual</dt>
                            <dd id="aa-account-access" class="text-sm font-medium text-gray-900 mt-0.5">—</dd>
                        </div>
                    </dl>

                    <div id="aa-account-notice" class="hidden rounded-lg border p-4 text-sm"></div>
                    <div id="aa-account-upgrade-return-notice" class="hidden rounded-lg border p-4 text-sm"></div>
                    <div id="aa-account-notice-actions" class="hidden flex flex-col gap-2"></div>

                    <ul id="aa-account-messages" class="hidden list-disc list-inside space-y-1 text-sm text-gray-600"></ul>

                    <div id="aa-account-benefit-quotas" class="hidden pt-4 border-t border-gray-100">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Beneficios del mes</h4>
                        <ul id="aa-account-benefit-quotas-list" class="space-y-2 text-sm text-gray-700"></ul>
                        <p id="aa-account-benefit-quotas-unavailable" class="hidden text-sm text-gray-500 mt-2"></p>
                    </div>

                    <div id="aa-account-upgrade-section" class="hidden pt-4 border-t border-gray-100 space-y-4">
                        <div id="aa-account-upgrade-cta-wrap">
                            <button
                                type="button"
                                id="aa-account-upgrade-button"
                                class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-violet-600 text-white hover:bg-violet-700 transition-colors"
                            >
                                Adquirir Pro
                            </button>
                        </div>

                        <div id="aa-account-upgrade-card" class="hidden rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                                <span class="text-lg font-semibold text-violet-900 tracking-wide">PRO</span>
                                <span class="text-sm font-medium text-violet-800">$100 MXN / mes</span>
                            </div>
                            <p class="text-sm text-gray-700 mb-4">
                                Más capacidad para automatizar tu agenda y crecer sin fricción.
                            </p>
                            <ul class="space-y-2 text-sm text-gray-700 mb-5" aria-label="Límites mensuales PRO">
                                <li class="flex items-start gap-2">
                                    <span class="text-violet-600 mt-0.5" aria-hidden="true">•</span>
                                    <span>300 correos de confirmación y recordatorio al mes</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-violet-600 mt-0.5" aria-hidden="true">•</span>
                                    <span>300 solicitudes IA al mes</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="text-violet-600 mt-0.5" aria-hidden="true">•</span>
                                    <span>700 sincronizaciones con Google Calendar al mes</span>
                                </li>
                            </ul>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    id="aa-account-upgrade-continue"
                                    class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                                >
                                    Continuar con PRO
                                </button>
                                <button
                                    type="button"
                                    id="aa-account-upgrade-back"
                                    class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    Volver
                                </button>
                            </div>
                            <p id="aa-account-upgrade-loading" class="hidden text-sm text-gray-600 mt-3">
                                Abriendo checkout seguro…
                            </p>
                            <p id="aa-account-upgrade-error" class="hidden text-sm text-red-700 mt-3"></p>
                        </div>
                    </div>

                    <div id="aa-account-billing-action" class="hidden pt-4 border-t border-gray-100">
                        <p id="aa-account-billing-hint" class="hidden text-sm text-gray-600 mb-2"></p>
                        <button
                            type="button"
                            id="aa-account-billing-button"
                            class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-60 disabled:cursor-not-allowed"
                        ></button>
                        <p id="aa-account-billing-loading" class="hidden text-sm text-gray-600 mt-2">
                            Abriendo gestión de suscripción…
                        </p>
                        <p id="aa-account-billing-error" class="hidden text-sm text-red-700 mt-2"></p>
                    </div>

                    <div id="aa-account-public-site-section" class="hidden pt-4 border-t border-gray-100">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Sitio web público</h4>
                        <p class="text-sm text-gray-600">
                            <span class="text-gray-500">Estado:</span>
                            <span id="aa-account-public-site-status" class="font-medium text-gray-900"></span>
                        </p>
                        <div id="aa-account-public-site-action" class="hidden mt-3">
                            <button
                                type="button"
                                id="aa-account-public-site-activate-button"
                                disabled
                                class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-violet-600 text-white opacity-60 cursor-not-allowed"
                            >
                                Activar sitio web
                            </button>
                            <p id="aa-account-public-site-help" class="text-sm text-gray-500 mt-2">
                                Disponible cuando el sitio web esté configurado.
                            </p>
                        </div>
                        <a
                            id="aa-account-public-site-preview-link"
                            href="#"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="hidden inline-flex items-center mt-3 text-sm font-medium text-violet-700 hover:text-violet-800"
                        >
                            Ver sitio web público
                        </a>
                    </div>
                </div>

                <div id="aa-account-status-error" class="hidden rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p id="aa-account-status-error-message" class="text-sm text-gray-600"></p>
                    <div id="aa-account-status-error-actions" class="hidden mt-3 flex flex-col gap-2"></div>
                </div>

            </div>

            <div id="aa-account-session-section" class="pt-4 mt-4 border-t border-gray-100">
                <h4 class="text-sm font-medium text-gray-900 mb-2">Sesión</h4>
                <a
                    href="<?php echo esc_url($aa_logout_url); ?>"
                    target="_top"
                    rel="noopener noreferrer"
                    class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors"
                >
                    Cerrar sesión
                </a>
            </div>
        </div>
    </div>

</div>

<script>
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    }

    window.AA_ACCOUNT_DATA = {
        ajaxUrl: window.ajaxurl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
        nonce: '<?php echo esc_js(wp_create_nonce('aa_get_account_status_nonce')); ?>',
        billingNonce: '<?php echo esc_js(wp_create_nonce('aa_create_billing_portal_session_nonce')); ?>',
        upgradeCheckoutNonce: '<?php echo esc_js(wp_create_nonce('aa_create_upgrade_checkout_session_nonce')); ?>'
    };
</script>
<?php
$account_upgrade_ux_js = function_exists('aa_asset_url')
    ? aa_asset_url('assets/js/services/accountUpgradeUx.js')
    : esc_url(plugins_url('assets/js/services/accountUpgradeUx.js', dirname(__DIR__, 5) . '/wp-agenda-automatizada.php'));
$account_status_error_ux_js = function_exists('aa_asset_url')
    ? aa_asset_url('assets/js/services/accountStatusErrorUx.js')
    : esc_url(plugins_url('assets/js/services/accountStatusErrorUx.js', dirname(__DIR__, 5) . '/wp-agenda-automatizada.php'));
$account_benefit_quotas_ux_js = function_exists('aa_asset_url')
    ? aa_asset_url('assets/js/services/accountBenefitQuotasUx.js')
    : esc_url(plugins_url('assets/js/services/accountBenefitQuotasUx.js', dirname(__DIR__, 5) . '/wp-agenda-automatizada.php'));
?>
<script src="<?php echo esc_url($account_upgrade_ux_js); ?>" defer></script>
<script src="<?php echo esc_url($account_status_error_ux_js); ?>" defer></script>
<script src="<?php echo esc_url($account_benefit_quotas_ux_js); ?>" defer></script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'module.js?ver=' . rawurlencode($account_module_ver)); ?>" defer></script>
