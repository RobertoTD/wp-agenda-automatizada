<?php
/**
 * Agenda App Route Handler
 * 
 * Creates a dedicated frontend endpoint /agenda-app that:
 * - Redirects non-logged users to WP login
 * - Validates permissions for logged users
 * - Redirects to the clean app UI
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// ================================
// Register rewrite rule
// ================================
function aa_register_agenda_app_rewrite_rule() {
    add_rewrite_rule(
        '^agenda-app/?$',
        'index.php?aa_agenda_app=1',
        'top'
    );
}
add_action('init', 'aa_register_agenda_app_rewrite_rule');

// ================================
// Register query var
// ================================
function aa_register_agenda_app_query_var($vars) {
    $vars[] = 'aa_agenda_app';
    return $vars;
}
add_filter('query_vars', 'aa_register_agenda_app_query_var');

// ================================
// Intercept request and redirect
// ================================
function aa_handle_agenda_app_redirect() {
    $aa_agenda_app = get_query_var('aa_agenda_app');
    
    if (!$aa_agenda_app) {
        return; // Not our route, continue normal flow
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        // Redirect to login with redirect_to parameter
        $redirect_url = wp_login_url(home_url('/agenda-app/'));
        wp_redirect($redirect_url);
        exit;
    }
    
    // Check permissions (for now, only manage_options)
    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado', 'Error', ['response' => 403]);
    }
    
    // User is logged in and has permissions, redirect to app UI
    $app_url = admin_url('admin-post.php?action=aa_iframe_content&module=calendar');
    wp_redirect($app_url);
    exit;
}
add_action('template_redirect', 'aa_handle_agenda_app_redirect');

