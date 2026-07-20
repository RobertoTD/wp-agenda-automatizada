<?php
/**
 * C8A2 — Training card / module shell structural AC.
 *
 * Ejecutar: php tests/admin/ui/test-training-c8a2-ac.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

$plugin_root = dirname(__DIR__, 3);

$total  = 0;
$passed = 0;
$failed = [];

function ac_assert(string $label, bool $ok, string $detail = ''): void {
    global $total, $passed, $failed;

    $total++;
    if ($ok) {
        $passed++;
        echo '[ OK ] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
        return;
    }

    $failed[] = $label;
    echo '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . "\n";
}

function ac_read(string $relative): string {
    global $plugin_root;
    $path = $plugin_root . '/' . $relative;
    $src  = file_get_contents($path);
    return is_string($src) ? $src : '';
}

$router   = ac_read('includes/admin/ui/index.php');
$account  = ac_read('includes/admin/ui/modules/account/index.php');
$account_js = ac_read('includes/admin/ui/modules/account/module.js');
$training = ac_read('includes/admin/ui/modules/training/index.php');
$training_js = ac_read('includes/admin/ui/modules/training/module.js');
$sidebar  = ac_read('includes/admin/ui/shared/sidebar.php');
$layout   = ac_read('includes/admin/ui/shared/layout.php');
$ux       = ac_read('assets/js/services/trainingAccountUx.js');

ac_assert('19. training está en la allowlist', strpos($router, "'training'") !== false);
ac_assert(
    '20. shell training index existe',
    $training !== '' && strpos($training, 'aa-training-shell-root') !== false
);
ac_assert(
    '20b. shell carga dentro del layout (module path)',
    strpos($router, "modules/' . \$active_module . '/index.php'") !== false
    || strpos($router, 'modules/') !== false
);
ac_assert(
    '21. Volver a Cuenta usa module=account',
    strpos($training, 'module=account') !== false
    && strpos($training, 'Volver a Cuenta') !== false
);
ac_assert(
    '22. Abrir curso usa module=training en config Cuenta',
    strpos($account, 'module=training') !== false
    && strpos($account, 'trainingModuleUrl') !== false
);
ac_assert(
    '23. training no aparece en sidebar',
    strpos($sidebar, 'module=training') === false
);
ac_assert(
    '24. shell Training encola servicio y portal (C8A3 carga contenido)',
    strpos($training, 'trainingService.js') !== false
    && strpos($training, 'trainingPortalUx.js') !== false
    && strpos($training_js, 'getCourse') !== false
    && strpos($training_js, 'getLesson') !== false
);
ac_assert(
    '25. config Cuenta sin secretos ni HMAC ni URL backend',
    strpos($account, 'AA_TRAINING_DATA') !== false
    && strpos($account, 'aa_client_secret') === false
    && strpos($account, 'client_secret') === false
    && !preg_match('/HMAC|hmac/', $account)
    && strpos($account, 'AA_API_BASE_URL') === false
    && strpos($account, 'api.deoia.com') === false
);
ac_assert(
    '25b. config Training módulo sin secretos',
    strpos($training, 'AA_TRAINING_DATA') !== false
    && strpos($training, 'client_secret') === false
    && !preg_match('/HMAC|hmac/', $training)
    && strpos($training, 'AA_API_BASE_URL') === false
);
ac_assert(
    'nonce aa_training_nonce / TrainingAjax::NONCE_ACTION',
    strpos($account, 'aa_training_nonce') !== false
    || strpos($account, 'TrainingAjax::NONCE_ACTION') !== false
);
ac_assert(
    'courseKey fundamentos-deoia publicado',
    strpos($account, "courseKey: 'fundamentos-deoia'") !== false
    && strpos($training, "courseKey: 'fundamentos-deoia'") !== false
);
ac_assert(
    'tarjeta DOM Capacitación DEOIA en Cuenta',
    strpos($account, 'aa-training-card-root') !== false
    && strpos($account, 'Capacitación DEOIA') !== false
);
ac_assert(
    'consentimiento subsección en Cuenta',
    strpos($account, 'aa-training-consent-section') !== false
);
ac_assert(
    'Cuenta encola trainingService + trainingAccountUx',
    strpos($account, 'trainingService.js') !== false
    && strpos($account, 'trainingAccountUx.js') !== false
);
ac_assert(
    'AA_TRAINING_DATA no está en layout global',
    strpos($layout, 'AA_TRAINING_DATA') === false
);
ac_assert(
    'module.js inicia Training de forma independiente',
    strpos($account_js, 'initTrainingCard') !== false
    && strpos($account_js, 'loadTrainingStatus') !== false
);
ac_assert(
    'unsubscribe requiere confirmación',
    strpos($account_js, 'confirm') !== false
    && strpos($ux, 'UNSUBSCRIBE_CONFIRM_MESSAGE') !== false
);
ac_assert(
    'reactivate mapea a enroll',
    strpos($ux, "actionId === 'enroll' || actionId === 'reactivate'") !== false
    || strpos($ux, "actionId === 'reactivate'") !== false
);
ac_assert(
    'consent no bloquea open (showConsent solo en active)',
    strpos($ux, 'showConsent = true') !== false
    && strpos($account_js, "actionId === 'open'") !== false
);
ac_assert(
    'slots catálogo/lección con estructura C8A3',
    strpos($training, 'aa-training-catalog-root') !== false
    && strpos($training, 'aa-training-lesson-root') !== false
    && strpos($training, 'aa-training-catalog-lessons') !== false
    && strpos($training_js, 'backToCatalog') !== false
);

// ─── Navigation URL encoding (Abrir curso / Volver a Cuenta) ───────────────

function ac_extract_training_module_url_emit(string $src): string {
    if (!preg_match('/trainingModuleUrl:\s*<\?php\s+echo\s+([^;]+);/', $src, $m)) {
        return '';
    }
    return trim($m[1]);
}

$account_url_emit  = ac_extract_training_module_url_emit($account);
$training_url_emit = ac_extract_training_module_url_emit($training);

ac_assert(
    '1. trainingModuleUrl se emite con wp_json_encode (Cuenta)',
    strpos($account_url_emit, 'wp_json_encode') !== false
    && strpos($account_url_emit, "module=training") !== false
    && strpos($account_url_emit, '&module=training') !== false
);
ac_assert(
    '1b. trainingModuleUrl se emite con wp_json_encode (Training)',
    strpos($training_url_emit, 'wp_json_encode') !== false
    && strpos($training_url_emit, '$aa_training_module_url') !== false
);
ac_assert(
    '2. trainingModuleUrl no usa esc_js/esc_url/esc_attr (Cuenta)',
    strpos($account_url_emit, 'esc_js') === false
    && strpos($account_url_emit, 'esc_url') === false
    && strpos($account_url_emit, 'esc_attr') === false
    && strpos($account_url_emit, 'htmlspecialchars') === false
);
ac_assert(
    '2b. trainingModuleUrl no usa esc_js (Training)',
    strpos($training_url_emit, 'esc_js') === false
    && strpos($training_url_emit, 'esc_url') === false
);
ac_assert(
    '2c. fuente no hardcodea &amp; ni &#038; en trainingModuleUrl',
    !preg_match('/trainingModuleUrl:[^\n]*&amp;/', $account)
    && !preg_match('/trainingModuleUrl:[^\n]*&#038;/', $account)
    && !preg_match('/trainingModuleUrl:[^\n]*&amp;/', $training)
);
ac_assert(
    '3. CTA Abrir curso asigna trainingModuleUrl lógica a href',
    strpos($account_js, 'cfg.trainingModuleUrl') !== false
    && strpos($account_js, "el.href = typeof cfg.trainingModuleUrl === 'string' ? cfg.trainingModuleUrl : '#'") !== false
    && strpos($account_js, 'trainingModuleUrl.replace') === false
);
ac_assert(
    '4. accountModuleUrl / Volver a Cuenta usa &module=account (admin_url + esc_url en HTML)',
    strpos($training, "admin_url('admin-post.php?action=aa_iframe_content&module=account')") !== false
    && strpos($training, 'esc_url($aa_training_account_url)') !== false
);
ac_assert(
    '5. allowlist y shell Training intactos',
    strpos($router, "'training'") !== false
    && strpos($training, 'aa-training-shell-root') !== false
);
ac_assert(
    '6. enrollment/consent no tocados en este fix (ux + handlers siguen)',
    strpos($ux, 'buildEnrollmentPresentation') !== false
    && strpos($ux, 'buildConsentPresentation') !== false
    && strpos($account_js, 'runTrainingEnrollmentMutation') !== false
    && strpos($account_js, 'runTrainingConsentMutation') !== false
);

/**
 * Runtime: wp_json_encode preserves & ; esc_js would HTML-encode it.
 */
