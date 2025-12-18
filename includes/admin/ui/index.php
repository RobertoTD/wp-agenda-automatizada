<?php
/**
 * Admin UI Router
 *
 * Responsibilities:
 * - Validate access
 * - Resolve active UI module
 * - Delegate rendering to shared layout
 *
 * This file contains NO HTML and NO business logic.
 */

defined('ABSPATH') or die('No direct access');

// Permission check
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado', 'Error', ['response' => 403]);
}

// Allowed UI modules (whitelist)
$allowed_modules = [
    'settings',
    'calendar',
    'clients',
];

// Resolve requested module
$requested_module = isset($_GET['module'])
    ? sanitize_key($_GET['module'])
    : 'settings';

// Fallback to default module
$active_module = in_array($requested_module, $allowed_modules, true)
    ? $requested_module
    : 'settings';

// Resolve module path
$module_path = __DIR__ . '/modules/' . $active_module . '/index.php';

// Final safety check
if (!file_exists($module_path)) {
    wp_die('UI module not found', 'Error', ['response' => 404]);
}

// Variables $active_module and $module_path are now available in parent scope
// Delegate rendering to layout (variables will be accessible in layout.php)
require __DIR__ . '/shared/layout.php';
