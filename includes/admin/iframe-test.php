<?php
/**
 * Admin UI Gateway - Container for iframe-based admin interface
 * 
 * This file acts as a gateway that:
 * 1. Registers the admin menu page
 * 2. Renders the iframe container
 * 3. Handles admin-post requests and delegates to the UI layer
 */

defined('ABSPATH') or die('¡Sin acceso directo!');


// ================================
// Render iframe container
// ================================
function aa_render_iframe_test_page() {
    $iframe_url = admin_url('admin-post.php?action=aa_iframe_content&module=calendar');
    ?>
    <div class="wrap">
        <iframe 
            id="aa-isolated-iframe"
            src="<?php echo esc_url($iframe_url); ?>"
            style="width: 100%; height: 800px; border: none;"
        ></iframe>
    </div>
    <?php
}

// ================================
// Handler: Delegate to UI layer
// ================================
add_action('admin_post_aa_iframe_content', 'aa_handle_iframe_content');
add_action('admin_post_nopriv_aa_iframe_content', 'aa_handle_iframe_content_nopriv');

if (!class_exists('AA_Canonical_Shell_Url_Policy')) {
    require_once dirname(__DIR__) . '/infrastructure/wp/class-aa-canonical-shell-url-policy.php';
}
if (!class_exists('AA_Canonical_Shell_Base_Url_Policy')) {
    require_once dirname(__DIR__) . '/infrastructure/wp/class-aa-canonical-shell-base-url-policy.php';
}

/**
 * Handle iframe content request for non-authenticated users
 * Redirects to login with redirect_to parameter
 */
function aa_handle_iframe_content_nopriv() {
    $module_raw = isset($_GET['module']) ? sanitize_key($_GET['module']) : '';
    if ($module_raw === AA_Canonical_Shell_Url_Policy::MODULE_CANONICAL) {
        $family = isset($_GET['family']) && is_string($_GET['family']) ? wp_unslash($_GET['family']) : '';
        $variant = isset($_GET['variant']) && is_string($_GET['variant']) ? wp_unslash($_GET['variant']) : null;
        $target_url = AA_Canonical_Shell_Url_Policy::build_url($family, $variant);
    } elseif ($module_raw === AA_Canonical_Shell_Base_Url_Policy::MODULE_SHELL) {
        $family = isset($_GET['family']) && is_string($_GET['family']) ? wp_unslash($_GET['family']) : '';
        $variant = isset($_GET['variant']) && is_string($_GET['variant']) ? wp_unslash($_GET['variant']) : null;
        if (is_string($family) && $family !== '' && AA_Canonical_Key::is_valid($family)) {
            $target_url = AA_Canonical_Shell_Base_Url_Policy::build_url(
                $family,
                (is_string($variant) && $variant !== '') ? $variant : null
            );
        } else {
            $target_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url();
        }
    } else {
        $target_url = admin_url('admin-post.php?action=aa_iframe_content');
        if ($module_raw !== '') {
            $target_url = add_query_arg('module', $module_raw, $target_url);
        }
    }
    
    // Redirect to login with redirect_to parameter
    $login_url = aa_app_login_url($target_url);
    wp_safe_redirect($login_url);
    exit;
}

function aa_handle_iframe_content() {
    // Defensive guard: redirect to login if not logged in
    if (!is_user_logged_in()) {
        $module_raw = isset($_GET['module']) ? sanitize_key($_GET['module']) : '';
        if ($module_raw === AA_Canonical_Shell_Url_Policy::MODULE_CANONICAL) {
            $family = isset($_GET['family']) && is_string($_GET['family']) ? wp_unslash($_GET['family']) : '';
            $variant = isset($_GET['variant']) && is_string($_GET['variant']) ? wp_unslash($_GET['variant']) : null;
            $target_url = AA_Canonical_Shell_Url_Policy::build_url($family, $variant);
        } elseif ($module_raw === AA_Canonical_Shell_Base_Url_Policy::MODULE_SHELL) {
            $family = isset($_GET['family']) && is_string($_GET['family']) ? wp_unslash($_GET['family']) : '';
            $variant = isset($_GET['variant']) && is_string($_GET['variant']) ? wp_unslash($_GET['variant']) : null;
            if (is_string($family) && $family !== '' && AA_Canonical_Key::is_valid($family)) {
                $target_url = AA_Canonical_Shell_Base_Url_Policy::build_url(
                    $family,
                    (is_string($variant) && $variant !== '') ? $variant : null
                );
            } else {
                $target_url = AA_Canonical_Shell_Base_Url_Policy::build_module_url();
            }
        } else {
            $target_url = admin_url('admin-post.php?action=aa_iframe_content');
            if ($module_raw !== '') {
                $target_url = add_query_arg('module', $module_raw, $target_url);
            }
        }
        
        // Redirect to login
        $login_url = aa_app_login_url($target_url);
        wp_safe_redirect($login_url);
        exit;
    }
    
    // Capability for the operational shell is enforced in ui/index.php after the
    // legal gate. Logged-in users without manage_options may still see the
    // informative blocking gate when terms are pending.
    
    // Delegate to UI entry point (module parameter is handled inside ui/index.php)
    $ui_path = plugin_dir_path(__FILE__) . 'ui/index.php';

    if (file_exists($ui_path)) {
        require_once $ui_path;
    } else {
        wp_die('UI no encontrada', 'Error', ['response' => 404]);
    }
}
