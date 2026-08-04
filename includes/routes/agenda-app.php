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
        $redirect_url = aa_app_login_url(home_url('/agenda-app/'));
        wp_redirect($redirect_url);
        exit;
    }
    
    // Capability for the operational shell is enforced in the UI router after the
    // legal gate. Non-admins may still be redirected so they can see the
    // informative blocking screen when acceptance is pending.
    
    // User is logged in; redirect to app UI (gate / manage_options checked there)
    $app_url = admin_url('admin-post.php?action=aa_iframe_content&module=calendar');
    wp_redirect($app_url);
    exit;
}
add_action('template_redirect', 'aa_handle_agenda_app_redirect');

