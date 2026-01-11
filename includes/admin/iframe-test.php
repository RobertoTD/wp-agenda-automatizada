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

/**
 * Handle iframe content request for non-authenticated users
 * Redirects to login with redirect_to parameter
 */
function aa_handle_iframe_content_nopriv() {
    // Build target URL (where to return after login)
    $target_url = admin_url('admin-post.php?action=aa_iframe_content');
    
    // Add module parameter if present
    if (isset($_GET['module']) && !empty($_GET['module'])) {
        $module = sanitize_key($_GET['module']);
        $target_url = add_query_arg('module', $module, $target_url);
    }
    
    // Redirect to login with redirect_to parameter
    $login_url = wp_login_url($target_url);
    wp_safe_redirect($login_url);
    exit;
}

function aa_handle_iframe_content() {
    // Defensive guard: redirect to login if not logged in
    if (!is_user_logged_in()) {
        // Build target URL (where to return after login)
        $target_url = admin_url('admin-post.php?action=aa_iframe_content');
        
        // Add module parameter if present
        if (isset($_GET['module']) && !empty($_GET['module'])) {
            $module = sanitize_key($_GET['module']);
            $target_url = add_query_arg('module', $module, $target_url);
        }
        
        // Redirect to login
        $login_url = wp_login_url($target_url);
        wp_safe_redirect($login_url);
        exit;
    }
    
    // Permission check
    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado', 'Error', ['response' => 403]);
    }
    
    // Delegate to UI entry point (module parameter is handled inside ui/index.php)
    $ui_path = plugin_dir_path(__FILE__) . 'ui/index.php';

    if (file_exists($ui_path)) {
        require_once $ui_path;
    } else {
        wp_die('UI no encontrada', 'Error', ['response' => 404]);
    }
}
