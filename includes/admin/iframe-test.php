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
    $iframe_url = admin_url('admin-post.php?action=aa_iframe_content');
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

function aa_handle_iframe_content() {
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
