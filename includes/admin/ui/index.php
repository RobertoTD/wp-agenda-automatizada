<?php
/**
 * Admin UI Entry Point - Router for iframe content
 * 
 * This file:
 * - Handles routing between UI modules
 * - Loads the appropriate module based on query parameter
 * - Provides permission checks
 * - Renders the main layout with the selected module
 */

defined('ABSPATH') or die('¡Sin acceso directo!');

// Permission check
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado', 'Error', ['response' => 403]);
}

// Get module from query parameter (default: settings)
$module = isset($_GET['module']) ? sanitize_text_field($_GET['module']) : 'settings';
$module_path = __DIR__ . '/modules/' . $module . '/index.php';

// Validate module exists
if (!file_exists($module_path)) {
    $module = 'settings';
    $module_path = __DIR__ . '/modules/settings/index.php';
}

// Load shared layout
require_once __DIR__ . '/shared/layout.php';

