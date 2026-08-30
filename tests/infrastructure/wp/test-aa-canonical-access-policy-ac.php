<?php
/**
 * AC Test — AA_Canonical_Access_Policy (Ciclo 3C1).
 *
 * Ejecutar:
 *   php tests/infrastructure/wp/test-aa-canonical-access-policy-ac.php
 */

$plugin_root = dirname(__DIR__, 3);
$policy_file = $plugin_root . '/includes/infrastructure/wp/class-aa-canonical-access-policy.php';

$total = 0;
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

echo "=== 1. Análisis estático de AA_Canonical_Access_Policy ===\n";

ac_assert('Archivo class-aa-canonical-access-policy.php existe y es legible', is_readable($policy_file));
$policy_src = file_get_contents($policy_file);
ac_assert('Contenido no vacío', is_string($policy_src) && $policy_src !== '');
ac_assert('Define clase AA_Canonical_Access_Policy', strpos($policy_src, 'final class AA_Canonical_Access_Policy') !== false);
ac_assert('No contiene wp_die ni wp_send_json', strpos($policy_src, 'wp_die(') === false && strpos($policy_src, 'wp_send_json') === false);
ac_assert('No contiene dependencias HTML ni AJAX', strpos($policy_src, '$_POST') === false && strpos($policy_src, '$_GET') === false);

echo "\n=== 2. Pruebas de Decisión Neutral con Entorno Simulado ===\n";

if (!defined('ABSPATH')) {
    define('ABSPATH', $plugin_root . '/');
}

$GLOBALS['mock_is_logged_in'] = true;
$GLOBALS['mock_is_multisite'] = false;
$GLOBALS['mock_is_member_of_blog'] = true;
$GLOBALS['mock_can_manage_options'] = false;

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool {
        return (bool) $GLOBALS['mock_is_logged_in'];
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool {
        return (bool) $GLOBALS['mock_is_multisite'];
    }
}

if (!function_exists('is_user_member_of_blog')) {
    function is_user_member_of_blog(): bool {
        return (bool) $GLOBALS['mock_is_member_of_blog'];
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $cap): bool {
        if ($cap === 'manage_options') {
            return (bool) $GLOBALS['mock_can_manage_options'];
        }
        return false;
    }
}

require_once $policy_file;

// 2.1 Usuario no autenticado
$GLOBALS['mock_is_logged_in'] = false;
$res_unauth = AA_Canonical_Access_Policy::check_family_access('finance');
ac_assert('Usuario no autenticado devuelve authorized=false', $res_unauth['authorized'] === false);
ac_assert('Usuario no autenticado devuelve code=unauthorized y status=401', $res_unauth['code'] === 'unauthorized' && $res_unauth['status'] === 401);

// 2.2 Single Site: Usuario autenticado sin manage_options para familia finance
$GLOBALS['mock_is_logged_in'] = true;
$GLOBALS['mock_is_multisite'] = false;
$GLOBALS['mock_can_manage_options'] = false;
$res_ss_finance = AA_Canonical_Access_Policy::check_family_access('finance');
ac_assert('Single site: autenticado sin manage_options accede a finance', $res_ss_finance['authorized'] === true);
ac_assert('Single site: devuelve code=authorized y status=200', $res_ss_finance['code'] === 'authorized' && $res_ss_finance['status'] === 200);

// 2.3 Single Site: Usuario autenticado sin manage_options para otra familia canónica (ej. 'hr')
$res_ss_other = AA_Canonical_Access_Policy::check_family_access('hr');
ac_assert('Single site: autenticado sin manage_options es rechazado para otra familia', $res_ss_other['authorized'] === false);
ac_assert('Single site: rechazo para otra familia devuelve code=forbidden y status=403', $res_ss_other['code'] === 'forbidden' && $res_ss_other['status'] === 403);

// Con manage_options sí accede a otra familia
$GLOBALS['mock_can_manage_options'] = true;
$res_ss_other_admin = AA_Canonical_Access_Policy::check_family_access('hr');
ac_assert('Single site: con manage_options accede a otra familia', $res_ss_other_admin['authorized'] === true);

// 2.4 Multisite: Usuario miembro del blog para finance
$GLOBALS['mock_is_multisite'] = true;
$GLOBALS['mock_is_member_of_blog'] = true;
$GLOBALS['mock_can_manage_options'] = false;
$res_ms_member = AA_Canonical_Access_Policy::check_family_access('finance');
ac_assert('Multisite: miembro del blog accede a finance', $res_ms_member['authorized'] === true);

// 2.5 Multisite: Usuario autenticado pero NO miembro del blog actual
$GLOBALS['mock_is_member_of_blog'] = false;
$res_ms_non_member = AA_Canonical_Access_Policy::check_family_access('finance');
ac_assert('Multisite: usuario no miembro es rechazado para finance', $res_ms_non_member['authorized'] === false);
ac_assert('Multisite: rechazo de no miembro devuelve code=forbidden y status=403', $res_ms_non_member['code'] === 'forbidden' && $res_ms_non_member['status'] === 403);

// 2.6 Multisite: Superadministrador no miembro (sin bypass accidental)
$GLOBALS['mock_is_member_of_blog'] = false;
$GLOBALS['mock_can_manage_options'] = true; // Simula tener capacidades amplias
$res_ms_super_non_member = AA_Canonical_Access_Policy::check_family_access('finance');
ac_assert('Multisite: superadministrador no miembro no obtiene bypass y es rechazado', $res_ms_super_non_member['authorized'] === false && $res_ms_super_non_member['status'] === 403);

echo "\n--- Resumen: {$passed}/{$total} ---\n";

if ($failed !== []) {
    echo "Fallidos:\n";
    foreach ($failed as $label) {
        echo "  - {$label}\n";
    }
    exit(1);
}

exit(0);
