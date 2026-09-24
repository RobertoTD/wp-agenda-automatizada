<?php
/**
 * Canonical Layout — Shell ligera para módulos y vistas canónicas.
 *
 * Proporciona:
 * - Estructura HTML y Tailwind CSS
 * - Header, Sidebar y Footer seguros
 * - Proyección de gate legal con URL base suministrada por PHP
 * - SIN consultas de reservaciones, SIN nonces legacy y SIN modales legacy.
 *
 * @package WP_Agenda_Automatizada
 * @subpackage Admin\UI\Shared
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

$active_module = isset($active_module) ? $active_module : 'canonical';
$module_path = isset($module_path) ? $module_path : '';

$aa_installation_slug = class_exists('AA_Installation_Display_Slug')
    ? AA_Installation_Display_Slug::resolve()
    : null;

$aa_auth_session_id = '';
if (function_exists('wp_get_session_token')) {
    $aa_session_token = wp_get_session_token();
    if (is_string($aa_session_token) && $aa_session_token !== '') {
        $aa_auth_session_id = hash_hmac('sha256', $aa_session_token, wp_salt('auth'));
    }
}

if (!function_exists('aa_asset_url')) {
    function aa_asset_url($relative_path) {
        $base = AA_PLUGIN_URL . ltrim($relative_path, '/');
        $ver  = defined('AA_PLUGIN_VERSION') ? AA_PLUGIN_VERSION : '1.0.0';
        return esc_url($base . '?ver=' . rawurlencode($ver));
    }
}

// Canon libre v2 no construye navegación por familias.
$aa_canonical_record_types_nav = [];
$can_manage_options = current_user_can('manage_options');
if (empty($aa_canonical_free_mode) && $can_manage_options
    && class_exists('AA_Canonical_Core_Bootstrap')
    && class_exists('AA_Canonical_Family_Enablement_Store')
    && class_exists('ReadCanonicalFamilyEnablementUseCase')
    && class_exists('AA_Canonical_Family_Enablement_Nav')
) {
    try {
        $aa_enablement_registry = AA_Canonical_Core_Bootstrap::instance();
        $aa_enablement_snapshot = (new ReadCanonicalFamilyEnablementUseCase(
            new AA_Canonical_Family_Enablement_Store()
        ))->execute($aa_enablement_registry);
        $aa_canonical_record_types_nav = AA_Canonical_Family_Enablement_Nav::build(
            $aa_enablement_registry,
            $aa_enablement_snapshot
        );
    } catch (\Throwable $e) {
        $aa_canonical_record_types_nav = [];
    }
}

// Fill-height de la vista de registros reales (empty/resolved_page). No preview ni gates.
$aa_shell_records_fill = false;
if (
    $active_module === 'canonical_shell'
    && isset($aa_shell_route_state)
    && $aa_shell_route_state === 'resolved'
    && isset($aa_shell_view)
    && is_array($aa_shell_view)
    && empty($aa_shell_view['is_preview'])
    && isset($aa_shell_view['shell_view'])
    && $aa_shell_view['shell_view'] === 'records'
    && isset($aa_shell_view['read_state'])
    && in_array((string) $aa_shell_view['read_state'], ['empty', 'resolved_page'], true)
) {
    $aa_shell_records_fill = true;
}

// Send headers
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Automatizada - Admin</title>
    <meta name="theme-color" content="#8b5cf6">
    <meta name="application-name" content="DEOIA">
    <meta name="apple-mobile-web-app-title" content="DEOIA">

    <script>
    (function () {
        var root = document.documentElement;
        if (window.self !== window.top) {
            root.classList.add('aa-embedded');
        } else {
            root.classList.add('aa-standalone');
        }
        <?php if ($aa_shell_records_fill) : ?>
        root.classList.add('aa-shell-records-fill');
        <?php endif; ?>
    })();
    </script>

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo aa_asset_url('includes/admin/ui/assets/css/admin.css'); ?>">
</head>
<body
    class="flex flex-col min-h-screen<?php echo $aa_shell_records_fill ? ' aa-shell-records-fill' : ''; ?>"
    data-aa-module="<?php echo esc_attr($active_module); ?>"
    <?php if ($aa_shell_records_fill) : ?>data-aa-shell-records-fill="1"<?php endif; ?>
    style="background-color: rgb(240, 240, 241);"
>

<script>
    window.ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

    // Proyección asíncrona de gate legal suministrada con URL base limpia segura
    window.AA_SHELL_ACCESS_DATA = {
        ajaxUrl: window.ajaxurl || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
        statusAction: 'aa_get_legal_gate_status',
        nonce: '<?php echo esc_js(wp_create_nonce('aa_legal_gate_nonce')); ?>',
        gateParam: 'aa_gate',
        blogId: <?php echo (int) get_current_blog_id(); ?>,
        authSessionId: <?php echo wp_json_encode($aa_auth_session_id); ?>,
        canonicalUrl: <?php echo wp_json_encode($aa_canonical_url ?? ''); ?>,
        ttlMs: 60000
    };
</script>

<!-- Scripts administrativos básicos (resize, sidebar, gate projection) -->
<script src="<?php echo aa_asset_url('includes/admin/ui/assets/js/main.js'); ?>" defer></script>
<script src="<?php echo aa_asset_url('includes/admin/ui/assets/js/sidebar.js'); ?>" defer></script>
<script src="<?php echo aa_asset_url('assets/js/services/shellAccessProjection.js'); ?>" defer></script>
<?php if ($active_module === 'canonical_shell' && empty($aa_canonical_free_mode)) : ?>
<script src="<?php echo aa_asset_url('includes/admin/ui/assets/js/canonical-family-switcher.js'); ?>" defer></script>
<?php endif; ?>

    <div id="aa-admin-app" class="w-full flex flex-col min-h-screen">
        <?php require_once __DIR__ . '/header.php'; ?>

        <main id="aa-admin-content" class="flex-1 px-2 pt-2 pb-2">
            <div id="aa-module-root">
            <?php
            if (file_exists($module_path)) {
                require_once $module_path;
            } else {
                echo '<div class="p-4 bg-red-50 text-red-700 rounded">Módulo no encontrado.</div>';
            }
            ?>
            </div>
        </main>

        <?php require_once __DIR__ . '/footer.php'; ?>
    </div>

    <!-- Sidebar compartido -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

</body>
</html>
<?php
// Terminate execution to prevent WordPress output
die();