if (!function_exists('esc_js')) {
    function esc_js($text) {
        $safe_text = htmlspecialchars((string) $text, ENT_COMPAT, 'UTF-8');
        $safe_text = str_replace("\n", '\\n', addslashes($safe_text));
        return $safe_text;
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

$sample_url = 'http://localhost/wp-admin/admin-post.php?action=aa_iframe_content&module=training';
$via_json   = wp_json_encode($sample_url);
$via_esc_js = esc_js($sample_url);

ac_assert(
    'runtime wp_json_encode conserva &module=training',
    is_string($via_json)
    && strpos($via_json, '&module=training') !== false
    && strpos($via_json, '&amp;') === false
    && strpos($via_json, '&#038;') === false
);
ac_assert(
    'runtime esc_js (regresión documentada) convertiría a &amp;',
    strpos($via_esc_js, '&amp;module=training') !== false
);

$account_back = 'http://localhost/wp-admin/admin-post.php?action=aa_iframe_content&module=account';
ac_assert(
    '4b. URL lógica account conserva &module=account',
    strpos($account_back, '&module=account') !== false
    && strpos(wp_json_encode($account_back), '&amp;') === false
);

echo "\nPassed {$passed}/{$total}\n";
if ($failed) {
    echo 'Failed: ' . implode(', ', $failed) . "\n";
    exit(1);
}

exit(0);
