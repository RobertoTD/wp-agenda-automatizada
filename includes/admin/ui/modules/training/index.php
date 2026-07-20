<?php
/**
 * Training Module — portal shell (C8A2).
 *
 * Catalog and lesson reader arrive in C8A3. Entry is via Cuenta → Abrir curso.
 * Not listed in the sidebar yet.
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$training_module_ver = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
$aa_training_account_url = admin_url('admin-post.php?action=aa_iframe_content&module=account');
$aa_training_module_url  = admin_url('admin-post.php?action=aa_iframe_content&module=training');
$aa_training_ajax_url    = admin_url('admin-ajax.php');
$aa_training_nonce       = wp_create_nonce(
    class_exists('TrainingAjax') ? TrainingAjax::NONCE_ACTION : 'aa_training_nonce'
);
?>

<div class="max-w-5xl mx-auto py-2">
    <div class="bg-white rounded-xl shadow border border-gray-200 mb-2 overflow-hidden">
        <div class="px-4 py-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white rounded-t-xl">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 text-gray-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900">Capacitación DEOIA</h3>
                        <p class="text-sm text-gray-500 mt-0.5">Portal del curso Fundamentos DEOIA</p>
                    </div>
                </div>
                <a
                    href="<?php echo esc_url($aa_training_account_url); ?>"
                    class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors shrink-0"
                >
                    Volver a Cuenta
                </a>
            </div>
        </div>

        <div class="p-4 transition-all duration-200">
            <div id="aa-training-shell-root" class="space-y-4">
                <div
                    id="aa-training-shell-loading"
                    class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                    role="status"
                    aria-live="polite"
                >
                    <p class="text-sm text-gray-600">Preparando tu curso…</p>
                </div>

                <div id="aa-training-shell-error" class="hidden rounded-lg border border-gray-200 bg-gray-50 p-4" role="alert">
                    <p id="aa-training-shell-error-message" class="text-sm text-gray-600"></p>
                </div>

                <!-- Reserved for C8A3 catalog -->
                <div id="aa-training-catalog-root" class="hidden" data-aa-training-slot="catalog" aria-hidden="true"></div>

                <!-- Reserved for C8A3 lesson reader -->
                <div id="aa-training-lesson-root" class="hidden" data-aa-training-slot="lesson" aria-hidden="true"></div>
            </div>
        </div>
    </div>
</div>

<script>
    if (typeof window.ajaxurl === 'undefined') {
        window.ajaxurl = '<?php echo esc_js($aa_training_ajax_url); ?>';
    }

    window.AA_TRAINING_DATA = {
        ajaxUrl: window.ajaxurl || '<?php echo esc_js($aa_training_ajax_url); ?>',
        nonce: '<?php echo esc_js($aa_training_nonce); ?>',
        actions: {
            getStatus: '<?php echo esc_js(class_exists('TrainingAjax') ? TrainingAjax::ACTION_GET_STATUS : 'aa_get_training_status'); ?>',
            enroll: '<?php echo esc_js(class_exists('TrainingAjax') ? TrainingAjax::ACTION_ENROLL : 'aa_enroll_training'); ?>',
            unsubscribe: '<?php echo esc_js(class_exists('TrainingAjax') ? TrainingAjax::ACTION_UNSUBSCRIBE : 'aa_unsubscribe_training'); ?>',
            getConsentStatus: '<?php echo esc_js(class_exists('TrainingAjax') ? TrainingAjax::ACTION_GET_CONSENT_STATUS : 'aa_get_training_consent_status'); ?>',
            acceptConsent: '<?php echo esc_js(class_exists('TrainingAjax') ? TrainingAjax::ACTION_ACCEPT_CONSENT : 'aa_accept_training_consent'); ?>',
            revokeConsent: '<?php echo esc_js(class_exists('TrainingAjax') ? TrainingAjax::ACTION_REVOKE_CONSENT : 'aa_revoke_training_consent'); ?>',
            getCourse: '<?php echo esc_js(class_exists('TrainingAjax') ? TrainingAjax::ACTION_GET_COURSE : 'aa_get_training_course'); ?>',
            getLesson: '<?php echo esc_js(class_exists('TrainingAjax') ? TrainingAjax::ACTION_GET_LESSON : 'aa_get_training_lesson'); ?>'
        },
        courseKey: 'fundamentos-deoia',
        trainingModuleUrl: <?php echo wp_json_encode($aa_training_module_url); ?>
    };
</script>
<?php
$training_service_js = function_exists('aa_asset_url')
    ? aa_asset_url('assets/js/services/trainingService.js')
    : esc_url(plugins_url('assets/js/services/trainingService.js', dirname(__DIR__, 5) . '/wp-agenda-automatizada.php'));
?>
<script src="<?php echo esc_url($training_service_js); ?>" defer></script>
<script src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'module.js?ver=' . rawurlencode($training_module_ver)); ?>" defer></script>
