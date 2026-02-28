<?php
/**
 * Citas Virtuales Route Handler
 *
 * Frontend endpoint /citas-virtuales that:
 * - Renders inside the active theme (header + footer)
 * - Resolves a join_token from query string
 * - Shows a countdown + join button for virtual appointments
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// ================================
// Register rewrite rule
// ================================
function aa_register_citas_virtuales_rewrite_rule() {
    add_rewrite_rule(
        '^citas-virtuales/?$',
        'index.php?aa_citas_virtuales=1',
        'top'
    );
}
add_action('init', 'aa_register_citas_virtuales_rewrite_rule');

// ================================
// Register query var
// ================================
function aa_register_citas_virtuales_query_var($vars) {
    $vars[] = 'aa_citas_virtuales';
    return $vars;
}
add_filter('query_vars', 'aa_register_citas_virtuales_query_var');

// ================================
// Load plugin view via template_include
// ================================
function aa_citas_virtuales_template($template) {
    if (!get_query_var('aa_citas_virtuales')) {
        return $template;
    }

    $view = plugin_dir_path(__FILE__) . '../../views/citas-virtuales.php';

    if (file_exists($view)) {
        return $view;
    }

    return $template;
}
add_filter('template_include', 'aa_citas_virtuales_template');
